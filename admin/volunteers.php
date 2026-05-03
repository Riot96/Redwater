<?php
/**
 * RedWater Entertainment - Admin: Volunteer Management
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$db = getDb();
$currentUser = currentUser();
assert($currentUser !== null);

$statusFilter = getString('status', 'all');
if (!in_array($statusFilter, ['all', 'pending', 'active', 'inactive'], true)) {
    $statusFilter = 'all';
}

$sort = getString('sort', 'date_newest');
$sortOptions = [
    'date_newest' => 'created_at DESC',
    'date_oldest' => 'created_at ASC',
    'name_asc' => 'full_name ASC',
    'name_desc' => 'full_name DESC',
    'status' => 'status ASC, full_name ASC',
];
if (!isset($sortOptions[$sort])) {
    $sort = 'date_newest';
}

$search = trim(getString('q'));
$interestFilter = trim(getString('interest'));

$where = [];
$params = [];

if ($statusFilter !== 'all') {
    $where[] = 'status = ?';
    $params[] = $statusFilter;
}

if ($search !== '') {
    $where[] = '(full_name LIKE ? OR email LIKE ? OR phone_number LIKE ?)';
    $searchLike = '%' . $search . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

if ($interestFilter !== '') {
    $where[] = 'areas_of_interest LIKE ?';
    $params[] = '%' . $interestFilter . '%';
}

$whereSql = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';
$orderBySql = $sortOptions[$sort];
$filterQuery = http_build_query([
    'status' => $statusFilter,
    'sort' => $sort,
    'q' => $search,
    'interest' => $interestFilter,
]);

if (getString('export') === 'csv') {
    $stmt = $db->prepare('SELECT * FROM volunteers' . $whereSql . ' ORDER BY ' . $orderBySql);
    $stmt->execute($params);
    /** @var list<array<string, mixed>> $exportVolunteers */
    $exportVolunteers = array_values($stmt->fetchAll());

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="redwater-volunteers.csv"');
    $output = fopen('php://output', 'wb');
    if (is_resource($output)) {
        fputcsv($output, ['ID', 'Full Name', 'Email', 'Phone', 'Preferred Contact', 'Location', 'Areas of Interest', 'Availability', 'Additional Notes', 'Internal Notes', 'Status', 'Created At', 'Updated At']);
        foreach ($exportVolunteers as $volunteer) {
            fputcsv($output, [
                intValue($volunteer['id'] ?? null),
                stringValue($volunteer['full_name'] ?? ''),
                stringValue($volunteer['email'] ?? ''),
                stringValue($volunteer['phone_number'] ?? ''),
                stringValue($volunteer['preferred_contact_method'] ?? 'email'),
                stringValue($volunteer['location_address'] ?? ''),
                stringValue($volunteer['areas_of_interest'] ?? ''),
                stringValue($volunteer['availability'] ?? ''),
                stringValue($volunteer['message'] ?? ''),
                stringValue($volunteer['internal_notes'] ?? ''),
                stringValue($volunteer['status'] ?? 'pending'),
                stringValue($volunteer['created_at'] ?? ''),
                stringValue($volunteer['updated_at'] ?? ''),
            ]);
            fflush($output);
        }
        fclose($output);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $act = postString('action');
    $volunteerId = postInt('volunteer_id');

    if (in_array($act, ['add_volunteer', 'edit_volunteer'], true)) {
        $redirectTarget = $act === 'add_volunteer'
            ? '/admin/volunteers.php?mode=create#volunteer-form'
            : '/admin/volunteers.php?edit=' . $volunteerId . '#volunteer-form';
        $fullName = trim(postString('full_name'));
        $email = trim(postString('email'));
        $phoneNumber = trim(postString('phone_number'));
        $preferredContactMethod = normalizePreferredContactMethod(postString('preferred_contact_method', 'email'));
        $locationAddress = trim(postString('location_address'));
        $areasOfInterest = trim(postString('areas_of_interest'));
        $availability = trim(postString('availability'));
        $message = trim(postString('message'));
        $internalNotes = trim(postString('internal_notes'));
        $status = postString('status', 'pending');
        if (!in_array($status, ['pending', 'active', 'inactive'], true)) {
            $status = 'pending';
        }

        if ($fullName === '') {
            flashMessage('error', 'Full name is required.');
        } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flashMessage('error', 'A valid email address is required.');
        } elseif ($preferredContactMethod === 'phone' && $phoneNumber === '') {
            flashMessage('error', 'Phone number is required when phone is the preferred contact method.');
        } else {
            if ($act === 'add_volunteer') {
                $stmt = $db->prepare(
                    'INSERT INTO volunteers (
                        full_name, email, phone_number, preferred_contact_method, location_address,
                        areas_of_interest, availability, message, internal_notes, privacy_consent, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $fullName,
                    $email,
                    $phoneNumber !== '' ? $phoneNumber : null,
                    $preferredContactMethod,
                    $locationAddress !== '' ? $locationAddress : null,
                    $areasOfInterest !== '' ? $areasOfInterest : null,
                    $availability !== '' ? $availability : null,
                    $message !== '' ? $message : null,
                    $internalNotes !== '' ? $internalNotes : null,
                    1,
                    $status,
                ]);
                $newVolunteerId = (int)$db->lastInsertId();
                logVolunteerAudit($db, $newVolunteerId, $fullName, $currentUser['id'], 'created', 'Volunteer record created from admin.');
                flashMessage('success', 'Volunteer added.');
                redirect('/admin/volunteers.php?edit=' . $newVolunteerId . '#volunteer-form');
            }

            $stmt = $db->prepare('SELECT * FROM volunteers WHERE id=?');
            $stmt->execute([$volunteerId]);
            /** @var array<string, mixed>|false $existingVolunteer */
            $existingVolunteer = $stmt->fetch();
            if (!$existingVolunteer) {
                flashMessage('error', 'Volunteer not found.');
                redirect('/admin/volunteers.php');
            }

            $stmt = $db->prepare(
                'UPDATE volunteers
                 SET full_name=?, email=?, phone_number=?, preferred_contact_method=?, location_address=?,
                     areas_of_interest=?, availability=?, message=?, internal_notes=?, status=?
                 WHERE id=?'
            );
            $stmt->execute([
                $fullName,
                $email,
                $phoneNumber !== '' ? $phoneNumber : null,
                $preferredContactMethod,
                $locationAddress !== '' ? $locationAddress : null,
                $areasOfInterest !== '' ? $areasOfInterest : null,
                $availability !== '' ? $availability : null,
                $message !== '' ? $message : null,
                $internalNotes !== '' ? $internalNotes : null,
                $status,
                $volunteerId,
            ]);

            $fieldLabels = [
                'full_name' => 'Name',
                'email' => 'Email',
                'phone_number' => 'Phone',
                'preferred_contact_method' => 'Preferred contact',
                'location_address' => 'Location',
                'areas_of_interest' => 'Areas of interest',
                'availability' => 'Availability',
                'message' => 'Additional notes',
                'internal_notes' => 'Internal notes',
                'status' => 'Status',
            ];
            $updatedValues = [
                'full_name' => $fullName,
                'email' => $email,
                'phone_number' => $phoneNumber,
                'preferred_contact_method' => $preferredContactMethod,
                'location_address' => $locationAddress,
                'areas_of_interest' => $areasOfInterest,
                'availability' => $availability,
                'message' => $message,
                'internal_notes' => $internalNotes,
                'status' => $status,
            ];

            $changedFields = [];
            foreach ($fieldLabels as $field => $label) {
                if (trim(stringValue($existingVolunteer[$field] ?? '')) !== trim($updatedValues[$field])) {
                    $changedFields[] = $label;
                }
            }

            if ($changedFields !== []) {
                logVolunteerAudit($db, $volunteerId, $fullName, $currentUser['id'], 'updated', 'Updated fields: ' . implode(', ', $changedFields) . '.');
            }

            flashMessage('success', 'Volunteer updated.');
        }

        redirect($redirectTarget);
    }

    if ($act === 'delete_volunteer') {
        $stmt = $db->prepare('SELECT full_name FROM volunteers WHERE id=?');
        $stmt->execute([$volunteerId]);
        /** @var array{full_name:string}|false $volunteer */
        $volunteer = $stmt->fetch();

        if ($volunteer) {
            $db->prepare('DELETE FROM volunteers WHERE id=?')->execute([$volunteerId]);
            logVolunteerAudit($db, $volunteerId, stringValue($volunteer['full_name']), $currentUser['id'], 'deleted', 'Volunteer record deleted.');
            flashMessage('success', 'Volunteer deleted.');
        } else {
            flashMessage('error', 'Volunteer not found.');
        }

        redirect('/admin/volunteers.php');
    }
}

