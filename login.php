<?php
/**
 * RedWater Entertainment - Login Page
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

initSession();

// Redirect if already logged in
if (isLoggedIn()) {
    $user = currentUser();
    assert($user !== null);
    redirect($user['role'] === 'admin' ? '/admin/' : '/member/');
}

$error  = '';
$next   = getString('next');
// Validate next URL to prevent open redirect
if (!empty($next) && (!str_starts_with($next, '/') || str_starts_with($next, '//'))) {
    $next = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email    = trim(postString('email'));
    $password = trim(postString('password'));
    $result   = loginUser($email, $password);
    if ($result['success']) {
        $user = currentUser();
        assert($user !== null);
        if (!empty($next)) {
            redirect($next);
        }
        redirect($user['role'] === 'admin' ? '/admin/' : '/member/');
    } else {
        $error = $result['error'];
    }
}

$pageTitle = 'Member Login';
$bodyClass = 'auth-body';
include __DIR__ . '/includes/header.php';
?>

<main>
  <div class="auth-page">
    <div class="auth-card">
      <div class="auth-logo">
        <img src="/assets/images/logo.png" alt="RedWater Entertainment" onerror="this.style.display='none'; this.nextElementSibling.style.display='block'">
        <div style="display:none;font-family:var(--font-head);font-size:1.4rem;text-align:center;">
          <span class="logo-red">Red</span><span class="logo-blue">Water</span>
        </div>
      </div>
      <h2 class="auth-title">Member Login</h2>
      <p class="auth-subtitle">Sign in to your RedWater Entertainment account</p>

      <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom:1.5rem;"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="/login.php<?= $next ? '?next=' . urlencode($next) : '' ?>">
        <?= csrfField() ?>
        <div class="form-group">
          <label class="form-label" for="email">Email Address</label>
          <input type="email" id="email" name="email" class="form-control"
                 value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email" required autofocus>
        </div>
        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <input type="password" id="password" name="password" class="form-control"
                 autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-primary w-full">Sign In</button>
      </form>

      <div class="auth-footer">
        <a href="/forgot-password.php">Forgot your password?</a>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
