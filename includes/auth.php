<?php
/**
 * RedWater Entertainment - Authentication Functions
 */

if (!defined('DB_HOST') || !defined('DEFAULT_PLACEHOLDER_SITE_URL')) {
    require_once __DIR__ . '/config.php';
}

// Password reset links remain valid for one hour from issuance.
defined('PASSWORD_RESET_TOKEN_LIFETIME') || define('PASSWORD_RESET_TOKEN_LIFETIME', 60 * 60);
// Prefix marks self-validating password reset tokens that embed their issue timestamp.
defined('PASSWORD_RESET_TOKEN_PREFIX') || define('PASSWORD_RESET_TOKEN_PREFIX', 'pr');
// Ten decimal digits preserve Unix timestamps through the year 2286 within a fixed-width token.
defined('PASSWORD_RESET_TOKEN_TIMESTAMP_DIGITS') || define('PASSWORD_RESET_TOKEN_TIMESTAMP_DIGITS', 10);
// Random suffix keeps the overall token at 64 characters while preserving strong entropy.
defined('PASSWORD_RESET_TOKEN_RANDOM_BYTES') || define('PASSWORD_RESET_TOKEN_RANDOM_BYTES', 26);

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

    $token = PASSWORD_RESET_TOKEN_PREFIX
        . sprintf('%0' . PASSWORD_RESET_TOKEN_TIMESTAMP_DIGITS . 'd', time())
        . bin2hex(random_bytes(PASSWORD_RESET_TOKEN_RANDOM_BYTES));

    $stmt = $db->prepare(
        'UPDATE users SET reset_token = ?, reset_token_expires = FROM_UNIXTIME(UNIX_TIMESTAMP(UTC_TIMESTAMP()) + ?) WHERE email = ?'
    );
    $stmt->execute([$token, PASSWORD_RESET_TOKEN_LIFETIME, $email]);

    return $token;
}

/**
 * Validates a reset token with an embedded issue timestamp.
 *
 * Tokens now prefer the stored reset_token_expires value during validation.
 * This helper remains as a fallback for legacy rows that do not have a stored
 * expiry in the database.
 */
function isCurrentPasswordResetToken(string $token): bool {
    $pattern = '/^'
        . preg_quote(PASSWORD_RESET_TOKEN_PREFIX, '/')
        . '([0-9]{' . PASSWORD_RESET_TOKEN_TIMESTAMP_DIGITS . '})'
        . '([0-9a-f]{' . (PASSWORD_RESET_TOKEN_RANDOM_BYTES * 2) . '})$/';
    if (preg_match($pattern, $token, $matches) !== 1) {
        return false;
    }

    $issuedAt = (int)$matches[1];
    $now = time();
    return ($now - $issuedAt) <= PASSWORD_RESET_TOKEN_LIFETIME;
}

/**
 * @return array{id: int, email: string, display_name: string}|null
 */
function validatePasswordResetToken(string $token): ?array {
    if (empty($token)) return null;
    $db = getDb();
    $stmt = $db->prepare(
        'SELECT id, email, display_name, reset_token_expires,
                (reset_token_expires IS NOT NULL AND reset_token_expires > UTC_TIMESTAMP()) AS reset_token_expires_valid
         FROM users
         WHERE reset_token = ?'
    );
    $stmt->execute([$token]);
    /** @var array{id: int, email: string, display_name: string, reset_token_expires?: mixed, reset_token_expires_valid?: mixed}|false $user */
    $user = $stmt->fetch();
    if (!$user) {
        return null;
    }

    $validatedUser = [
        'id' => $user['id'],
        'email' => $user['email'],
        'display_name' => $user['display_name'],
    ];

    $resetTokenExpiresValue = $user['reset_token_expires'] ?? null;
    if (is_string($resetTokenExpiresValue) && $resetTokenExpiresValue !== '') {
        return ($user['reset_token_expires_valid'] ?? false) ? $validatedUser : null;
    }

    return isCurrentPasswordResetToken($token) ? $validatedUser : null;
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

function requestUsesHttps(): bool {
    $requestScheme = strtolower(serverString('REQUEST_SCHEME'));
    if ($requestScheme === 'https') {
        return true;
    }

    $https = strtolower(serverString('HTTPS'));
    return $https !== '' && $https !== 'off';
}

function requestHostWithoutPort(string $host): string {
    if (preg_match('/^\[([0-9A-Fa-f:.]+)\](?::\d+)?$/', $host, $ipv6Matches) === 1) {
        return $ipv6Matches[1];
    }

    if (preg_match('/^([^:]+)(?::\d+)?$/', $host, $hostMatches) === 1) {
        return $hostMatches[1];
    }

    return $host;
}

function isValidPasswordResetHost(string $host): bool {
    if ($host === '') {
        return false;
    }

    return filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false
        || filter_var($host, FILTER_VALIDATE_IP) !== false;
}

function isAllowedPasswordResetHost(string $requestHost, string $configuredHost): bool {
    $normalizedRequestHost = strtolower(trim($requestHost, '.'));
    $normalizedConfiguredHost = strtolower(trim($configuredHost, '.'));

    if (
        $normalizedRequestHost === ''
        || $normalizedConfiguredHost === ''
        || !isValidPasswordResetHost($normalizedRequestHost)
        || !isValidPasswordResetHost($normalizedConfiguredHost)
    ) {
        return false;
    }

    return $normalizedRequestHost === $normalizedConfiguredHost
        || str_ends_with($normalizedRequestHost, '.' . $normalizedConfiguredHost);
}

function buildPasswordResetSiteUrl(): string {
    $rawRequestHost = serverString('SERVER_NAME');
    if ($rawRequestHost === '') {
        $rawRequestHost = serverString('HTTP_HOST', 'localhost');
    }
    $rawRequestHostWithoutPort = requestHostWithoutPort($rawRequestHost);
    $requestHostIsValid = isValidPasswordResetHost($rawRequestHostWithoutPort);
    $requestHost = $requestHostIsValid ? $rawRequestHost : 'localhost';
    $validatedRequestHostWithoutPort = $requestHostIsValid ? $rawRequestHostWithoutPort : 'localhost';
    $requestSiteUrl = (requestUsesHttps() ? 'https' : 'http') . '://' . $requestHost;
    $configuredSiteUrl = defined('SITE_URL') ? rtrim(stringValue(SITE_URL), '/') : '';
    $configuredHost = null;
    if ($configuredSiteUrl !== '' && is_string(parse_url($configuredSiteUrl, PHP_URL_SCHEME))) {
        $parsedConfiguredHost = parse_url($configuredSiteUrl, PHP_URL_HOST);
        if (is_string($parsedConfiguredHost) && $parsedConfiguredHost !== '') {
            $configuredHost = $parsedConfiguredHost;
        }
    }

    if (
        $configuredSiteUrl !== ''
        && is_string($configuredHost)
        && isAllowedPasswordResetHost($validatedRequestHostWithoutPort, $configuredHost)
    ) {
        return $requestSiteUrl;
    }

    if ($configuredSiteUrl !== '' && $configuredSiteUrl !== DEFAULT_PLACEHOLDER_SITE_URL) {
        return $configuredSiteUrl;
    }

    return $requestSiteUrl;
}

// ─── Send password reset email ────────────────────────────────────────────────
function sendPasswordResetEmail(string $email, string $token): bool {
    $resetUrl = buildPasswordResetSiteUrl() . '/reset-password.php?token=' . urlencode($token);

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
