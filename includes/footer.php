<?php
/**
 * RedWater Entertainment - Site Footer
 */

if (!defined('DB_HOST')) {
    require_once __DIR__ . '/config.php';
}

$user = currentUser();
$socialFacebook  = trim(getSetting('social_facebook'));
$socialInstagram = trim(getSetting('social_instagram'));
$socialTiktok    = trim(getSetting('social_tiktok'));
$socialYoutube   = trim(getSetting('social_youtube'));
$socialPinterest = trim(getSetting('social_pinterest'));
$contactEmail    = getSetting('contact_email');
$contactPhone    = getSetting('contact_phone');
$contactPhoneHref = preg_replace('/\D/', '', $contactPhone) ?? '';
?>

<footer class="site-footer">
    <div class="footer-glow"></div>
    <div class="container footer-grid">
        <!-- Brand -->
        <div class="footer-col footer-brand">
            <a href="/" class="footer-logo">
                <img src="/assets/images/logo.png" alt="RedWater Entertainment" class="logo-img-sm" onerror="this.style.display='none'; this.nextElementSibling.style.display='block'">
                <span class="logo-text-fallback-sm" style="display:none;">
                    <span class="logo-red">Red</span><span class="logo-blue">Water</span>
                    <span class="logo-ent">Entertainment</span>
                </span>
            </a>
            <p class="footer-tagline"><?= e(getSetting('site_tagline', 'Where Fear Meets Wonder')) ?></p>
            <?php if ($socialFacebook || $socialInstagram || $socialTiktok || $socialYoutube || $socialPinterest): ?>
            <div class="social-links">
                <?php if ($socialFacebook): ?><a href="<?= e($socialFacebook) ?>" target="_blank" rel="noopener" class="social-link" aria-label="Facebook">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                </a><?php endif; ?>
                <?php if ($socialInstagram): ?><a href="<?= e($socialInstagram) ?>" target="_blank" rel="noopener" class="social-link" aria-label="Instagram">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="0.5" fill="currentColor"/></svg>
                </a><?php endif; ?>
                <?php if ($socialTiktok): ?><a href="<?= e($socialTiktok) ?>" target="_blank" rel="noopener" class="social-link" aria-label="TikTok">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16.5 3c.34 1.86 1.45 3.4 3 4.39V10a8.06 8.06 0 0 1-3-.71V14.5a5.5 5.5 0 1 1-5.5-5.5c.34 0 .67.03 1 .09v2.79a2.75 2.75 0 1 0 1.75 2.56V3h2.75z"/></svg>
                </a><?php endif; ?>
                <?php if ($socialYoutube): ?><a href="<?= e($socialYoutube) ?>" target="_blank" rel="noopener" class="social-link" aria-label="YouTube">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46A2.78 2.78 0 0 0 1.46 6.42 29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58 2.78 2.78 0 0 0 1.95 1.95C5.12 20 12 20 12 20s6.88 0 8.59-.47a2.78 2.78 0 0 0 1.95-1.95A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58z"/><polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02" fill="#000"/></svg>
                </a><?php endif; ?>
                <?php if ($socialPinterest): ?><a href="<?= e($socialPinterest) ?>" target="_blank" rel="noopener" class="social-link" aria-label="Pinterest">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-3.64 19.31c-.05-1.64-.01-3.6.42-5.13l1.36-4.87s-.34-.68-.34-1.68c0-1.58.92-2.76 2.06-2.76.97 0 1.43.73 1.43 1.61 0 .98-.62 2.45-.95 3.81-.28 1.14.57 2.07 1.69 2.07 2.03 0 3.4-2.61 3.4-5.7 0-2.35-1.58-4.11-4.47-4.11-3.26 0-5.29 2.43-5.29 5.15 0 .94.28 1.61.72 2.13.2.24.23.34.16.61l-.18.74c-.06.27-.25.37-.49.27-1.36-.56-2-2.06-2-4.21 0-3.13 2.64-6.88 7.87-6.88 4.21 0 6.98 3.04 6.98 6.3 0 4.31-2.4 7.54-5.94 7.54-1.19 0-2.31-.64-2.69-1.37l-.73 2.78c-.27 1.02-.8 2.28-1.29 3.12A10 10 0 1 0 12 2z"/></svg>
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
                <li><a href="/raffle.php">Raffle</a></li>
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
