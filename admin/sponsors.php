<?php
/**
 * RedWater Entertainment - Admin: Sponsors & Tiers
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$db = getDb();

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $act = $_POST['action'] ?? '';

    // Add/edit tier
    if ($act === 'save_tier') {
        $tierId       = (int)($_POST['tier_id'] ?? 0);
        $name         = trim($_POST['name'] ?? '');
        $sortOrder    = (int)($_POST['sort_order'] ?? 0);
        $cardsPerRow  = max(1, min(6, (int)($_POST['cards_per_row'] ?? 3)));
        $showName     = isset($_POST['show_name'])        ? 1 : 0;
        $showDesc     = isset($_POST['show_description']) ? 1 : 0;
        $showLogo     = isset($_POST['show_logo'])        ? 1 : 0;
        $showLink     = isset($_POST['show_link'])        ? 1 : 0;

        if ($tierId) {
            $db->prepare('UPDATE sponsor_tiers SET name=?, sort_order=?, cards_per_row=?, show_name=?, show_description=?, show_logo=?, show_link=? WHERE id=?')
               ->execute([$name, $sortOrder, $cardsPerRow, $showName, $showDesc, $showLogo, $showLink, $tierId]);
        } else {
            $db->prepare('INSERT INTO sponsor_tiers (name, sort_order, cards_per_row, show_name, show_description, show_logo, show_link) VALUES (?,?,?,?,?,?,?)')
               ->execute([$name, $sortOrder, $cardsPerRow, $showName, $showDesc, $showLogo, $showLink]);
        }
        flashMessage('success', 'Tier saved.');
        redirect('/admin/sponsors.php');
    }

    // Delete tier
    if ($act === 'delete_tier') {
        $tierId = (int)($_POST['tier_id'] ?? 0);
        $db->prepare('DELETE FROM sponsor_tiers WHERE id=?')->execute([$tierId]);
        flashMessage('success', 'Tier and its sponsors deleted.');
        redirect('/admin/sponsors.php');
    }

    // Add/edit sponsor
    if ($act === 'save_sponsor') {
        $sponsorId  = (int)($_POST['sponsor_id'] ?? 0);
        $tierId     = (int)($_POST['tier_id'] ?? 0);
        $name       = trim($_POST['sponsor_name'] ?? '');
        $description= trim($_POST['description'] ?? '');
        $logoUrl    = trim($_POST['logo_url'] ?? '');
        $linkUrl    = trim($_POST['link_url'] ?? '');
        $sortOrder  = (int)($_POST['sort_order'] ?? 0);

        if ($sponsorId) {
            $db->prepare('UPDATE sponsors SET tier_id=?, name=?, description=?, logo_url=?, link_url=?, sort_order=? WHERE id=?')
               ->execute([$tierId, $name, $description, $logoUrl, $linkUrl, $sortOrder, $sponsorId]);
        } else {
            $db->prepare('INSERT INTO sponsors (tier_id, name, description, logo_url, link_url, sort_order) VALUES (?,?,?,?,?,?)')
               ->execute([$tierId, $name, $description, $logoUrl, $linkUrl, $sortOrder]);
        }
        flashMessage('success', 'Sponsor saved.');
        redirect('/admin/sponsors.php');
    }

    // Delete sponsor
    if ($act === 'delete_sponsor') {
        $sponsorId = (int)($_POST['sponsor_id'] ?? 0);
        $db->prepare('DELETE FROM sponsors WHERE id=?')->execute([$sponsorId]);
        flashMessage('success', 'Sponsor deleted.');
        redirect('/admin/sponsors.php');
    }
}

// ── Load data ────────────────────────────────────────────────────────────────
$tiers = getSponsorTiers();

// Get all tiers (for sponsor dropdown)
$allTiersStmt = $db->query('SELECT id, name FROM sponsor_tiers ORDER BY sort_order ASC');
assert($allTiersStmt instanceof PDOStatement);
$allTiers = $allTiersStmt->fetchAll();

$pageTitle = 'Manage Sponsors';
include __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <main class="admin-main">
    <div class="d-flex justify-between align-center mb-3">
      <h1 class="admin-page-title" style="margin:0;border:none;padding:0;">Manage <span>Sponsors</span></h1>
      <div class="d-flex gap-1">
        <button class="btn btn-outline btn-sm" data-modal-open="addTierModal">+ Add Tier</button>
        <button class="btn btn-primary btn-sm" data-modal-open="addSponsorModal">+ Add Sponsor</button>
      </div>
    </div>

    <?php if (empty($tiers)): ?>
      <div class="card">
        <div class="card-body text-center" style="padding:3rem;">
          <p class="text-muted">No sponsor tiers configured yet. Add a tier first, then add sponsors to it.</p>
          <button class="btn btn-primary mt-2" data-modal-open="addTierModal">Add First Tier</button>
        </div>
      </div>
    <?php endif; ?>

    <?php foreach ($tiers as $tier): ?>
      <div class="card mb-3">
        <div class="card-body">
          <div class="d-flex justify-between align-center mb-2">
            <div>
              <h3 style="font-size:1rem;margin:0;"><?= e($tier['name']) ?></h3>
              <span class="text-muted" style="font-size:0.8rem;">
                <?= (int)$tier['cards_per_row'] ?> per row &mdash;
                <?= $tier['show_name']        ? '✓ Name ' : '' ?>
                <?= $tier['show_description'] ? '✓ Desc ' : '' ?>
                <?= $tier['show_logo']        ? '✓ Logo ' : '' ?>
                <?= $tier['show_link']        ? '✓ Link'  : '' ?>
              </span>
            </div>
            <div class="d-flex gap-1">
              <button class="btn btn-outline btn-sm"
                      onclick="openEditTier(<?= htmlspecialchars(json_encode($tier) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>)">Edit Tier</button>
              <form method="POST" style="display:inline;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete_tier">
                <input type="hidden" name="tier_id" value="<?= $tier['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm"
                        data-confirm="Delete this tier and all its sponsors?">Delete Tier</button>
              </form>
            </div>
          </div>

          <!-- Sponsors in this tier -->
          <?php if (!empty($tier['sponsors'])): ?>
            <div class="table-wrap" style="margin-top:1rem;">
              <table>
                <thead><tr><th>Name</th><th>Description</th><th>Logo URL</th><th>Link URL</th><th>Order</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($tier['sponsors'] as $sponsor): ?>
                  <tr>
                    <td><?= e($sponsor['name'] ?: '—') ?></td>
                    <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($sponsor['description'] ?: '—') ?></td>
                    <td><?= $sponsor['logo_url'] ? '<a href="' . e($sponsor['logo_url']) . '" target="_blank" style="font-size:0.8rem;">View</a>' : '—' ?></td>
                    <td><?= $sponsor['link_url'] ? '<a href="' . e($sponsor['link_url']) . '" target="_blank" style="font-size:0.8rem;">Visit</a>' : '—' ?></td>
                    <td><?= (int)$sponsor['sort_order'] ?></td>
                    <td>
                      <div class="td-actions">
                        <button class="btn btn-outline btn-sm"
                                onclick="openEditSponsor(<?= htmlspecialchars(json_encode($sponsor) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>)">Edit</button>
                        <form method="POST" style="display:inline;">
                          <?= csrfField() ?>
                          <input type="hidden" name="action" value="delete_sponsor">
                          <input type="hidden" name="sponsor_id" value="<?= $sponsor['id'] ?>">
                          <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete this sponsor?">Delete</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p class="text-muted" style="margin-top:1rem;font-size:0.9rem;">No sponsors in this tier yet.</p>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </main>
</div>

<!-- Add/Edit Tier Modal -->
<div id="addTierModal" class="modal-backdrop">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title" id="tierModalTitle">Add Sponsor Tier</h3>
      <span class="modal-close" data-modal-close>&times;</span>
    </div>
    <div class="modal-body">
      <form method="POST" action="/admin/sponsors.php" id="tierForm">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save_tier">
        <input type="hidden" name="tier_id" id="tierFormId" value="0">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Tier Name</label>
            <input type="text" name="name" id="tierName" class="form-control" placeholder="e.g., Gold, Silver, Bronze" required>
          </div>
          <div class="form-group">
            <label class="form-label">Sort Order (lower = higher/larger)</label>
            <input type="number" name="sort_order" id="tierOrder" class="form-control" value="0" min="0">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Cards Per Row</label>
          <select name="cards_per_row" id="tierCols" class="form-control">
            <option value="1">1 (Full Width)</option>
            <option value="2">2</option>
            <option value="3" selected>3</option>
            <option value="4">4</option>
            <option value="5">5</option>
            <option value="6">6</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Show Content</label>
          <div class="d-flex gap-2" style="flex-wrap:wrap;">
            <label class="form-check"><input type="checkbox" name="show_name"        id="tierShowName" checked> Name</label>
            <label class="form-check"><input type="checkbox" name="show_description" id="tierShowDesc" checked> Description</label>
            <label class="form-check"><input type="checkbox" name="show_logo"        id="tierShowLogo" checked> Logo</label>
            <label class="form-check"><input type="checkbox" name="show_link"        id="tierShowLink" checked> Link</label>
          </div>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
      <button type="submit" form="tierForm" class="btn btn-primary btn-sm">Save Tier</button>
    </div>
  </div>
</div>

<!-- Add/Edit Sponsor Modal -->
<div id="addSponsorModal" class="modal-backdrop">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title" id="sponsorModalTitle">Add Sponsor</h3>
      <span class="modal-close" data-modal-close>&times;</span>
    </div>
    <div class="modal-body">
      <form method="POST" action="/admin/sponsors.php" id="sponsorForm">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save_sponsor">
        <input type="hidden" name="sponsor_id" id="sponsorFormId" value="0">
        <div class="form-group">
          <label class="form-label">Sponsor Tier</label>
          <select name="tier_id" id="sponsorTier" class="form-control" required>
            <?php foreach ($allTiers as $t): ?>
              <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
            <?php endforeach; ?>
            <?php if (!$allTiers): ?><option value="">No tiers — add a tier first</option><?php endif; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Sponsor Name</label>
            <input type="text" name="sponsor_name" id="sponsorName" class="form-control" placeholder="Business Name">
          </div>
          <div class="form-group">
            <label class="form-label">Sort Order</label>
            <input type="number" name="sort_order" id="sponsorOrder" class="form-control" value="0" min="0">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" id="sponsorDesc" class="form-control" rows="3"></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Logo URL</label>
            <input type="url" name="logo_url" id="sponsorLogo" class="form-control" placeholder="https://...">
            <div class="form-hint">Direct URL to the sponsor's logo image.</div>
          </div>
          <div class="form-group">
            <label class="form-label">Website URL</label>
            <input type="url" name="link_url" id="sponsorLink" class="form-control" placeholder="https://...">
          </div>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
      <button type="submit" form="sponsorForm" class="btn btn-primary btn-sm">Save Sponsor</button>
    </div>
  </div>
</div>

<script>
function openEditTier(tier) {
  document.getElementById('tierModalTitle').textContent = 'Edit Tier: ' + tier.name;
  document.getElementById('tierFormId').value  = tier.id;
  document.getElementById('tierName').value    = tier.name;
  document.getElementById('tierOrder').value   = tier.sort_order;
  document.getElementById('tierCols').value    = tier.cards_per_row;
  document.getElementById('tierShowName').checked = !!parseInt(tier.show_name);
  document.getElementById('tierShowDesc').checked = !!parseInt(tier.show_description);
  document.getElementById('tierShowLogo').checked = !!parseInt(tier.show_logo);
  document.getElementById('tierShowLink').checked = !!parseInt(tier.show_link);
  document.getElementById('addTierModal').classList.add('open');
}
function openEditSponsor(sponsor) {
  document.getElementById('sponsorModalTitle').textContent = 'Edit Sponsor';
  document.getElementById('sponsorFormId').value  = sponsor.id;
  document.getElementById('sponsorTier').value    = sponsor.tier_id;
  document.getElementById('sponsorName').value    = sponsor.name || '';
  document.getElementById('sponsorOrder').value   = sponsor.sort_order;
  document.getElementById('sponsorDesc').value    = sponsor.description || '';
  document.getElementById('sponsorLogo').value    = sponsor.logo_url || '';
  document.getElementById('sponsorLink').value    = sponsor.link_url || '';
  document.getElementById('addSponsorModal').classList.add('open');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
