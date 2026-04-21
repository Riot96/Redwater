<?php
/**
 * RedWater Entertainment - Home Page
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle    = getSetting('site_name', 'RedWater Entertainment');
$seoDescription = 'RedWater Entertainment — Highlands County\'s premier haunted attraction, educational events, workshops, and live entertainment.';
$bodyClass    = 'home-page';

include __DIR__ . '/includes/header.php';
?>

<main>
  <!-- ── Hero ─────────────────────────────────────────────────────────────── -->
  <section class="hero">
    <div class="hero-bg"></div>
    <div class="hero-content">
      <span class="hero-eyebrow">Highlands County, FL &amp; Beyond</span>
      <h1>
        <span class="highlight-red"><?= e(getSetting('home_hero_heading', 'Experience the Fear')) ?></span>
      </h1>
      <p class="hero-subtitle"><?= e(getSetting('home_hero_subheading', 'RedWater Entertainment brings you unforgettable haunted experiences, educational events, and so much more.')) ?></p>
      <div class="hero-cta">
        <a href="/tickets.php" class="btn btn-primary btn-lg">Get Tickets</a>
        <a href="/gallery.php" class="btn btn-secondary btn-lg">View Gallery</a>
      </div>
    </div>
  </section>

  <!-- ── About ─────────────────────────────────────────────────────────────── -->
  <section class="section" id="about">
    <div class="container">
      <div class="about-grid">
        <div class="about-image-wrap">
          <img src="/assets/images/about-placeholder.jpg" alt="RedWater Entertainment" class="about-image" onerror="this.style.display='none'">
        </div>
        <div class="about-text">
          <h2>About <span>RedWater</span> Entertainment</h2>
          <p><?= getSetting('home_about_text', 'RedWater Entertainment is Highlands County\'s premier entertainment organization. We are best known for our spine-chilling &ldquo;Red Water Haunted Homestead&rdquo; each October, but we also offer educational events, workshops, and a variety of other live experiences throughout the year.') ?></p>
          <div class="about-badges">
            <span class="badge badge-red">🎃 Haunted Homestead</span>
            <span class="badge badge-blue">📚 Educational Events</span>
            <span class="badge">🎭 Live Workshops</span>
            <span class="badge">🎉 Special Events</span>
          </div>
          <div class="mt-3">
            <a href="/contact.php" class="btn btn-secondary">Contact Us</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ── What We Offer ─────────────────────────────────────────────────────── -->
  <section class="section" style="background: rgba(255,255,255,0.01); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);">
    <div class="container">
      <div class="section-title">
        <h2>What We Offer</h2>
        <p>From spine-chilling haunts to enriching educational experiences, there's something for everyone.</p>
      </div>
      <div class="features-grid">

        <!-- Haunted Homestead -->
        <div class="feature-card animate-on-scroll">
          <div class="feature-card-image placeholder" style="background: linear-gradient(135deg, #1a0000, #3d0000);">
            <span style="font-size:4rem;">🏚️</span>
          </div>
          <div class="feature-card-body">
            <h3>Red Water Haunted Homestead</h3>
            <p>Our flagship event every October — a terrifying walk-through haunted attraction that will leave you breathless. Featuring professional actors, elaborate sets, and unforgettable scares.</p>
            <a href="/tickets.php" class="btn btn-primary btn-sm mt-2">Get Tickets</a>
          </div>
        </div>

        <!-- Educational Events -->
        <div class="feature-card animate-on-scroll">
          <div class="feature-card-image placeholder" style="background: linear-gradient(135deg, #001a3d, #000d2e);">
            <span style="font-size:4rem;">📚</span>
          </div>
          <div class="feature-card-body">
            <h3>Educational Events</h3>
            <p>We believe entertainment and education go hand in hand. Our educational programs bring history, science, and the arts to life in ways that are engaging and memorable.</p>
            <a href="/contact.php" class="btn btn-secondary btn-sm mt-2">Learn More</a>
          </div>
        </div>

        <!-- Workshops -->
        <div class="feature-card animate-on-scroll">
          <div class="feature-card-image placeholder" style="background: linear-gradient(135deg, #001a0d, #001a0d);">
            <span style="font-size:4rem;">🎭</span>
          </div>
          <div class="feature-card-body">
            <h3>Workshops &amp; Trainings</h3>
            <p>Hands-on workshops covering makeup effects, theatrical performance, prop-making, and more. Perfect for aspiring entertainers and enthusiasts of all ages.</p>
            <a href="/contact.php" class="btn btn-secondary btn-sm mt-2">Learn More</a>
          </div>
        </div>

        <!-- Special Events -->
        <div class="feature-card animate-on-scroll">
          <div class="feature-card-image placeholder" style="background: linear-gradient(135deg, #1a1a00, #0d0d00);">
            <span style="font-size:4rem;">🎉</span>
          </div>
          <div class="feature-card-body">
            <h3>Special &amp; Community Events</h3>
            <p>Throughout the year we host and participate in a variety of community events, pop-ups, and special performances across Highlands County and beyond.</p>
            <a href="/contact.php" class="btn btn-secondary btn-sm mt-2">Stay Updated</a>
          </div>
        </div>

        <!-- Sponsors -->
        <div class="feature-card animate-on-scroll">
          <div class="feature-card-image placeholder" style="background: linear-gradient(135deg, #1a0033, #0d001a);">
            <span style="font-size:4rem;">🤝</span>
          </div>
          <div class="feature-card-body">
            <h3>Sponsorship Opportunities</h3>
            <p>Partner with RedWater Entertainment and align your brand with one of Highlands County's most exciting and growing entertainment organizations.</p>
            <a href="/sponsors.php" class="btn btn-secondary btn-sm mt-2">View Sponsors</a>
          </div>
        </div>

        <!-- Gallery -->
        <div class="feature-card animate-on-scroll">
          <div class="feature-card-image placeholder" style="background: linear-gradient(135deg, #001515, #000f0f);">
            <span style="font-size:4rem;">📸</span>
          </div>
          <div class="feature-card-body">
            <h3>Photo &amp; Video Gallery</h3>
            <p>Explore photos and videos from our events, behind-the-scenes moments, and community highlights. Relive the experience or get a taste of what's to come.</p>
            <a href="/gallery.php" class="btn btn-secondary btn-sm mt-2">Browse Gallery</a>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- ── Call to Action ─────────────────────────────────────────────────────── -->
  <section class="cta-section">
    <div class="container">
      <span class="cta-divider"></span>
      <h2>Ready for the Experience?</h2>
      <p>Secure your tickets for the Red Water Haunted Homestead and other upcoming events. Don't miss out — tickets sell fast!</p>
      <a href="/tickets.php" class="btn btn-primary btn-lg">Get Your Tickets Now</a>
    </div>
  </section>

</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
