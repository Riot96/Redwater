<?php
/**
 * RedWater Entertainment - Reset Password
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

initSession();
if (isLoggedIn()) redirect('/');

$token  = trim(getString('token'));
$user   = validatePasswordResetToken($token);
$error  = '';
$done   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $token      = trim(postString('token'));
    $password   = postString('password');
    $password2  = postString('password2');

    if ($password !== $password2) {
        $error = 'Passwords do not match.';
    } else {
        $result = resetPassword($token, $password);
        if ($result['success']) {
            $done = true;
        } else {
            $error = $result['error'];
        }
    }
    // Re-validate for display
    $user = validatePasswordResetToken($token);
}

$pageTitle = 'Reset Password';
include __DIR__ . '/includes/header.php';
?>

<main>
  <div class="auth-page">
    <div class="auth-card">
      <div class="auth-logo">
        <img src="/assets/images/logo.png" alt="RedWater Entertainment" onerror="this.style.display='none'">
      </div>
      <h2 class="auth-title">Reset Password</h2>

      <?php if ($done): ?>
        <div class="alert alert-success" style="margin-bottom:1.5rem;">
          Your password has been reset successfully. You can now log in with your new password.
        </div>
        <a href="/login.php" class="btn btn-primary w-full">Go to Login</a>

      <?php elseif (!$user && empty($_POST)): ?>
        <div class="alert alert-error" style="margin-bottom:1.5rem;">
          This reset link is invalid or has expired. Please request a new one.
        </div>
        <a href="/forgot-password.php" class="btn btn-secondary w-full">Request New Link</a>

      <?php else: ?>
        <p class="auth-subtitle">Enter your new password below</p>
        <?php if ($error): ?>
          <div class="alert alert-error" style="margin-bottom:1.5rem;"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="/reset-password.php">
          <?= csrfField() ?>
          <input type="hidden" name="token" value="<?= e($token) ?>">
          <div class="form-group">
            <label class="form-label" for="password">New Password</label>
            <input type="password" id="password" name="password" class="form-control"
                   autocomplete="new-password" required minlength="8" autofocus>
            <div class="form-hint">Minimum 8 characters.</div>
          </div>
          <div class="form-group">
            <label class="form-label" for="password2">Confirm Password</label>
            <input type="password" id="password2" name="password2" class="form-control"
                   autocomplete="new-password" required minlength="8">
          </div>
          <button type="submit" class="btn btn-primary w-full">Reset Password</button>
        </form>
      <?php endif; ?>

      <div class="auth-footer">
        <a href="/login.php">&larr; Back to Login</a>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
