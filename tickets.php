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
$manualEvents = getTicketManualEvents();
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

      <?php if ($manualEvents !== []): ?>
        <div class="text-center mb-2">
          <h2>Featured Events</h2>
          <p class="text-muted mt-1">Reserve your spot for special events that are listed outside of our HauntPay ticket feed.</p>
        </div>

        <div class="features-grid tickets-manual-grid">
          <?php foreach ($manualEvents as $event): ?>
            <article class="feature-card tickets-event-card">
              <img src="<?= e($event['photo_url']) ?>" alt="<?= e($event['name']) ?>" class="feature-card-image tickets-event-image" loading="lazy">
              <div class="feature-card-body">
                <div class="tickets-event-meta">
                  <div class="tickets-event-meta-item">
                    <span class="tickets-event-meta-label">Date</span>
                    <strong><?= e($event['date']) ?></strong>
                  </div>
                  <div class="tickets-event-meta-item">
                    <span class="tickets-event-meta-label">Time</span>
                    <strong><?= e($event['time']) ?></strong>
                  </div>
                  <div class="tickets-event-meta-item">
                    <span class="tickets-event-meta-label">Cost</span>
                    <strong><?= e($event['cost']) ?></strong>
                  </div>
                </div>

                <h3><?= e($event['name']) ?></h3>
                <p class="tickets-event-description"><?= nl2br(e($event['description'])) ?></p>

                <?php if ($event['booking_url'] !== ''): ?>
                  <a href="<?= e($event['booking_url']) ?>" class="btn btn-primary btn-sm mt-2" target="_blank" rel="noopener noreferrer">Book This Event</a>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($embedCode)): ?>
        <?php if ($manualEvents !== []): ?>
          <div class="divider"></div>
        <?php endif; ?>

        <div class="text-center mb-2">
          <h2>HauntPay Events</h2>
          <p class="text-muted mt-1">Browse the live HauntPay event feed for current ticket availability.</p>
        </div>

        <div class="tickets-embed-wrap">
          <?= $embedCode /* Admin-controlled, stored securely in DB */ ?>
        </div>
      <?php elseif ($manualEvents === []): ?>
        <div class="tickets-embed-wrap">
          <div class="tickets-placeholder">
            <span style="font-size:4rem;">🎟️</span>
            <h2>Ticketing Coming Soon</h2>
            <p style="color:var(--text-muted)">Our ticketing system is being set up. Please check back soon or contact us for more information.</p>
            <a href="/contact.php" class="btn btn-secondary">Contact Us</a>
            <?php if (isAdmin()): ?>
              <div class="mt-3">
                <a href="/admin/tickets.php" class="btn btn-outline btn-sm">⚙️ Configure Ticket Settings</a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if (isAdmin()): ?>
        <div class="mt-2 text-center">
          <a href="/admin/tickets.php" class="btn btn-outline btn-sm">⚙️ Edit Ticket Settings</a>
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
