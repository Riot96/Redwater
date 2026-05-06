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

/**
 * @param array<string, mixed> $source
 * @return list<string>
 */
function requestStringList(array $source, string $key): array {
    $value = $source[$key] ?? [];
    if (!is_array($value)) {
        return [];
    }

    $normalized = [];
    foreach ($value as $item) {
        $normalized[] = stringValue($item);
    }

    return $normalized;
}

function postString(string $key, string $default = ''): string {
    /** @var array<string, mixed> $post */
    $post = $_POST;
    return requestString($post, $key, $default);
}

/**
 * @return list<string>
 */
function postStringList(string $key): array {
    /** @var array<string, mixed> $post */
    $post = $_POST;
    return requestStringList($post, $key);
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
 * @return array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int}|null
 */
function uploadedFileAtIndex(string $key, int $index): ?array {
    $file = $_FILES[$key] ?? null;
    if (!is_array($file)) {
        return null;
    }

    $name = $file['name'] ?? null;
    $type = $file['type'] ?? null;
    $tmpName = $file['tmp_name'] ?? null;
    $error = $file['error'] ?? null;
    $size = $file['size'] ?? null;

    if (!is_array($name) || !is_array($type) || !is_array($tmpName) || !is_array($error) || !is_array($size)) {
        return null;
    }

    return [
        'name' => stringValue($name[$index] ?? null),
        'type' => stringValue($type[$index] ?? null),
        'tmp_name' => stringValue($tmpName[$index] ?? null),
        'error' => intValue($error[$index] ?? null, UPLOAD_ERR_NO_FILE),
        'size' => intValue($size[$index] ?? null),
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

/**
 * @return array{
 *   enabled: bool,
 *   site_key: string,
 *   secret_key: string,
 *   configured: bool
 * }
 */
function getTurnstileSettings(): array {
    $siteKey = trim(getSetting('turnstile_site_key'));
    $secretKey = trim(getSetting('turnstile_secret_key'));

    return [
        'enabled' => getSetting('turnstile_enabled', '0') === '1',
        'site_key' => $siteKey,
        'secret_key' => $secretKey,
        'configured' => $siteKey !== '' && $secretKey !== '',
    ];
}

function renderTurnstileWidget(string $action): string {
    $settings = getTurnstileSettings();
    if (!$settings['enabled']) {
        return '';
    }

    if ($settings['site_key'] === '') {
        return '<div class="alert alert-warning">Human verification is temporarily unavailable right now. Please try again later.</div>';
    }

    static $scriptIncluded = false;

    $html = '';
    if (!$scriptIncluded) {
        $html .= '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
        $scriptIncluded = true;
    }

    $html .= '<div class="form-group">';
    $html .= '<div class="cf-turnstile" data-sitekey="' . e($settings['site_key']) . '" data-action="' . e($action) . '" data-theme="auto"></div>';
    $html .= '<div class="form-hint">Please complete the security check before submitting.</div>';
    $html .= '</div>';

    return $html;
}

/**
 * @param mixed $decoded
 * @return list<string>
 */
function turnstileErrorCodes(mixed $decoded): array {
    if (!is_array($decoded) || !isset($decoded['error-codes']) || !is_array($decoded['error-codes'])) {
        return [];
    }

    $codes = [];
    foreach ($decoded['error-codes'] as $code) {
        if (is_string($code) && $code !== '') {
            $codes[] = $code;
        }
    }

    return $codes;
}

/**
 * @param list<string> $errorCodes
 */
function turnstileVerificationMessage(array $errorCodes): string {
    if (in_array('missing-input-response', $errorCodes, true)) {
        return 'Please complete the security check before submitting the form.';
    }

    if (in_array('timeout-or-duplicate', $errorCodes, true)) {
        return 'The security check expired. Please complete it again and resubmit the form.';
    }

    if (in_array('invalid-input-response', $errorCodes, true)) {
        return 'The security check response was invalid. Please try again.';
    }

    return 'We could not verify the security check. Please try again.';
}

function validateTurnstileSubmission(string $action): string {
    $settings = getTurnstileSettings();
    if (!$settings['enabled']) {
        return '';
    }

    if (!$settings['configured']) {
        return 'Human verification is temporarily unavailable right now. Please try again later.';
    }

    $token = trim(postString('cf-turnstile-response'));
    if ($token === '') {
        return 'Please complete the security check before submitting the form.';
    }

    $payload = [
        'secret' => $settings['secret_key'],
        'response' => $token,
    ];
    $remoteIp = trim(serverString('REMOTE_ADDR'));
    if ($remoteIp !== '') {
        $payload['remoteip'] = $remoteIp;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
            'content' => http_build_query($payload),
            'timeout' => 10,
        ],
    ]);

    $response = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
    if ($response === false) {
        error_log('Cloudflare Turnstile verification request failed for action "' . $action . '".');
        return 'We could not verify the security check. Please try again.';
    }

    $decoded = json_decode($response, true);
    if (is_array($decoded) && !empty($decoded['success'])) {
        return '';
    }

    $errorCodes = turnstileErrorCodes($decoded);
    if ($errorCodes !== []) {
        error_log('Cloudflare Turnstile rejected action "' . $action . '" with codes: ' . implode(', ', $errorCodes));
    } else {
        error_log('Cloudflare Turnstile returned an unexpected response for action "' . $action . '".');
    }

    return turnstileVerificationMessage($errorCodes);
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
function normalizeLocalGalleryWatermarkImagePath(string $path): string {
    $normalized = trim($path);
    if ($normalized === '') {
        return '';
    }
    if (preg_match('/[\x00-\x1F\x7F\\\\]/', $normalized) === 1 || str_contains($normalized, '..')) {
        return '';
    }
    if (preg_match('#^/uploads/watermarks/[A-Za-z0-9_](?:[A-Za-z0-9_-]*[A-Za-z0-9_])?(?:\.[A-Za-z0-9_](?:[A-Za-z0-9_-]*[A-Za-z0-9_])?)*\.(?:jpe?g|png|gif|webp)$#i', $normalized) !== 1) {
        return '';
    }

    return $normalized;
}

function isManagedGalleryWatermarkImagePath(string $path): bool {
    return normalizeLocalGalleryWatermarkImagePath($path) !== '';
}

function deleteManagedGalleryWatermarkImage(string $path): void {
    $path = trim($path);
    if (!isManagedGalleryWatermarkImagePath($path)) {
        return;
    }

    $candidate = realpath(dirname(__DIR__) . '/' . ltrim($path, '/'));
    $uploadsRoot = realpath(dirname(__DIR__) . '/uploads/watermarks');
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
 *   enabled: bool,
 *   text: string,
 *   image_path: string
 * }
 */
function normalizeGalleryWatermarkSettings(array $settings): array {
    return [
        'enabled' => !empty($settings['enabled']),
        'text' => trim(stringValue($settings['text'] ?? '')),
        'image_path' => normalizeLocalGalleryWatermarkImagePath(stringValue($settings['image_path'] ?? '')),
    ];
}

/**
 * @return array{
 *   enabled: bool,
 *   text: string,
 *   image_path: string
 * }
 */
function getGalleryWatermarkSettings(): array {
    $raw = trim(getSetting('gallery_watermark_settings', ''));
    if ($raw === '') {
        return normalizeGalleryWatermarkSettings([]);
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return normalizeGalleryWatermarkSettings([]);
    }

    /** @var array<string, mixed> $decoded */
    return normalizeGalleryWatermarkSettings($decoded);
}

/**
 * @param array{
 *   enabled: bool,
 *   text: string,
 *   image_path: string
 * } $settings
 */
function saveGalleryWatermarkSettings(array $settings): void {
    $json = json_encode(normalizeGalleryWatermarkSettings($settings), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    setSetting('gallery_watermark_settings', is_string($json) ? $json : '{}');
}

function normalizeLocalTicketEventImagePath(string $path): string {
    $normalized = trim($path);
    if ($normalized === '') {
        return '';
    }
    if (preg_match('/[\x00-\x1F\x7F\\\\]/', $normalized) === 1 || str_contains($normalized, '..')) {
        return '';
    }
    if (preg_match('#^/uploads/tickets/[A-Za-z0-9_](?:[A-Za-z0-9_-]*[A-Za-z0-9_])?(?:\.[A-Za-z0-9_](?:[A-Za-z0-9_-]*[A-Za-z0-9_])?)*\.(?:jpe?g|png|gif|webp)$#i', $normalized) !== 1) {
        return '';
    }

    return $normalized;
}

function isSupportedTicketManualEventImagePath(string $path): bool {
    $path = trim($path);
    if ($path === '') {
        return false;
    }

    if (normalizeLocalTicketEventImagePath($path) !== '') {
        return true;
    }

    return isSupportedGalleryLinkUrl($path);
}

function isManagedTicketManualEventImagePath(string $path): bool {
    return normalizeLocalTicketEventImagePath($path) !== '';
}

function deleteManagedTicketManualEventImage(string $path): void {
    $path = trim($path);
    if (!isManagedTicketManualEventImagePath($path)) {
        return;
    }

    $candidate = realpath(dirname(__DIR__) . '/' . ltrim($path, '/'));
    $uploadsRoot = realpath(dirname(__DIR__) . '/uploads/tickets');
    if ($candidate === false || $uploadsRoot === false) {
        return;
    }

    if ($candidate !== $uploadsRoot && str_starts_with($candidate, $uploadsRoot . DIRECTORY_SEPARATOR)) {
        deleteUploadedFile($candidate);
    }
}

/**
 * @param array<string, mixed> $event
 * @return array{
 *   name: string,
 *   description: string,
 *   date: string,
 *   time: string,
 *   cost: string,
 *   photo_url: string,
 *   booking_url: string
 * }
 */
function normalizeTicketManualEvent(array $event): array {
    $photoUrl = trim(stringValue($event['photo_url'] ?? ''));
    if ($photoUrl !== '' && !isSupportedTicketManualEventImagePath($photoUrl)) {
        $photoUrl = '';
    }

    $bookingUrl = trim(stringValue($event['booking_url'] ?? ''));
    if ($bookingUrl !== '' && !isSupportedGalleryLinkUrl($bookingUrl)) {
        $bookingUrl = '';
    }

    return [
        'name' => trim(stringValue($event['name'] ?? '')),
        'description' => trim(stringValue($event['description'] ?? '')),
        'date' => trim(stringValue($event['date'] ?? '')),
        'time' => trim(stringValue($event['time'] ?? '')),
        'cost' => trim(stringValue($event['cost'] ?? '')),
        'photo_url' => $photoUrl,
        'booking_url' => $bookingUrl,
    ];
}

/**
 * @param array{
 *   name: string,
 *   description: string,
 *   date: string,
 *   time: string,
 *   cost: string,
 *   photo_url: string,
 *   booking_url: string
 * } $event
 */
function ticketManualEventIsComplete(array $event): bool {
    return $event['name'] !== ''
        && $event['description'] !== ''
        && $event['date'] !== ''
        && $event['time'] !== ''
        && $event['cost'] !== ''
        && $event['photo_url'] !== '';
}

/**
 * @return list<array{
 *   name: string,
 *   description: string,
 *   date: string,
 *   time: string,
 *   cost: string,
 *   photo_url: string,
 *   booking_url: string
 * }>
 */
function getTicketManualEvents(): array {
    $raw = trim(getSetting('tickets_manual_events', '[]'));
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    $events = [];
    foreach ($decoded as $event) {
        if (!is_array($event)) {
            continue;
        }

        /** @var array<string, mixed> $event */
        $normalized = normalizeTicketManualEvent($event);
        if (!ticketManualEventIsComplete($normalized)) {
            continue;
        }

        $events[] = $normalized;
    }

    return $events;
}

/**
 * @param list<array{
 *   name: string,
 *   description: string,
 *   date: string,
 *   time: string,
 *   cost: string,
 *   photo_url: string,
 *   booking_url: string
 * }> $events
 */
function saveTicketManualEvents(array $events): void {
    $normalized = [];
    foreach ($events as $event) {
        $normalizedEvent = normalizeTicketManualEvent($event);
        if (!ticketManualEventIsComplete($normalizedEvent)) {
            continue;
        }

        $normalized[] = $normalizedEvent;
    }

    $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    setSetting('tickets_manual_events', is_string($json) ? $json : '[]');
}

/**
 * @param array{
 *   enabled: bool,
 *   text: string,
 *   image_path: string
 * } $settings
 */
function galleryWatermarkHasContent(array $settings): bool {
    return $settings['text'] !== '' || $settings['image_path'] !== '';
}

function galleryWatermarkFontPath(): string {
    static $fontPath = null;
    if (is_string($fontPath)) {
        return $fontPath;
    }

    $candidates = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf',
    ];

    foreach ($candidates as $candidate) {
        if (is_readable($candidate)) {
            $fontPath = $candidate;
            return $fontPath;
        }
    }

    $fontPath = '';
    return $fontPath;
}

/**
 * Converts a normalized opacity value (0.0 transparent through 1.0 opaque)
 * into GD's alpha range, where 0 is opaque and 127 is fully transparent.
 *
 * @return int<0, 127>
 */
function galleryWatermarkGdAlpha(float $opacity): int {
    return max(0, min(127, (int) round((1 - $opacity) * 127)));
}

/**
 * @return int<0, 127>
 */
function galleryWatermarkShadowAlpha(int $alpha): int {
    $offset = defined('GALLERY_WATERMARK_SHADOW_ALPHA_OFFSET') ? (int) GALLERY_WATERMARK_SHADOW_ALPHA_OFFSET : 5;
    return max(0, min(127, $alpha + $offset));
}

function galleryWatermarkSourcePath(string $configuredPath): string {
    $normalizedPath = normalizeLocalGalleryWatermarkImagePath($configuredPath);
    if ($normalizedPath === '') {
        return '';
    }

    return dirname(__DIR__) . '/' . ltrim($normalizedPath, '/');
}

function isAnimatedGifFile(string $path): bool {
    $chunkSize = 1024 * 100;
    $stream = @fopen($path, 'rb');
    if (!is_resource($stream)) {
        return false;
    }

    $frames = 0;
    while (!feof($stream) && $frames < 2) {
        $chunk = fread($stream, $chunkSize);
        if ($chunk === false || $chunk === '') {
            break;
        }
        // Match a GIF Graphics Control Extension followed by an image or extension block.
        $matches = preg_match_all('#\x00\x21\xF9\x04.{4}\x00[\x2C\x21]#s', $chunk, $unusedMatches);
        if (is_int($matches)) {
            $frames += $matches;
        }
    }

    fclose($stream);
    return $frames > 1;
}

function isAnimatedWebpFile(string $path): bool {
    $stream = @fopen($path, 'rb');
    if (!is_resource($stream)) {
        return false;
    }

    $header = fread($stream, 12);
    if (!is_string($header) || strlen($header) < 12 || substr($header, 0, 4) !== 'RIFF' || substr($header, 8, 4) !== 'WEBP') {
        fclose($stream);
        return false;
    }

    while (!feof($stream)) {
        $chunkHeader = fread($stream, 8);
        if (!is_string($chunkHeader) || strlen($chunkHeader) < 8) {
            break;
        }

        $chunkType = substr($chunkHeader, 0, 4);
        $chunkSizeData = unpack('Vsize', substr($chunkHeader, 4, 4));
        $chunkSize = is_array($chunkSizeData) ? (int) ($chunkSizeData['size'] ?? 0) : 0;

        if ($chunkType === 'ANIM' || $chunkType === 'ANMF') {
            fclose($stream);
            return true;
        }

        if ($chunkSize < 0) {
            break;
        }

        $seekBytes = $chunkSize + ($chunkSize % 2);
        if (fseek($stream, $seekBytes, SEEK_CUR) !== 0) {
            break;
        }
    }

    fclose($stream);
    return false;
}

function isAnimatedGalleryImageFile(string $path, string $mime): bool {
    return match ($mime) {
        'image/gif' => isAnimatedGifFile($path),
        'image/webp' => isAnimatedWebpFile($path),
        default => false,
    };
}

/**
 * @param array{
 *   enabled: bool,
 *   text: string,
 *   image_path: string
 * }|null $settings
 * @return array{success: bool, applied: bool, error?: string}
 */
function applyGalleryWatermark(string $imagePath, ?array $settings = null): array {
    $settings = $settings !== null ? normalizeGalleryWatermarkSettings($settings) : getGalleryWatermarkSettings();
    if (!$settings['enabled'] || !galleryWatermarkHasContent($settings)) {
        return ['success' => true, 'applied' => false];
    }

    if (!is_file($imagePath)) {
        return ['success' => false, 'applied' => false, 'error' => 'The uploaded image could not be found for watermarking.'];
    }

    $watermarkSourcePath = galleryWatermarkSourcePath($settings['image_path']);
    if ($settings['image_path'] !== '' && !is_file($watermarkSourcePath) && $settings['text'] === '') {
        return ['success' => false, 'applied' => false, 'error' => 'The configured watermark image is missing. Please update your watermark settings and try again.'];
    }

    if (class_exists('Imagick')) {
        try {
            return applyGalleryWatermarkWithImagick($imagePath, $settings, $watermarkSourcePath);
        } catch (Throwable $e) {
            error_log('Gallery watermark Imagick processing failed: ' . $e->getMessage());
        }
    }

    return applyGalleryWatermarkWithGd($imagePath, $settings, $watermarkSourcePath);
}

/**
 * @param array{
 *   enabled: bool,
 *   text: string,
 *   image_path: string
 * } $settings
 * @return array{success: bool, applied: bool, error?: string}
 */
function applyGalleryWatermarkWithImagick(string $imagePath, array $settings, string $watermarkSourcePath = ''): array {
    $sequence = new Imagick($imagePath);
    $sequence = $sequence->coalesceImages();
    $watermarkSource = null;
    $imageOpacity = defined('GALLERY_WATERMARK_IMAGE_OPACITY') ? (float) GALLERY_WATERMARK_IMAGE_OPACITY : 0.45;
    $textOpacity = defined('GALLERY_WATERMARK_TEXT_OPACITY') ? (float) GALLERY_WATERMARK_TEXT_OPACITY : 0.35;

    if ($watermarkSourcePath !== '') {
        if (is_file($watermarkSourcePath)) {
            $watermarkSource = new Imagick($watermarkSourcePath);
            if ($watermarkSource->getNumberImages() > 1) {
                $watermarkSource->setIteratorIndex(0);
            }
        } elseif ($settings['text'] === '') {
            $sequence->clear();
            $sequence->destroy();
            return ['success' => false, 'applied' => false, 'error' => 'The configured watermark image is missing. Please update your watermark settings and try again.'];
        }
    }

    foreach ($sequence as $frame) {
        $width = (int) $frame->getImageWidth();
        $height = (int) $frame->getImageHeight();
        $margin = max(12, (int) floor(min($width, $height) * 0.03));
        $gap = max(8, (int) floor($margin / 2));
        $baselineY = $height - $margin;
        $rightX = $width - $margin;

        if ($watermarkSource instanceof Imagick) {
            $watermark = clone $watermarkSource;
            $watermarkWidth = (int) $watermark->getImageWidth();
            $watermarkHeight = (int) $watermark->getImageHeight();
            $scaleRatio = min(
                1,
                ($width * 0.22) / max(1, $watermarkWidth),
                ($height * 0.18) / max(1, $watermarkHeight)
            );
            $targetWidth = max(1, (int) round($watermarkWidth * $scaleRatio));
            $targetHeight = max(1, (int) round($watermarkHeight * $scaleRatio));
            $watermark->resizeImage($targetWidth, $targetHeight, Imagick::FILTER_LANCZOS, 1, true);
            $watermark->evaluateImage(Imagick::EVALUATE_MULTIPLY, $imageOpacity, Imagick::CHANNEL_ALPHA);
            $frame->compositeImage($watermark, Imagick::COMPOSITE_OVER, $rightX - $targetWidth, $baselineY - $targetHeight);
            $baselineY -= $targetHeight + $gap;
            $watermark->clear();
            $watermark->destroy();
        }

        if ($settings['text'] !== '') {
            $draw = new ImagickDraw();
            $draw->setGravity(Imagick::GRAVITY_NORTHWEST);
            $draw->setFillColor(new ImagickPixel('rgba(255,255,255,' . $textOpacity . ')'));
            $fontPath = galleryWatermarkFontPath();
            if ($fontPath !== '') {
                $draw->setFont($fontPath);
            }
            $draw->setFontSize(max(14, min(42, (int) round($width * 0.035))));
            $metrics = $frame->queryFontMetrics($draw, $settings['text']);
            $textWidth = (int) ceil($metrics['textWidth']);
            $textHeight = (int) ceil($metrics['textHeight']);
            $textX = max($margin, $rightX - $textWidth);
            $textY = max($margin + $textHeight, $baselineY);
            $frame->annotateImage($draw, $textX, $textY, 0, $settings['text']);
        }

        $frame->setImagePage($width, $height, 0, 0);
    }

    $written = $sequence->writeImages($imagePath, true);
    if (!$written) {
        $sequence->clear();
        $sequence->destroy();
        if ($watermarkSource instanceof Imagick) {
            $watermarkSource->clear();
            $watermarkSource->destroy();
        }
        return ['success' => false, 'applied' => false, 'error' => 'Unable to save the watermarked image.'];
    }

    $sequence->clear();
    $sequence->destroy();
    if ($watermarkSource instanceof Imagick) {
        $watermarkSource->clear();
        $watermarkSource->destroy();
    }

    return ['success' => true, 'applied' => true];
}

/**
 * @param array{
 *   enabled: bool,
 *   text: string,
 *   image_path: string
 * } $settings
 * @return array{success: bool, applied: bool, error?: string}
 */
function applyGalleryWatermarkWithGd(string $imagePath, array $settings, string $watermarkSourcePath = ''): array {
    if (!function_exists('getimagesize')) {
        return ['success' => false, 'applied' => false, 'error' => 'Image watermarking is not available on this server.'];
    }

    $imageAlpha = galleryWatermarkGdAlpha(defined('GALLERY_WATERMARK_IMAGE_OPACITY') ? (float) GALLERY_WATERMARK_IMAGE_OPACITY : 0.45);
    $textAlpha = galleryWatermarkGdAlpha(defined('GALLERY_WATERMARK_TEXT_OPACITY') ? (float) GALLERY_WATERMARK_TEXT_OPACITY : 0.35);
    $imageInfo = @getimagesize($imagePath);
    if (!is_array($imageInfo)) {
        return ['success' => false, 'applied' => false, 'error' => 'The uploaded file is not a supported image.'];
    }

    $mime = stringValue($imageInfo['mime']);
    if (isAnimatedGalleryImageFile($imagePath, $mime)) {
        return ['success' => false, 'applied' => false, 'error' => 'Animated GIF and WebP files cannot be watermarked on this server. Please contact your administrator or upload a static image.'];
    }

    $image = match ($mime) {
        'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($imagePath) : false,
        'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($imagePath) : false,
        'image/gif' => function_exists('imagecreatefromgif') ? @imagecreatefromgif($imagePath) : false,
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($imagePath) : false,
        default => false,
    };

    if ($image === false) {
        return ['success' => false, 'applied' => false, 'error' => 'The uploaded image format is not supported for watermarking.'];
    }

    imagealphablending($image, true);
    imagesavealpha($image, true);

    $width = (int) imagesx($image);
    $height = (int) imagesy($image);
    $margin = max(12, (int) floor(min($width, $height) * 0.03));
    $gap = max(8, (int) floor($margin / 2));
    $baselineY = $height - $margin;
    $rightX = $width - $margin;

    if ($watermarkSourcePath !== '') {
        if (is_file($watermarkSourcePath)) {
            $watermarkInfo = @getimagesize($watermarkSourcePath);
            if (is_array($watermarkInfo)) {
                $watermarkMime = stringValue($watermarkInfo['mime']);
                $watermark = match ($watermarkMime) {
                    'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($watermarkSourcePath) : false,
                    'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($watermarkSourcePath) : false,
                    'image/gif' => function_exists('imagecreatefromgif') ? @imagecreatefromgif($watermarkSourcePath) : false,
                    'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($watermarkSourcePath) : false,
                    default => false,
                };
                if ($watermark !== false) {
                    imagealphablending($watermark, true);
                    imagesavealpha($watermark, true);
                    $watermarkWidth = (int) imagesx($watermark);
                    $watermarkHeight = (int) imagesy($watermark);
                    $scaleRatio = min(
                        1,
                        ($width * 0.22) / max(1, $watermarkWidth),
                        ($height * 0.18) / max(1, $watermarkHeight)
                    );
                    $targetWidth = max(1, (int) round($watermarkWidth * $scaleRatio));
                    $targetHeight = max(1, (int) round($watermarkHeight * $scaleRatio));
                    $overlay = imagecreatetruecolor($targetWidth, $targetHeight);
                    if ($overlay !== false) {
                        imagealphablending($overlay, false);
                        imagesavealpha($overlay, true);
                        $transparent = imagecolorallocatealpha($overlay, 0, 0, 0, 127);
                        if ($transparent === false) {
                            $transparent = 0;
                        }
                        imagefill($overlay, 0, 0, $transparent);
                        imagecopyresampled($overlay, $watermark, 0, 0, 0, 0, $targetWidth, $targetHeight, $watermarkWidth, $watermarkHeight);
                        imagefilter($overlay, IMG_FILTER_COLORIZE, 0, 0, 0, $imageAlpha);
                        imagealphablending($image, true);
                        imagecopy($image, $overlay, $rightX - $targetWidth, $baselineY - $targetHeight, 0, 0, $targetWidth, $targetHeight);
                        $baselineY -= $targetHeight + $gap;
                        imagedestroy($overlay);
                    }
                    imagedestroy($watermark);
                }
            } elseif ($settings['text'] === '') {
                imagedestroy($image);
                return ['success' => false, 'applied' => false, 'error' => 'The configured watermark image could not be loaded. Please update your watermark settings and try again.'];
            }
        } elseif ($settings['text'] === '') {
            imagedestroy($image);
            return ['success' => false, 'applied' => false, 'error' => 'The configured watermark image is missing. Please update your watermark settings and try again.'];
        }
    }

    if ($settings['text'] !== '') {
        $fontPath = galleryWatermarkFontPath();
        if ($fontPath !== '' && function_exists('imagettftext') && function_exists('imagettfbbox')) {
            $fontSize = max(14, min(42, (int) round($width * 0.035)));
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $settings['text']);
            if (is_array($bbox)) {
                $textWidth = (int) abs(($bbox[4] ?? 0) - ($bbox[0] ?? 0));
                $textHeight = (int) abs(($bbox[5] ?? 0) - ($bbox[1] ?? 0));
                $textX = max($margin, $rightX - $textWidth);
                $textY = max($margin + $textHeight, $baselineY);
                $shadowColor = imagecolorallocatealpha($image, 0, 0, 0, galleryWatermarkShadowAlpha($textAlpha));
                if ($shadowColor === false) {
                    $shadowColor = 0;
                }
                $textColor = imagecolorallocatealpha($image, 255, 255, 255, $textAlpha);
                if ($textColor === false) {
                    $textColor = 0;
                }
                imagettftext($image, $fontSize, 0, $textX + 2, $textY + 2, $shadowColor, $fontPath, $settings['text']);
                imagettftext($image, $fontSize, 0, $textX, $textY, $textColor, $fontPath, $settings['text']);
            }
        } else {
            $font = 5;
            $textWidth = imagefontwidth($font) * strlen($settings['text']);
            $textHeight = imagefontheight($font);
            $textX = max($margin, $rightX - $textWidth);
            $textY = max($margin, $baselineY - $textHeight);
            $shadowColor = imagecolorallocatealpha($image, 0, 0, 0, galleryWatermarkShadowAlpha($textAlpha));
            if ($shadowColor === false) {
                $shadowColor = 0;
            }
            $textColor = imagecolorallocatealpha($image, 255, 255, 255, $textAlpha);
            if ($textColor === false) {
                $textColor = 0;
            }
            imagestring($image, $font, $textX + 1, $textY + 1, $settings['text'], $shadowColor);
            imagestring($image, $font, $textX, $textY, $settings['text'], $textColor);
        }
    }

    $written = match ($mime) {
        'image/jpeg' => function_exists('imagejpeg') ? imagejpeg($image, $imagePath, 90) : false,
        'image/png' => function_exists('imagepng') ? imagepng($image, $imagePath, 6) : false,
        'image/gif' => function_exists('imagegif') ? imagegif($image, $imagePath) : false,
        'image/webp' => function_exists('imagewebp') ? imagewebp($image, $imagePath, 90) : false,
        default => false,
    };

    imagedestroy($image);

    if (!$written) {
        return ['success' => false, 'applied' => false, 'error' => 'Unable to save the watermarked image.'];
    }

    return ['success' => true, 'applied' => true];
}

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

