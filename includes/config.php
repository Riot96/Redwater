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
defined('GALLERY_WATERMARK_IMAGE_OPACITY') || define('GALLERY_WATERMARK_IMAGE_OPACITY', 0.45);
defined('GALLERY_WATERMARK_TEXT_OPACITY') || define('GALLERY_WATERMARK_TEXT_OPACITY', 0.35);
defined('GALLERY_WATERMARK_SHADOW_ALPHA_OFFSET') || define('GALLERY_WATERMARK_SHADOW_ALPHA_OFFSET', 5);
defined('MERCH_CHECKOUT_MAX_QUANTITY') || define('MERCH_CHECKOUT_MAX_QUANTITY', 25);
defined('RAFFLE_ENTRY_MAX_COUNT') || define('RAFFLE_ENTRY_MAX_COUNT', 5000);
defined('RAFFLE_ENTRY_MAX_BYTES') || define('RAFFLE_ENTRY_MAX_BYTES', 1024 * 1024);
defined('TURNSTILE_ADMIN_RECOVERY_DELAY_MICROSECONDS') || define('TURNSTILE_ADMIN_RECOVERY_DELAY_MICROSECONDS', 500000);

// ─── Session Settings ─────────────────────────────────────────────────────────
defined('SESSION_LIFETIME') || define('SESSION_LIFETIME', 3600 * 8);

// ─── Security ─────────────────────────────────────────────────────────────────
defined('APP_KEY') || define('APP_KEY', 'CHANGE_ME_RANDOM_32_CHAR_STRING_HERE');
// Advisory lock wait time in seconds for automatic schema migrations.
defined('AUTOMATIC_MIGRATION_LOCK_TIMEOUT') || define('AUTOMATIC_MIGRATION_LOCK_TIMEOUT', 10);

// ─── PDO Connection ───────────────────────────────────────────────────────────
function getDb(): PDO {
    /** @var PDO|null $pdo */
    static $pdo = null;
    static $migrationsRan = false;
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
    if (!$migrationsRan && shouldRunAutomaticDbMigrations()) {
        runAutomaticDbMigrations($pdo);
        $migrationsRan = true;
    }
    return $pdo;
}

// ─── Automatic Database Migrations ─────────────────────────────────────────────
function shouldRunAutomaticDbMigrations(): bool {
    if (defined('SETUP_MODE')) {
        return true;
    }

    if (defined('REDWATER_ALLOW_DB_MIGRATIONS') && constant('REDWATER_ALLOW_DB_MIGRATIONS')) {
        return true;
    }

    if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
        return true;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }

    $user = $_SESSION['user'] ?? null;
    return is_array($user) && (($user['role'] ?? null) === 'admin');
}

function automaticMigrationColumnName(string $columnDefinition): string {
    if (preg_match('/^`?([A-Za-z_][A-Za-z0-9_]*)`?\s+/', trim($columnDefinition), $matches) !== 1) {
        throw new InvalidArgumentException('Invalid migration column definition.');
    }

    return (string)$matches[1];
}

function automaticMigrationTableName(string $table): string {
    $allowedTables = [
        'users',
        'site_settings',
        'gallery_items',
        'sponsor_tiers',
        'sponsors',
        'policies',
        'contact_submissions',
        'volunteers',
        'volunteer_audit_log',
    ];
    if (!in_array($table, $allowedTables, true)) {
        throw new InvalidArgumentException('Invalid migration table name.');
    }

    return $table;
}

function validateAutomaticMigrationColumnDefinition(string $columnDefinition): void {
    if (preg_match('/^`?[A-Za-z_][A-Za-z0-9_]*`?\s+[A-Za-z]/', trim($columnDefinition)) !== 1) {
        throw new InvalidArgumentException('Invalid migration column definition.');
    }
}

/**
 * @return list<string>
 */
function automaticMigrationTableColumns(PDO $db, string $table): array {
    $table = automaticMigrationTableName($table);

    $stmt = $db->query('SHOW COLUMNS FROM `' . $table . '`');
    assert($stmt instanceof PDOStatement);
    $columns = [];
    /** @var list<array<string, mixed>> $rows */
    $rows = $stmt->fetchAll();
    foreach ($rows as $row) {
        $field = $row['Field'] ?? null;
        if (is_string($field) && $field !== '') {
            $columns[] = $field;
        }
    }

    return $columns;
}

function automaticMigrationHasColumn(PDO $db, string $table, string $column): bool {
    $table = automaticMigrationTableName($table);
    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) !== 1) {
        throw new InvalidArgumentException('Invalid migration column name.');
    }

    return in_array($column, automaticMigrationTableColumns($db, $table), true);
}

