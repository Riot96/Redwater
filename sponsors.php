<?php
/**
 * RedWater Entertainment - Sponsors Page
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle    = 'Sponsors';
$seoDescription = 'Our generous sponsors make RedWater Entertainment possible. Learn more about sponsorship opportunities.';

include __DIR__ . '/includes/header.php';

$tiers = getSponsorTiers();
?>

<main class="page-wrapper">
  <div class="page-header">
    <div class="container">
      <h1>Our <span style="color:var(--blue)">Sponsors</span></h1>
      <p>We are grateful for the support of our generous sponsors who help make RedWater Entertainment possible.</p>
    </div>
  </div>

  <section class="section-sm">
    <div class="container">

      <?php if (isAdmin()): ?>
        <div class="mb-3 text-center">
          <a href="/admin/sponsors.php" class="btn btn-outline btn-sm">⚙️ Manage Sponsors</a>
        </div>
      <?php endif; ?>

      <?php if (empty($tiers)): ?>
        <!-- No tiers configured yet -->
        <div class="text-center" style="padding: 4rem 0;">
          <div style="font-size:4rem;margin-bottom:1rem;">🤝</div>
          <h3>Sponsorship Opportunities Available</h3>
          <p class="text-muted">Interested in sponsoring RedWater Entertainment? Contact us to learn more about our sponsorship packages.</p>
          <a href="/contact.php" class="btn btn-primary mt-2">Become a Sponsor</a>
        </div>
      <?php else: ?>

        <?php foreach ($tiers as $tierIndex => $tier): ?>
          <?php
          /** @var list<array<string, mixed>> $tierSponsors */
          $tierSponsors = isset($tier['sponsors']) && is_array($tier['sponsors']) ? $tier['sponsors'] : [];
          // Determine cards per row
          $cols = max(1, min(6, intValue($tier['cards_per_row'] ?? 3, 3)));
          // Higher tiers (lower sort_order index) get bigger cards
          $tierLevel = $tierIndex + 1; // 1 = top/largest
          $cardClass = $tierIndex === 0 ? 'sponsor-tier-1' : '';
          ?>
          <div class="sponsor-tier <?= e($cardClass) ?>">
            <h3 class="sponsor-tier-heading">
              <span><?= e($tier['name']) ?></span> Sponsors
            </h3>

            <div class="sponsor-grid" style="--sponsor-cols: <?= $cols ?>;">

              <?php if (empty($tierSponsors)): ?>
                <!-- Show 2 placeholder cards when no sponsors -->
                <?php for ($p = 0; $p < $cols; $p++): ?>
                  <div class="sponsor-card placeholder">
                    <?php if ($tier['show_logo']): ?>
                      <div style="width:80px;height:80px;background:var(--bg-card2);border-radius:var(--radius);display:flex;align-items:center;justify-content:center;font-size:2rem;opacity:0.4;">🏢</div>
                    <?php endif; ?>
                    <?php if ($tier['show_name']): ?>
                      <div class="sponsor-placeholder-name">Your Business Here</div>
                    <?php endif; ?>
                    <?php if ($tier['show_description']): ?>
                      <div class="sponsor-description">This spot is still open! Contact us to claim your sponsorship.</div>
                    <?php endif; ?>
                    <?php if ($tier['show_link']): ?>
                      <a href="/contact.php" class="btn btn-outline btn-sm sponsor-link-btn">Claim This Spot</a>
                    <?php endif; ?>
                  </div>
                <?php endfor; ?>

              <?php else: ?>

                 <?php foreach ($tierSponsors as $sponsor): ?>
                  <div class="sponsor-card <?= (!empty($sponsor['link_url']) && $tier['show_link']) ? 'sponsor-card--clickable' : '' ?>">
                    <?php if ($tier['show_logo'] && !empty($sponsor['logo_url'])): ?>
                      <img src="<?= e($sponsor['logo_url']) ?>"
                           alt="<?= e($sponsor['name'] ?? 'Sponsor') ?> logo"
                           class="sponsor-logo"
                           onerror="this.style.display='none'">
                    <?php elseif ($tier['show_logo']): ?>
                      <div style="width:80px;height:80px;background:var(--bg-card2);border-radius:var(--radius);display:flex;align-items:center;justify-content:center;font-size:2rem;opacity:0.4;">🏢</div>
                    <?php endif; ?>

                    <?php if ($tier['show_name']): ?>
                      <?php if (!empty($sponsor['name'])): ?>
                        <div class="sponsor-name"><?= e($sponsor['name']) ?></div>
                      <?php else: ?>
                        <div class="sponsor-placeholder-name">Your Business Here</div>
                      <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($tier['show_description']): ?>
                      <?php if (!empty($sponsor['description'])): ?>
                        <div class="sponsor-description"><?= e($sponsor['description']) ?></div>
                      <?php else: ?>
                        <div class="sponsor-description">This spot is still open!</div>
                      <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($tier['show_link']): ?>
                      <?php if (!empty($sponsor['link_url']) && !empty($sponsor['name'])): ?>
                        <a href="<?= e($sponsor['link_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm sponsor-link-btn">Visit Website</a>
                      <?php else: ?>
                        <a href="/contact.php" class="btn btn-outline btn-sm sponsor-link-btn">Claim This Spot</a>
                      <?php endif; ?>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>

              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>

        <div class="divider"></div>
        <div class="text-center">
          <h3>Interested in Becoming a Sponsor?</h3>
          <p class="text-muted mt-1">Contact us to learn more about our sponsorship opportunities and packages.</p>
          <a href="/contact.php" class="btn btn-primary mt-2">Contact Us</a>
        </div>

      <?php endif; ?>
    </div>
  </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