function normalizeRaffleName(string $name): string {
    $normalized = str_replace("\xC2\xA0", ' ', $name);
    $normalized = preg_replace('/\s+/u', ' ', trim($normalized));
    return is_string($normalized) ? $normalized : trim($name);
}

function raffleNameKey(string $name): string {
    $normalized = normalizeRaffleName($name);
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($normalized, 'UTF-8');
    }

    return strtolower($normalized);
}

function isValidRaffleName(string $name): bool {
    $normalized = normalizeRaffleName($name);
    return $normalized !== ''
        && raffleNameLength($normalized) <= 100
        && preg_match("/^[\p{L}\p{N}][\p{L}\p{N}\s'’.,()&\/\-]*$/u", $normalized) === 1;
}

function raffleNameLength(string $name): int {
    if (function_exists('mb_strlen')) {
        return mb_strlen($name, 'UTF-8');
    }

    if (function_exists('iconv_strlen')) {
        $length = iconv_strlen($name, 'UTF-8');
        if ($length !== false) {
            return (int) $length;
        }
    }

    $matchCount = preg_match_all('/./u', $name, $matches);
    if ($matchCount !== false) {
        return $matchCount;
    }

    return strlen($name);
}

/**
 * @param list<string> $candidates
 * @return array{
 *   names: list<string>,
 *   duplicates: list<string>,
 *   invalid: list<string>
 * }
 */