function ensureAutomaticMigrationColumn(PDO $db, string $table, string $columnDefinition): void {
    /** @var array<string, list<string>> $tableColumns */
    static $tableColumns = [];

    $table = automaticMigrationTableName($table);
    validateAutomaticMigrationColumnDefinition($columnDefinition);
    $columnName = automaticMigrationColumnName($columnDefinition);
    if (!isset($tableColumns[$table])) {
        $tableColumns[$table] = automaticMigrationTableColumns($db, $table);
    }

    if (in_array($columnName, $tableColumns[$table], true)) {
        return;
    }

    try {
        $db->exec('ALTER TABLE `' . $table . '` ADD COLUMN ' . $columnDefinition);
    } catch (PDOException $e) {
        $errorCode = $e->errorInfo[1] ?? null;
        if ($errorCode !== 1060) {
            throw $e;
        }
    }

    $tableColumns[$table][] = $columnName;
}

function hasAutomaticMigrationUniqueColumn(PDO $db, string $table, string $column): bool {
    $table = automaticMigrationTableName($table);
    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) !== 1) {
        throw new InvalidArgumentException('Invalid migration column name.');
    }

    $stmt = $db->query('SHOW INDEX FROM `' . $table . '`');
    assert($stmt instanceof PDOStatement);
    /** @var list<array<string, mixed>> $indexes */
    $indexes = $stmt->fetchAll();

    /** @var array<string, array<int, string>> $uniqueIndexes */
    $uniqueIndexes = [];

    foreach ($indexes as $index) {
        $keyName = $index['Key_name'] ?? null;
        $columnName = $index['Column_name'] ?? null;
        $nonUnique = $index['Non_unique'] ?? null;
        $seqInIndex = $index['Seq_in_index'] ?? null;

        if (!is_string($keyName) || $keyName === '' || !is_string($columnName)) {
            continue;
        }

        if (!is_numeric($nonUnique) || (int)$nonUnique !== 0) {
            continue;
        }

        if (!is_numeric($seqInIndex)) {
            continue;
        }

        $position = (int)$seqInIndex;
        $uniqueIndexes[$keyName][$position] = $columnName;
    }

    foreach ($uniqueIndexes as $indexColumns) {
        ksort($indexColumns);
        $indexColumns = array_values($indexColumns);
        if (count($indexColumns) === 1 && $indexColumns[0] === $column) {
            return true;
        }
    }

    return false;
}

function ensureAutomaticMigrationUniqueColumn(PDO $db, string $table, string $column, string $indexName): void {
    $table = automaticMigrationTableName($table);
    if (hasAutomaticMigrationUniqueColumn($db, $table, $column)) {
        return;
    }

    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $indexName) !== 1 || strlen($indexName) > 64) {
        throw new InvalidArgumentException('Invalid migration index name.');
    }

    try {
        $db->exec('ALTER TABLE `' . $table . '` ADD UNIQUE KEY `' . $indexName . '` (`' . $column . '`)');
    } catch (PDOException $e) {
        $errorCode = $e->errorInfo[1] ?? null;
        if ($errorCode !== 1061) {
            throw $e;
        }
    }
}

