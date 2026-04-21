<?php
/**
 * RedWater Entertainment - Member Profile
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireMemberOrAdmin();

$user = currentUser();
assert($user !== null);
if ($user['role'] === 'admin') {
    redirect('/admin/profile.php');
}

$db     = getDb();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $act = $_POST['action'] ?? '';

    if ($act === 'update_name') {
        $displayName = trim($_POST['display_name'] ?? '');
        if (empty($displayName)) {
            $errors['display_name'] = 'Display name is required.';
        } else {
            $db->prepare('UPDATE users SET display_name=? WHERE id=?')->execute([$displayName, $user['id']]);
            refreshSessionUser();
            flashMessage('success', 'Display name updated!');
            redirect('/member/profile.php');
        }
    }

    if ($act === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = $db->prepare('SELECT password_hash FROM users WHERE id=?');
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();

        if (!password_verify($current, $row['password_hash'])) {
            $errors['current_password'] = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $errors['new_password'] = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $errors['confirm_password'] = 'Passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
            $db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$hash, $user['id']]);
            flashMessage('success', 'Password changed successfully.');
            redirect('/member/profile.php');
        }
    }
}

$user = currentUser();
$pageTitle = 'My Profile';
include __DIR__ . '/../includes/header.php';
?>

<div class="member-layout">
  <div class="member-header">
    <h1>My Profile</h1>
    <div class="d-flex gap-1">
      <a href="/member/" class="btn btn-outline btn-sm">Dashboard</a>
      <a href="/logout.php" class="btn btn-outline btn-sm">Log Out</a>
    </div>
  </div>

  <div class="member-tabs">
    <a href="/member/" class="member-tab">Dashboard</a>
    <a href="/member/gallery.php" class="member-tab">Gallery</a>
    <a href="/member/profile.php" class="member-tab active">Profile</a>
  </div>

  <div class="member-content" style="max-width:600px;">

    <div class="card mb-3">
      <div class="card-body">
        <h3 style="font-size:1rem;margin-bottom:1rem;">Account Info</h3>
        <p class="text-muted"><strong>Login Email:</strong> <?= e($user['email']) ?></p>
        <p class="text-muted"><strong>Role:</strong> <?= ucfirst($user['role']) ?></p>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-body">
        <h3 style="font-size:1rem;margin-bottom:0.5rem;">Display Name</h3>
        <p class="text-muted" style="font-size:0.85rem;margin-bottom:1rem;">Your display name appears on gallery uploads. It is separate from your login email.</p>
        <form method="POST" action="/member/profile.php">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="update_name">
          <div class="form-group">
            <label class="form-label">Display Name</label>
            <input type="text" name="display_name" class="form-control" value="<?= e($user['display_name']) ?>" required>
            <?php if (isset($errors['display_name'])): ?><div class="form-error"><?= e($errors['display_name']) ?></div><?php endif; ?>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">Update Name</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <h3 style="font-size:1rem;margin-bottom:1rem;">Change Password</h3>
        <form method="POST" action="/member/profile.php">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="change_password">
          <div class="form-group">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
            <?php if (isset($errors['current_password'])): ?><div class="form-error"><?= e($errors['current_password']) ?></div><?php endif; ?>
          </div>
          <div class="form-group">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" required minlength="8">
            <?php if (isset($errors['new_password'])): ?><div class="form-error"><?= e($errors['new_password']) ?></div><?php endif; ?>
          </div>
          <div class="form-group">
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" required minlength="8">
            <?php if (isset($errors['confirm_password'])): ?><div class="form-error"><?= e($errors['confirm_password']) ?></div><?php endif; ?>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">Change Password</button>
        </form>
      </div>
    </div>

  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
