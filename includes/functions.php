<?php
/**
 * RedWater Entertainment - Utility Functions
 */

// ─── Output Helpers ───────────────────────────────────────────────────────────
function stringValue(mixed $value, string $default = ''): string {
    return is_scalar($value) ? (string)$value : $default;
}

function intValue(mixed $value, int $default = 0): int {
    return is_numeric($value) ? (int)$value : $default;
}

function formatDateOrFallback(mixed $value, string $format, string $fallback = '—'): string {
    $timestamp = strtotime(stringValue($value));
    return $timestamp === false ? $fallback : date($format, $timestamp);
}

function e(mixed $s): string {
    return htmlspecialchars(stringValue($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * @param array<string, mixed> $source
 */
function requestString(array $source, string $key, string $default = ''): string {
    $value = $source[$key] ?? $default;
    return stringValue($value, $default);
}

function postString(string $key, string $default = ''): string {
    /** @var array<string, mixed> $post */
    $post = $_POST;
    return requestString($post, $key, $default);
}

function getString(string $key, string $default = ''): string {
    /** @var array<string, mixed> $get */
    $get = $_GET;
    return requestString($get, $key, $default);
}

function serverString(string $key, string $default = ''): string {
    /** @var array<string, mixed> $server */
    $server = $_SERVER;
    return requestString($server, $key, $default);
}

function postInt(string $key, int $default = 0): int {
    return intValue(postString($key, (string)$default), $default);
}

function getInt(string $key, int $default = 0): int {
    return intValue(getString($key, (string)$default), $default);
}

function postBool(string $key): bool {
    return array_key_exists($key, $_POST);
}

/**
 * @return array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int}|null
 */
function uploadedFile(string $key): ?array {
    $file = $_FILES[$key] ?? null;
    if (!is_array($file)) {
        return null;
    }

    return [
        'name' => stringValue($file['name'] ?? null),
        'type' => stringValue($file['type'] ?? null),
        'tmp_name' => stringValue($file['tmp_name'] ?? null),
        'error' => intValue($file['error'] ?? null, UPLOAD_ERR_NO_FILE),
        'size' => intValue($file['size'] ?? null),
    ];
}

/**
 * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int}|null $file
 */
function hasUploadedFile(?array $file): bool {
    return $file !== null
        && !empty($file['name'])
        && intValue($file['error'] ?? null, UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

function isSupportedPaypalCurrencyCode(string $currency): bool {
    return in_array($currency, [
        'AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP',
        'HKD', 'ILS', 'MXN', 'MYR', 'NOK', 'NZD',
        'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'USD',
    ], true);
}

function flashMessage(string $type, string $message): void {
    initSession();
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * @return list<array{type: string, message: string}>
 */
function getFlashMessages(): array {
    initSession();
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    if (!is_array($messages)) {
        return [];
    }

    $normalized = [];
    foreach ($messages as $message) {
        if (!is_array($message)) {
            continue;
        }
        $normalized[] = [
            'type' => isset($message['type']) && is_string($message['type']) ? $message['type'] : 'info',
            'message' => isset($message['message']) && is_string($message['message']) ? $message['message'] : '',
        ];
    }

    return $normalized;
}

function renderFlashMessages(): string {
    $html = '';
    foreach (getFlashMessages() as $flash) {
        $type = in_array($flash['type'], ['success', 'error', 'info', 'warning']) ? $flash['type'] : 'info';
        $html .= '<div class="alert alert-' . $type . '">' . e($flash['message']) . '</div>';
    }
    return $html;
}

// ─── File Upload Helpers ───────────────────────────────────────────────────────
/**
 * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int} $file
 * @param list<string> $allowedMimes
 * @return array{success: true, path: string, filename: string}|array{success: false, error: string}
 */
function handleFileUpload(array $file, string $destDir, array $allowedMimes, int $maxSize = 0): array {
    if ($maxSize <= 0) $maxSize = defined('MAX_UPLOAD_SIZE') ? MAX_UPLOAD_SIZE : 50 * 1024 * 1024;

    $error = isset($file['error']) ? (int)$file['error'] : UPLOAD_ERR_NO_FILE;
    if ($error !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension.',
        ];
        return ['success' => false, 'error' => $errors[$error] ?? 'Unknown upload error.'];
    }

    $size = isset($file['size']) ? (int)$file['size'] : 0;
    if ($size > $maxSize) {
        return ['success' => false, 'error' => 'File is too large. Maximum size is ' . formatBytes($maxSize) . '.'];
    }

    // Validate MIME type using finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $tmpName = isset($file['tmp_name']) ? (string)$file['tmp_name'] : '';
    $mime = $finfo->file($tmpName);
    if (!in_array($mime, $allowedMimes, true)) {
        return ['success' => false, 'error' => 'File type not allowed. Allowed types: ' . implode(', ', $allowedMimes)];
    }

    // Generate safe filename
    $name = isset($file['name']) ? (string)$file['name'] : '';
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $filename = uniqid('rw_', true) . '.' . $ext;
    $destPath = rtrim($destDir, '/') . '/' . $filename;

    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    if (!move_uploaded_file($tmpName, $destPath)) {
        return ['success' => false, 'error' => 'Failed to save uploaded file.'];
    }

    return ['success' => true, 'path' => $destPath, 'filename' => $filename];
}

function formatBytes(int $bytes, int $precision = 2): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow   = (int) floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow   = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function deleteUploadedFile(string $path): void {
    if (!empty($path) && file_exists($path)) {
        @unlink($path);
    }
}

// ─── Gallery Helpers ──────────────────────────────────────────────────────────
/**
 * @return list<array<string, mixed>>
 */
function getGalleryItems(bool $approvedOnly = true, ?int $userId = null): array {
    $db = getDb();
    $sql = 'SELECT g.*, u.display_name AS uploader_name
            FROM gallery_items g
            LEFT JOIN users u ON g.user_id = u.id
            WHERE 1=1';
    $params = [];

    if ($approvedOnly) {
        $sql .= ' AND g.is_approved = 1';
    }
    if ($userId !== null) {
        $sql .= ' AND g.user_id = ?';
        $params[] = $userId;
    }

    $sql .= ' ORDER BY g.sort_order ASC, g.created_at DESC';
    $stmt = $db->prepare($sql);
    assert($stmt instanceof PDOStatement);
    $stmt->execute($params);
    /** @var list<array<string, mixed>> $items */
    $items = array_values($stmt->fetchAll());
    return $items;
}

/**
 * @return array<string, mixed>|null
 */
function getGalleryItem(int $id): ?array {
    $db = getDb();
    $stmt = $db->prepare(
        'SELECT g.*, u.display_name AS uploader_name
         FROM gallery_items g
         LEFT JOIN users u ON g.user_id = u.id
         WHERE g.id = ?'
    );
    assert($stmt instanceof PDOStatement);
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    /** @var array<string, mixed>|false $item */
    return $item ?: null;
}

function isSupportedGalleryLinkUrl(string $url): bool {
    $url = trim($url);
    if ($url === '') {
        return false;
    }

    $parts = parse_url($url);
    if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
        return false;
    }

    return strtolower((string) $parts['scheme']) === 'https';
}

/**
 * @param array<string, mixed> $item
 */
function getGalleryItemSourceType(array $item): string {
    $sourceType = stringValue($item['source_type'] ?? '');
    $type = stringValue($item['type'] ?? '');
    $videoType = stringValue($item['video_type'] ?? '');
    $videoUrl = stringValue($item['video_url'] ?? '');
    $linkUrl = stringValue($item['link_url'] ?? '');

    if ($sourceType === 'link' && isSupportedGalleryLinkUrl($linkUrl)) {
        return 'link';
    }

    if ($sourceType === 'embed' && $type === 'video' && getVideoEmbedUrl($videoUrl) !== '') {
        return 'embed';
    }

    if (isSupportedGalleryLinkUrl($linkUrl)) {
        return 'link';
    }

    if ($type === 'video' && $videoType === 'embed' && getVideoEmbedUrl($videoUrl) !== '') {
        return 'embed';
    }

    return 'upload';
}

/**
 * @return array{source_type: string, video_type: string}
 */
function getGalleryStoredSourceTypes(string $type, string $photoSource, string $videoType): array {
    $sourceType = 'upload';

    if ($type === 'photo' && $photoSource === 'link') {
        $sourceType = 'link';
    } elseif ($type === 'video' && $videoType === 'link') {
        $sourceType = 'link';
    } elseif ($type === 'video' && $videoType === 'embed') {
        $sourceType = 'embed';
    }

    return [
        'source_type' => $sourceType,
        'video_type' => $sourceType === 'embed' ? 'embed' : 'upload',
    ];
}

/**
 * @return array{type: string, photo_source: string, video_type: string}|null
 */
function getValidatedGalleryUploadSelections(string $type, string $photoSource, string $videoType): ?array {
    if (!in_array($type, ['photo', 'video'], true)) {
        return null;
    }

    if (!in_array($photoSource, ['upload', 'link'], true)) {
        return null;
    }

    if (!in_array($videoType, ['embed', 'upload', 'link'], true)) {
        return null;
    }

    return [
        'type' => $type,
        'photo_source' => $photoSource,
        'video_type' => $videoType,
    ];
}

function isYoutubeUrl(string $url): bool {
    return (bool)preg_match('/(?:youtube\.com|youtu\.be)/', $url);
}

function isVimeoUrl(string $url): bool {
    return (bool)preg_match('/vimeo\.com/', $url);
}

function isSupportedVideoUrl(string $url): bool {
    return getVideoEmbedUrl($url) !== '';
}

function getVideoEmbedUrl(string $url): string {
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    $parts = parse_url($url);
    if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }

    $scheme = strtolower((string) $parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return '';
    }

    $host = strtolower((string) $parts['host']);

    // YouTube
    if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'], true)) {
        $videoId = '';

        if ($host === 'youtu.be') {
            $path = trim((string) ($parts['path'] ?? ''), '/');
            if (preg_match('/^[a-zA-Z0-9_-]+$/', $path)) {
                $videoId = $path;
            }
        } else {
            $query = [];
            parse_str($parts['query'] ?? '', $query);
            $videoParam = $query['v'] ?? '';
            if (is_string($videoParam) && preg_match('/^[a-zA-Z0-9_-]+$/', $videoParam)) {
                $videoId = $videoParam;
            }
        }

        if ($videoId !== '') {
            return 'https://www.youtube.com/embed/' . $videoId;
        }

        return '';
    }

    // Vimeo
    if (in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)) {
        $path = trim((string) ($parts['path'] ?? ''), '/');
        if (preg_match('/(?:video\/)?(\d+)/', $path, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }

        return '';
    }

    return '';
}

// ─── Sponsor Helpers ──────────────────────────────────────────────────────────
/**
 * @return list<array<string, mixed>>
 */
function getSponsorTiers(): array {
    $db = getDb();
    $stmt = $db->query(
        'SELECT * FROM sponsor_tiers ORDER BY sort_order ASC'
    );
    assert($stmt instanceof PDOStatement);
    /** @var list<array<string, mixed>> $tiers */
    $tiers = array_values($stmt->fetchAll());

    foreach ($tiers as &$tier) {
        $stmt = $db->prepare('SELECT * FROM sponsors WHERE tier_id = ? ORDER BY sort_order ASC');
        assert($stmt instanceof PDOStatement);
        $stmt->execute([$tier['id']]);
        /** @var list<array<string, mixed>> $sponsors */
        $sponsors = array_values($stmt->fetchAll());
        $tier['sponsors'] = $sponsors;
    }
    return $tiers;
}

// ─── Redirect ─────────────────────────────────────────────────────────────────
/**
 * @return never
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/**
 * @return never
 */
function redirectWithMessage(string $url, string $type, string $message): void {
    flashMessage($type, $message);
    redirect($url);
}

function normalizePreferredContactMethod(string $method): string {
    if (in_array($method, ['email', 'phone'], true)) {
        return $method;
    }

    if ($method !== '') {
        error_log('Unexpected preferred contact method received: ' . sanitizeMailHeaderValue($method));
    }

    return 'email';
}

function sanitizeMailHeaderValue(string $value): string {
    return trim(str_replace(["\r", "\n"], '', $value));
}

function sendSiteMail(string $toEmail, string $subject, string $body, string $replyToEmail = '', string $replyToName = ''): bool {
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $host = serverString('HTTP_HOST', 'localhost');
    $fallbackFrom = 'noreply@' . ($host !== '' ? $host : 'localhost');
    $from = defined('MAIL_FROM') ? stringValue(MAIL_FROM, $fallbackFrom) : $fallbackFrom;
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $from = 'noreply@localhost';
    }

    $fallbackSiteName = defined('SITE_NAME') ? stringValue(SITE_NAME, 'RedWater Entertainment') : 'RedWater Entertainment';
    $fromName = sanitizeMailHeaderValue(defined('MAIL_FROM_NAME') ? stringValue(MAIL_FROM_NAME, $fallbackSiteName) : $fallbackSiteName);
    $safeSubject = sanitizeMailHeaderValue($subject);
    $headers = 'From: ' . $fromName . ' <' . $from . '>';

    if (filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
        $safeReplyToName = sanitizeMailHeaderValue($replyToName);
        $headers .= "\r\nReply-To: " . $safeReplyToName . ' <' . sanitizeMailHeaderValue($replyToEmail) . '>';
    }

    return @mail($toEmail, $safeSubject, $body, $headers);
}

function logVolunteerAudit(PDO $db, ?int $volunteerId, string $volunteerName, ?int $actorUserId, string $action, string $details = ''): void {
    $stmt = $db->prepare(
        'INSERT INTO volunteer_audit_log (volunteer_id, volunteer_name, actor_user_id, action, details)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $volunteerId,
        $volunteerName,
        $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null,
        $action,
        $details,
    ]);
}

