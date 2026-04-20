<?php
/**
 * RedWater Entertainment - Admin: Contact Settings & Messages
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$db = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $act = $_POST['action'] ?? 'save_settings';

    if ($act === 'save_settings') {
        $fields = ['contact_phone','contact_email','contact_address','contact_map_embed',
                   'social_facebook','social_instagram','social_twitter','social_youtube',
                   'site_name','site_tagline',
                   'home_hero_heading','home_hero_subheading','home_about_text'];
        foreach ($fields as $field) {
            setSetting($field, trim($_POST[$field] ?? ''));
        }
        flashMessage('success', 'Settings saved successfully.');
        redirect('/admin/contact.php');
    }

    if ($act === 'mark_read') {
        $msgId = (int)($_POST['msg_id'] ?? 0);
        $db->prepare('UPDATE contact_submissions SET is_read=1 WHERE id=?')->execute([$msgId]);
        redirect('/admin/contact.php#messages');
    }

    if ($act === 'delete_message') {
        $msgId = (int)($_POST['msg_id'] ?? 0);
        $db->prepare('DELETE FROM contact_submissions WHERE id=?')->execute([$msgId]);
        flashMessage('success', 'Message deleted.');
        redirect('/admin/contact.php#messages');
    }
}

// Load messages
$messages = $db->query('SELECT * FROM contact_submissions ORDER BY created_at DESC')->fetchAll();

$pageTitle = 'Contact Settings';
include __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <main class="admin-main">
    <h1 class="admin-page-title">Contact &amp; Site <span>Settings</span></h1>

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

    <!-- Contact Messages -->
    <div class="card" id="messages">
      <div class="card-body">
        <h3 style="font-size:1rem;margin-bottom:1rem;">Contact Form Submissions</h3>
        <?php if ($messages): ?>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Name</th><th>Email</th><th>Subject</th><th>Message</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>
              <?php foreach ($messages as $msg): ?>
                <tr>
                  <td><?= e($msg['name']) ?></td>
                  <td><a href="mailto:<?= e($msg['email']) ?>"><?= e($msg['email']) ?></a></td>
                  <td><?= e($msg['subject'] ?: '—') ?></td>
                  <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= e($msg['message']) ?>"><?= e($msg['message']) ?></td>
                  <td><?= date('M j, Y g:ia', strtotime($msg['created_at'])) ?></td>
                  <td><span class="status-badge <?= $msg['is_read'] ? 'status-approved' : 'status-pending' ?>"><?= $msg['is_read'] ? 'Read' : 'New' ?></span></td>
                  <td>
                    <div class="td-actions">
                      <?php if (!$msg['is_read']): ?>
                        <form method="POST" style="display:inline;">
                          <?= csrfField() ?>
                          <input type="hidden" name="action" value="mark_read">
                          <input type="hidden" name="msg_id" value="<?= $msg['id'] ?>">
                          <button class="btn btn-outline btn-sm">Mark Read</button>
                        </form>
                      <?php endif; ?>
                      <form method="POST" style="display:inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_message">
                        <input type="hidden" name="msg_id" value="<?= $msg['id'] ?>">
                        <button class="btn btn-danger btn-sm" data-confirm="Delete this message?">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="text-muted">No messages yet.</p>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
