<?php
/**
 * RedWater Entertainment - Member Gallery
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireMemberOrAdmin();

$user = currentUser();
assert($user !== null);
if ($user['role'] === 'admin') {
    redirect('/admin/gallery.php');
}

$db     = getDb();
$errors = [];

function memberGalleryWantsJsonResponse(): bool {
    $accept = strtolower(serverString('HTTP_ACCEPT'));
    $requestedWith = strtolower(serverString('HTTP_X_REQUESTED_WITH'));

    return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest';
}

/**
 * @param array<string, mixed> $payload
 */
function memberGalleryRespondJson(int $status, array $payload): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $act = postString('action');

    // Upload new item
    if ($act === 'upload') {
        $wantsJson = memberGalleryWantsJsonResponse();
        $respondUploadError = static function (string $message) use ($wantsJson): void {
            if ($wantsJson) {
                memberGalleryRespondJson(422, [
                    'success' => false,
                    'message' => $message,
                ]);
            }

            flashMessage('error', $message);
            redirect('/member/gallery.php');
        };
        $respondUploadSuccess = static function (string $message, bool $approved) use ($wantsJson): void {
            if ($wantsJson) {
                memberGalleryRespondJson(200, [
                    'success' => true,
                    'message' => $message,
                    'status' => $approved ? 'uploaded' : 'pending',
                    'redirect' => '/member/gallery.php',
                ]);
            }

            flashMessage('success', $message);
            redirect('/member/gallery.php');
        };
        $type      = postString('type', 'photo');
        $photoSource = postString('photo_source', 'upload');
        $title     = trim(postString('title'));
        $desc      = trim(postString('description'));
        $tags      = trim(postString('tags'));
        $altText   = trim(postString('alt_text'));
        $seoTitle  = trim(postString('seo_title'));
        $seoDesc   = trim(postString('seo_description'));
        $videoUrl  = trim(postString('video_url'));
        $linkUrl   = trim(postString('link_url'));
        $videoType = postString('video_type', 'embed');
        $selections = getValidatedGalleryUploadSelections($type, $photoSource, $videoType);
        if ($selections === null) {
            $respondUploadError('Invalid gallery media selection.');
        }
        $type = $selections['type'];
        $photoSource = $selections['photo_source'];
        $videoType = $selections['video_type'];
        $filePath  = null;
        $mediaFile = uploadedFile('media_file');
        $requiresFile = ($type === 'photo' && $photoSource === 'upload') || ($type === 'video' && $videoType === 'upload');
        $requiresEmbed = $type === 'video' && $videoType === 'embed';
        $requiresLink = ($type === 'photo' && $photoSource === 'link') || ($type === 'video' && $videoType === 'link');

        if ($requiresFile) {
            $mimes = $type === 'photo'
                ? (defined('ALLOWED_IMAGE_TYPES') ? ALLOWED_IMAGE_TYPES : ['image/jpeg','image/png','image/gif','image/webp'])
                : (defined('ALLOWED_VIDEO_TYPES') ? ALLOWED_VIDEO_TYPES : ['video/mp4','video/webm','video/ogg']);

            if ($mediaFile !== null && !empty($mediaFile['name'])) {
                $upload = handleFileUpload($mediaFile, __DIR__ . '/../uploads/gallery', $mimes);
                if (!$upload['success']) {
                    $respondUploadError('Upload failed: ' . $upload['error']);
                }
                if ($type === 'photo') {
                    $watermark = applyGalleryWatermark($upload['path']);
                    if (!$watermark['success']) {
                        deleteUploadedFile($upload['path']);
                        $respondUploadError('Upload failed: ' . stringValue($watermark['error'] ?? 'Unable to apply the gallery watermark. Please check your watermark settings or try again.'));
                    }
                }
                $filePath = 'uploads/gallery/' . $upload['filename'];
            } else {
                $respondUploadError('Please select a file to upload.');
            }
        }

        if ($requiresEmbed) {
            if ($videoUrl === '') {
                $respondUploadError('Please provide a video URL for embedded videos.');
            }
            if (!isSupportedVideoUrl($videoUrl)) {
                $respondUploadError('Only YouTube and Vimeo URLs are supported for video embeds.');
            }
        }

        if ($requiresLink) {
            if ($linkUrl === '') {
                $respondUploadError('Please provide a link for linked gallery items.');
            }
            if (!isSupportedGalleryLinkUrl($linkUrl)) {
                $respondUploadError('Please provide a valid https link for linked gallery items.');
            }
        }

        // Auto-approve if member has bypass
        $autoApprove = $user['bypass_approval'] ? 1 : 0;

        $stmt = $db->prepare(
            'INSERT INTO gallery_items (user_id, type, file_path, video_url, link_url, source_type, video_type, title, description, tags, alt_text, seo_title, seo_description, is_approved)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $storedSourceTypes = getGalleryStoredSourceTypes($type, $photoSource, $videoType);
        $stmt->execute([
            $user['id'],
            $type,
            $filePath,
            $videoUrl ?: null,
            $linkUrl ?: null,
            $storedSourceTypes['source_type'],
            $storedSourceTypes['video_type'],
            $title,
            $desc,
            $tags,
            $altText,
            $seoTitle,
            $seoDesc,
            $autoApprove
        ]);

        $msg = $autoApprove
            ? 'Upload successful! Your item is now live in the gallery.'
            : 'Upload successful! Your item is pending admin approval before it appears publicly.';
        $respondUploadSuccess($msg, $autoApprove === 1);
    }

    // Edit own item (only if belongs to user AND account active)
    if ($act === 'edit') {
        $itemId = postInt('item_id');
        // Verify ownership
        $stmt = $db->prepare('SELECT id FROM gallery_items WHERE id=? AND user_id=?');
        $stmt->execute([$itemId, $user['id']]);
        if (!$stmt->fetch()) {
            flashMessage('error', 'Item not found or you do not have permission to edit it.');
            redirect('/member/gallery.php');
        }

        $title    = trim(postString('title'));
        $desc     = trim(postString('description'));
        $tags     = trim(postString('tags'));
        $altText  = trim(postString('alt_text'));
        $seoTitle = trim(postString('seo_title'));
        $seoDesc  = trim(postString('seo_description'));

        $db->prepare(
            'UPDATE gallery_items SET title=?, description=?, tags=?, alt_text=?, seo_title=?, seo_description=? WHERE id=? AND user_id=?'
        )->execute([$title, $desc, $tags, $altText, $seoTitle, $seoDesc, $itemId, $user['id']]);

        flashMessage('success', 'Item updated.');
        redirect('/member/gallery.php');
    }

    // Delete own item
    if ($act === 'delete') {
        $itemId = postInt('item_id');
        $stmt   = $db->prepare('SELECT * FROM gallery_items WHERE id=? AND user_id=?');
        $stmt->execute([$itemId, $user['id']]);
        /** @var array{file_path?: string}|false $row */
        $row = $stmt->fetch();
        if ($row) {
            if ($row['file_path']) deleteUploadedFile(__DIR__ . '/../' . ltrim($row['file_path'], '/'));
            $db->prepare('DELETE FROM gallery_items WHERE id=? AND user_id=?')->execute([$itemId, $user['id']]);
            flashMessage('success', 'Item deleted.');
        }
        redirect('/member/gallery.php');
    }
}