// ─── Pagination ───────────────────────────────────────────────────────────────
/**
 * @return array{total: int, per_page: int, current_page: int, total_pages: int, offset: int}
 */
function paginate(int $total, int $perPage, int $currentPage): array {
    $totalPages = max(1, (int)ceil($total / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    return [
        'total'        => $total,
        'per_page'     => $perPage,
        'current_page' => $currentPage,
        'total_pages'  => $totalPages,
        'offset'       => $offset,
    ];
}

// ─── Tags Helper ─────────────────────────────────────────────────────────────
/**
 * @return list<string>
 */
function parseTags(string $tags): array {
    return array_values(array_filter(array_map('trim', explode(',', $tags)), static fn (string $tag): bool => $tag !== ''));
}

/**
 * @param list<string> $tags
 */
function renderTags(array $tags): string {
    $html = '';
    foreach ($tags as $tag) {
        $html .= '<span class="tag">' . e($tag) . '</span>';
    }
    return $html;
}

// ─── Merch Helpers ────────────────────────────────────────────────────────────
function merchNormalizeAmount(string $value): string {
    $normalized = str_replace([',', '$', ' '], '', trim($value));
    if (str_starts_with($normalized, '.')) {
        $normalized = '0' . $normalized;
    }
    if ($normalized === '' || preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized) !== 1) {
        return '0.00';
    }

    return number_format(max(0, (float) $normalized), 2, '.', '');
}

function isValidMerchAmountInput(string $value, bool $allowEmpty = false): bool {
    $trimmed = trim($value);
    if ($trimmed === '') {
        return $allowEmpty;
    }

    $normalizedForValidation = $trimmed;
    if (str_starts_with($normalizedForValidation, '$')) {
        $normalizedForValidation = substr($normalizedForValidation, 1);
    }
    if (str_starts_with($normalizedForValidation, '.')) {
        $normalizedForValidation = '0' . $normalizedForValidation;
    }

    return preg_match('/^\d+(?:\.\d{1,2})?$/', $normalizedForValidation) === 1;
}

function merchFormatAmount(string $amount, string $currency = 'USD'): string {
    $normalized = merchNormalizeAmount($amount);
    if ($currency === 'USD') {
        return '$' . $normalized;
    }

    return $currency . ' ' . $normalized;
}

function merchSlugify(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    if ($value === null) {
        return 'item';
    }
    $value = trim($value, '-');
    return $value !== '' ? $value : 'item';
}

function merchGenerateItemId(): string {
    try {
        return 'merch_' . bin2hex(random_bytes(8));
    } catch (Exception $e) {
        return uniqid('merch_', false);
    }
}

function merchCheckoutMaxQuantity(): int {
    return max(1, defined('MERCH_CHECKOUT_MAX_QUANTITY') ? (int) MERCH_CHECKOUT_MAX_QUANTITY : 25);
}

/**
 * @return list<string>
 */
function merchParseVariantLines(string $variants): array {
    $lines = preg_split('/\r?\n|\r/', $variants);
    if ($lines === false) {
        return [];
    }
    $normalized = [];
    foreach ($lines as $line) {
        $variant = trim($line);
        if ($variant === '' || in_array($variant, $normalized, true)) {
            continue;
        }
        $normalized[] = $variant;
    }

    return $normalized;
}

function normalizeLocalMerchImagePath(string $path): string {
    $normalized = trim($path);
    if ($normalized === '') {
        return '';
    }
    if (preg_match('/[\x00-\x1F\x7F\\\\]/', $normalized) === 1 || str_contains($normalized, '..')) {
        return '';
    }
    if (preg_match('#^/uploads/merch/[A-Za-z0-9_](?:[A-Za-z0-9_-]*[A-Za-z0-9_])?(?:\.[A-Za-z0-9_](?:[A-Za-z0-9_-]*[A-Za-z0-9_])?)*\.(?:jpe?g|png|gif|webp)$#i', $normalized) !== 1) {
        return '';
    }

    return $normalized;
}

function isSupportedMerchImageUrl(string $url): bool {
    $url = trim($url);
    if ($url === '') {
        return false;
    }

    if (normalizeLocalMerchImagePath($url) !== '') {
        return true;
    }

    $parts = parse_url($url);
    if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
        return false;
    }

    return strtolower((string) $parts['scheme']) === 'https';
}

