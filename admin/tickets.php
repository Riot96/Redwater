<?php
/**
 * RedWater Entertainment - Admin: Tickets
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $embedCode = postString('embed_code');
    setSetting('tickets_embed_code', $embedCode);
    flashMessage('success', 'Ticket embed code updated successfully.');
    redirect('/admin/tickets.php');
}

$pageTitle  = 'Tickets Settings';
$embedCode  = getSetting('tickets_embed_code', '');
include __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <main class="admin-main">
    <h1 class="admin-page-title">Tickets <span>Settings</span></h1>

    <div class="card">
      <div class="card-body">
        <p class="text-muted" style="margin-bottom:1.5rem;">
          Paste your HauntPay embed code below. This code will be displayed on the public Tickets page.
          Log into your HauntPay account to get the embed code for your events.
        </p>

        <form method="POST" action="/admin/tickets.php">
          <?= csrfField() ?>
          <div class="form-group">
            <label class="form-label" for="embed_code">HauntPay Embed Code</label>
            <textarea id="embed_code" name="embed_code" class="form-control"
                      rows="10" placeholder="Paste your HauntPay embed code here (e.g., <iframe ...></iframe> or <script ...></script>)"
                      style="font-family: monospace; font-size:0.85rem;"><?= e($embedCode) ?></textarea>
            <div class="form-hint">This is typically an &lt;iframe&gt; or &lt;script&gt; tag provided by HauntPay.</div>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="/tickets.php" class="btn btn-outline" target="_blank">Preview Page</a>
          </div>
        </form>

        <?php if (!empty($embedCode)): ?>
          <div class="divider"></div>
          <h4 class="mb-2">Current Preview</h4>
          <div class="tickets-embed-wrap" style="min-height:200px;">
            <?= $embedCode ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
