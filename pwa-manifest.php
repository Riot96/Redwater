<?php
/**
 * Logged-in PWA manifest.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$siteName = getSetting('site_name', 'RedWater Entertainment');
$manifest = [
    'name' => $siteName . ' Uploads',
    'short_name' => 'RedWater',
    'description' => 'Install RedWater to upload member gallery photos and videos with offline queue support.',
    'start_url' => '/member/gallery.php?source=pwa',
    'scope' => '/',
    'display' => 'standalone',
    'background_color' => '#080808',
    'theme_color' => '#080808',
    'icons' => [
        [
            'src' => '/assets/images/pwa-icon-192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
        ],
        [
            'src' => '/assets/images/pwa-icon-512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
        ],
    ],
];

header('Content-Type: application/manifest+json; charset=UTF-8');
header('Cache-Control: private, max-age=300');
echo (string) json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