function parseRaffleNameCandidates(array $candidates): array {
    $names = [];
    $duplicates = [];
    $invalid = [];
    /** @var array<string, bool> $seenNames */
    $seenNames = [];
    /** @var array<string, bool> $seenDuplicates */
    $seenDuplicates = [];
    /** @var array<string, bool> $seenInvalid */
    $seenInvalid = [];

    foreach ($candidates as $candidate) {
        $normalized = normalizeRaffleName($candidate);
        if ($normalized === '') {
            continue;
        }

        if (!isValidRaffleName($normalized)) {
            $invalidKey = raffleNameKey($normalized);
            if (!isset($seenInvalid[$invalidKey])) {
                $invalid[] = $normalized;
                $seenInvalid[$invalidKey] = true;
            }
            continue;
        }

        $key = raffleNameKey($normalized);
        if (isset($seenNames[$key])) {
            if (!isset($seenDuplicates[$key])) {
                $duplicates[] = $normalized;
                $seenDuplicates[$key] = true;
            }
            continue;
        }

        $seenNames[$key] = true;
        $names[] = $normalized;
    }

    return [
        'names' => $names,
        'duplicates' => $duplicates,
        'invalid' => $invalid,
    ];
}

/**
 * @return array{
 *   names: list<string>,
 *   duplicates: list<string>,
 *   invalid: list<string>
 * }
 */
