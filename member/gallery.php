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

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $act = postString('action');

    // Upload new item
    if ($act === 'upload') {
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
            flashMessage('error', 'Invalid gallery media selection.');
            redirect('/member/gallery.php');
        }
        $type = $selections['type'];
        $photoSource = $selections['photo_source'];
        $videoType = $selections['video_type'];
        $filePath  = null;
        $mediaFile = uploadedFile('media_file');

        if (($type === 'photo' && $photoSource === 'upload') || ($type === 'video' && $videoType === 'upload')) {
            $mimes = $type === 'photo'
                ? (defined('ALLOWED_IMAGE_TYPES') ? ALLOWED_IMAGE_TYPES : ['image/jpeg','image/png','image/gif','image/webp'])
                : (defined('ALLOWED_VIDEO_TYPES') ? ALLOWED_VIDEO_TYPES : ['video/mp4','video/webm','video/ogg']);

            if ($mediaFile !== null && !empty($mediaFile['name'])) {
                $upload = handleFileUpload($mediaFile, __DIR__ . '/../uploads/gallery', $mimes);
                if (!$upload['success']) {
                    flashMessage('error', 'Upload failed: ' . $upload['error']);
                    redirect('/member/gallery.php');
                }
                if ($type === 'photo') {
                    $watermark = applyGalleryWatermark($upload['path']);
                    if (!$watermark['success']) {
                        deleteUploadedFile($upload['path']);
                        flashMessage('error', 'Upload failed: ' . stringValue($watermark['error'] ?? 'Unable to apply the gallery watermark. Please check your watermark settings or try again.'));
                        redirect('/member/gallery.php');
                    }
                }
                $filePath = 'uploads/gallery/' . $upload['filename'];
            } else {
                flashMessage('error', 'Please select a file to upload.');
                redirect('/member/gallery.php');
            }
        }

        if ($type === 'video' && $videoType === 'embed') {
            if ($videoUrl === '') {
                flashMessage('error', 'Please provide a video URL for embedded videos.');
                redirect('/member/gallery.php');
            }
            if (!isSupportedVideoUrl($videoUrl)) {
                flashMessage('error', 'Only YouTube and Vimeo URLs are supported for video embeds.');
                redirect('/member/gallery.php');
            }
        }

        if (($type === 'photo' && $photoSource === 'link') || ($type === 'video' && $videoType === 'link')) {
            if ($linkUrl === '') {
                flashMessage('error', 'Please provide a link for linked gallery items.');
                redirect('/member/gallery.php');
            }
            if (!isSupportedGalleryLinkUrl($linkUrl)) {
                flashMessage('error', 'Please provide a valid https link for linked gallery items.');
                redirect('/member/gallery.php');
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
        flashMessage('success', $msg);
        redirect('/member/gallery.php');
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

    <!-- My Items -->
    <?php if ($myItems): ?>
      <div class="gallery-grid">
        <?php foreach ($myItems as $item): ?>
          <?php
          $itemFilePath = stringValue($item['file_path'] ?? '');
          $itemVideoUrl = stringValue($item['video_url'] ?? '');
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

            <?php if (!$item['is_approved']): ?>
              <div class="gallery-pending-badge">Pending</div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="text-center" style="padding:4rem 0;color:var(--text-muted);">
        <div style="font-size:4rem;margin-bottom:1rem;">📷</div>
        <h3>No uploads yet</h3>
        <p>Click the upload button to add your first photo or video.</p>
        <button class="btn btn-primary mt-2" data-modal-open="uploadModal">+ Upload Content</button>
      </div>
    <?php endif; ?>
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
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