function isManagedMerchImagePath(string $path): bool {
    return normalizeLocalMerchImagePath($path) !== '';
}

function deleteManagedMerchImage(string $path): void {
    $path = trim($path);
    if (!isManagedMerchImagePath($path)) {
        return;
    }

    $candidate = realpath(dirname(__DIR__) . '/' . ltrim($path, '/'));
    $uploadsRoot = realpath(dirname(__DIR__) . '/uploads/merch');
    if ($candidate === false || $uploadsRoot === false) {
        return;
    }

    if ($candidate !== $uploadsRoot && str_starts_with($candidate, $uploadsRoot . DIRECTORY_SEPARATOR)) {
        deleteUploadedFile($candidate);
    }
}

/**
 * @param array<string, mixed> $settings
 * @return array{
 *   paypal_email: string,
 *   paypal_currency: string,
 *   paypal_use_sandbox: bool,
 *   shipping_notice: string,
 *   pickup_notice: string
 * }
 */
function normalizeMerchStoreSettings(array $settings): array {
    $paypalEmail = trim(stringValue($settings['paypal_email'] ?? ''));
    if ($paypalEmail !== '' && filter_var($paypalEmail, FILTER_VALIDATE_EMAIL) === false) {
        $paypalEmail = '';
    }

    $currency = strtoupper(trim(stringValue($settings['paypal_currency'] ?? 'USD')));
    if (preg_match('/^[A-Z]{3}$/', $currency) !== 1 || !isSupportedPaypalCurrencyCode($currency)) {
        $currency = 'USD';
    }

    return [
        'paypal_email' => $paypalEmail,
        'paypal_currency' => $currency,
        'paypal_use_sandbox' => !empty($settings['paypal_use_sandbox']),
        'shipping_notice' => trim(stringValue($settings['shipping_notice'] ?? '')),
        'pickup_notice' => trim(stringValue($settings['pickup_notice'] ?? '')),
    ];
}

