<?php
/**
 * RedWater Entertainment - Utility Functions
 */

// ─── Output Helpers ───────────────────────────────────────────────────────────
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function flashMessage(string $type, string $message): void {
    initSession();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function getFlashMessages(): array {
    initSession();
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
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
function handleFileUpload(array $file, string $destDir, array $allowedMimes, int $maxSize = 0): array {
    if ($maxSize <= 0) $maxSize = defined('MAX_UPLOAD_SIZE') ? MAX_UPLOAD_SIZE : 50 * 1024 * 1024;

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension.',
        ];
        return ['success' => false, 'error' => $errors[$file['error']] ?? 'Unknown upload error.'];
    }

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File is too large. Maximum size is ' . formatBytes($maxSize) . '.'];
    }

    // Validate MIME type using finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedMimes, true)) {
        return ['success' => false, 'error' => 'File type not allowed. Allowed types: ' . implode(', ', $allowedMimes)];
    }

    // Generate safe filename
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid('rw_', true) . '.' . $ext;
    $destPath = rtrim($destDir, '/') . '/' . $filename;

    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['success' => false, 'error' => 'Failed to save uploaded file.'];
    }

    return ['success' => true, 'path' => $destPath, 'filename' => $filename];
}

function formatBytes(int $bytes, int $precision = 2): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
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
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getGalleryItem(int $id): ?array {
    $db = getDb();
    $stmt = $db->prepare(
        'SELECT g.*, u.display_name AS uploader_name
         FROM gallery_items g
         LEFT JOIN users u ON g.user_id = u.id
         WHERE g.id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function isYoutubeUrl(string $url): bool {
    return (bool)preg_match('/(?:youtube\.com|youtu\.be)/', $url);
}

function isVimeoUrl(string $url): bool {
    return (bool)preg_match('/vimeo\.com/', $url);
}

function getVideoEmbedUrl(string $url): string {
    // YouTube
    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }
    // Vimeo
    if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
        return 'https://player.vimeo.com/video/' . $m[1];
    }
    return $url;
}

// ─── Sponsor Helpers ──────────────────────────────────────────────────────────
function getSponsorTiers(): array {
    $db = getDb();
    $tiers = $db->query(
        'SELECT * FROM sponsor_tiers ORDER BY sort_order ASC'
    )->fetchAll();

    foreach ($tiers as &$tier) {
        $stmt = $db->prepare('SELECT * FROM sponsors WHERE tier_id = ? ORDER BY sort_order ASC');
        $stmt->execute([$tier['id']]);
        $tier['sponsors'] = $stmt->fetchAll();
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
function parseTags(string $tags): array {
    return array_filter(array_map('trim', explode(',', $tags)));
}

function renderTags(array $tags): string {
    $html = '';
    foreach ($tags as $tag) {
        $html .= '<span class="tag">' . e($tag) . '</span>';
    }
    return $html;
}
