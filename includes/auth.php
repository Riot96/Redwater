<?php
/**
 * RedWater Entertainment - Authentication Functions
 */

if (!defined('DB_HOST')) {
    require_once __DIR__ . '/config.php';
}

// ─── Session Init ─────────────────────────────────────────────────────────────
function initSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            ini_set('session.cookie_secure', '1');
        }
        session_start();
    }
    // Regenerate session ID periodically
    if (!isset($_SESSION['_last_regen'])) {
        session_regenerate_id(true);
        $_SESSION['_last_regen'] = time();
    } elseif (time() - intValue($_SESSION['_last_regen']) > 300) {
        session_regenerate_id(true);
        $_SESSION['_last_regen'] = time();
    }
}

// ─── CSRF ─────────────────────────────────────────────────────────────────────
function csrfToken(): string {
    initSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return is_string($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

function verifyCsrf(): void {
    $token = postString('csrf_token');
    if (!hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        die('Invalid security token. Please go back and try again.');
    }
}

// ─── Auth Helpers ─────────────────────────────────────────────────────────────
/**
 * @return array{id: int, email: string, display_name: string, role: string, is_active: bool, bypass_approval: bool}|null
 */
function currentUser(): ?array {
    initSession();
    $user = $_SESSION['user'] ?? null;
    if (!is_array($user)) {
        return null;
    }

    if (!isset($user['id'], $user['email'], $user['display_name'], $user['role'], $user['is_active'], $user['bypass_approval'])) {
        return null;
    }

    return [
        'id' => intValue($user['id']),
        'email' => stringValue($user['email']),
        'display_name' => stringValue($user['display_name']),
        'role' => stringValue($user['role']),
        'is_active' => (bool)$user['is_active'],
        'bypass_approval' => (bool)$user['bypass_approval'],
    ];
}

function isLoggedIn(): bool {
    return currentUser() !== null;
}

function isAdmin(): bool {
    $user = currentUser();
    return $user && $user['role'] === 'admin';
}

function isMember(): bool {
    $user = currentUser();
    return $user && $user['role'] === 'member' && $user['is_active'];
}

function requireLogin(string $redirect = '/login.php'): void {
    if (!isLoggedIn()) {
        header('Location: ' . $redirect . '?next=' . urlencode(serverString('REQUEST_URI')));
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        http_response_code(403);
        include __DIR__ . '/../includes/header.php';
        echo '<main class="container"><div class="alert alert-error"><h2>Access Denied</h2><p>You do not have permission to view this page.</p><a href="/" class="btn btn-primary">Go Home</a></div></main>';
        include __DIR__ . '/../includes/footer.php';
        exit;
    }
}

function requireMemberOrAdmin(): void {
    requireLogin();
    $user = currentUser();
    if (!$user) {
        header('Location: /login.php');
        exit;
    }
    if ($user['role'] === 'member' && !$user['is_active']) {
        http_response_code(403);
        include __DIR__ . '/../includes/header.php';
        echo '<main class="container"><div class="alert alert-error"><h2>Account Deactivated</h2><p>Your account has been deactivated. Please contact an administrator.</p></div></main>';
        include __DIR__ . '/../includes/footer.php';
        exit;
    }
}

// ─── Login / Logout ───────────────────────────────────────────────────────────
/**
 * @return array{success: true, user: array<string, mixed>}|array{success: false, error: string}
 */
function authenticateUserCredentials(string $email, string $password): array {
    $db = getDb();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([strtolower(trim($email))]);
    /** @var array<string, mixed>|false $user */
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, stringValue($user['password_hash']))) {
        return ['success' => false, 'error' => 'Invalid email or password.'];
    }
    if (!$user['is_active'] && $user['role'] === 'member') {
        return ['success' => false, 'error' => 'Your account has been deactivated. Please contact an administrator.'];
    }

    return ['success' => true, 'user' => $user];
}

/**
 * @param array<string, mixed> $user
 */
function establishAuthenticatedSession(array $user): void {
    initSession();
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'              => intValue($user['id']),
        'email'           => stringValue($user['email']),
        'display_name'    => stringValue($user['display_name']),
        'role'            => stringValue($user['role']),
        'is_active'       => (bool)$user['is_active'],
        'bypass_approval' => (bool)$user['bypass_approval'],
    ];
}

/**
 * @return array{success: true}|array{success: false, error: string}
 */
function loginUser(string $email, string $password): array {
    $authResult = authenticateUserCredentials($email, $password);
    if (!$authResult['success']) {
        return $authResult;
    }

    establishAuthenticatedSession($authResult['user']);

    return ['success' => true];
}

