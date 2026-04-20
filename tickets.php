<?php
/**
 * RedWater Entertainment - Tickets Page
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Tickets';
$seoDescription = 'Purchase tickets for RedWater Entertainment events including the Red Water Haunted Homestead.';

include __DIR__ . '/includes/header.php';

$embedCode = getSetting('tickets_embed_code', '');
?>

<main class="page-wrapper">
  <div class="page-header">
    <div class="container">
      <h1>Get Your <span style="color:var(--red)">Tickets</span></h1>
      <p>Secure your spot at the Red Water Haunted Homestead and all upcoming RedWater Entertainment events.</p>
    </div>
  </div>

  <section class="section-sm">
    <div class="container">

      <?php if (!empty($embedCode)): ?>
        <div class="tickets-embed-wrap">
          <?= $embedCode /* Admin-controlled, stored securely in DB */ ?>
        </div>
      <?php else: ?>
        <div class="tickets-embed-wrap">
          <div class="tickets-placeholder">
            <span style="font-size:4rem;">🎟️</span>
            <h2>Ticketing Coming Soon</h2>
            <p style="color:var(--text-muted)">Our ticketing system is being set up. Please check back soon or contact us for more information.</p>
            <a href="/contact.php" class="btn btn-secondary">Contact Us</a>
            <?php if (isAdmin()): ?>
              <div class="mt-3">
                <a href="/admin/tickets.php" class="btn btn-outline btn-sm">⚙️ Configure Embed Code</a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if (isAdmin()): ?>
        <div class="mt-2 text-center">
          <a href="/admin/tickets.php" class="btn btn-outline btn-sm">⚙️ Edit Embed Code</a>
        </div>
      <?php endif; ?>

      <div class="divider"></div>

      <div class="text-center">
        <h3>Questions About Tickets?</h3>
        <p class="text-muted mt-1">We're here to help. Reach out to us and we'll get back to you promptly.</p>
        <a href="/contact.php" class="btn btn-secondary mt-2">Contact Us</a>
      </div>

    </div>
  </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