$volunteerCountsStmt = $db->query(
    "SELECT
        COUNT(*) AS total_count,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_count,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive_count
     FROM volunteers"
);
assert($volunteerCountsStmt instanceof PDOStatement);
/** @var array<string, mixed>|false $volunteerCounts */
$volunteerCounts = $volunteerCountsStmt->fetch();
if (!is_array($volunteerCounts)) {
    $volunteerCounts = ['total_count' => 0, 'pending_count' => 0, 'active_count' => 0, 'inactive_count' => 0];
}

$volunteersStmt = $db->prepare('SELECT * FROM volunteers' . $whereSql . ' ORDER BY ' . $orderBySql);
$volunteersStmt->execute($params);
/** @var list<array<string, mixed>> $volunteers */
$volunteers = array_values($volunteersStmt->fetchAll());

$editVolunteerId = getInt('edit');
$showVolunteerForm = getString('mode') === 'create' || $editVolunteerId > 0;
$editVolunteer = null;
if ($editVolunteerId > 0) {
    $stmt = $db->prepare('SELECT * FROM volunteers WHERE id=?');
    $stmt->execute([$editVolunteerId]);
    /** @var array<string, mixed>|false $editVolunteer */
    $editVolunteer = $stmt->fetch();
    if (!is_array($editVolunteer)) {
        $editVolunteer = null;
    }
}