// ── Load own items ────────────────────────────────────────────────────────────
$editItemId = getInt('edit');
$editItem   = null;
if ($editItemId) {
    $stmt = $db->prepare('SELECT * FROM gallery_items WHERE id=? AND user_id=?');
    $stmt->execute([$editItemId, $user['id']]);
    /** @var array<string, mixed>|false $editItem */
    $editItem = $stmt->fetch();
}

$myItems = getGalleryItems(false, $user['id']);
$pendingItems = array_values(array_filter(
    $myItems,
    static fn (array $item): bool => !((bool) ($item['is_approved'] ?? false))
));
$uploadedItems = array_values(array_filter(
    $myItems,
    static fn (array $item): bool => (bool) ($item['is_approved'] ?? false)
));

$pageTitle = 'My Gallery';
include __DIR__ . '/../includes/header.php';
?>

<div class="member-layout">
  <div class="member-header">
    <h1>My Gallery</h1>
    <div class="d-flex gap-1">
      <button class="btn btn-primary btn-sm" data-modal-open="uploadModal">+ Upload</button>
      <a href="/member/" class="btn btn-outline btn-sm">Dashboard</a>
    </div>
  </div>

  <div class="member-tabs">
    <a href="/member/" class="member-tab">Dashboard</a>
    <a href="/member/gallery.php" class="member-tab active">Gallery</a>
    <a href="/member/profile.php" class="member-tab">Profile</a>
  </div>

  <div class="member-content">
    <div class="card member-upload-status">
      <div class="card-body">
        <div class="member-upload-status-summary">
          <div>
            <h3 style="font-size:1rem;margin-bottom:0;">Upload status</h3>
            <p id="memberUploadStatusText">Ready to upload photos and videos from this device.</p>
          </div>
          <span class="status-badge status-blue" id="memberUploadQueueBadge">0 queued</span>
        </div>
        <div class="member-upload-progress" id="memberUploadProgress" hidden>
          <div class="member-upload-progress-label">
            <span id="memberUploadProgressLabel">Preparing upload…</span>
            <span id="memberUploadProgressPercent">0%</span>
          </div>
          <div class="member-upload-progress-track">
            <div class="member-upload-progress-bar" id="memberUploadProgressBar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="Upload progress"></div>
          </div>
        </div>
      </div>
    </div>

    <?php if ($editItem): ?>
    <!-- Edit Form -->
    <div class="card mb-3">
      <div class="card-body">
        <h3 style="font-size:1rem;margin-bottom:1rem;">Edit Item</h3>
        <form method="POST" action="/member/gallery.php">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="item_id" value="<?= e($editItem['id']) ?>">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Title</label>
              <input type="text" name="title" class="form-control" value="<?= e($editItem['title'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Tags (comma-separated)</label>
              <input type="text" name="tags" class="form-control" value="<?= e($editItem['tags'] ?? '') ?>">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3"><?= e($editItem['description'] ?? '') ?></textarea>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Alt Text</label>
              <input type="text" name="alt_text" class="form-control" value="<?= e($editItem['alt_text'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">SEO Title</label>
              <input type="text" name="seo_title" class="form-control" value="<?= e($editItem['seo_title'] ?? '') ?>">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">SEO Description</label>
            <textarea name="seo_description" class="form-control" rows="2"><?= e($editItem['seo_description'] ?? '') ?></textarea>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
            <a href="/member/gallery.php" class="btn btn-outline btn-sm">Cancel</a>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <div class="member-gallery-sections">
      <section class="card">
        <div class="card-body">
          <div class="member-gallery-section-header">
            <div>
              <h3 style="font-size:1rem;margin-bottom:0;">Pending</h3>
              <p>Uploads waiting for approval will stay here until an admin reviews them.</p>
            </div>
            <span class="status-badge status-pending"><?= count($pendingItems) ?></span>
          </div>

          <?php if ($pendingItems): ?>
            <div class="gallery-grid">
              <?php foreach ($pendingItems as $item): ?>
                <?php
                $itemFilePath = stringValue($item['file_path'] ?? '');
                $itemLinkUrl = stringValue($item['link_url'] ?? '');
                $sourceType = getGalleryItemSourceType($item);
                ?>
                <div class="gallery-item" style="cursor:default;">
                  <?php if ($item['type'] === 'photo' && $item['file_path']): ?>
                    <img src="/<?= e(ltrim($itemFilePath, '/')) ?>" alt="<?= e($item['alt_text'] ?: '') ?>" loading="lazy">
                  <?php elseif ($sourceType === 'link' && $itemLinkUrl !== ''): ?>
                    <div class="gallery-linked-placeholder gallery-linked-placeholder-compact">
                      <div class="gallery-linked-placeholder-icon" aria-hidden="true">🔗</div>
                      <div><?= e($item['type'] === 'photo' ? 'Linked Photo' : 'Linked Video') ?></div>
                    </div>
                  <?php else: ?>
                    <div style="width:100%;height:100%;background:var(--bg-card2);display:flex;align-items:center;justify-content:center;font-size:3rem;">▶️</div>
                  <?php endif; ?>

                  <div class="gallery-item-overlay" style="opacity:1;">
                    <div class="gallery-item-title"><?= e($item['title'] ?: '(untitled)') ?></div>
                    <div class="d-flex gap-1 mt-1">
                      <a href="/member/gallery.php?edit=<?= e($item['id']) ?>" class="btn btn-outline btn-sm" style="padding:0.2rem 0.6rem;font-size:0.7rem;">Edit</a>
                      <?php if ($sourceType === 'link' && $itemLinkUrl !== ''): ?>
                        <a href="<?= e($itemLinkUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm" style="padding:0.2rem 0.6rem;font-size:0.7rem;">Open Link</a>
                      <?php endif; ?>
                      <form method="POST" style="display:inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="item_id" value="<?= e($item['id']) ?>">
                        <button class="btn btn-danger btn-sm" style="padding:0.2rem 0.6rem;font-size:0.7rem;" data-confirm="Delete this item?">Delete</button>
                      </form>
                    </div>
                  </div>

                  <?php if ($item['type'] === 'video'): ?>
                    <div class="gallery-item-type-badge">Video</div>
                  <?php endif; ?>

                  <div class="gallery-pending-badge">Pending</div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="member-gallery-empty">
              <div style="font-size:2rem;margin-bottom:0.75rem;">🕒</div>
              <p>No pending uploads right now.</p>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <section class="card">
        <div class="card-body">
          <div class="member-gallery-section-header">
            <div>
              <h3 style="font-size:1rem;margin-bottom:0;">Uploaded</h3>
              <p>Approved uploads and auto-approved items appear here.</p>
            </div>
            <span class="status-badge status-approved"><?= count($uploadedItems) ?></span>
          </div>

          <?php if ($uploadedItems): ?>
            <div class="gallery-grid">
              <?php foreach ($uploadedItems as $item): ?>
                <?php
                $itemFilePath = stringValue($item['file_path'] ?? '');
                $itemLinkUrl = stringValue($item['link_url'] ?? '');
                $sourceType = getGalleryItemSourceType($item);
                ?>
                <div class="gallery-item" style="cursor:default;">
                  <?php if ($item['type'] === 'photo' && $item['file_path']): ?>
                    <img src="/<?= e(ltrim($itemFilePath, '/')) ?>" alt="<?= e($item['alt_text'] ?: '') ?>" loading="lazy">
                  <?php elseif ($sourceType === 'link' && $itemLinkUrl !== ''): ?>
                    <div class="gallery-linked-placeholder gallery-linked-placeholder-compact">
                      <div class="gallery-linked-placeholder-icon" aria-hidden="true">🔗</div>
                      <div><?= e($item['type'] === 'photo' ? 'Linked Photo' : 'Linked Video') ?></div>
                    </div>
                  <?php else: ?>
                    <div style="width:100%;height:100%;background:var(--bg-card2);display:flex;align-items:center;justify-content:center;font-size:3rem;">▶️</div>
                  <?php endif; ?>

                  <div class="gallery-item-overlay" style="opacity:1;">
                    <div class="gallery-item-title"><?= e($item['title'] ?: '(untitled)') ?></div>
                    <div class="d-flex gap-1 mt-1">
                      <a href="/member/gallery.php?edit=<?= e($item['id']) ?>" class="btn btn-outline btn-sm" style="padding:0.2rem 0.6rem;font-size:0.7rem;">Edit</a>
                      <?php if ($sourceType === 'link' && $itemLinkUrl !== ''): ?>
                        <a href="<?= e($itemLinkUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm" style="padding:0.2rem 0.6rem;font-size:0.7rem;">Open Link</a>
                      <?php endif; ?>
                      <form method="POST" style="display:inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="item_id" value="<?= e($item['id']) ?>">
                        <button class="btn btn-danger btn-sm" style="padding:0.2rem 0.6rem;font-size:0.7rem;" data-confirm="Delete this item?">Delete</button>
                      </form>
                    </div>
                  </div>

                  <?php if ($item['type'] === 'video'): ?>
                    <div class="gallery-item-type-badge">Video</div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="member-gallery-empty">
              <div style="font-size:2rem;margin-bottom:0.75rem;">✅</div>
              <p>Uploaded items will appear here as soon as they are approved.</p>
              <?php if (!$myItems): ?>
                <button class="btn btn-primary mt-2" data-modal-open="uploadModal">+ Upload Content</button>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>
    </div>
  </div>
