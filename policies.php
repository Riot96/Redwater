<?php
/**
 * RedWater Entertainment - Policies Page
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle    = 'Policies';
$seoDescription = 'RedWater Entertainment event policies, rules, and guidelines for all attendees.';

include __DIR__ . '/includes/header.php';

$db     = getDb();
$policyStmt = $db->query('SELECT * FROM policies WHERE id = 1');
assert($policyStmt instanceof PDOStatement);
$policy = $policyStmt->fetch();
$contentHtml = $policy['content_html'] ?? '<p>Policies coming soon.</p>';
$imagePath   = $policy['image_path'] ?? null;
$hasPolicyImage = !empty($imagePath) && file_exists($imagePath);
?>

<main class="page-wrapper">
  <div class="page-header">
    <div class="container">
      <h1>Event <span style="color:var(--red)">Policies</span></h1>
      <p>Please review our policies before attending any RedWater Entertainment event.</p>
    </div>
  </div>

  <section class="section-sm">
    <div class="container">

      <?php if (isAdmin()): ?>
        <div class="mb-3 text-center">
          <a href="/admin/policies.php" class="btn btn-outline btn-sm">⚙️ Edit Policies</a>
        </div>
      <?php endif; ?>

      <div class="policies-content">
        <?php if ($hasPolicyImage): ?>
          <div>
            <img src="/<?= e(ltrim($imagePath, '/')) ?>" alt="Event Policies" class="policies-image">
          </div>
        <?php endif; ?>
        <div class="policies-text <?= !$hasPolicyImage ? 'w-full' : '' ?>"
             style="<?= !$hasPolicyImage ? 'max-width:800px;margin:0 auto;' : '' ?>">
          <?php
          // NOTE: $contentHtml is admin-only content stored in the database and managed via
          // the admin policies editor. It is intentionally output as raw HTML to allow
          // rich formatting. Only trusted admin users can modify this content.
          ?>
          <?= $contentHtml ?>
        </div>
      </div>

      <div class="divider"></div>
      <div class="text-center">
        <p class="text-muted">Questions about our policies? <a href="/contact.php">Contact us</a> and we'll be happy to help.</p>
      </div>

    </div>
  </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
