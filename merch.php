<?php
/**
 * RedWater Entertainment - Merch Page (Placeholder)
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle    = 'Merch';
$seoDescription = 'RedWater Entertainment merchandise — coming soon!';

include __DIR__ . '/includes/header.php';
?>

<main class="page-wrapper">
  <div class="page-header">
    <div class="container">
      <h1>Merch <span style="color:var(--blue)">Shop</span></h1>
    </div>
  </div>

  <section class="section-sm">
    <div class="container">
      <div class="merch-placeholder">
        <div class="icon">👕</div>
        <h2>Coming Soon</h2>
        <p>Our merch shop is under construction. Check back soon for RedWater Entertainment apparel, accessories, and more!</p>
        <a href="/contact.php" class="btn btn-secondary">Get Notified</a>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
