<?php
/**
 * RedWater Entertainment - Admin: Gallery Management
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db     = getDb();
$action = getString('action');
$itemId = getInt('id');

// ── Handle actions ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $act = postString('action');

    // Upload new item (admin)
    if ($act === 'upload') {
        $type          = postString('type', 'photo');
        $photoSource   = postString('photo_source', 'upload');
        $title         = trim(postString('title'));
        $desc          = trim(postString('description'));
        $tags          = trim(postString('tags'));
        $altText       = trim(postString('alt_text'));
        $seoTitle      = trim(postString('seo_title'));
        $seoDesc       = trim(postString('seo_description'));
        $videoUrl      = trim(postString('video_url'));
        $linkUrl       = trim(postString('link_url'));
        $videoType     = postString('video_type', 'embed');
        $filePath      = null;
        $requiresFile  = ($type === 'photo' && $photoSource !== 'link') || ($type === 'video' && $videoType === 'upload');
        $requiresEmbed = $type === 'video' && $videoType === 'embed';
        $requiresLink  = ($type === 'photo' && $photoSource === 'link') || ($type === 'video' && $videoType === 'link');
        $mediaFile     = uploadedFile('media_file');

        if ($requiresFile) {
            if ($mediaFile === null) {
                flashMessage('error', $type === 'photo'
                    ? 'Please select a photo to upload.'
                    : 'Please select a video file to upload.');
                redirect('/admin/gallery.php');
            }
            if (empty($mediaFile['name'])) {
                flashMessage('error', $type === 'photo'
                    ? 'Please select a photo to upload.'
                    : 'Please select a video file to upload.');
                redirect('/admin/gallery.php');
            }

            $mimes = $type === 'photo'
                ? (defined('ALLOWED_IMAGE_TYPES') ? ALLOWED_IMAGE_TYPES : ['image/jpeg','image/png','image/gif','image/webp'])
                : (defined('ALLOWED_VIDEO_TYPES') ? ALLOWED_VIDEO_TYPES : ['video/mp4','video/webm','video/ogg']);

            /** @var array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int} $mediaFile */
            $upload = handleFileUpload($mediaFile, __DIR__ . '/../uploads/gallery', $mimes);
            if (!$upload['success']) {
                flashMessage('error', 'Upload failed: ' . $upload['error']);
                redirect('/admin/gallery.php');
            }
            assert(isset($upload['filename']));
            $filePath = 'uploads/gallery/' . $upload['filename'];
        }

        if ($requiresEmbed) {
            if ($videoUrl === '') {
                flashMessage('error', 'Please provide a video URL for embedded videos.');
                redirect('/admin/gallery.php');
            }
            if (!isSupportedVideoUrl($videoUrl)) {
                flashMessage('error', 'Only YouTube and Vimeo URLs are supported for video embeds.');
                redirect('/admin/gallery.php');
            }
        }

        if ($requiresLink) {
            if ($linkUrl === '') {
                flashMessage('error', 'Please provide a link for linked gallery items.');
                redirect('/admin/gallery.php');
            }
            if (!isSupportedGalleryLinkUrl($linkUrl)) {
                flashMessage('error', 'Please provide a valid http or https link for linked gallery items.');
                redirect('/admin/gallery.php');
            }
        }

        $stmt = $db->prepare(
            'INSERT INTO gallery_items (user_id, type, file_path, video_url, link_url, source_type, video_type, title, description, tags, alt_text, seo_title, seo_description, is_approved)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
        );
        $user = currentUser();
        assert($user !== null);
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
            $seoDesc
        ]);
        flashMessage('success', 'Gallery item added successfully.');
        redirect('/admin/gallery.php');
    }

    // Approve item
    if ($act === 'approve' && $itemId) {
        $db->prepare('UPDATE gallery_items SET is_approved = 1 WHERE id = ?')->execute([$itemId]);
        flashMessage('success', 'Item approved.');
        redirect('/admin/gallery.php');
    }

    // Reject / unapprove item
    if ($act === 'unapprove' && $itemId) {
        $db->prepare('UPDATE gallery_items SET is_approved = 0 WHERE id = ?')->execute([$itemId]);
        flashMessage('info', 'Item set to pending/unapproved.');
        redirect('/admin/gallery.php');
    }

    // Delete item
    if ($act === 'delete' && $itemId) {
        $item = $db->prepare('SELECT * FROM gallery_items WHERE id = ?');
        $item->execute([$itemId]);
        /** @var array{file_path?: string}|false $row */
        $row = $item->fetch();
        if ($row && $row['file_path']) {
            deleteUploadedFile(__DIR__ . '/../' . ltrim($row['file_path'], '/'));
        }
        $db->prepare('DELETE FROM gallery_items WHERE id = ?')->execute([$itemId]);
        flashMessage('success', 'Gallery item deleted.');
        redirect('/admin/gallery.php');
    }

    // Edit item
    if ($act === 'edit' && $itemId) {
        $title     = trim(postString('title'));
        $desc      = trim(postString('description'));
        $tags      = trim(postString('tags'));
        $altText   = trim(postString('alt_text'));
        $seoTitle  = trim(postString('seo_title'));
        $seoDesc   = trim(postString('seo_description'));
        $stmt = $db->prepare(
            'UPDATE gallery_items SET title=?, description=?, tags=?, alt_text=?, seo_title=?, seo_description=? WHERE id=?'
        );
        $stmt->execute([$title, $desc, $tags, $altText, $seoTitle, $seoDesc, $itemId]);
        flashMessage('success', 'Gallery item updated.');
        redirect('/admin/gallery.php');
    }
}

