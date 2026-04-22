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

    return in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true);
}

/**
 * @param array<string, mixed> $item
 */
function getGalleryItemSourceType(array $item): string {
    $sourceType = stringValue($item['source_type'] ?? '');
    if (in_array($sourceType, ['upload', 'embed', 'link'], true)) {
        return $sourceType;
    }

    if (stringValue($item['link_url'] ?? '') !== '') {
        return 'link';
    }

    if (stringValue($item['type'] ?? '') === 'video' && stringValue($item['video_type'] ?? '') === 'embed') {
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
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function redirectWithMessage(string $url, string $type, string $message): void {
    flashMessage($type, $message);
    redirect($url);
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
