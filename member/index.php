<?php
/**
 * RedWater Entertainment - Member Dashboard
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireMemberOrAdmin();

$user = currentUser();
if ($user['role'] === 'admin') {
    redirect('/admin/');
}

$db = getDb();

$stmt = $db->prepare('SELECT COUNT(*) FROM gallery_items WHERE user_id=? AND is_approved=1');
$stmt->execute([$user['id']]);
$approvedCount = (int)$stmt->fetchColumn();

$stmt2 = $db->prepare('SELECT COUNT(*) FROM gallery_items WHERE user_id=? AND is_approved=0');
$stmt2->execute([$user['id']]);
$pendingCount  = (int)$stmt2->fetchColumn();

$pageTitle = 'My Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="member-layout">
  <div class="member-header">
    <h1>Welcome, <?= e($user['display_name']) ?></h1>
    <div class="d-flex gap-1">
      <a href="/member/gallery.php" class="btn btn-secondary btn-sm">My Gallery</a>
      <a href="/member/profile.php" class="btn btn-outline btn-sm">Profile</a>
      <a href="/logout.php" class="btn btn-outline btn-sm">Log Out</a>
    </div>
  </div>

  <div class="member-tabs">
    <a href="/member/" class="member-tab active">Dashboard</a>
    <a href="/member/gallery.php" class="member-tab">Gallery</a>
    <a href="/member/profile.php" class="member-tab">Profile</a>
  </div>

  <div class="member-content">
    <div class="stat-grid" style="max-width:500px;">
      <div class="stat-card stat-blue">
        <div class="stat-number"><?= $approvedCount ?></div>
        <div class="stat-label">Published Items</div>
      </div>
      <div class="stat-card <?= $pendingCount > 0 ? 'stat-red' : '' ?>">
        <div class="stat-number"><?= $pendingCount ?></div>
        <div class="stat-label">Pending Approval</div>
      </div>
    </div>

    <div class="card mt-3" style="max-width:600px;">
      <div class="card-body">
        <h3 style="font-size:1rem;margin-bottom:1rem;">Quick Links</h3>
        <div class="d-flex gap-2" style="flex-wrap:wrap;">
          <a href="/member/gallery.php" class="btn btn-secondary btn-sm">Upload to Gallery</a>
          <a href="/member/profile.php" class="btn btn-outline btn-sm">Update Display Name</a>
          <a href="/gallery.php" class="btn btn-outline btn-sm">View Public Gallery</a>
        </div>
      </div>
    </div>

    <?php if (!$user['bypass_approval']): ?>
    <div class="alert alert-info mt-3" style="max-width:600px;position:static;">
      📋 Your uploads require admin approval before they appear publicly. Once approved, they'll show in the gallery.
    </div>
    <?php else: ?>
    <div class="alert alert-success mt-3" style="max-width:600px;position:static;">
      ✓ Your uploads are auto-approved and go live immediately.
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
