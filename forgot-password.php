<?php
/**
 * RedWater Entertainment - Forgot Password
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

initSession();
if (isLoggedIn()) redirect('/');

$sent  = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email = trim(postString('email'));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $error = validateTurnstileSubmission('forgot_password');
        if ($error === '') {
            $token = generatePasswordResetToken($email);
            if ($token) {
                sendPasswordResetEmail($email, $token);
            }
            // Always show success to prevent email enumeration
            $sent = true;
        }
    }
}

$pageTitle = 'Forgot Password';
include __DIR__ . '/includes/header.php';
?>

<main>
  <div class="auth-page">
    <div class="auth-card">
      <div class="auth-logo">
        <img src="/assets/images/logo.png" alt="RedWater Entertainment" onerror="this.style.display='none'">
      </div>
      <h2 class="auth-title">Forgot Password</h2>
      <p class="auth-subtitle">Enter your email and we'll send you a reset link</p>

      <?php if ($sent): ?>
        <div class="alert alert-success" style="margin-bottom:1.5rem;">
          If an account exists for that email, a password reset link has been sent. Please check your inbox (and spam folder).
        </div>
        <div class="auth-footer">
          <a href="/login.php">&larr; Back to Login</a>
        </div>
      <?php else: ?>
        <?php if ($error): ?>
          <div class="alert alert-error" style="margin-bottom:1.5rem;"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="/forgot-password.php">
          <?= csrfField() ?>
          <div class="form-group">
            <label class="form-label" for="email">Email Address</label>
            <input type="email" id="email" name="email" class="form-control"
                   value="<?= e(postString('email')) ?>" autocomplete="email" required autofocus>
          </div>
          <?= renderTurnstileWidget('forgot_password') ?>
          <button type="submit" class="btn btn-primary w-full">Send Reset Link</button>
        </form>
        <div class="auth-footer">
          <a href="/login.php">&larr; Back to Login</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