</div>

<!-- Upload Modal -->
<div id="uploadModal" class="modal-backdrop">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Upload to Gallery</h3>
      <span class="modal-close" data-modal-close>&times;</span>
    </div>
    <div class="modal-body">
      <?php if (!$user['bypass_approval']): ?>
        <div class="alert alert-info" style="position:static;max-width:100%;margin-bottom:1rem;">
          ℹ️ Your uploads will be reviewed by an admin before they appear publicly.
        </div>
      <?php endif; ?>
      <form method="POST" action="/member/gallery.php" enctype="multipart/form-data" id="memberUploadForm">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="upload">
        <div class="form-group">
          <label class="form-label">Content Type</label>
          <select name="type" class="form-control" id="memberMediaType" onchange="memberToggleType(this.value)">
            <option value="photo">Photo</option>
            <option value="video">Video</option>
          </select>
        </div>
        <div id="memberPhotoField">
          <div class="form-group">
            <label class="form-label">Photo Source</label>
            <select name="photo_source" class="form-control" id="memberPhotoSource" onchange="memberTogglePhotoSource(this.value)">
              <option value="upload">Upload Photo File</option>
              <option value="link">Link to Photo Page/Media</option>
            </select>
          </div>
          <div id="memberPhotoUploadField">
          <div class="form-group">
            <div class="dropzone">
              <div class="dropzone-icon">📷</div>
              <p>Drop image here or click to select</p>
              <input type="file" name="media_file" accept="image/*" id="memberPhotoMediaFile">
            </div>
          </div>
          </div>
          <div id="memberPhotoLinkField" style="display:none;">
            <div class="form-group">
              <label class="form-label" for="memberGalleryLinkUrl">Photo Link</label>
              <input type="url" name="link_url" class="form-control" id="memberGalleryLinkUrl" placeholder="https://www.pinterest.com/...">
            </div>
          </div>
        </div>
        <div id="memberVideoField" style="display:none;">
          <div class="form-group">
            <label class="form-label">Video Source</label>
            <select name="video_type" class="form-control" id="memberVideoType" onchange="memberToggleVideoType(this.value)">
              <option value="embed">Embed URL (YouTube, Vimeo)</option>
              <option value="upload">Upload Video File</option>
              <option value="link">Link to Video Page</option>
            </select>
          </div>
          <div id="memberEmbedField">
            <div class="form-group">
              <label class="form-label">Video URL</label>
              <input type="url" name="video_url" class="form-control" placeholder="https://youtu.be/...">
            </div>
          </div>
          <div id="memberUploadField" style="display:none;">
            <div class="form-group">
              <div class="dropzone">
                <div class="dropzone-icon">🎬</div>
                <p>Drop video file here or click to select</p>
                <input type="file" name="media_file" accept="video/*" id="memberVideoMediaFile" disabled>
              </div>
            </div>
          </div>
          <div id="memberVideoLinkField" style="display:none;">
            <div class="form-group">
              <label class="form-label" for="memberVideoLinkUrl">Video Link</label>
              <input type="url" name="link_url" class="form-control" id="memberVideoLinkUrl" placeholder="https://www.youtube.com/watch?v=...">
            </div>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Tags</label>
            <input type="text" name="tags" class="form-control" placeholder="haunted, 2024">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="3"></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Alt Text (SEO)</label>
            <input type="text" name="alt_text" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">SEO Title</label>
            <input type="text" name="seo_title" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">SEO Description</label>
          <textarea name="seo_description" class="form-control" rows="2"></textarea>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
      <button type="submit" form="memberUploadForm" class="btn btn-primary btn-sm">Upload</button>
    </div>
  </div>