/**
 * @param array{
 *   paypal_email: string,
 *   paypal_currency: string,
 *   paypal_use_sandbox: bool,
 *   shipping_notice: string,
 *   pickup_notice: string
 * } $storeSettings
 */
function merchPaypalCheckoutUrl(array $storeSettings): string {
    return $storeSettings['paypal_use_sandbox']
        ? 'https://www.sandbox.paypal.com/cgi-bin/webscr'
        : 'https://www.paypal.com/cgi-bin/webscr';
}

/**
 * @param array{
 *   paypal_email: string,
 *   paypal_currency: string,
 *   paypal_use_sandbox: bool,
 *   shipping_notice: string,
 *   pickup_notice: string
 * } $storeSettings
 */
function merchPaypalEnvironmentLabel(array $storeSettings): string {
    return $storeSettings['paypal_use_sandbox'] ? 'PayPal Sandbox' : 'Live PayPal';
}

function merchPaypalSandboxTestingHint(): string {
    return 'Sandbox testing requires the email for a PayPal Developer sandbox business seller account, and checkout must be completed with a different sandbox personal buyer account. Using the seller login or a live PayPal account will trigger PayPal\'s generic payment error.';
}

/**
 * @return array{
 *   paypal_email: string,
 *   paypal_currency: string,
 *   paypal_use_sandbox: bool,
 *   shipping_notice: string,
 *   pickup_notice: string
 * }
 */
