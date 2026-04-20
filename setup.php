<?php
/**
 * RedWater Entertainment - First-Time Admin Setup Script
 *
 * Run this script ONCE to create the initial admin account.
 * DELETE THIS FILE after use for security.
 *
 * Usage: Access this file in your browser after setting up the database.
 * Or run from CLI: php setup.php
 */

// Only allow setup if no admins exist yet (security guard)
define('SETUP_MODE', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$db = getDb();

// Check if admin already exists
$adminCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic CSRF (simple token in session for setup page)
    session_start();
    $email       = trim($_POST['email'] ?? '');
    $displayName = trim($_POST['display_name'] ?? '');
    $password    = $_POST['password'] ?? '';
    $confirm     = $_POST['confirm'] ?? '';
    $setupKey    = trim($_POST['setup_key'] ?? '');

    // Require a setup key (set this in the form or .env)
    $expectedKey = defined('APP_KEY') ? APP_KEY : '';

    if ($adminCount > 0) {
        $error = 'An admin account already exists. Please log in normally.';
    } elseif (empty($setupKey) || $setupKey !== $expectedKey) {
        $error = 'Invalid setup key.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (empty($displayName)) {
        $error = 'Display name is required.';
    } else {
        $result = registerUser($email, $password, $displayName, 'admin', false);
        if ($result['success']) {
            $success = true;
        } else {
            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Setup — RedWater Entertainment</title>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Raleway:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <h2 class="auth-title">Initial Setup</h2>
    <p class="auth-subtitle">Create the first admin account</p>

    <?php if ($success): ?>
      <div class="alert alert-success" style="margin-bottom:1.5rem;">
        ✅ Admin account created successfully!
        <strong>Please delete <code>setup.php</code> from your server immediately.</strong>
      </div>
      <a href="/login.php" class="btn btn-primary w-full">Go to Login</a>

    <?php elseif ($adminCount > 0): ?>
      <div class="alert alert-error" style="margin-bottom:1.5rem;">
        An admin account already exists. This setup page is no longer needed.
        <strong>Please delete <code>setup.php</code>.</strong>
      </div>
      <a href="/login.php" class="btn btn-secondary w-full">Go to Login</a>

    <?php else: ?>
      <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom:1.5rem;"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="POST">
        <div class="form-group">
          <label class="form-label">Setup Key</label>
          <input type="password" name="setup_key" class="form-control" required
                 placeholder="The APP_KEY from includes/config.php">
          <div class="form-hint">Found in your <code>includes/config.php</code> as APP_KEY.</div>
        </div>
        <div class="form-group">
          <label class="form-label">Admin Email</label>
          <input type="email" name="email" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Display Name</label>
          <input type="text" name="display_name" class="form-control" required placeholder="Your public name">
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required minlength="8">
        </div>
        <div class="form-group">
          <label class="form-label">Confirm Password</label>
          <input type="password" name="confirm" class="form-control" required minlength="8">
        </div>
        <button type="submit" class="btn btn-primary w-full">Create Admin Account</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