// GET action: edit form
$editItem = null;
if ($action === 'edit' && $itemId) {
    $stmt = $db->prepare('SELECT * FROM gallery_items WHERE id = ?');
    $stmt->execute([$itemId]);
    /** @var array<string, mixed>|false $editItem */
    $editItem = $stmt->fetch();
}

// Load all items (paginated)
$perPage     = 20;
$page        = max(1, getInt('page', 1));
$totalItemsStmt = $db->query('SELECT COUNT(*) FROM gallery_items');
assert($totalItemsStmt instanceof PDOStatement);
$totalItems  = (int)$totalItemsStmt->fetchColumn();
$pagination  = paginate($totalItems, $perPage, $page);
$itemsStmt   = $db->query(
    "SELECT g.*, u.display_name AS uploader_name
     FROM gallery_items g LEFT JOIN users u ON g.user_id = u.id
      ORDER BY g.is_approved ASC, g.created_at DESC
      LIMIT {$perPage} OFFSET {$pagination['offset']}"
);
assert($itemsStmt instanceof PDOStatement);
/** @var list<array<string, mixed>> $items */
$items = array_values($itemsStmt->fetchAll());

$pageTitle = 'Manage Gallery';
include __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <main class="admin-main">
    <div class="d-flex justify-between align-center mb-3">
      <h1 class="admin-page-title" style="margin:0;border:none;padding:0;">Manage <span>Gallery</span></h1>
      <button class="btn btn-primary btn-sm" data-modal-open="uploadModal">+ Add Item</button>
    </div>

    <?php if ($editItem): ?>
    <!-- Edit Form -->
    <div class="card mb-3">
      <div class="card-body">
        <h3 style="margin-bottom:1.5rem;font-size:1rem;">Edit Gallery Item</h3>
        <form method="POST" action="/admin/gallery.php">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="id" value="<?= e($editItem['id']) ?>">
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
              <label class="form-label">Alt Text (SEO)</label>
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
            <a href="/admin/gallery.php" class="btn btn-outline btn-sm">Cancel</a>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- Gallery Items Table -->
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Preview</th>
            <th>Title</th>
            <th>Type</th>
            <th>Uploaded By</th>
            <th>Date</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
          <?php
          $itemFilePath = stringValue($item['file_path'] ?? '');
          $itemLinkUrl = stringValue($item['link_url'] ?? '');
          $itemType = stringValue($item['type'] ?? '');
          $itemCreatedAt = stringValue($item['created_at'] ?? '');
          $itemCreatedAtRawTs = strtotime($itemCreatedAt);
          $itemCreatedAtTs = $itemCreatedAtRawTs === false ? null : $itemCreatedAtRawTs;
          ?>
          <tr>
            <td style="width:60px;">
              <?php if ($itemType === 'photo' && $itemFilePath !== ''): ?>
                <img src="/<?= e(ltrim($itemFilePath, '/')) ?>" alt="" style="width:50px;height:50px;object-fit:cover;border-radius:4px;">
              <?php elseif ($itemLinkUrl !== ''): ?>
                <div style="width:50px;height:50px;background:var(--bg-card2);border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:1.25rem;">🔗</div>
              <?php elseif ($itemType === 'video'): ?>
                <div style="width:50px;height:50px;background:var(--bg-card2);border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:1.25rem;">▶️</div>
              <?php endif; ?>
            </td>
            <td><?= e($item['title'] ?: '(untitled)') ?></td>
            <td><?= e(ucfirst($itemType)) ?></td>
            <td><?= e($item['uploader_name'] ?? '—') ?></td>
            <td><?= $itemCreatedAtTs !== null ? e(date('M j, Y', $itemCreatedAtTs)) : '—' ?></td>
            <td><span class="status-badge <?= $item['is_approved'] ? 'status-approved' : 'status-pending' ?>"><?= $item['is_approved'] ? 'Approved' : 'Pending' ?></span></td>
            <td>
              <div class="td-actions">
                <a href="/admin/gallery.php?action=edit&id=<?= e($item['id']) ?>" class="btn btn-outline btn-sm">Edit</a>
                <?php if (!$item['is_approved']): ?>
                  <form method="POST" style="display:inline;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                    <button type="submit" class="btn btn-secondary btn-sm">Approve</button>
                  </form>
                <?php else: ?>
                  <form method="POST" style="display:inline;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="unapprove">
                    <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                    <button type="submit" class="btn btn-outline btn-sm">Unapprove</button>
                  </form>
                <?php endif; ?>
                <form method="POST" style="display:inline;">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                  <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete this item? This cannot be undone.">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:2rem;">No gallery items yet.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($pagination['total_pages'] > 1): ?>
      <div class="pagination">
        <?php for ($p = 1; $p <= $pagination['total_pages']; $p++): ?>
          <a href="/admin/gallery.php?page=<?= $p ?>" class="page-link <?= $p === $pagination['current_page'] ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>

  </main>