$auditEntries = [];
if ($editVolunteer) {
    $auditStmt = $db->prepare(
        'SELECT l.*, u.display_name AS actor_name
         FROM volunteer_audit_log l
         LEFT JOIN users u ON u.id = l.actor_user_id
         WHERE l.volunteer_id = ?
         ORDER BY l.created_at DESC'
    );
    $auditStmt->execute([$editVolunteerId]);
    /** @var list<array<string, mixed>> $auditEntries */
    $auditEntries = array_values($auditStmt->fetchAll());
}

$pageTitle = 'Volunteer Management';
include __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <main class="admin-main">
    <div class="d-flex justify-between align-center mb-3" style="gap:1rem;flex-wrap:wrap;">
      <h1 class="admin-page-title" style="margin:0;border:none;padding:0;">Volunteer <span>Management</span></h1>
      <div class="d-flex gap-1" style="flex-wrap:wrap;">
        <a href="/admin/volunteers.php?mode=create#volunteer-form" class="btn btn-primary btn-sm">+ Add Volunteer</a>
        <a href="/admin/volunteers.php?<?= e($filterQuery !== '' ? $filterQuery . '&' : '') ?>export=csv" class="btn btn-outline btn-sm">Export CSV</a>
        <a href="/admin/contact.php" class="btn btn-outline btn-sm">Manage Inquiries</a>
      </div>
    </div>

    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-number"><?= intValue($volunteerCounts['total_count'] ?? 0) ?></div>
        <div class="stat-label">Total Volunteers</div>
      </div>
      <div class="stat-card stat-red">
        <div class="stat-number"><?= intValue($volunteerCounts['pending_count'] ?? 0) ?></div>
        <div class="stat-label">Pending</div>
      </div>
      <div class="stat-card stat-blue">
        <div class="stat-number"><?= intValue($volunteerCounts['active_count'] ?? 0) ?></div>
        <div class="stat-label">Active</div>
      </div>
      <div class="stat-card">
        <div class="stat-number"><?= intValue($volunteerCounts['inactive_count'] ?? 0) ?></div>
        <div class="stat-label">Inactive</div>
      </div>
    </div>

    <?php if ($showVolunteerForm): ?>
      <?php
      $volunteerFormValues = [
          'id' => intValue($editVolunteer['id'] ?? null),
          'full_name' => stringValue($editVolunteer['full_name'] ?? ''),
          'email' => stringValue($editVolunteer['email'] ?? ''),
          'phone_number' => stringValue($editVolunteer['phone_number'] ?? ''),
          'preferred_contact_method' => normalizePreferredContactMethod(stringValue($editVolunteer['preferred_contact_method'] ?? 'email')),
          'location_address' => stringValue($editVolunteer['location_address'] ?? ''),
          'areas_of_interest' => stringValue($editVolunteer['areas_of_interest'] ?? ''),
          'availability' => stringValue($editVolunteer['availability'] ?? ''),
          'message' => stringValue($editVolunteer['message'] ?? ''),
          'internal_notes' => stringValue($editVolunteer['internal_notes'] ?? ''),
          'status' => stringValue($editVolunteer['status'] ?? 'pending'),
      ];
      ?>
      <div class="card mb-3" id="volunteer-form">
        <div class="card-body">
          <div class="d-flex justify-between align-center mb-2" style="gap:1rem;flex-wrap:wrap;">
            <h3 style="font-size:1rem;"><?= $editVolunteer ? 'Volunteer Profile' : 'Add Volunteer' ?></h3>
            <a href="/admin/volunteers.php" class="btn btn-outline btn-sm">Back to List</a>
          </div>
          <form method="POST" action="/admin/volunteers.php<?= $editVolunteer ? '?edit=' . intValue($volunteerFormValues['id']) . '#volunteer-form' : '?mode=create#volunteer-form' ?>">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="<?= $editVolunteer ? 'edit_volunteer' : 'add_volunteer' ?>">
            <input type="hidden" name="volunteer_id" value="<?= intValue($volunteerFormValues['id']) ?>">

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?= e($volunteerFormValues['full_name']) ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" value="<?= e($volunteerFormValues['email']) ?>" required>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone_number" class="form-control" value="<?= e($volunteerFormValues['phone_number']) ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Preferred Contact Method</label>
                <select name="preferred_contact_method" class="form-control">
                  <option value="email" <?= $volunteerFormValues['preferred_contact_method'] === 'email' ? 'selected' : '' ?>>Email</option>
                  <option value="phone" <?= $volunteerFormValues['preferred_contact_method'] === 'phone' ? 'selected' : '' ?>>Phone</option>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Location / Address</label>
                <input type="text" name="location_address" class="form-control" value="<?= e($volunteerFormValues['location_address']) ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Volunteer Status</label>
                <select name="status" class="form-control">
                  <option value="pending" <?= $volunteerFormValues['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                  <option value="active" <?= $volunteerFormValues['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                  <option value="inactive" <?= $volunteerFormValues['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Areas of Interest or Skills</label>
              <textarea name="areas_of_interest" class="form-control" rows="4"><?= e($volunteerFormValues['areas_of_interest']) ?></textarea>
            </div>

            <div class="form-group">
              <label class="form-label">Availability</label>
              <textarea name="availability" class="form-control" rows="3"><?= e($volunteerFormValues['availability']) ?></textarea>
            </div>

            <div class="form-group">
              <label class="form-label">Additional Notes</label>
              <textarea name="message" class="form-control" rows="4"><?= e($volunteerFormValues['message']) ?></textarea>
            </div>

            <div class="form-group">
              <label class="form-label">Internal Notes</label>
              <textarea name="internal_notes" class="form-control" rows="4"><?= e($volunteerFormValues['internal_notes']) ?></textarea>
            </div>

            <div class="d-flex gap-1" style="flex-wrap:wrap;">
              <button type="submit" class="btn btn-primary btn-sm"><?= $editVolunteer ? 'Save Volunteer' : 'Add Volunteer' ?></button>
              <a href="/admin/volunteers.php" class="btn btn-outline btn-sm">Cancel</a>
            </div>
          </form>

          <?php if ($editVolunteer): ?>
            <div class="divider"></div>
            <h4 style="font-size:0.95rem;margin-bottom:1rem;">Audit Log</h4>
            <?php if ($auditEntries): ?>
              <div class="table-wrap">
                <table>
                  <thead>
                    <tr>
                      <th>Action</th>
                      <th>Actor</th>
                      <th>Details</th>
                      <th>Date</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($auditEntries as $entry): ?>
                      <tr>
                        <td><?= e(ucfirst(stringValue($entry['action'] ?? ''))) ?></td>
                        <td><?= e(stringValue($entry['actor_name'] ?? '') !== '' ? stringValue($entry['actor_name']) : 'System / Public Form') ?></td>
                        <td><?= e(stringValue($entry['details'] ?? '—')) ?></td>
                        <td><?= formatDateOrFallback($entry['created_at'] ?? null, 'M j, Y g:ia') ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="text-muted">No audit history yet.</p>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="card mb-3">
      <div class="card-body">
        <form method="GET" action="/admin/volunteers.php" class="filter-grid">
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Search</label>
            <input type="text" name="q" class="form-control" value="<?= e($search) ?>" placeholder="Name, email, or phone">
          </div>
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Interest Filter</label>
            <input type="text" name="interest" class="form-control" value="<?= e($interestFilter) ?>" placeholder="Example: events, build, acting">
          </div>
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
              <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All statuses</option>
              <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
              <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
              <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Sort</label>
            <select name="sort" class="form-control">
              <option value="date_newest" <?= $sort === 'date_newest' ? 'selected' : '' ?>>Newest first</option>
              <option value="date_oldest" <?= $sort === 'date_oldest' ? 'selected' : '' ?>>Oldest first</option>
              <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name A–Z</option>
              <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name Z–A</option>
              <option value="status" <?= $sort === 'status' ? 'selected' : '' ?>>Status</option>
            </select>
          </div>
          <div class="d-flex gap-1" style="align-items:end;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
            <a href="/admin/volunteers.php" class="btn btn-outline btn-sm">Reset</a>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="d-flex justify-between align-center mb-2" style="gap:1rem;flex-wrap:wrap;">
          <h3 style="font-size:1rem;">Volunteer Directory</h3>
          <div class="text-muted"><?= count($volunteers) ?> matching record<?= count($volunteers) === 1 ? '' : 's' ?></div>
        </div>
        <?php if ($volunteers): ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Contact</th>
                  <th>Interest / Skills</th>
                  <th>Availability</th>
                  <th>Status</th>
                  <th>Added</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($volunteers as $volunteer): ?>
                  <tr>
                    <td>
                      <strong><?= e($volunteer['full_name']) ?></strong>
                      <?php if (!empty($volunteer['location_address'])): ?>
                        <div class="text-muted"><?= e($volunteer['location_address']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div><a href="mailto:<?= e($volunteer['email']) ?>"><?= e($volunteer['email']) ?></a></div>
                      <?php if (!empty($volunteer['phone_number'])): ?>
                        <div><a href="tel:<?= e(preg_replace('/\D/', '', stringValue($volunteer['phone_number'])) ?? '') ?>"><?= e($volunteer['phone_number']) ?></a></div>
                      <?php endif; ?>
                      <div class="text-muted">Prefers <?= e(ucfirst(stringValue($volunteer['preferred_contact_method'] ?? 'email'))) ?></div>
                    </td>
                    <td style="max-width:220px;white-space:pre-line;"><?= e(stringValue($volunteer['areas_of_interest'] ?? '—')) ?></td>
                    <td style="max-width:220px;white-space:pre-line;"><?= e(stringValue($volunteer['availability'] ?? '—')) ?></td>
                    <td>
                      <?php
                      $statusValue = stringValue($volunteer['status'] ?? 'pending');
                      $statusClass = $statusValue === 'active' ? 'status-approved' : ($statusValue === 'inactive' ? 'status-inactive' : 'status-pending');
                      ?>
                      <span class="status-badge <?= $statusClass ?>"><?= e(ucfirst($statusValue)) ?></span>
                    </td>
                    <td><?= formatDateOrFallback($volunteer['created_at'] ?? null, 'M j, Y') ?></td>
                    <td>
                      <div class="td-actions">
                        <a href="/admin/volunteers.php?edit=<?= intValue($volunteer['id']) ?>#volunteer-form" class="btn btn-outline btn-sm">Edit</a>
                        <form method="POST" style="display:inline;">
                          <?= csrfField() ?>
                          <input type="hidden" name="action" value="delete_volunteer">
                          <input type="hidden" name="volunteer_id" value="<?= intValue($volunteer['id']) ?>">
                          <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete this volunteer record?">Delete</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="text-muted">No volunteers match the current filters.</p>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
