<?php
/**
 * RedWater Entertainment - Admin: Contact Settings & Inquiries
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$db = getDb();
$contactSubmissionColumnsStmt = $db->query('SHOW COLUMNS FROM `contact_submissions`');
assert($contactSubmissionColumnsStmt instanceof PDOStatement);
$hasConvertedVolunteerIdColumn = false;
foreach ($contactSubmissionColumnsStmt->fetchAll() as $columnDefinition) {
    if (stringValue($columnDefinition['Field'] ?? '') === 'converted_volunteer_id') {
        $hasConvertedVolunteerIdColumn = true;
        break;
    }
}

if (getString('export') === 'inquiries') {
    $messagesStmt = $db->query('SELECT * FROM contact_submissions ORDER BY created_at DESC');
    assert($messagesStmt instanceof PDOStatement);
    /** @var list<array<string, mixed>> $messages */
    $messages = array_values($messagesStmt->fetchAll());

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="redwater-inquiries.csv"');
    $output = fopen('php://output', 'wb');
    if (is_resource($output)) {
        fputcsv($output, ['ID', 'Name', 'Email', 'Phone', 'Preferred Contact', 'Location', 'Subject', 'Message', 'Read', 'Created At', 'Updated At']);
        foreach ($messages as $message) {
            fputcsv($output, [
                intValue($message['id'] ?? null),
                stringValue($message['name'] ?? ''),
                stringValue($message['email'] ?? ''),
                stringValue($message['phone_number'] ?? ''),
                stringValue($message['preferred_contact_method'] ?? 'email'),
                stringValue($message['location_address'] ?? ''),
                stringValue($message['subject'] ?? ''),
                stringValue($message['message'] ?? ''),
                !empty($message['is_read']) ? 'Yes' : 'No',
                stringValue($message['created_at'] ?? ''),
                stringValue($message['updated_at'] ?? ''),
            ]);
            fflush($output);
        }
        fclose($output);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $act = postString('action', 'save_settings');

    if ($act === 'save_settings') {
        $fields = ['contact_phone', 'contact_email', 'contact_address', 'contact_map_embed',
                   'social_facebook', 'social_instagram', 'social_twitter', 'social_youtube',
                   'site_name', 'site_tagline',
                   'home_hero_heading', 'home_hero_subheading', 'home_about_text'];
        foreach ($fields as $field) {
            setSetting($field, trim(postString($field)));
        }
        flashMessage('success', 'Settings saved successfully.');
        redirect('/admin/contact.php');
    }

    if ($act === 'mark_read') {
        $msgId = postInt('msg_id');
        $db->prepare('UPDATE contact_submissions SET is_read=1 WHERE id=?')->execute([$msgId]);
        redirect('/admin/contact.php#inquiries');
    }

    if ($act === 'convert_to_volunteer') {
        $msgId = postInt('msg_id');
        if (!$hasConvertedVolunteerIdColumn) {
            try {
                ensureAutomaticMigrationColumn($db, 'contact_submissions', 'converted_volunteer_id INT NULL');
                $hasConvertedVolunteerIdColumn = true;
            } catch (PDOException $e) {
                // Fall back to conversion without a persistent inquiry link on hosts that have not run this migration yet.
                error_log('Unable to add contact_submissions.converted_volunteer_id automatically: ' . $e->getMessage());
            }
        }

        $stmt = $db->prepare('SELECT * FROM contact_submissions WHERE id=?');
        $stmt->execute([$msgId]);
        /** @var array<string, mixed>|false $message */
        $message = $stmt->fetch();

        if (!$message) {
            flashMessage('error', 'Inquiry not found.');
            redirect('/admin/contact.php#inquiries');
        }

        $existingVolunteerId = intValue($message['converted_volunteer_id'] ?? null);
        if ($existingVolunteerId > 0) {
            flashMessage('info', 'This inquiry has already been converted to a volunteer entry.');
            redirect('/admin/volunteers.php?edit=' . $existingVolunteerId . '#volunteer-form');
        }

        $subject = trim(stringValue($message['subject'] ?? ''));
        $internalNotes = 'Converted from inquiry #' . intValue($message['id'] ?? 0) . ' on ' . date('M j, Y g:ia') . '.';
        if ($subject !== '') {
            $internalNotes .= "\nOriginal inquiry subject: " . $subject;
        }

        $stmt = $db->prepare(
            'INSERT INTO volunteers (
                full_name, email, phone_number, preferred_contact_method, location_address,
                areas_of_interest, availability, message, internal_notes, privacy_consent, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            stringValue($message['name'] ?? ''),
            stringValue($message['email'] ?? ''),
            ($message['phone_number'] ?? null) !== null && stringValue($message['phone_number']) !== '' ? stringValue($message['phone_number']) : null,
            normalizePreferredContactMethod(stringValue($message['preferred_contact_method'] ?? 'email')),
            ($message['location_address'] ?? null) !== null && stringValue($message['location_address']) !== '' ? stringValue($message['location_address']) : null,
            $subject !== '' ? $subject : null,
            null,
            stringValue($message['message'] ?? ''),
            $internalNotes,
            !empty($message['privacy_consent']) ? 1 : 0,
            'pending',
        ]);
        $volunteerId = (int)$db->lastInsertId();
        if ($hasConvertedVolunteerIdColumn) {
            $db->prepare('UPDATE contact_submissions SET converted_volunteer_id=?, is_read=1 WHERE id=?')->execute([$volunteerId, $msgId]);
        } else {
            $db->prepare('UPDATE contact_submissions SET is_read=1 WHERE id=?')->execute([$msgId]);
        }

        $currentUser = currentUser();
        logVolunteerAudit(
            $db,
            $volunteerId,
            stringValue($message['name'] ?? ''),
            is_array($currentUser) ? intValue($currentUser['id']) : null,
            'created',
            'Volunteer record converted from inquiry #' . intValue($message['id'] ?? 0) . '.'
        );

        flashMessage('success', 'Inquiry converted to a volunteer entry. Review and complete the volunteer profile.');
        if (!$hasConvertedVolunteerIdColumn) {
            flashMessage('info', 'The volunteer was created successfully. This inquiry will not keep an automatic volunteer link until the latest database migrations are applied.');
        }
        redirect('/admin/volunteers.php?edit=' . $volunteerId . '#volunteer-form');
    }

    if ($act === 'delete_message') {
        $msgId = postInt('msg_id');
        $db->prepare('DELETE FROM contact_submissions WHERE id=?')->execute([$msgId]);
        flashMessage('success', 'Inquiry deleted.');
        redirect('/admin/contact.php#inquiries');
    }

    if ($act === 'add_inquiry' || $act === 'edit_inquiry') {
        $messageId = postInt('msg_id');
        $redirectTarget = $act === 'edit_inquiry'
            ? '/admin/contact.php?edit=' . $messageId . '#inquiry-form'
            : '/admin/contact.php?mode=create#inquiry-form';
        $name = trim(postString('name'));
        $email = trim(postString('email'));
        $phoneNumber = trim(postString('phone_number'));
        $preferredContactMethod = normalizePreferredContactMethod(postString('preferred_contact_method', 'email'));
        $locationAddress = trim(postString('location_address'));
        $subject = trim(postString('subject'));
        $message = trim(postString('message'));
        $isRead = postBool('is_read') ? 1 : 0;
        $savedSuccessfully = false;

        if ($name === '') {
            flashMessage('error', 'Name is required.');
        } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flashMessage('error', 'A valid email address is required.');
        } elseif ($preferredContactMethod === 'phone' && $phoneNumber === '') {
            flashMessage('error', 'Phone number is required when phone is the preferred contact method.');
        } elseif ($message === '') {
            flashMessage('error', 'Message is required.');
        } else {
            if ($act === 'add_inquiry') {
                $stmt = $db->prepare(
                    'INSERT INTO contact_submissions (
                        name, email, phone_number, preferred_contact_method, location_address,
                        subject, message, privacy_consent, is_read
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $name,
                    $email,
                    $phoneNumber !== '' ? $phoneNumber : null,
                    $preferredContactMethod,
                    $locationAddress !== '' ? $locationAddress : null,
                    $subject !== '' ? $subject : null,
                    $message,
                    1,
                    $isRead,
                ]);
                flashMessage('success', 'Inquiry added.');
                $savedSuccessfully = true;
            } else {
                $stmt = $db->prepare(
                    'UPDATE contact_submissions
                     SET name=?, email=?, phone_number=?, preferred_contact_method=?, location_address=?,
                         subject=?, message=?, privacy_consent=1, is_read=?
                     WHERE id=?'
                );
                $stmt->execute([
                    $name,
                    $email,
                    $phoneNumber !== '' ? $phoneNumber : null,
                    $preferredContactMethod,
                    $locationAddress !== '' ? $locationAddress : null,
                    $subject !== '' ? $subject : null,
                    $message,
                    $isRead,
                    $messageId,
                ]);
                flashMessage('success', 'Inquiry updated.');
                $savedSuccessfully = true;
            }
        }

        redirect($savedSuccessfully && $act === 'add_inquiry' ? '/admin/contact.php#inquiries' : $redirectTarget);
    }
}

