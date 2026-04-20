<?php
/**
 * RedWater Entertainment - Site Header
 * @var string $pageTitle      (optional) Page title
 * @var string $pageMeta       (optional) Additional meta tags
 * @var string $bodyClass      (optional) Extra body classes
 */

if (!defined('DB_HOST')) {
    require_once __DIR__ . '/config.php';
}
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

initSession();

$siteName  = getSetting('site_name', 'RedWater Entertainment');
$pageTitle = isset($pageTitle) ? e($pageTitle) . ' | ' . e($siteName) : e($siteName);
$bodyClass = $bodyClass ?? '';

$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <?php if (!empty($seoDescription)): ?>
    <meta name="description" content="<?= e($seoDescription) ?>">
    <?php endif; ?>
    <?php if (!empty($pageMeta)) echo $pageMeta; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;900&family=Raleway:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">
</head>
<body class="<?= e($bodyClass) ?>">

<!-- ── Navigation ─────────────────────────────────────────────────────────── -->
<header class="site-header">
    <nav class="nav-container">
        <a href="/" class="nav-logo">
            <img src="/assets/images/logo.png" alt="<?= e($siteName) ?> Logo" class="logo-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='block'">
            <span class="logo-text-fallback" style="display:none;">
                <span class="logo-red">Red</span><span class="logo-blue">Water</span>
                <span class="logo-ent">Entertainment</span>
            </span>
        </a>

        <button class="nav-toggle" aria-label="Toggle navigation" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>

        <ul class="nav-menu">
            <li><a href="/" class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? ' active' : '' ?>">Home</a></li>
            <li><a href="/tickets.php" class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'tickets.php' ? ' active' : '' ?>">Tickets</a></li>
            <li><a href="/gallery.php" class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'gallery.php' ? ' active' : '' ?>">Gallery</a></li>
            <li><a href="/sponsors.php" class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'sponsors.php' ? ' active' : '' ?>">Sponsors</a></li>
            <li><a href="/merch.php" class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'merch.php' ? ' active' : '' ?>">Merch</a></li>
            <li><a href="/policies.php" class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'policies.php' ? ' active' : '' ?>">Policies</a></li>
            <li><a href="/contact.php" class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'contact.php' ? ' active' : '' ?>">Contact</a></li>
            <?php if ($user): ?>
            <li class="nav-dropdown">
                <button class="nav-link nav-user-btn" aria-expanded="false">
                    <?= e($user['display_name']) ?> ▾
                </button>
                <ul class="nav-dropdown-menu">
                    <?php if ($user['role'] === 'admin'): ?>
                    <li><a href="/admin/">Admin Dashboard</a></li>
                    <?php else: ?>
                    <li><a href="/member/">My Dashboard</a></li>
                    <li><a href="/member/gallery.php">My Gallery</a></li>
                    <?php endif; ?>
                    <li><a href="<?= $user['role'] === 'admin' ? '/admin/profile.php' : '/member/profile.php' ?>">My Profile</a></li>
                    <li class="dropdown-divider"></li>
                    <li><a href="/logout.php">Log Out</a></li>
                </ul>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
</header>

<!-- ── Flash Messages ─────────────────────────────────────────────────────── -->
<?php
$flashes = getFlashMessages();
if ($flashes):
?>
<div class="flash-container">
    <?php foreach ($flashes as $flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>">
        <?= e($flash['message']) ?>
        <button class="alert-close" aria-label="Close">&times;</button>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