</div>

<div id="memberUploadCompleteModal" class="modal-backdrop">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Upload complete</h3>
      <span class="modal-close" data-modal-close>&times;</span>
    </div>
    <div class="modal-body member-upload-complete">
      <div class="member-upload-complete-icon" aria-hidden="true">✅</div>
      <p id="memberUploadCompleteMessage">Your upload has been received.</p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline btn-sm" data-modal-close>Stay Here</button>
      <button type="button" class="btn btn-primary btn-sm" id="memberUploadRefreshButton">Refresh Uploads</button>
    </div>
  </div>
</div>

<script>
function memberSyncUploadInputs() {
  const mediaType = document.getElementById('memberMediaType')?.value;
  const photoSource = document.getElementById('memberPhotoSource')?.value;
  const videoType = document.getElementById('memberVideoType')?.value;
  const photoInput = document.getElementById('memberPhotoMediaFile');
  const videoInput = document.getElementById('memberVideoMediaFile');
  const photoLinkInput = document.getElementById('memberGalleryLinkUrl');
  const videoLinkInput = document.getElementById('memberVideoLinkUrl');

  if (photoInput) photoInput.disabled = !(mediaType === 'photo' && photoSource === 'upload');
  if (videoInput) videoInput.disabled = !(mediaType === 'video' && videoType === 'upload');
  if (photoLinkInput) photoLinkInput.disabled = !(mediaType === 'photo' && photoSource === 'link');
  if (videoLinkInput) videoLinkInput.disabled = !(mediaType === 'video' && videoType === 'link');
}
function memberTogglePhotoSource(source) {
  document.getElementById('memberPhotoUploadField').style.display = source === 'link' ? 'none' : '';
  document.getElementById('memberPhotoLinkField').style.display = source === 'link' ? '' : 'none';
  memberSyncUploadInputs();
}
function memberToggleType(type) {
  document.getElementById('memberPhotoField').style.display = type === 'photo' ? '' : 'none';
  document.getElementById('memberVideoField').style.display = type === 'video' ? '' : 'none';
  memberSyncUploadInputs();
}
function memberToggleVideoType(type) {
  document.getElementById('memberEmbedField').style.display  = type === 'embed'  ? '' : 'none';
  document.getElementById('memberUploadField').style.display = type === 'upload' ? '' : 'none';
  document.getElementById('memberVideoLinkField').style.display = type === 'link' ? '' : 'none';
  memberSyncUploadInputs();
}
memberSyncUploadInputs();