$editMessageId = getInt('edit');
$showInquiryForm = getString('mode') === 'create' || $editMessageId > 0;
$editMessage = null;
if ($editMessageId > 0) {
    $stmt = $db->prepare('SELECT * FROM contact_submissions WHERE id=?');
    $stmt->execute([$editMessageId]);
    /** @var array<string, mixed>|false $editMessage */
    $editMessage = $stmt->fetch();
    if (!is_array($editMessage)) {
        $editMessage = null;
    }
}

$messagesStmt = $db->query('SELECT * FROM contact_submissions ORDER BY created_at DESC');
assert($messagesStmt instanceof PDOStatement);
/** @var list<array<string, mixed>> $messages */
$messages = array_values($messagesStmt->fetchAll());

$pageTitle = 'Contact Settings';
include __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <main class="admin-main">
    <div class="d-flex justify-between align-center mb-3" style="gap:1rem;flex-wrap:wrap;">
      <h1 class="admin-page-title" style="margin:0;border:none;padding:0;">Contact, <span>Inquiries</span> &amp; Settings</h1>
      <div class="d-flex gap-1" style="flex-wrap:wrap;">
        <a href="/admin/contact.php?mode=create#inquiry-form" class="btn btn-primary btn-sm">+ Add Inquiry</a>
        <a href="/admin/contact.php?export=inquiries" class="btn btn-outline btn-sm">Export CSV</a>
        <a href="/admin/volunteers.php" class="btn btn-outline btn-sm">Manage Volunteers</a>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-body">
        <h3 style="font-size:1rem;margin-bottom:1.5rem;">Site Settings</h3>
        <form method="POST" action="/admin/contact.php">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="save_settings">

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Site Name</label>
              <input type="text" name="site_name" class="form-control" value="<?= e(getSetting('site_name')) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Site Tagline</label>
              <input type="text" name="site_tagline" class="form-control" value="<?= e(getSetting('site_tagline')) ?>">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Hero Heading</label>
            <input type="text" name="home_hero_heading" class="form-control" value="<?= e(getSetting('home_hero_heading')) ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Hero Subheading</label>
            <textarea name="home_hero_subheading" class="form-control" rows="2"><?= e(getSetting('home_hero_subheading')) ?></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">About Section Text</label>
            <textarea name="home_about_text" class="form-control" rows="4"><?= e(getSetting('home_about_text')) ?></textarea>
            <div class="form-hint">Basic HTML allowed (e.g., &amp;mdash;, &amp;lsquo;)</div>
          </div>

          <div class="divider"></div>
          <h4 style="margin-bottom:1rem;font-size:0.95rem;">Contact Information</h4>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Phone Number</label>
              <input type="text" name="contact_phone" class="form-control" value="<?= e(getSetting('contact_phone')) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Contact Email</label>
              <input type="email" name="contact_email" class="form-control" value="<?= e(getSetting('contact_email')) ?>">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Address</label>
            <textarea name="contact_address" class="form-control" rows="3"><?= e(getSetting('contact_address')) ?></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Map Embed Code</label>
            <textarea name="contact_map_embed" class="form-control" rows="3" style="font-family:monospace;font-size:0.85rem;" placeholder="<iframe src=&quot;...&quot;></iframe>"><?= e(getSetting('contact_map_embed')) ?></textarea>
            <div class="form-hint">Paste the Google Maps embed &lt;iframe&gt; code here.</div>
          </div>

          <div class="divider"></div>
          <h4 style="margin-bottom:1rem;font-size:0.95rem;">Social Media Links</h4>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Facebook URL</label>
              <input type="url" name="social_facebook" class="form-control" value="<?= e(getSetting('social_facebook')) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Instagram URL</label>
              <input type="url" name="social_instagram" class="form-control" value="<?= e(getSetting('social_instagram')) ?>">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Twitter / X URL</label>
              <input type="url" name="social_twitter" class="form-control" value="<?= e(getSetting('social_twitter')) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">YouTube URL</label>
              <input type="url" name="social_youtube" class="form-control" value="<?= e(getSetting('social_youtube')) ?>">
            </div>
          </div>

          <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
      </div>
    </div>

    <?php if ($showInquiryForm): ?>
      <?php
      $inquiryFormValues = [
          'id' => intValue($editMessage['id'] ?? null),
          'name' => stringValue($editMessage['name'] ?? ''),
          'email' => stringValue($editMessage['email'] ?? ''),
          'phone_number' => stringValue($editMessage['phone_number'] ?? ''),
          'preferred_contact_method' => normalizePreferredContactMethod(stringValue($editMessage['preferred_contact_method'] ?? 'email')),
           'location_address' => stringValue($editMessage['location_address'] ?? ''),
           'subject' => stringValue($editMessage['subject'] ?? ''),
           'message' => stringValue($editMessage['message'] ?? ''),
           'converted_volunteer_id' => intValue($editMessage['converted_volunteer_id'] ?? null),
           'is_read' => !empty($editMessage['is_read']),
       ];
       ?>
      <div class="card mb-3" id="inquiry-form">
        <div class="card-body">
          <div class="d-flex justify-between align-center mb-2" style="gap:1rem;flex-wrap:wrap;">
            <h3 style="font-size:1rem;"><?= $editMessage ? 'Edit Inquiry' : 'Add Inquiry' ?></h3>
            <a href="/admin/contact.php#inquiries" class="btn btn-outline btn-sm">Back to List</a>
          </div>
          <form method="POST" action="/admin/contact.php<?= $editMessage ? '?edit=' . intValue($inquiryFormValues['id']) . '#inquiry-form' : '#inquiry-form' ?>">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="<?= $editMessage ? 'edit_inquiry' : 'add_inquiry' ?>">
            <input type="hidden" name="msg_id" value="<?= intValue($inquiryFormValues['id']) ?>">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="name" class="form-control" value="<?= e($inquiryFormValues['name']) ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" value="<?= e($inquiryFormValues['email']) ?>" required>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone_number" class="form-control" value="<?= e($inquiryFormValues['phone_number']) ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Preferred Contact Method</label>
                <select name="preferred_contact_method" class="form-control">
                  <option value="email" <?= $inquiryFormValues['preferred_contact_method'] === 'email' ? 'selected' : '' ?>>Email</option>
                  <option value="phone" <?= $inquiryFormValues['preferred_contact_method'] === 'phone' ? 'selected' : '' ?>>Phone</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Location / Address</label>
              <input type="text" name="location_address" class="form-control" value="<?= e($inquiryFormValues['location_address']) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Subject</label>
              <input type="text" name="subject" class="form-control" value="<?= e($inquiryFormValues['subject']) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Message</label>
              <textarea name="message" class="form-control" rows="5" required><?= e($inquiryFormValues['message']) ?></textarea>
            </div>
            <div class="form-group">
              <label class="form-check">
                <input type="checkbox" name="is_read" value="1" <?= $inquiryFormValues['is_read'] ? 'checked' : '' ?>>
                Mark as read
              </label>
            </div>
            <div class="d-flex gap-1" style="flex-wrap:wrap;">
              <button type="submit" class="btn btn-primary btn-sm"><?= $editMessage ? 'Save Inquiry' : 'Add Inquiry' ?></button>
              <a href="/admin/contact.php#inquiries" class="btn btn-outline btn-sm">Cancel</a>
            </div>
          </form>
          <?php if ($editMessage): ?>
            <div class="d-flex gap-1 mt-2" style="flex-wrap:wrap;">
              <?php if ($inquiryFormValues['converted_volunteer_id'] > 0): ?>
                <a href="/admin/volunteers.php?edit=<?= intValue($inquiryFormValues['converted_volunteer_id']) ?>#volunteer-form" class="btn btn-outline btn-sm">View Volunteer</a>
              <?php else: ?>
                <form method="POST" style="display:inline;">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="convert_to_volunteer">
                  <input type="hidden" name="msg_id" value="<?= intValue($inquiryFormValues['id']) ?>">
                  <button type="submit" class="btn btn-secondary btn-sm">Convert to Volunteer</button>
                </form>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="card" id="inquiries">
      <div class="card-body">
        <div class="d-flex justify-between align-center mb-2" style="gap:1rem;flex-wrap:wrap;">
          <h3 style="font-size:1rem;">Inquiry Records</h3>
          <div class="text-muted"><?= count($messages) ?> total</div>
        </div>
        <?php if ($messages): ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Contact</th>
                  <th>Preferred</th>
                  <th>Subject</th>
                  <th>Message</th>
                  <th>Location</th>
                  <th>Date</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($messages as $msg): ?>
                <tr>
                  <td><?= e($msg['name']) ?></td>
                  <td>
                    <div><a href="mailto:<?= e($msg['email']) ?>"><?= e($msg['email']) ?></a></div>
                    <?php if (!empty($msg['phone_number'])): ?>
                      <div><a href="tel:<?= e(preg_replace('/\D/', '', stringValue($msg['phone_number'])) ?? '') ?>"><?= e($msg['phone_number']) ?></a></div>
                    <?php endif; ?>
                  </td>
                  <td><?= e(ucfirst(stringValue($msg['preferred_contact_method'] ?? 'email'))) ?></td>
                  <td><?= e($msg['subject'] ?: '—') ?></td>
                  <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= e($msg['message']) ?>"><?= e($msg['message']) ?></td>
                  <td><?= e($msg['location_address'] ?: '—') ?></td>
                  <td><?= formatDateOrFallback($msg['created_at'] ?? null, 'M j, Y g:ia') ?></td>
                  <td><span class="status-badge <?= $msg['is_read'] ? 'status-approved' : 'status-pending' ?>"><?= $msg['is_read'] ? 'Read' : 'New' ?></span></td>
                  <td>
                    <div class="td-actions">
                      <a href="/admin/contact.php?edit=<?= intValue($msg['id']) ?>#inquiry-form" class="btn btn-outline btn-sm">Edit</a>
                      <?php if (intValue($msg['converted_volunteer_id'] ?? null) > 0): ?>
                        <a href="/admin/volunteers.php?edit=<?= intValue($msg['converted_volunteer_id'] ?? null) ?>#volunteer-form" class="btn btn-outline btn-sm">Volunteer</a>
                      <?php else: ?>
                        <form method="POST" style="display:inline;">
                          <?= csrfField() ?>
                          <input type="hidden" name="action" value="convert_to_volunteer">
                          <input type="hidden" name="msg_id" value="<?= intValue($msg['id']) ?>">
                          <button type="submit" class="btn btn-secondary btn-sm">Convert</button>
                        </form>
                      <?php endif; ?>
                      <?php if (!$msg['is_read']): ?>
                        <form method="POST" style="display:inline;">
                          <?= csrfField() ?>
                          <input type="hidden" name="action" value="mark_read">
                          <input type="hidden" name="msg_id" value="<?= intValue($msg['id']) ?>">
                          <button class="btn btn-outline btn-sm">Mark Read</button>
                        </form>
                      <?php endif; ?>
                      <form method="POST" style="display:inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_message">
                        <input type="hidden" name="msg_id" value="<?= intValue($msg['id']) ?>">
                        <button class="btn btn-danger btn-sm" data-confirm="Delete this inquiry?">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="text-muted">No inquiries yet.</p>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