function getMerchStoreSettings(): array {
    $raw = trim(getSetting('merch_store_settings', ''));
    if ($raw === '') {
        return normalizeMerchStoreSettings([]);
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return normalizeMerchStoreSettings([]);
    }

    /** @var array<string, mixed> $decoded */
    return normalizeMerchStoreSettings($decoded);
}

/**
 * @param array{
 *   paypal_email: string,
 *   paypal_currency: string,
 *   paypal_use_sandbox: bool,
 *   shipping_notice: string,
 *   pickup_notice: string
 * } $settings
 */
function saveMerchStoreSettings(array $settings): void {
    $json = json_encode(normalizeMerchStoreSettings($settings), JSON_UNESCAPED_SLASHES);
    setSetting('merch_store_settings', is_string($json) ? $json : '{}');
}

/**
 * @param array<string, mixed> $item
 * @return array{
 *   id: string,
 *   slug: string,
 *   name: string,
 *   seo_title: string,
 *   seo_description: string,
 *   description: string,
 *   price: string,
 *   category: string,
 *   tags: string,
 *   variants: list<string>,
 *   image_path: string,
 *   shipping_enabled: bool,
 *   shipping_cost: string,
 *   shipping_notes: string,
 *   pickup_enabled: bool,
 *   pickup_notes: string,
 *   sort_order: int,
 *   is_active: bool
 * }
 */
