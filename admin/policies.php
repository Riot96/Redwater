<?php
/**
 * RedWater Entertainment - Admin: Policies
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db     = getDb();
$policyStmt = $db->query('SELECT * FROM policies WHERE id = 1');
assert($policyStmt instanceof PDOStatement);
$policy = $policyStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $contentHtml = $_POST['content_html'] ?? '';
    $imagePath   = $policy['image_path'] ?? null;

    // Handle image upload
    if (!empty($_FILES['policy_image']['name'])) {
        $upload = handleFileUpload(
            $_FILES['policy_image'],
            __DIR__ . '/../uploads/policies',
            defined('ALLOWED_IMAGE_TYPES') ? ALLOWED_IMAGE_TYPES : ['image/jpeg','image/png','image/gif','image/webp']
        );
        if ($upload['success']) {
            // Delete old image
            if ($imagePath && file_exists(__DIR__ . '/../' . ltrim($imagePath, '/'))) {
                @unlink(__DIR__ . '/../' . ltrim($imagePath, '/'));
            }
            $imagePath = 'uploads/policies/' . $upload['filename'];
        } else {
            flashMessage('error', 'Image upload failed: ' . $upload['error']);
            redirect('/admin/policies.php');
        }
    }

    // Handle image removal
    if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
        if ($imagePath && file_exists(__DIR__ . '/../' . ltrim($imagePath, '/'))) {
            @unlink(__DIR__ . '/../' . ltrim($imagePath, '/'));
        }
        $imagePath = null;
    }

    $stmt = $db->prepare('INSERT INTO policies (id, content_html, image_path) VALUES (1, ?, ?) ON DUPLICATE KEY UPDATE content_html=VALUES(content_html), image_path=VALUES(image_path)');
    $stmt->execute([$contentHtml, $imagePath]);

    flashMessage('success', 'Policies updated successfully.');
    redirect('/admin/policies.php');
}

$pageTitle   = 'Edit Policies';
$contentHtml = $policy['content_html'] ?? '';
$imagePath   = $policy['image_path'] ?? null;
include __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <main class="admin-main">
    <h1 class="admin-page-title">Edit <span>Policies</span></h1>

    <div class="card">
      <div class="card-body">
        <form method="POST" action="/admin/policies.php" enctype="multipart/form-data" data-editor-form>
          <?= csrfField() ?>

          <!-- Policy Image -->
          <div class="form-group">
            <label class="form-label">Policy Image</label>
            <?php if ($imagePath): ?>
              <div style="margin-bottom:1rem;">
                <img src="/<?= e(ltrim($imagePath, '/')) ?>" alt="Current policy image" style="max-height:200px;border-radius:var(--radius);border:1px solid var(--border);">
                <div class="mt-1">
                  <label class="form-check">
                    <input type="checkbox" name="remove_image" value="1"> Remove current image
                  </label>
                </div>
              </div>
            <?php endif; ?>
            <input type="file" name="policy_image" class="form-control" accept="image/*">
            <div class="form-hint">Upload a photo copy of your policies (JPG, PNG, GIF, or WebP). Optional.</div>
          </div>

          <!-- Policy Text Content -->
          <div class="form-group">
            <label class="form-label">Policy Text Content</label>
            <div class="editor-toolbar">
              <button data-cmd="bold" title="Bold"><strong>B</strong></button>
              <button data-cmd="italic" title="Italic"><em>I</em></button>
              <button data-cmd="underline" title="Underline"><u>U</u></button>
              <button data-cmd="insertUnorderedList" title="Bullet List">• List</button>
              <button data-cmd="insertOrderedList" title="Numbered List">1. List</button>
              <button data-cmd="formatBlock" data-val="h2" title="Heading 2">H2</button>
              <button data-cmd="formatBlock" data-val="h3" title="Heading 3">H3</button>
              <button data-cmd="formatBlock" data-val="p" title="Paragraph">P</button>
              <button data-cmd="removeFormat" title="Clear Formatting">Clear</button>
            </div>
            <div class="content-editable"
                 contenteditable="true"
                 data-sync-to="content_html"><?= $contentHtml ?></div>
            <input type="hidden" name="content_html" value="">
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Save Policies</button>
            <a href="/policies.php" class="btn btn-outline" target="_blank">Preview</a>
          </div>
        </form>
      </div>
    </div>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
