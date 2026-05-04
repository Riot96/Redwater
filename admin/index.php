<?php
/**
 * RedWater Entertainment - Admin Dashboard
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$pageTitle = 'Admin Dashboard';

$db = getDb();

// Stats
$totalMembersStmt = $db->query("SELECT COUNT(*) FROM users WHERE role='member'");
assert($totalMembersStmt instanceof PDOStatement);
$totalMembers    = (int)$totalMembersStmt->fetchColumn();
$activeMembersStmt = $db->query("SELECT COUNT(*) FROM users WHERE role='member' AND is_active=1");
assert($activeMembersStmt instanceof PDOStatement);
$activeMembers   = (int)$activeMembersStmt->fetchColumn();
$pendingItemsStmt = $db->query("SELECT COUNT(*) FROM gallery_items WHERE is_approved=0");
assert($pendingItemsStmt instanceof PDOStatement);
$pendingItems    = (int)$pendingItemsStmt->fetchColumn();
$totalGalleryStmt = $db->query("SELECT COUNT(*) FROM gallery_items WHERE is_approved=1");
assert($totalGalleryStmt instanceof PDOStatement);
$totalGallery    = (int)$totalGalleryStmt->fetchColumn();
$totalSponsorsStmt = $db->query("SELECT COUNT(*) FROM sponsors");
assert($totalSponsorsStmt instanceof PDOStatement);
$totalSponsors   = (int)$totalSponsorsStmt->fetchColumn();
$unreadContactsStmt = $db->query("SELECT COUNT(*) FROM contact_submissions WHERE is_read=0");
assert($unreadContactsStmt instanceof PDOStatement);
$unreadContacts  = (int)$unreadContactsStmt->fetchColumn();
$pendingVolunteersStmt = $db->query("SELECT COUNT(*) FROM volunteers WHERE status='pending'");
assert($pendingVolunteersStmt instanceof PDOStatement);
$pendingVolunteers = (int)$pendingVolunteersStmt->fetchColumn();

// Recent contact submissions
$recentContactsStmt = $db->query("SELECT * FROM contact_submissions ORDER BY created_at DESC LIMIT 5");
assert($recentContactsStmt instanceof PDOStatement);
/** @var list<array<string, mixed>> $recentContacts */
$recentContacts = $recentContactsStmt->fetchAll();
$recentVolunteersStmt = $db->query("SELECT * FROM volunteers ORDER BY created_at DESC LIMIT 5");
assert($recentVolunteersStmt instanceof PDOStatement);
/** @var list<array<string, mixed>> $recentVolunteers */
$recentVolunteers = $recentVolunteersStmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
  <?php include __DIR__ . '/sidebar.php'; ?>

  <main class="admin-main">
    <h1 class="admin-page-title">Admin <span>Dashboard</span></h1>

    <!-- Stats -->
    <div class="stat-grid">
      <div class="stat-card stat-blue">
        <div class="stat-number"><?= $activeMembers ?></div>
        <div class="stat-label">Active Members</div>
      </div>
      <div class="stat-card stat-red">
        <div class="stat-number"><?= $pendingItems ?></div>
        <div class="stat-label">Pending Approvals</div>
      </div>
      <div class="stat-card">
        <div class="stat-number"><?= $totalGallery ?></div>
        <div class="stat-label">Gallery Items</div>
      </div>
      <div class="stat-card">
        <div class="stat-number"><?= $totalSponsors ?></div>
        <div class="stat-label">Sponsors</div>
      </div>
      <div class="stat-card <?= $unreadContacts > 0 ? 'stat-red' : '' ?>">
        <div class="stat-number"><?= $unreadContacts ?></div>
        <div class="stat-label">Unread Messages</div>
      </div>
      <div class="stat-card <?= $pendingVolunteers > 0 ? 'stat-blue' : '' ?>">
        <div class="stat-number"><?= $pendingVolunteers ?></div>
        <div class="stat-label">Pending Volunteers</div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="card mb-3">
      <div class="card-body">
        <h3 class="mb-2" style="font-size:1rem;">Quick Actions</h3>
        <div class="d-flex gap-1" style="flex-wrap:wrap;">
          <a href="/admin/gallery.php" class="btn btn-secondary btn-sm">Approve Gallery</a>
          <a href="/admin/members.php" class="btn btn-secondary btn-sm">Manage Members</a>
          <a href="/admin/sponsors.php" class="btn btn-secondary btn-sm">Manage Sponsors</a>
          <a href="/admin/merch.php" class="btn btn-secondary btn-sm">Manage Merch</a>
          <a href="/admin/tickets.php" class="btn btn-secondary btn-sm">Update Tickets</a>
          <a href="/admin/contact.php" class="btn btn-secondary btn-sm">Manage Inquiries</a>
          <a href="/admin/volunteers.php" class="btn btn-secondary btn-sm">Manage Volunteers</a>
        </div>
      </div>
    </div>

    <div class="form-row">
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-between align-center mb-2">
            <h3 style="font-size:1rem;">Recent Messages</h3>
            <a href="/admin/contact.php#inquiries" class="btn btn-outline btn-sm">View All</a>
          </div>
          <?php if ($recentContacts): ?>
            <div class="table-wrap">
              <table>
                <thead><tr>
                  <th>Name</th><th>Email</th><th>Subject</th><th>Date</th><th>Status</th>
                </tr></thead>
                <tbody>
                <?php foreach ($recentContacts as $msg): ?>
                  <tr>
                    <td><?= e($msg['name']) ?></td>
                    <td><a href="mailto:<?= e($msg['email']) ?>"><?= e($msg['email']) ?></a></td>
                    <td><?= e($msg['subject'] ?: '—') ?></td>
                    <td><?= formatDateOrFallback($msg['created_at'] ?? null, 'M j, Y') ?></td>
                    <td><span class="status-badge <?= $msg['is_read'] ? 'status-approved' : 'status-pending' ?>"><?= $msg['is_read'] ? 'Read' : 'New' ?></span></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p class="text-muted">No contact submissions yet.</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-between align-center mb-2">
            <h3 style="font-size:1rem;">Recent Volunteers</h3>
            <a href="/admin/volunteers.php" class="btn btn-outline btn-sm">View All</a>
          </div>
          <?php if ($recentVolunteers): ?>
            <div class="table-wrap">
              <table>
                <thead><tr>
                  <th>Name</th><th>Email</th><th>Status</th><th>Date</th>
                </tr></thead>
                <tbody>
                <?php foreach ($recentVolunteers as $volunteer): ?>
                  <?php
                  $statusValue = stringValue($volunteer['status'] ?? 'pending');
                  $statusClass = $statusValue === 'active' ? 'status-approved' : ($statusValue === 'inactive' ? 'status-inactive' : 'status-pending');
                  ?>
                  <tr>
                    <td><?= e($volunteer['full_name']) ?></td>
                    <td><a href="mailto:<?= e($volunteer['email']) ?>"><?= e($volunteer['email']) ?></a></td>
                    <td><span class="status-badge <?= $statusClass ?>"><?= e(ucfirst($statusValue)) ?></span></td>
                    <td><?= formatDateOrFallback($volunteer['created_at'] ?? null, 'M j, Y') ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p class="text-muted">No volunteer submissions yet.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