</div>

<!-- Upload Modal -->
<div id="uploadModal" class="modal-backdrop">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Add Gallery Item</h3>
      <span class="modal-close" data-modal-close>&times;</span>
    </div>
    <div class="modal-body">
      <form method="POST" action="/admin/gallery.php" enctype="multipart/form-data" id="uploadForm">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="upload">

        <div class="form-group">
          <label class="form-label">Content Type</label>
          <select name="type" class="form-control" id="mediaType" onchange="toggleMediaType(this.value)">
            <option value="photo">Photo</option>
            <option value="video">Video</option>
          </select>
        </div>

        <div id="photoFields">
          <div class="form-group">
            <label class="form-label">Photo Source</label>
            <select name="photo_source" class="form-control" id="photoSource" onchange="togglePhotoSource(this.value)">
              <option value="upload">Upload Photo File</option>
              <option value="link">Link to Photo Page/Media</option>
            </select>
          </div>
          <div id="photoUploadField">
          <div class="form-group">
            <label class="form-label">Photo File</label>
            <div class="dropzone">
              <div class="dropzone-icon">📷</div>
              <p>Drop image here or click to select</p>
              <input type="file" name="media_file" accept="image/*" id="photoMediaFile">
            </div>
          </div>
          </div>
          <div id="photoLinkField" style="display:none;">
            <div class="form-group">
              <label class="form-label">Photo Link</label>
              <input type="url" name="link_url" class="form-control" id="galleryLinkUrl" placeholder="https://www.flickr.com/...">
            </div>
          </div>
        </div>

        <div id="videoFields" style="display:none;">
          <div class="form-group">
            <label class="form-label">Video Source</label>
            <select name="video_type" class="form-control" id="videoType" onchange="toggleVideoType(this.value)">
              <option value="embed">Embed URL (YouTube, Vimeo)</option>
              <option value="upload">Upload Video File</option>
              <option value="link">Link to Video Page</option>
            </select>
          </div>
          <div id="videoEmbedField">
            <div class="form-group">
              <label class="form-label">Video URL</label>
              <input type="url" name="video_url" class="form-control" placeholder="https://www.youtube.com/watch?v=...">
            </div>
          </div>
          <div id="videoUploadField" style="display:none;">
            <div class="form-group">
              <label class="form-label">Video File</label>
              <div class="dropzone">
                <div class="dropzone-icon">🎬</div>
                <p>Drop video here or click to select (MP4, WebM)</p>
                <input type="file" name="media_file" accept="video/*" id="videoMediaFile" disabled>
              </div>
            </div>
          </div>
          <div id="videoLinkField" style="display:none;">
            <div class="form-group">
              <label class="form-label">Video Link</label>
              <input type="url" name="link_url" class="form-control" id="galleryVideoLinkUrl" placeholder="https://www.youtube.com/watch?v=...">
            </div>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Tags (comma-separated)</label>
            <input type="text" name="tags" class="form-control" placeholder="haunted, event, 2024">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="3"></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Alt Text</label>
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
      <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
      <button type="submit" form="uploadForm" class="btn btn-primary btn-sm">Upload</button>
    </div>
  </div>