function runAutomaticDbMigrations(PDO $db): void {
    static $running = false;
    $lockName = 'redwater_auto_migrations';
    $lockAcquired = false;

    if ($running) {
        return;
    }

    $running = true;

    try {
        $lockStmt = $db->prepare('SELECT GET_LOCK(?, ?)');
        assert($lockStmt instanceof PDOStatement);
        $lockStmt->execute([$lockName, AUTOMATIC_MIGRATION_LOCK_TIMEOUT]);
        $lockResult = $lockStmt->fetchColumn();
        $lockAcquired = is_numeric($lockResult) && (int)$lockResult === 1;
        if (!$lockAcquired) {
            throw new RuntimeException('Failed to acquire automatic migration lock after ' . AUTOMATIC_MIGRATION_LOCK_TIMEOUT . ' seconds. Another migration may be in progress.');
        }

        $createTableStatements = [
            <<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'member') NOT NULL DEFAULT 'member',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    bypass_approval TINYINT(1) NOT NULL DEFAULT 0,
    reset_token VARCHAR(64) NULL DEFAULT NULL,
    reset_token_expires DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS site_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value LONGTEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS gallery_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    type ENUM('photo', 'video') NOT NULL DEFAULT 'photo',
    file_path VARCHAR(500) NULL,
    video_url VARCHAR(500) NULL,
    link_url VARCHAR(500) NULL,
    source_type ENUM('upload', 'embed', 'link') NOT NULL DEFAULT 'upload',
    video_type ENUM('upload', 'embed') NOT NULL DEFAULT 'upload',
    title VARCHAR(255) NULL,
    description TEXT NULL,
    tags VARCHAR(500) NULL,
    alt_text VARCHAR(500) NULL,
    seo_title VARCHAR(255) NULL,
    seo_description TEXT NULL,
    is_approved TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS sponsor_tiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    show_name TINYINT(1) NOT NULL DEFAULT 1,
    show_description TINYINT(1) NOT NULL DEFAULT 1,
    show_logo TINYINT(1) NOT NULL DEFAULT 1,
    show_link TINYINT(1) NOT NULL DEFAULT 1,
    cards_per_row INT NOT NULL DEFAULT 3,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS sponsors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tier_id INT NOT NULL,
    name VARCHAR(255) NULL,
    description TEXT NULL,
    logo_url VARCHAR(500) NULL,
    link_url VARCHAR(500) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tier_id) REFERENCES sponsor_tiers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS policies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_html LONGTEXT NULL,
    image_path VARCHAR(500) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS contact_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone_number VARCHAR(50) NULL,
    preferred_contact_method ENUM('email', 'phone') NOT NULL DEFAULT 'email',
    location_address VARCHAR(255) NULL,
    subject VARCHAR(255) NULL,
    message TEXT NOT NULL,
    privacy_consent TINYINT(1) NOT NULL DEFAULT 0,
    converted_volunteer_id INT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS volunteers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone_number VARCHAR(50) NULL,
    preferred_contact_method ENUM('email', 'phone') NOT NULL DEFAULT 'email',
    location_address VARCHAR(255) NULL,
    areas_of_interest TEXT NULL,
    availability TEXT NULL,
    message TEXT NULL,
    internal_notes TEXT NULL,
    privacy_consent TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('pending', 'active', 'inactive') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS volunteer_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    volunteer_id INT NULL,
    volunteer_name VARCHAR(255) NOT NULL,
    actor_user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        ];

        foreach ($createTableStatements as $statement) {
            $db->exec($statement);
        }

        $columnDefinitions = [
            'users' => [
                'email VARCHAR(255) NOT NULL',
                'password_hash VARCHAR(255) NOT NULL',
                'display_name VARCHAR(100) NOT NULL',
                "role ENUM('admin', 'member') NOT NULL DEFAULT 'member'",
                'is_active TINYINT(1) NOT NULL DEFAULT 1',
                'bypass_approval TINYINT(1) NOT NULL DEFAULT 0',
                'reset_token VARCHAR(64) NULL DEFAULT NULL',
                'reset_token_expires DATETIME NULL DEFAULT NULL',
                'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
                'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            ],
            'site_settings' => [
                'setting_key VARCHAR(100) NOT NULL',
                'setting_value LONGTEXT NULL',
                'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            ],
            'gallery_items' => [
                'user_id INT NULL',
                "type ENUM('photo', 'video') NOT NULL DEFAULT 'photo'",
                'file_path VARCHAR(500) NULL',
                'video_url VARCHAR(500) NULL',
                'link_url VARCHAR(500) NULL',
                "source_type ENUM('upload', 'embed', 'link') NOT NULL DEFAULT 'upload'",
                "video_type ENUM('upload', 'embed') NOT NULL DEFAULT 'upload'",
                'title VARCHAR(255) NULL',
                'description TEXT NULL',
                'tags VARCHAR(500) NULL',
                'alt_text VARCHAR(500) NULL',
                'seo_title VARCHAR(255) NULL',
                'seo_description TEXT NULL',
                'is_approved TINYINT(1) NOT NULL DEFAULT 0',
                'sort_order INT NOT NULL DEFAULT 0',
                'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
                'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            ],
            'sponsor_tiers' => [
                'name VARCHAR(100) NOT NULL',
                'sort_order INT NOT NULL DEFAULT 0',
                'show_name TINYINT(1) NOT NULL DEFAULT 1',
                'show_description TINYINT(1) NOT NULL DEFAULT 1',
                'show_logo TINYINT(1) NOT NULL DEFAULT 1',
                'show_link TINYINT(1) NOT NULL DEFAULT 1',
                'cards_per_row INT NOT NULL DEFAULT 3',
                'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            ],
            'sponsors' => [
                'tier_id INT NOT NULL',
                'name VARCHAR(255) NULL',
                'description TEXT NULL',
                'logo_url VARCHAR(500) NULL',
                'link_url VARCHAR(500) NULL',
                'sort_order INT NOT NULL DEFAULT 0',
                'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            ],
            'policies' => [
                'content_html LONGTEXT NULL',
                'image_path VARCHAR(500) NULL',
                'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            ],
            'contact_submissions' => [
                'name VARCHAR(255) NOT NULL',
                'email VARCHAR(255) NOT NULL',
                'phone_number VARCHAR(50) NULL',
                "preferred_contact_method ENUM('email', 'phone') NOT NULL DEFAULT 'email'",
                'location_address VARCHAR(255) NULL',
                'subject VARCHAR(255) NULL',
                'message TEXT NOT NULL',
                'privacy_consent TINYINT(1) NOT NULL DEFAULT 0',
                'converted_volunteer_id INT NULL',
                'is_read TINYINT(1) NOT NULL DEFAULT 0',
                'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
                'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            ],
            'volunteers' => [
                'full_name VARCHAR(255) NOT NULL',
                'email VARCHAR(255) NOT NULL',
                'phone_number VARCHAR(50) NULL',
                "preferred_contact_method ENUM('email', 'phone') NOT NULL DEFAULT 'email'",
                'location_address VARCHAR(255) NULL',
                'areas_of_interest TEXT NULL',
                'availability TEXT NULL',
                'message TEXT NULL',
                'internal_notes TEXT NULL',
                'privacy_consent TINYINT(1) NOT NULL DEFAULT 0',
                "status ENUM('pending', 'active', 'inactive') NOT NULL DEFAULT 'pending'",
                'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
                'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            ],
            'volunteer_audit_log' => [
                'volunteer_id INT NULL',
                'volunteer_name VARCHAR(255) NOT NULL',
                'actor_user_id INT NULL',
                'action VARCHAR(50) NOT NULL',
                'details TEXT NULL',
                'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            ],
        ];

        foreach ($columnDefinitions as $table => $definitions) {
            foreach ($definitions as $definition) {
                ensureAutomaticMigrationColumn($db, $table, $definition);
            }
        }

        $db->exec(
            "UPDATE gallery_items
             SET source_type = 'link'
             WHERE link_url IS NOT NULL
               AND TRIM(link_url) <> ''
               AND source_type <> 'link'"
        );
        $db->exec(
            "UPDATE gallery_items
             SET source_type = 'embed'
             WHERE type = 'video'
               AND video_type = 'embed'
               AND source_type = 'upload'"
        );

        ensureAutomaticMigrationUniqueColumn($db, 'users', 'email', 'users_email_unique');
        ensureAutomaticMigrationUniqueColumn($db, 'site_settings', 'setting_key', 'site_settings_key_unique');

        $db->exec(
            <<<'SQL'
INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES
('site_name', 'RedWater Entertainment'),
('site_tagline', 'Where Fear Meets Wonder'),
('raffle_settings', '{"entry_form_enabled":false,"title":"RedWater Giveaway Entry","description":"","collect_email":false,"require_email":false,"opt_in_label":"I want to receive email updates about future promotions.","expires_at":""}'),
('raffle_entries', '[]'),
('tickets_embed_code', ''),
('contact_phone', ''),
('contact_email', ''),
('contact_address', ''),
('contact_map_embed', ''),
('home_hero_heading', 'Experience the Fear'),
('home_hero_subheading', 'RedWater Entertainment brings you unforgettable haunted experiences, educational events, and so much more.'),
('home_about_text', 'RedWater Entertainment is Highlands County''s premier entertainment organization. We are best known for our spine-chilling "Red Water Haunted Homestead" each October, but we also offer educational events, workshops, and a variety of other live experiences throughout the year.'),
('gallery_watermark_settings', '{"enabled":false,"text":"","image_path":""}'),
('turnstile_enabled', '0'),
('turnstile_site_key', ''),
('turnstile_secret_key', ''),
('merch_store_settings', '{"paypal_email":"","paypal_currency":"USD","paypal_use_sandbox":false,"shipping_notice":"","pickup_notice":""}'),
('merch_catalog', '[]'),
('social_facebook', ''),
('social_instagram', ''),
('social_tiktok', ''),
('social_youtube', ''),
('social_pinterest', '')
SQL
        );

        $db->exec("INSERT INTO policies (id, content_html, image_path) VALUES (1, '<p>Policies content coming soon. Please check back later.</p>', NULL) ON DUPLICATE KEY UPDATE id = id");
    } finally {
        if ($lockAcquired) {
            $releaseStmt = $db->prepare('SELECT RELEASE_LOCK(?)');
            if ($releaseStmt instanceof PDOStatement) {
                $releaseStmt->execute([$lockName]);
            }
        }
        $running = false;
    }
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