function normalizeMerchItem(array $item): array {
    $variants = [];
    $rawVariants = $item['variants'] ?? [];
    if (is_array($rawVariants)) {
        foreach ($rawVariants as $variant) {
            $variantName = trim(stringValue($variant));
            if ($variantName === '' || in_array($variantName, $variants, true)) {
                continue;
            }
            $variants[] = $variantName;
        }
    }

    $name = trim(stringValue($item['name'] ?? ''));
    $slug = merchSlugify(stringValue($item['slug'] ?? $name));
    $imagePath = trim(stringValue($item['image_path'] ?? ''));
    $trimmedId = trim(stringValue($item['id'] ?? ''));
    if ($imagePath !== '' && !isSupportedMerchImageUrl($imagePath)) {
        $imagePath = '';
    }

    return [
        'id' => $trimmedId !== '' ? $trimmedId : merchGenerateItemId(),
        'slug' => $slug,
        'name' => $name,
        'seo_title' => trim(stringValue($item['seo_title'] ?? '')),
        'seo_description' => trim(stringValue($item['seo_description'] ?? '')),
        'description' => trim(stringValue($item['description'] ?? '')),
        'price' => merchNormalizeAmount(stringValue($item['price'] ?? '0')),
        'category' => trim(stringValue($item['category'] ?? '')),
        'tags' => trim(stringValue($item['tags'] ?? '')),
        'variants' => $variants,
        'image_path' => $imagePath,
        'shipping_enabled' => !empty($item['shipping_enabled']),
        'shipping_cost' => merchNormalizeAmount(stringValue($item['shipping_cost'] ?? '0')),
        'shipping_notes' => trim(stringValue($item['shipping_notes'] ?? '')),
        'pickup_enabled' => !empty($item['pickup_enabled']),
        'pickup_notes' => trim(stringValue($item['pickup_notes'] ?? '')),
        'sort_order' => intValue($item['sort_order'] ?? 0),
        'is_active' => !empty($item['is_active']),
    ];
}

/**
 * @return list<array{
 *   id: string,
 *   slug: string,
 *   name: string,
 *   seo_title: string,
 *   seo_description: string,
 *   description: string,
 *   price: string,
 *   category: string,
 *   tags: string,
 *   variants: list<string>,
 *   image_path: string,
 *   shipping_enabled: bool,
 *   shipping_cost: string,
 *   shipping_notes: string,
 *   pickup_enabled: bool,
 *   pickup_notes: string,
 *   sort_order: int,
 *   is_active: bool
 * }>
 */
function getMerchItems(bool $activeOnly = true): array {
    $raw = trim(getSetting('merch_catalog', '[]'));
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    $items = [];
    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }
        /** @var array<string, mixed> $item */
        $normalized = normalizeMerchItem($item);
        if ($activeOnly && !$normalized['is_active']) {
            continue;
        }
        $items[] = $normalized;
    }

    usort(
        $items,
        static function (array $left, array $right): int {
            if ($left['sort_order'] !== $right['sort_order']) {
                return $left['sort_order'] <=> $right['sort_order'];
            }

            return strcasecmp($left['name'], $right['name']);
        }
    );

    return $items;
}

/**
 * @param list<array{
 *   id: string,
 *   slug: string,
 *   name: string,
 *   seo_title: string,
 *   seo_description: string,
 *   description: string,
 *   price: string,
 *   category: string,
 *   tags: string,
 *   variants: list<string>,
 *   image_path: string,
 *   shipping_enabled: bool,
 *   shipping_cost: string,
 *   shipping_notes: string,
 *   pickup_enabled: bool,
 *   pickup_notes: string,
 *   sort_order: int,
 *   is_active: bool
 * }> $items
 */
