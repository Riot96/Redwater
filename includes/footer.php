<?php
/**
 * RedWater Entertainment - Site Footer
 */

if (!defined('DB_HOST')) {
    require_once __DIR__ . '/config.php';
}

$user = currentUser();
$socialFacebook  = getSetting('social_facebook');
$socialInstagram = getSetting('social_instagram');
$socialTwitter   = getSetting('social_twitter');
$socialYoutube   = getSetting('social_youtube');
$contactEmail    = getSetting('contact_email');
$contactPhone    = getSetting('contact_phone');
$contactPhoneHref = preg_replace('/\D/', '', $contactPhone) ?? '';
$logoAssetUrl = getLogoAssetUrl();
?>

<footer class="site-footer">
    <div class="footer-glow"></div>
    <div class="container footer-grid">
        <!-- Brand -->
        <div class="footer-col footer-brand">
            <a href="/" class="footer-logo">
                <img src="<?= e($logoAssetUrl) ?>" alt="RedWater Entertainment" class="logo-img-sm" onerror="this.style.display='none'; this.nextElementSibling.style.display='block'">
                <span class="logo-text-fallback-sm" style="display:none;">
                    <span class="logo-red">Red</span><span class="logo-blue">Water</span>
                    <span class="logo-ent">Entertainment</span>
                </span>
            </a>
            <p class="footer-tagline"><?= e(getSetting('site_tagline', 'Where Fear Meets Wonder')) ?></p>
            <?php if ($socialFacebook || $socialInstagram || $socialTwitter || $socialYoutube): ?>
            <div class="social-links">
                <?php if ($socialFacebook): ?><a href="<?= e($socialFacebook) ?>" target="_blank" rel="noopener" class="social-link" aria-label="Facebook">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                </a><?php endif; ?>
                <?php if ($socialInstagram): ?><a href="<?= e($socialInstagram) ?>" target="_blank" rel="noopener" class="social-link" aria-label="Instagram">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="0.5" fill="currentColor"/></svg>
                </a><?php endif; ?>
                <?php if ($socialTwitter): ?><a href="<?= e($socialTwitter) ?>" target="_blank" rel="noopener" class="social-link" aria-label="Twitter / X">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                </a><?php endif; ?>
                <?php if ($socialYoutube): ?><a href="<?= e($socialYoutube) ?>" target="_blank" rel="noopener" class="social-link" aria-label="YouTube">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46A2.78 2.78 0 0 0 1.46 6.42 29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58 2.78 2.78 0 0 0 1.95 1.95C5.12 20 12 20 12 20s6.88 0 8.59-.47a2.78 2.78 0 0 0 1.95-1.95A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58z"/><polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02" fill="#000"/></svg>
                </a><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Quick Links -->
        <div class="footer-col">
            <h4 class="footer-heading">Quick Links</h4>
            <ul class="footer-links">
                <li><a href="/">Home</a></li>
                <li><a href="/tickets.php">Tickets</a></li>
                <li><a href="/gallery.php">Gallery</a></li>
                <li><a href="/sponsors.php">Sponsors</a></li>
                <li><a href="/merch.php">Merch</a></li>
                <li><a href="/policies.php">Policies</a></li>
                <li><a href="/contact.php">Contact</a></li>
            </ul>
        </div>

        <!-- Contact Info -->
        <div class="footer-col">
            <h4 class="footer-heading">Contact</h4>
            <ul class="footer-links">
                <?php if ($contactPhone): ?><li><a href="tel:<?= e($contactPhoneHref) ?>"><?= e($contactPhone) ?></a></li><?php endif; ?>
                <?php if ($contactEmail): ?><li><a href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a></li><?php endif; ?>
                <li><a href="/contact.php">Send a Message</a></li>
            </ul>
        </div>
    </div>

    <!-- Bottom Bar -->
    <div class="footer-bottom">
        <div class="container footer-bottom-inner">
            <p>&copy; <?= date('Y') ?> <?= e(getSetting('site_name', 'RedWater Entertainment')) ?>. All rights reserved.</p>
            <div class="footer-bottom-links">
                <a href="/policies.php">Policies</a>
                <?php if ($user): ?>
                    <a href="<?= $user['role'] === 'admin' ? '/admin/' : '/member/' ?>">Dashboard</a>
                    <a href="/logout.php">Log Out</a>
                <?php else: ?>
                    <a href="/login.php">Member Login</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</footer>

<script src="/assets/js/main.js"></script>
</body>
</html>
