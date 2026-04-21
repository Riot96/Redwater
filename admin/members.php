<?php
/**
 * RedWater Entertainment - Admin: Members Management
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$db = getDb();
$currentUser = currentUser();
assert($currentUser !== null);

// ── Handle POST actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $act = $_POST['action'] ?? '';

    // Add new member
    if ($act === 'add_member') {
        $email          = trim($_POST['email'] ?? '');
        $displayName    = trim($_POST['display_name'] ?? '');
        $password       = $_POST['password'] ?? '';
        $bypassApproval = isset($_POST['bypass_approval']);
        $role           = ($_POST['role'] ?? 'member') === 'admin' ? 'admin' : 'member';

        $result = registerUser($email, $password, $displayName, $role, $bypassApproval);
        if ($result['success']) {
            flashMessage('success', 'Account created for ' . $email);
        } else {
            flashMessage('error', $result['error']);
        }
        redirect('/admin/members.php');
    }

    // Toggle active status
    if ($act === 'toggle_active') {
        $userId    = (int)($_POST['user_id'] ?? 0);
        $newStatus = (int)($_POST['new_status'] ?? 0);
        // Don't deactivate self
        if ($userId === $currentUser['id']) {
            flashMessage('error', 'You cannot deactivate your own account.');
        } else {
            $db->prepare('UPDATE users SET is_active=? WHERE id=?')->execute([$newStatus, $userId]);
            flashMessage('success', 'Account status updated.');
        }
        redirect('/admin/members.php');
    }

    // Toggle bypass approval
    if ($act === 'toggle_bypass') {
        $userId      = (int)($_POST['user_id'] ?? 0);
        $newBypass   = (int)($_POST['new_bypass'] ?? 0);
        $db->prepare('UPDATE users SET bypass_approval=? WHERE id=?')->execute([$newBypass, $userId]);
        flashMessage('success', 'Approval setting updated.');
        redirect('/admin/members.php');
    }

    // Edit member
    if ($act === 'edit_member') {
        $userId      = (int)($_POST['user_id'] ?? 0);
        $displayName = trim($_POST['display_name'] ?? '');
        $role        = ($_POST['role'] ?? 'member') === 'admin' ? 'admin' : 'member';
        $bypass      = isset($_POST['bypass_approval']) ? 1 : 0;

        if (!$displayName) {
            flashMessage('error', 'Display name is required.');
        } else {
            $db->prepare('UPDATE users SET display_name=?, role=?, bypass_approval=? WHERE id=?')
               ->execute([$displayName, $role, $bypass, $userId]);
            flashMessage('success', 'Member updated.');
        }
        redirect('/admin/members.php');
    }

    // Reset password for member
    if ($act === 'reset_member_password') {
        $userId      = (int)($_POST['user_id'] ?? 0);
        $newPassword = $_POST['new_password'] ?? '';
        if (strlen($newPassword) < 8) {
            flashMessage('error', 'Password must be at least 8 characters.');
        } else {
            $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            $db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$hash, $userId]);
            flashMessage('success', 'Password reset successfully.');
        }
        redirect('/admin/members.php');
    }

    // Delete member
    if ($act === 'delete_member') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId === $currentUser['id']) {
            flashMessage('error', 'You cannot delete your own account.');
        } else {
            // Nullify gallery items (preserve content, remove user ref is handled by FK ON DELETE SET NULL)
            $db->prepare('DELETE FROM users WHERE id=?')->execute([$userId]);
            flashMessage('success', 'Member deleted. Their gallery content has been preserved.');
        }
        redirect('/admin/members.php');
    }
}

// ── Load members ──────────────────────────────────────────────────────────────
$membersStmt = $db->query(
    "SELECT u.*,
            (SELECT COUNT(*) FROM gallery_items g WHERE g.user_id = u.id AND g.is_approved=1) AS approved_items,
            (SELECT COUNT(*) FROM gallery_items g WHERE g.user_id = u.id AND g.is_approved=0) AS pending_items
     FROM users u
      ORDER BY u.role ASC, u.display_name ASC"
);
assert($membersStmt instanceof PDOStatement);
$members = $membersStmt->fetchAll();

$editUserId = (int)($_GET['edit'] ?? 0);
$editUser   = null;
if ($editUserId) {
    $stmt = $db->prepare('SELECT * FROM users WHERE id=?');
    $stmt->execute([$editUserId]);
    $editUser = $stmt->fetch();
}

$pageTitle = 'Manage Members';
include __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <main class="admin-main">
    <div class="d-flex justify-between align-center mb-3">
      <h1 class="admin-page-title" style="margin:0;border:none;padding:0;">Manage <span>Members</span></h1>
      <button class="btn btn-primary btn-sm" data-modal-open="addMemberModal">+ Add Member</button>
    </div>

    <?php if ($editUser): ?>
    <!-- Edit Member Form -->
    <div class="card mb-3">
      <div class="card-body">
        <h3 style="font-size:1rem;margin-bottom:1rem;">Edit: <?= e($editUser['email']) ?></h3>
        <form method="POST" action="/admin/members.php">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="edit_member">
          <input type="hidden" name="user_id" value="<?= $editUser['id'] ?>">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Display Name</label>
              <input type="text" name="display_name" class="form-control" value="<?= e($editUser['display_name']) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Role</label>
              <select name="role" class="form-control">
                <option value="member" <?= $editUser['role'] === 'member' ? 'selected' : '' ?>>Member</option>
                <option value="admin"  <?= $editUser['role'] === 'admin'  ? 'selected' : '' ?>>Admin</option>
              </select>
            </div>
          </div>
          <?php if ($editUser['role'] === 'member'): ?>
          <div class="form-group">
            <label class="form-check">
              <input type="checkbox" name="bypass_approval" value="1" <?= $editUser['bypass_approval'] ? 'checked' : '' ?>>
              Bypass content approval (uploads auto-approved)
            </label>
          </div>
          <?php endif; ?>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
            <a href="/admin/members.php" class="btn btn-outline btn-sm">Cancel</a>
          </div>
        </form>

        <div class="divider"></div>
        <h4 style="font-size:0.9rem;margin-bottom:1rem;">Reset Password</h4>
        <form method="POST" action="/admin/members.php">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="reset_member_password">
          <input type="hidden" name="user_id" value="<?= $editUser['id'] ?>">
          <div class="form-row">
            <div class="form-group">
              <input type="password" name="new_password" class="form-control" placeholder="New password (min 8 chars)" minlength="8">
            </div>
            <div>
              <button type="submit" class="btn btn-outline btn-sm">Reset Password</button>
            </div>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- Members Table -->
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Auto-Approve</th>
            <th>Gallery</th>
            <th>Joined</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($members as $m): ?>
          <tr>
            <td><?= e($m['display_name']) ?></td>
            <td><?= e($m['email']) ?></td>
            <td><span class="status-badge <?= $m['role'] === 'admin' ? 'status-blue' : '' ?>" style="<?= $m['role']==='admin' ? 'background:rgba(67,214,251,0.15);color:var(--blue);' : '' ?>"><?= ucfirst($m['role']) ?></span></td>
            <td><span class="status-badge <?= $m['is_active'] ? 'status-active' : 'status-inactive' ?>"><?= $m['is_active'] ? 'Active' : 'Inactive' ?></span></td>
            <td>
              <?php if ($m['role'] === 'member'): ?>
                <form method="POST" style="display:inline;">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="toggle_bypass">
                  <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                  <input type="hidden" name="new_bypass" value="<?= $m['bypass_approval'] ? 0 : 1 ?>">
                  <button type="submit" class="btn btn-outline btn-sm"><?= $m['bypass_approval'] ? '✓ Auto' : 'Manual' ?></button>
                </form>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td>
              <span title="Approved"><?= (int)$m['approved_items'] ?> ✓</span>
              <?php if ($m['pending_items'] > 0): ?>
                / <span style="color:var(--red);" title="Pending"><?= (int)$m['pending_items'] ?> ⏳</span>
              <?php endif; ?>
            </td>
            <td><?= date('M j, Y', strtotime($m['created_at'])) ?></td>
            <td>
              <div class="td-actions">
                <a href="/admin/members.php?edit=<?= $m['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                <?php if ($m['id'] !== $currentUser['id']): ?>
                  <form method="POST" style="display:inline;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                    <input type="hidden" name="new_status" value="<?= $m['is_active'] ? 0 : 1 ?>">
                    <button type="submit" class="btn btn-outline btn-sm"><?= $m['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                  </form>
                  <form method="POST" style="display:inline;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete_member">
                    <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete this account? Gallery content will be preserved but disassociated.">Delete</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$members): ?>
          <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:2rem;">No users yet.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>

<!-- Add Member Modal -->
<div id="addMemberModal" class="modal-backdrop">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Add New Member / Admin</h3>
      <span class="modal-close" data-modal-close>&times;</span>
    </div>
    <div class="modal-body">
      <form method="POST" action="/admin/members.php" id="addMemberForm">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add_member">
        <div class="form-group">
          <label class="form-label">Email (Login Username)</label>
          <input type="email" name="email" class="form-control" required autocomplete="off">
        </div>
        <div class="form-group">
          <label class="form-label">Display Name (shown publicly)</label>
          <input type="text" name="display_name" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Temporary Password</label>
          <input type="password" name="password" class="form-control" required minlength="8" autocomplete="new-password">
          <div class="form-hint">Minimum 8 characters. Ask the user to change it after first login.</div>
        </div>
        <div class="form-group">
          <label class="form-label">Role</label>
          <select name="role" class="form-control" id="newMemberRole" onchange="toggleBypassField(this.value)">
            <option value="member">Member</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div class="form-group" id="bypassField">
          <label class="form-check">
            <input type="checkbox" name="bypass_approval" value="1">
            Bypass content approval (uploads are auto-approved)
          </label>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
      <button type="submit" form="addMemberForm" class="btn btn-primary btn-sm">Create Account</button>
    </div>
  </div>
</div>

<script>
function toggleBypassField(role) {
  document.getElementById('bypassField').style.display = role === 'admin' ? 'none' : '';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