function saveMerchItems(array $items): void {
    $normalized = [];
    foreach ($items as $item) {
        $normalized[] = normalizeMerchItem($item);
    }

    $json = json_encode($normalized, JSON_UNESCAPED_SLASHES);
    setSetting('merch_catalog', is_string($json) ? $json : '[]');
}

/**
 * @param array{
 *   id: string,
 *   slug: string,
 *   name: string,
 *   seo_title: string,
 *   seo_description: string,
 *   description: string,
 *   price: string,
 *   category: string,
 *   tags: string,
 *   variants: list<string>,
 *   image_path: string,
 *   shipping_enabled: bool,
 *   shipping_cost: string,
 *   shipping_notes: string,
 *   pickup_enabled: bool,
 *   pickup_notes: string,
 *   sort_order: int,
 *   is_active: bool
 * } $item
 */
function merchItemUrl(array $item): string {
    return '/merch-item.php?item=' . urlencode($item['id']);
}

/**
 * @param list<array{
 *   id: string,
 *   slug: string,
 *   name: string,
 *   seo_title: string,
 *   seo_description: string,
 *   description: string,
 *   price: string,
 *   category: string,
 *   tags: string,
 *   variants: list<string>,
 *   image_path: string,
 *   shipping_enabled: bool,
 *   shipping_cost: string,
 *   shipping_notes: string,
 *   pickup_enabled: bool,
 *   pickup_notes: string,
 *   sort_order: int,
 *   is_active: bool
 * }> $items
 * @return array{
 *   id: string,
 *   slug: string,
 *   name: string,
 *   seo_title: string,
 *   seo_description: string,
 *   description: string,
 *   price: string,
 *   category: string,
 *   tags: string,
 *   variants: list<string>,
 *   image_path: string,
 *   shipping_enabled: bool,
 *   shipping_cost: string,
 *   shipping_notes: string,
 *   pickup_enabled: bool,
 *   pickup_notes: string,
 *   sort_order: int,
 *   is_active: bool
 * }|null
 */
function findMerchItemById(array $items, string $id): ?array {
    foreach ($items as $item) {
        if ($item['id'] === $id) {
            return $item;
        }
    }

    return null;
}

/**
 * @return array{
 *   id: string,
 *   slug: string,
 *   name: string,
 *   seo_title: string,
 *   seo_description: string,
 *   description: string,
 *   price: string,
 *   category: string,
 *   tags: string,
 *   variants: list<string>,
 *   image_path: string,
 *   shipping_enabled: bool,
 *   shipping_cost: string,
 *   shipping_notes: string,
 *   pickup_enabled: bool,
 *   pickup_notes: string,
 *   sort_order: int,
 *   is_active: bool
 * }|null
 */
function getMerchItemById(string $id, bool $activeOnly = true): ?array {
    $trimmedId = trim($id);
    if ($trimmedId === '') {
        return null;
    }

    return findMerchItemById(getMerchItems($activeOnly), $trimmedId);
}

function merchFormatFulfillmentLabel(string $fulfillmentMode): string {
    return merchNormalizeFulfillmentMode($fulfillmentMode) === 'pickup' ? 'Local Pickup' : 'Shipping';
}

function merchNormalizeFulfillmentMode(string $fulfillmentMode): string {
    return strtolower(trim($fulfillmentMode)) === 'pickup' ? 'pickup' : 'shipping';
}

/**
 * @return list<array{item_id: string, variant: string, fulfillment: string, quantity: int}>
 */
function getMerchCart(): array {
    initSession();
    $rawCart = $_SESSION['merch_cart'] ?? [];
    if (!is_array($rawCart)) {
        return [];
    }

    $cart = [];
    $maxQuantity = merchCheckoutMaxQuantity();
    foreach ($rawCart as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $itemId = trim(stringValue($entry['item_id'] ?? ''));
        if ($itemId === '') {
            continue;
        }

        $cart[] = [
            'item_id' => $itemId,
            'variant' => trim(stringValue($entry['variant'] ?? '')),
            'fulfillment' => merchNormalizeFulfillmentMode(stringValue($entry['fulfillment'] ?? 'shipping')),
            'quantity' => max(1, min($maxQuantity, intValue($entry['quantity'] ?? 1, 1))),
        ];
    }

    return $cart;
}

/**
 * @param list<array{item_id: string, variant: string, fulfillment: string, quantity: int}> $cart
 */