(function () {
  const form = document.getElementById('memberUploadForm');
  if (!form || !window.indexedDB) {
    return;
  }

  const uploadModal = document.getElementById('uploadModal');
  const completionModal = document.getElementById('memberUploadCompleteModal');
  const refreshButton = document.getElementById('memberUploadRefreshButton');
  const statusText = document.getElementById('memberUploadStatusText');
  const queueBadge = document.getElementById('memberUploadQueueBadge');
  const progressWrap = document.getElementById('memberUploadProgress');
  const progressLabel = document.getElementById('memberUploadProgressLabel');
  const progressPercent = document.getElementById('memberUploadProgressPercent');
  const progressBar = document.getElementById('memberUploadProgressBar');
  const completeMessage = document.getElementById('memberUploadCompleteMessage');
  const submitButton = document.querySelector('button[form="memberUploadForm"]');
  const uploadEndpoint = form.getAttribute('action') || window.location.pathname;
  const progressMin = 0;
  const progressMax = 100;
  const uploadTimeoutMs = 180000;
  const uploadDbName = 'redwater-member-gallery';
  const uploadStoreName = 'uploadQueue';
  let uploadSequence = 0;
  const createUploadId = window.crypto && typeof window.crypto.randomUUID === 'function'
    ? function () { return window.crypto.randomUUID(); }
    : function () {
        uploadSequence += 1;
        return String(Date.now()) + '-' + uploadSequence + '-' + Math.random().toString(16).slice(2) + '-' + Math.random().toString(16).slice(2);
      };
  let isSyncingQueue = false;

  function setStatus(message, tone) {
    if (!statusText) {
      return;
    }

    statusText.textContent = message;
    statusText.style.color = tone === 'error'
      ? '#fca5a5'
      : tone === 'success'
        ? '#86efac'
        : tone === 'pending'
          ? '#fde047'
          : '';
  }

  function setBusy(isBusy) {
    if (submitButton) {
      submitButton.disabled = isBusy;
      submitButton.textContent = isBusy ? 'Uploading…' : 'Upload';
    }
  }

  function setProgress(percent, label) {
    if (!progressWrap || !progressBar || !progressPercent || !progressLabel) {
      return;
    }

    progressWrap.hidden = false;
    progressBar.style.width = percent + '%';
    progressPercent.textContent = percent + '%';
    progressBar.setAttribute('aria-valuenow', String(percent));
    progressBar.setAttribute('aria-label', label + ' (' + percent + '%)');
    progressLabel.textContent = label;
  }

  function hideProgress() {
    if (!progressWrap || !progressBar || !progressPercent || !progressLabel) {
      return;
    }

    progressWrap.hidden = true;
    progressBar.style.width = progressMin + '%';
    progressBar.setAttribute('aria-valuenow', String(progressMin));
    progressBar.setAttribute('aria-label', 'Upload progress');
    progressPercent.textContent = progressMin + '%';
    progressLabel.textContent = 'Preparing upload…';
  }

  function openModal(modal) {
    if (modal) {
      modal.classList.add('open');
    }
  }

  function closeModal(modal) {
    if (modal) {
      modal.classList.remove('open');
    }
  }

  function resetUploadForm() {
    form.reset();
    memberToggleType('photo');
    memberTogglePhotoSource('upload');
    memberToggleVideoType('embed');
    const photoDropzoneLabel = document.querySelector('#memberPhotoUploadField .dropzone p');
    const videoDropzoneLabel = document.querySelector('#memberUploadField .dropzone p');
    if (photoDropzoneLabel) {
      photoDropzoneLabel.textContent = 'Drop image here or click to select';
    }
    if (videoDropzoneLabel) {
      videoDropzoneLabel.textContent = 'Drop video file here or click to select';
    }
  }

  function openQueueDatabase() {
    return new Promise(function (resolve, reject) {
      const request = window.indexedDB.open(uploadDbName, 1);
      request.onupgradeneeded = function () {
        const db = request.result;
        if (!db.objectStoreNames.contains(uploadStoreName)) {
          db.createObjectStore(uploadStoreName, { keyPath: 'id' });
        }
      };
      request.onsuccess = function () {
        resolve(request.result);
      };
      request.onerror = function () {
        reject(request.error || new Error('Unable to open offline upload queue.'));
      };
    });
  }

  async function saveQueuedUpload(entry) {
    const db = await openQueueDatabase();
    return new Promise(function (resolve, reject) {
      const tx = db.transaction(uploadStoreName, 'readwrite');
      tx.oncomplete = function () {
        db.close();
        resolve();
      };
      tx.onerror = function () {
        db.close();
        reject(tx.error || new Error('Unable to save queued upload.'));
      };
      tx.objectStore(uploadStoreName).put(entry);
    });
  }

  async function listQueuedUploads() {
    const db = await openQueueDatabase();
    return new Promise(function (resolve, reject) {
      const tx = db.transaction(uploadStoreName, 'readonly');
      const request = tx.objectStore(uploadStoreName).getAll();
      request.onsuccess = function () {
        db.close();
        resolve(Array.isArray(request.result) ? request.result : []);
      };
      request.onerror = function () {
        db.close();
        reject(request.error || new Error('Unable to load queued uploads.'));
      };
    });
  }

  async function deleteQueuedUpload(id) {
    const db = await openQueueDatabase();
    return new Promise(function (resolve, reject) {
      const tx = db.transaction(uploadStoreName, 'readwrite');
      tx.oncomplete = function () {
        db.close();
        resolve();
      };
      tx.onerror = function () {
        db.close();
        reject(tx.error || new Error('Unable to remove queued upload.'));
      };
      tx.objectStore(uploadStoreName).delete(id);
    });
  }

  async function updateQueueBadge() {
    try {
      const queuedUploads = await listQueuedUploads();
      if (queueBadge) {
        queueBadge.textContent = queuedUploads.length + ' queued';
      }

      if (queuedUploads.length > 0 && navigator.onLine) {
        setStatus('Queued uploads will sync automatically while you stay online.', 'pending');
      } else if (queuedUploads.length > 0) {
        setStatus('You are offline. Your queued uploads will retry automatically when the connection returns.', 'pending');
      } else if (navigator.onLine) {
        setStatus('Ready to upload photos and videos from this device.', 'info');
      } else {
        setStatus('You are offline. New uploads will be queued and sent automatically later.', 'pending');
      }
    } catch (error) {
      setStatus('Offline upload queue is unavailable on this device.', 'error');
    }
  }

  function getActiveFile() {
    const photoInput = document.getElementById('memberPhotoMediaFile');
    const videoInput = document.getElementById('memberVideoMediaFile');
    const activeInput = photoInput && !photoInput.disabled ? photoInput : videoInput && !videoInput.disabled ? videoInput : null;
    return activeInput && activeInput.files && activeInput.files[0] ? activeInput.files[0] : null;
  }

  function buildQueueEntry() {
    const formData = new FormData(form);
    const fields = {};
    formData.forEach(function (value, key) {
      if (value instanceof File) {
        return;
      }
      fields[key] = value;
    });

    const activeFile = getActiveFile();

    return {
      id: createUploadId(),
      createdAt: new Date().toISOString(),
      fields: fields,
      file: activeFile,
      fileName: activeFile ? activeFile.name : '',
    };
  }

  function buildFormData(entry) {
    const formData = new FormData();
    Object.entries(entry.fields || {}).forEach(function ([key, value]) {
      formData.append(key, value);
    });
    if (entry.file) {
      formData.append('media_file', entry.file, entry.fileName || 'upload');
    }
    return formData;
  }

  function uploadEntry(entry, progressText) {
    return new Promise(function (resolve, reject) {
      const xhr = new XMLHttpRequest();
      xhr.open('POST', uploadEndpoint);
      xhr.responseType = 'json';
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.timeout = uploadTimeoutMs;
      xhr.upload.onprogress = function (event) {
        if (!event.lengthComputable) {
          return;
        }
        const percent = Math.max(progressMin, Math.min(progressMax, Math.round((event.loaded / event.total) * progressMax)));
        setProgress(percent, progressText);
      };
      xhr.onload = function () {
        const response = xhr.response && typeof xhr.response === 'object' ? xhr.response : null;
        if (xhr.status >= 200 && xhr.status < 300 && response && response.success) {
          setProgress(progressMax, 'Upload complete');
          resolve(response);
          return;
        }

        if (xhr.status === 0 || xhr.status >= 500) {
          reject({ type: 'network', message: 'Upload connection failed.' });
          return;
        }

        reject({
          type: xhr.status === 403 ? 'auth' : 'server',
          message: response && response.message ? response.message : 'Upload failed. Please review your form and try again.'
        });
      };
      xhr.onerror = function () {
        reject({ type: 'network', message: 'Upload connection failed.' });
      };
      xhr.ontimeout = function () {
        reject({ type: 'network', message: 'Upload timed out before it could finish.' });
      };
      xhr.send(buildFormData(entry));
    });
  }

  async function queueCurrentUpload(entry, message) {
    await saveQueuedUpload(entry);
    resetUploadForm();
    closeModal(uploadModal);
    hideProgress();
    setBusy(false);
    setStatus(message, 'pending');
    await updateQueueBadge();
  }

  function showCompletion(message) {
    if (completeMessage) {
      completeMessage.textContent = message;
    }
    openModal(completionModal);
  }

  async function syncQueuedUploads() {
    if (isSyncingQueue || !navigator.onLine) {
      return;
    }

    isSyncingQueue = true;

    try {
      const queuedUploads = await listQueuedUploads();
      if (!queuedUploads.length) {
        hideProgress();
        await updateQueueBadge();
        return;
      }

      let lastSuccessMessage = '';
      for (let index = 0; index < queuedUploads.length; index += 1) {
        const entry = queuedUploads[index];
        try {
          const response = await uploadEntry(entry, 'Syncing queued upload ' + (index + 1) + ' of ' + queuedUploads.length);
          await deleteQueuedUpload(entry.id);
          lastSuccessMessage = response.message || 'Queued upload finished successfully.';
          await updateQueueBadge();
        } catch (error) {
          hideProgress();
          if (error && error.type === 'network') {
            setStatus('Connection dropped again. Remaining uploads will retry automatically.', 'pending');
          } else if (error && error.type === 'auth') {
            setStatus('Queued upload is waiting for you to sign in again before it can finish.', 'error');
          } else {
            setStatus(error && error.message ? error.message : 'Queued upload needs your attention before it can continue.', 'error');
          }
          return;
        }
      }

      hideProgress();
      await updateQueueBadge();
      if (lastSuccessMessage !== '') {
        showCompletion(lastSuccessMessage);
      }
    } finally {
      isSyncingQueue = false;
    }
  }

  if (refreshButton) {
    refreshButton.addEventListener('click', function () {
      window.location.reload();
    });
  }

  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    const entry = buildQueueEntry();
    setBusy(true);

    if (!navigator.onLine) {
      await queueCurrentUpload(entry, 'You are offline. This upload has been queued and will send itself automatically when you reconnect.');
      return;
    }

    setProgress(progressMin, 'Starting upload…');

    try {
      const response = await uploadEntry(entry, 'Uploading now…');
      closeModal(uploadModal);
      resetUploadForm();
      if (queueBadge) {
        queueBadge.textContent = '0 queued';
      }
      setStatus(response.message || 'Upload complete.', 'success');
      showCompletion(response.message || 'Upload complete.');
    } catch (error) {
      if (error && error.type === 'network') {
        await queueCurrentUpload(entry, 'The connection dropped during upload, so your file was queued and will retry automatically.');
        return;
      }

      hideProgress();
      setStatus(error && error.message ? error.message : 'Upload failed. Please try again.', 'error');
    } finally {
      setBusy(false);
      hideProgress();
    }
  });

  window.addEventListener('online', function () {
    updateQueueBadge();
    syncQueuedUploads();
  });
  window.addEventListener('offline', function () {
    updateQueueBadge();
  });

  updateQueueBadge();
  syncQueuedUploads();
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
