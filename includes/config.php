<?php
/**
 * RedWater Entertainment - Database Configuration
 *
 * To configure for your server, create 'includes/config.local.php' and define
 * the constants you want to override (see INSTALL.md for instructions).
 * All functions are defined here; config.local.php only overrides constants.
 *
 * IMPORTANT: Never commit real credentials to version control.
 */

// ─── Load local overrides (constants only) ────────────────────────────────────
if (file_exists(__DIR__ . '/config.local.php') && !defined('_RW_LOCAL_LOADED')) {
    define('_RW_LOCAL_LOADED', true);
    require_once __DIR__ . '/config.local.php';
}

// ─── Database Settings (defaults; override in config.local.php) ───────────────
defined('DB_HOST')    || define('DB_HOST',    'localhost');
defined('DB_NAME')    || define('DB_NAME',    'redwater');
defined('DB_USER')    || define('DB_USER',    'redwater_user');
defined('DB_PASS')    || define('DB_PASS',    'CHANGE_ME_STRONG_PASSWORD');
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');

// ─── Site Settings ────────────────────────────────────────────────────────────
defined('SITE_URL')   || define('SITE_URL',  'https://yourdomain.com');
defined('SITE_NAME')  || define('SITE_NAME', 'RedWater Entertainment');

// ─── Email Settings ───────────────────────────────────────────────────────────
defined('MAIL_FROM')      || define('MAIL_FROM',      'noreply@yourdomain.com');
defined('MAIL_FROM_NAME') || define('MAIL_FROM_NAME', 'RedWater Entertainment');

// ─── Upload Settings ──────────────────────────────────────────────────────────
defined('MAX_UPLOAD_SIZE')    || define('MAX_UPLOAD_SIZE',    50 * 1024 * 1024);
defined('ALLOWED_IMAGE_TYPES')|| define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
defined('ALLOWED_VIDEO_TYPES')|| define('ALLOWED_VIDEO_TYPES', ['video/mp4', 'video/webm', 'video/ogg']);

// ─── Session Settings ─────────────────────────────────────────────────────────
defined('SESSION_LIFETIME') || define('SESSION_LIFETIME', 3600 * 8);

// ─── Security ─────────────────────────────────────────────────────────────────
defined('APP_KEY') || define('APP_KEY', 'CHANGE_ME_RANDOM_32_CHAR_STRING_HERE');

// ─── PDO Connection ───────────────────────────────────────────────────────────
function getDb(): PDO {
    /** @var PDO|null $pdo */
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // In production, log this error; don't expose details
            error_log('Database connection failed: ' . $e->getMessage());
            die('A database error occurred. Please try again later.');
        }
    }
    return $pdo;
}

// ─── Helper: Get site setting ─────────────────────────────────────────────────
function getSetting(string $key, string $default = ''): string {
    /** @var array<string, string> $cache */
    static $cache = [];
    if (isset($cache[$key])) return $cache[$key];
    try {
        $db = getDb();
        $stmt = $db->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        /** @var array{setting_value: string}|false $row */
        $row = $stmt->fetch();
        $cache[$key] = $row ? (string)$row['setting_value'] : $default;
    } catch (Exception $e) {
        $cache[$key] = $default;
    }
    return $cache[$key];
}

// ─── Helper: Update site setting ──────────────────────────────────────────────
function setSetting(string $key, string $value): void {
    $db = getDb();
    $stmt = $db->prepare(
        'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}