function saveMerchCart(array $cart): void {
    initSession();
    $_SESSION['merch_cart'] = $cart;
}

function clearMerchCart(): void {
    saveMerchCart([]);
}

function addMerchCartItem(string $itemId, string $variant, string $fulfillmentMode, int $quantity): void {
    $normalizedItemId = trim($itemId);
    if ($normalizedItemId === '') {
        return;
    }

    $normalizedVariant = trim($variant);
    $normalizedFulfillment = merchNormalizeFulfillmentMode($fulfillmentMode);
    $maxQuantity = merchCheckoutMaxQuantity();
    $normalizedQuantity = max(1, min($maxQuantity, $quantity));
    $cart = getMerchCart();

    foreach ($cart as $index => $entry) {
        if (
            $entry['item_id'] === $normalizedItemId
            && $entry['variant'] === $normalizedVariant
            && $entry['fulfillment'] === $normalizedFulfillment
        ) {
            $cart[$index]['quantity'] = min($maxQuantity, $entry['quantity'] + $normalizedQuantity);
            saveMerchCart($cart);
            return;
        }
    }

    $cart[] = [
        'item_id' => $normalizedItemId,
        'variant' => $normalizedVariant,
        'fulfillment' => $normalizedFulfillment,
        'quantity' => $normalizedQuantity,
    ];
    saveMerchCart($cart);
}

function removeMerchCartItem(int $index): void {
    $cart = getMerchCart();
    if (!isset($cart[$index])) {
        return;
    }

    unset($cart[$index]);
    saveMerchCart(array_values($cart));
}

/**
 * @param array<string, mixed> $quantities
 */
function updateMerchCartQuantities(array $quantities): void {
    $cart = getMerchCart();
    $maxQuantity = merchCheckoutMaxQuantity();
    foreach ($cart as $index => $entry) {
        $rawQuantity = $quantities[(string) $index] ?? null;
        $cart[$index]['quantity'] = max(
            1,
            min(
                $maxQuantity,
                intValue($rawQuantity, $entry['quantity'])
            )
        );
    }

    saveMerchCart($cart);
}

function getMerchCartItemCount(): int {
    $count = 0;
    foreach (getMerchCart() as $entry) {
        $count += $entry['quantity'];
    }

    return $count;
}

/**
 * @param array{
 *   id: string,
 *   slug: string,
 *   name: string,
 *   seo_title: string,
 *   seo_description: string,
 *   description: string,
 *   price: string,
 *   category: string,
 *   tags: string,
 *   variants: list<string>,
 *   image_path: string,
 *   shipping_enabled: bool,
 *   shipping_cost: string,
 *   shipping_notes: string,
 *   pickup_enabled: bool,
 *   pickup_notes: string,
 *   sort_order: int,
 *   is_active: bool
 * } $item
 */
function renderMerchAddToCartForm(array $item, string $fulfillmentMode, string $currency = 'USD'): void {
    $normalizedFulfillment = merchNormalizeFulfillmentMode($fulfillmentMode);
    $isShipping = $normalizedFulfillment === 'shipping';
    $shippingCost = $isShipping ? merchNormalizeAmount($item['shipping_cost']) : '0.00';
    $fieldSuffix = merchSlugify($item['id'] . '-' . $normalizedFulfillment . '-cart');
    $variantFieldId = 'variant-' . $fieldSuffix;
    ?>
    <form method="post" action="/merch-cart.php" class="merch-checkout-form">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="item_id" value="<?= e($item['id']) ?>">
      <input type="hidden" name="fulfillment" value="<?= e($normalizedFulfillment) ?>">
      <input type="hidden" name="quantity" value="1">
      <?php if ($item['variants']): ?>
        <label class="form-label" for="<?= e($variantFieldId) ?>">Variant</label>
        <select id="<?= e($variantFieldId) ?>" name="variant" class="form-control" required>
          <?php foreach ($item['variants'] as $variant): ?>
            <option value="<?= e($variant) ?>"><?= e($variant) ?></option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
      <button type="submit" class="btn btn-primary w-full">
        Add to Cart<?= $isShipping ? ' + Shipping' : ' for Pickup' ?>
      </button>
      <?php if ($isShipping && (float) $shippingCost > 0): ?>
        <p class="merch-checkout-note">Flat shipping fee: <?= e(merchFormatAmount($shippingCost, $currency)) ?></p>
      <?php endif; ?>
      <p class="merch-checkout-note">Checkout is completed from your cart with PayPal Standard.</p>
    </form>
    <?php
}