function parseRaffleNames(string $input): array {
    $normalizedInput = preg_replace('/^\xEF\xBB\xBF/u', '', $input);
    $lines = preg_split('/\r\n|\r|\n/', is_string($normalizedInput) ? $normalizedInput : $input);
    if (!is_array($lines)) {
        $lines = [$input];
    }

    /** @var list<string> $lines */
    return parseRaffleNameCandidates($lines);
}

/**
 * @return array{
 *   names: list<string>,
 *   duplicates: list<string>,
 *   invalid: list<string>
 * }
 */
function parseRaffleCsvNames(string $input): array {
    $stream = fopen('php://temp', 'r+');
    if (!is_resource($stream)) {
        return parseRaffleNames($input);
    }

    fwrite($stream, preg_replace('/^\xEF\xBB\xBF/u', '', $input) ?: $input);
    rewind($stream);

    /** @var list<string> $candidates */
    $candidates = [];
    $rowIndex = 0;
    $headerLabels = [
        'name',
        'full name',
        'fullname',
        'participant',
        'participant name',
        'email',
        'newsletter opt in',
        'newsletter_opt_in',
        'opt in',
        'opt_in',
        'created at',
        'created_at',
    ];
    while (($row = fgetcsv($stream)) !== false) {
        $cells = [];
        foreach ($row as $cell) {
            if (!is_string($cell)) {
                continue;
            }
            $normalizedCell = normalizeRaffleName($cell);
            if ($normalizedCell !== '') {
                $cells[] = $normalizedCell;
            }
        }

        if ($cells === []) {
            $rowIndex++;
            continue;
        }

        if ($rowIndex === 0) {
            $headerMatches = 0;
            foreach ($cells as $cell) {
                if (in_array(raffleNameKey($cell), $headerLabels, true)) {
                    $headerMatches++;
                }
            }

            if ($headerMatches >= 2 || ($headerMatches >= 1 && count($cells) === 1)) {
                $rowIndex++;
                continue;
            }
        }

        foreach ($cells as $cell) {
            if (filter_var($cell, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $candidates[] = $cell;
        }

        $rowIndex++;
    }

    fclose($stream);

    return parseRaffleNameCandidates($candidates);
}

/**
 * @param array<string, mixed> $settings
 *
 * Email can only be required when the public form is also configured to
 * collect email addresses.
 */
function raffleRequiresEmail(array $settings): bool {
    return ($settings['collect_email'] ?? false) === true
        && ($settings['require_email'] ?? false) === true;
}

/**
 * @return array{
 *   entry_form_enabled: bool,
 *   title: string,
 *   description: string,
 *   collect_email: bool,
 *   require_email: bool,
 *   opt_in_label: string,
 *   expires_at: string
 * }
 */
function getRaffleSettings(): array {
    $raw = getSetting('raffle_settings', '');
    $decoded = json_decode($raw, true);
    /** @var array<string, mixed> $settings */
    $settings = is_array($decoded) ? $decoded : [];

    $title = trim(stringValue($settings['title'] ?? ''));
    $optInLabel = trim(stringValue($settings['opt_in_label'] ?? ''));
    $collectEmail = !empty($settings['collect_email']);
    $requireEmail = raffleRequiresEmail($settings);

    return [
        'entry_form_enabled' => !empty($settings['entry_form_enabled']),
        'title' => $title !== '' ? $title : 'RedWater Giveaway Entry',
        'description' => trim(stringValue($settings['description'] ?? '')),
        'collect_email' => $collectEmail,
        'require_email' => $requireEmail,
        'opt_in_label' => $optInLabel !== '' ? $optInLabel : 'I want to receive email updates about future promotions.',
        'expires_at' => trim(stringValue($settings['expires_at'] ?? '')),
    ];
}

/**
 * @param array{
 *   entry_form_enabled: bool,
 *   title: string,
 *   description: string,
 *   collect_email: bool,
 *   require_email: bool,
 *   opt_in_label: string,
 *   expires_at: string
 * } $settings
 */
function saveRaffleSettings(array $settings): void {
    $collectEmail = !empty($settings['collect_email']);
    $requireEmail = $collectEmail && $settings['require_email'] === true;
    setSetting('raffle_settings', (string) json_encode([
        'entry_form_enabled' => !empty($settings['entry_form_enabled']),
        'title' => trim($settings['title']),
        'description' => trim($settings['description']),
        'collect_email' => $collectEmail,
        'require_email' => $requireEmail,
        'opt_in_label' => trim($settings['opt_in_label']),
        'expires_at' => trim($settings['expires_at']),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function raffleEntryMaxCount(): int {
    return max(1, defined('RAFFLE_ENTRY_MAX_COUNT') ? (int) RAFFLE_ENTRY_MAX_COUNT : 5000);
}

function raffleEntryMaxBytes(): int {
    return max(1024, defined('RAFFLE_ENTRY_MAX_BYTES') ? (int) RAFFLE_ENTRY_MAX_BYTES : 1024 * 1024);
}

/**
 * @return list<array{name: string, email: string, newsletter_opt_in: bool, created_at: string}>
 */
function parseStoredRaffleEntries(mixed $decoded): array {
    if (!is_array($decoded)) {
        return [];
    }

    $entries = [];
    foreach ($decoded as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $name = normalizeRaffleName(stringValue($entry['name'] ?? ''));
        $email = trim(stringValue($entry['email'] ?? ''));
        if (!isValidRaffleName($name)) {
            continue;
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }

        $entries[] = [
            'name' => $name,
            'email' => $email,
            'newsletter_opt_in' => !empty($entry['newsletter_opt_in']),
            'created_at' => trim(stringValue($entry['created_at'] ?? '')),
        ];
    }

    return $entries;
}

/**
 * @param list<array{name: string, email: string, newsletter_opt_in: bool, created_at: string}> $entries
 */
function encodeRaffleEntries(array $entries): string {
    $json = json_encode($entries, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return is_string($json) ? $json : '[]';
}

/**
 * @param list<array{name: string, email: string, newsletter_opt_in: bool, created_at: string}> $entries
 */
function canStoreRaffleEntries(array $entries): bool {
    return count($entries) <= raffleEntryMaxCount()
        && strlen(encodeRaffleEntries($entries)) <= raffleEntryMaxBytes();
}

/**
 * @param list<array{name: string, email: string, newsletter_opt_in: bool, created_at: string}> $entries
 * @param array{name: string, email: string, newsletter_opt_in: bool, created_at: string} $entry
 */
function findRaffleEntryConflict(array $entries, array $entry): string {
    $entryNameKey = raffleNameKey($entry['name']);
    $entryEmailKey = $entry['email'] !== '' ? strtolower($entry['email']) : '';

    foreach ($entries as $storedEntry) {
        $storedEmailKey = $storedEntry['email'] !== '' ? strtolower($storedEntry['email']) : '';
        if (raffleNameKey($storedEntry['name']) === $entryNameKey) {
            return 'That participant is already in the raffle list.';
        }
        if ($entryEmailKey !== '' && $storedEmailKey === $entryEmailKey) {
            return 'That email address is already in the raffle list.';
        }
    }

    return '';
}

/**
 * @param array{name: string, email: string, newsletter_opt_in: bool, created_at: string} $entry
 */
function addRaffleEntry(array $entry): string {
    $db = getDb();

    try {
        $db->beginTransaction();

        $ensureStmt = $db->prepare(
            'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_key = setting_key'
        );
        $ensureStmt->execute(['raffle_entries', '[]']);

        $selectStmt = $db->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ? FOR UPDATE');
        $selectStmt->execute(['raffle_entries']);
        /** @var array{setting_value: string|null}|false $row */
        $row = $selectStmt->fetch();
        $storedValue = $row !== false ? stringValue($row['setting_value']) : '[]';
        $entries = parseStoredRaffleEntries(json_decode($storedValue, true));

        $conflictMessage = findRaffleEntryConflict($entries, $entry);
        if ($conflictMessage !== '') {
            $db->rollBack();
            return $conflictMessage;
        }

        $entries[] = $entry;
        if (!canStoreRaffleEntries($entries)) {
            $db->rollBack();
            return 'This raffle has reached its entry storage limit. Please contact the organizer.';
        }

        $updateStmt = $db->prepare('UPDATE site_settings SET setting_value = ? WHERE setting_key = ?');
        $updateStmt->execute([encodeRaffleEntries($entries), 'raffle_entries']);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Failed to save raffle entry: ' . $e->getMessage());
        return 'We could not save your raffle entry right now. Please try again.';
    }

    return '';
}

/**
 * @return list<array{name: string, email: string, newsletter_opt_in: bool, created_at: string}>
 */
function getRaffleEntries(): array {
    $raw = getSetting('raffle_entries', '[]');
    return parseStoredRaffleEntries(json_decode($raw, true));
}

/**
 * @param list<array{name: string, email: string, newsletter_opt_in: bool, created_at: string}> $entries
 */
function saveRaffleEntries(array $entries): void {
    setSetting('raffle_entries', encodeRaffleEntries($entries));
}

/**
 * Builds the public raffle entry URL, preferring SITE_URL and only falling back
 * to the current request host when it is a valid hostname.
 */
function getRaffleShareUrl(): string {
    $baseUrl = defined('SITE_URL') ? rtrim(stringValue(SITE_URL), '/') : '';
    if ($baseUrl === '' || $baseUrl === 'https://yourdomain.com') {
        $scheme = serverString('HTTPS') !== '' && serverString('HTTPS') !== 'off' ? 'https' : 'http';
        $host = serverString('HTTP_HOST');
        $hostWithoutPort = $host;
        if (preg_match('/^\[([0-9A-Fa-f:.]+)\](?::\d+)?$/', $host, $ipv6Matches) === 1) {
            $hostWithoutPort = $ipv6Matches[1];
        } elseif (preg_match('/^([^:]+)(?::\d+)?$/', $host, $hostMatches) === 1) {
            $hostWithoutPort = $hostMatches[1];
        }

        if (
            $hostWithoutPort !== ''
            && (
                filter_var($hostWithoutPort, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false
                || filter_var($hostWithoutPort, FILTER_VALIDATE_IP) !== false
            )
        ) {
            $baseUrl = $scheme . '://' . $host;
        }
    }

    return ($baseUrl !== '' ? $baseUrl : '') . '/raffle.php#raffle-entry-form';
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