function logoutUser(): void {
    initSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        $sessionName = session_name();
        if (is_string($sessionName)) {
            setcookie($sessionName, '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
    }
    session_destroy();
}

// ─── Registration ─────────────────────────────────────────────────────────────
/**
 * @return array{success: true, id: int}|array{success: false, error: string}
 */
function registerUser(string $email, string $password, string $displayName, string $role = 'member', bool $bypassApproval = false): array {
    $db = getDb();
    $email = strtolower(trim($email));

    // Validate role before inserting into the database
    $allowedRoles = ['admin', 'member'];
    if (!in_array($role, $allowedRoles, true)) {
        return ['success' => false, 'error' => 'Invalid role specified.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Invalid email address.'];
    }
    if (strlen($password) < 8) {
        return ['success' => false, 'error' => 'Password must be at least 8 characters.'];
    }
    if (empty($displayName)) {
        return ['success' => false, 'error' => 'Display name is required.'];
    }

    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'An account with this email already exists.'];
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $db->prepare(
        'INSERT INTO users (email, password_hash, display_name, role, is_active, bypass_approval) VALUES (?, ?, ?, ?, 1, ?)'
    );
    $stmt->execute([$email, $hash, $displayName, $role, $bypassApproval ? 1 : 0]);

    return ['success' => true, 'id' => (int)$db->lastInsertId()];
}

// ─── Password Reset ───────────────────────────────────────────────────────────
function generatePasswordResetToken(string $email): ?string {
    $db = getDb();
    $email = strtolower(trim($email));

    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    /** @var array{id:int}|false $user */
    $user = $stmt->fetch();
    if (!$user) {
        return null; // Don't reveal whether email exists
    }

    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour

    $stmt = $db->prepare('UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE email = ?');
    $stmt->execute([$token, $expires, $email]);

    return $token;
}

/**
 * @return array{id: int, email: string, display_name: string}|null
 */
function validatePasswordResetToken(string $token): ?array {
    if (empty($token)) return null;
    $db = getDb();
    $stmt = $db->prepare(
        'SELECT id, email, display_name FROM users WHERE reset_token = ? AND reset_token_expires > NOW()'
    );
    $stmt->execute([$token]);
    /** @var array{id: int, email: string, display_name: string}|false $user */
    $user = $stmt->fetch();
    return $user ?: null;
}

/**
 * @return array{success: true}|array{success: false, error: string}
 */
function resetPassword(string $token, string $newPassword): array {
    if (strlen($newPassword) < 8) {
        return ['success' => false, 'error' => 'Password must be at least 8 characters.'];
    }
    $user = validatePasswordResetToken($token);
    if (!$user) {
        return ['success' => false, 'error' => 'Invalid or expired reset link.'];
    }

    $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    $db = getDb();
    $stmt = $db->prepare(
        'UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?'
    );
    $stmt->execute([$hash, $user['id']]);

    return ['success' => true];
}

// ─── Send password reset email ────────────────────────────────────────────────
function sendPasswordResetEmail(string $email, string $token): bool {
    $host = serverString('HTTP_HOST', 'localhost');
    $siteUrl = defined('SITE_URL') ? SITE_URL : 'http://' . $host;
    $resetUrl = $siteUrl . '/reset-password.php?token=' . urlencode($token);

    $subject = 'Password Reset - ' . buildDefaultMailFromName();
    $message = "Hello,\n\nYou requested a password reset for your RedWater Entertainment account.\n\n";
    $message .= "Click the link below to reset your password (valid for 1 hour):\n";
    $message .= $resetUrl . "\n\n";
    $message .= "If you did not request this, you can safely ignore this email.\n\n";
    $message .= "— The RedWater Entertainment Team";

    return sendSiteMail($email, $subject, $message);
}

// ─── Refresh session user data ────────────────────────────────────────────────
function refreshSessionUser(): void {
    $user = currentUser();
    if (!$user) return;
    $db = getDb();
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    /** @var array<string, mixed>|false $row */
    $row = $stmt->fetch();
    if ($row) {
        $_SESSION['user'] = [
            'id'              => intValue($row['id']),
            'email'           => stringValue($row['email']),
            'display_name'    => stringValue($row['display_name']),
            'role'            => stringValue($row['role']),
            'is_active'       => (bool)$row['is_active'],
            'bypass_approval' => (bool)$row['bypass_approval'],
        ];
    }
}