</div>

<script>
function syncGalleryUploadInputs() {
  const mediaType = document.getElementById('mediaType')?.value;
  const photoSource = document.getElementById('photoSource')?.value;
  const videoType = document.getElementById('videoType')?.value;
  const photoInput = document.getElementById('photoMediaFile');
  const videoInput = document.getElementById('videoMediaFile');
  const photoLinkInput = document.getElementById('galleryLinkUrl');
  const videoLinkInput = document.getElementById('galleryVideoLinkUrl');

  if (photoInput) photoInput.disabled = !(mediaType === 'photo' && photoSource === 'upload');
  if (videoInput) videoInput.disabled = !(mediaType === 'video' && videoType === 'upload');
  if (photoLinkInput) photoLinkInput.disabled = !(mediaType === 'photo' && photoSource === 'link');
  if (videoLinkInput) videoLinkInput.disabled = !(mediaType === 'video' && videoType === 'link');
}
function togglePhotoSource(source) {
  document.getElementById('photoUploadField').style.display = source === 'link' ? 'none' : '';
  document.getElementById('photoLinkField').style.display = source === 'link' ? '' : 'none';
  syncGalleryUploadInputs();
}
function toggleMediaType(type) {
  document.getElementById('photoFields').style.display = type === 'photo' ? '' : 'none';
  document.getElementById('videoFields').style.display = type === 'video' ? '' : 'none';
  syncGalleryUploadInputs();
}
function toggleVideoType(type) {
  document.getElementById('videoEmbedField').style.display  = type === 'embed'  ? '' : 'none';
  document.getElementById('videoUploadField').style.display = type === 'upload' ? '' : 'none';
  document.getElementById('videoLinkField').style.display = type === 'link' ? '' : 'none';
  syncGalleryUploadInputs();
}
syncGalleryUploadInputs();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
