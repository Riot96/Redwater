<?php
/**
 * RedWater Entertainment - Admin: Raffles
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pageTitle = 'Raffles';
$raffleSettings = getRaffleSettings();
$storedEntries = getRaffleEntries();
$shareUrl = getRaffleShareUrl();
$iframeCode = '<iframe src="' . $shareUrl . '" title="Raffle entry form" loading="lazy" style="width:100%;min-height:720px;border:0;"></iframe>';

$settingsValues = $raffleSettings;
$drawValues = [
    'names_text' => '',
    'include_form_entries' => true,
];
$preparedNames = [];
$drawSummary = [
    'duplicates' => [],
    'invalid' => [],
];
$drawError = '';

$expiresAtTimestamp = $raffleSettings['expires_at'] !== '' ? strtotime($raffleSettings['expires_at']) : false;
$entryFormClosed = !$raffleSettings['entry_form_enabled']
    || ($expiresAtTimestamp !== false && $expiresAtTimestamp < time());
$expiresAtDisplay = $expiresAtTimestamp !== false ? date('M j, Y g:ia', $expiresAtTimestamp) : '';
$entryStatusText = !$raffleSettings['entry_form_enabled'] ? 'Not accepting entries' : ($entryFormClosed ? 'Closed' : 'Open');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = postString('action');

    if ($action === 'save_raffle_settings') {
        $settingsValues = [
            'entry_form_enabled' => postBool('entry_form_enabled'),
            'title' => trim(postString('title')),
            'description' => trim(postString('description')),
            'collect_email' => postBool('collect_email'),
            'opt_in_label' => trim(postString('opt_in_label')),
            'expires_at' => trim(postString('expires_at')),
        ];

        if ($settingsValues['title'] === '') {
            $settingsValues['title'] = 'RedWater Giveaway Entry';
        }
        if ($settingsValues['opt_in_label'] === '') {
            $settingsValues['opt_in_label'] = 'I want to receive email updates about future promotions.';
        }

        if ($settingsValues['expires_at'] !== '' && strtotime($settingsValues['expires_at']) === false) {
            flashMessage('error', 'Please choose a valid entry form closing date and time.');
        } else {
            saveRaffleSettings($settingsValues);
            redirectWithMessage('/admin/raffle.php#raffle-settings', 'success', 'Raffle entry form settings saved.');
        }
    }

    if ($action === 'delete_raffle_entry') {
        $entryIndex = postInt('entry_index', -1);
        if (isset($storedEntries[$entryIndex])) {
            unset($storedEntries[$entryIndex]);
            saveRaffleEntries(array_values($storedEntries));
            redirectWithMessage('/admin/raffle.php#raffle-entries', 'success', 'Raffle entry removed.');
        }

        redirectWithMessage('/admin/raffle.php#raffle-entries', 'error', 'The selected raffle entry could not be found.');
    }

    if ($action === 'clear_raffle_entries') {
        saveRaffleEntries([]);
        redirectWithMessage('/admin/raffle.php#raffle-entries', 'success', 'All raffle entries were cleared.');
    }

    if ($action === 'prepare_raffle_draw') {
        $drawValues = [
            'names_text' => trim(postString('names_text')),
            'include_form_entries' => postBool('include_form_entries'),
        ];

        $drawCandidates = [];
        $textParse = parseRaffleNames($drawValues['names_text']);
        $drawCandidates = $textParse['names'];
        $drawSummary['duplicates'] = $textParse['duplicates'];
        $drawSummary['invalid'] = $textParse['invalid'];

        $namesFile = uploadedFile('names_file');
        if (hasUploadedFile($namesFile)) {
            $extension = strtolower(pathinfo(stringValue($namesFile['name'] ?? ''), PATHINFO_EXTENSION));
            if (intValue($namesFile['error'] ?? null, UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                $drawError = 'The uploaded raffle list could not be processed. Please try uploading the file again.';
            } elseif (!in_array($extension, ['csv', 'txt'], true)) {
                $drawError = 'Please upload a CSV or TXT file for raffle names.';
            } elseif (intValue($namesFile['size'] ?? 0) > 1024 * 1024) {
                $drawError = 'Please keep uploaded raffle lists under 1 MB.';
            } else {
                $fileContents = file_get_contents(stringValue($namesFile['tmp_name'] ?? ''));
                if ($fileContents === false) {
                    $drawError = 'We could not read the uploaded file. Please try again.';
                } else {
                    $fileParse = $extension === 'csv'
                        ? parseRaffleCsvNames($fileContents)
                        : parseRaffleNames($fileContents);
                    $drawCandidates = array_merge($drawCandidates, $fileParse['names']);
                    $drawSummary['duplicates'] = array_values(array_unique(array_merge($drawSummary['duplicates'], $fileParse['duplicates'])));
                    $drawSummary['invalid'] = array_values(array_unique(array_merge($drawSummary['invalid'], $fileParse['invalid'])));
                }
            }
        }

        if ($drawValues['include_form_entries']) {
            foreach ($storedEntries as $storedEntry) {
                $drawCandidates[] = $storedEntry['name'];
            }
        }

        $preparedParse = parseRaffleNameCandidates($drawCandidates);
        $preparedNames = $preparedParse['names'];
        $drawSummary['duplicates'] = array_values(array_unique(array_merge($drawSummary['duplicates'], $preparedParse['duplicates'])));
        $drawSummary['invalid'] = array_values(array_unique(array_merge($drawSummary['invalid'], $preparedParse['invalid'])));

        if ($preparedNames === [] && $drawError === '') {
            $drawError = 'Add at least one valid name or include at least one saved raffle entry before drawing a winner.';
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
  <?php include __DIR__ . '/sidebar.php'; ?>

  <main class="admin-main">
    <h1 class="admin-page-title">Manage <span>Raffles</span></h1>

    <section class="card mb-3">
      <div class="card-body">
        <div class="raffle-hero">
          <div>
            <span class="badge badge-red">Admin Only</span>
            <h2>Raffle Manager</h2>
            <p class="raffle-lead">Configure the public raffle page, manage saved entries, and run winner draws from the admin panel only.</p>
            <div class="d-flex gap-1" style="flex-wrap:wrap;">
              <a href="#raffle-settings" class="btn btn-primary btn-sm">Edit Settings</a>
              <a href="#raffle-randomizer" class="btn btn-secondary btn-sm">Open Randomizer</a>
              <a href="<?= e($shareUrl) ?>" class="btn btn-outline btn-sm">View Public Page</a>
            </div>
          </div>
          <div class="raffle-status-card">
            <h3>Public Page Status</h3>
            <div class="raffle-status-pill <?= !$entryFormClosed && $raffleSettings['entry_form_enabled'] ? 'is-open' : 'is-closed' ?>"><?= e($entryStatusText) ?></div>
            <p><strong>Title:</strong> <?= e($raffleSettings['title']) ?></p>
            <p><strong>Closes:</strong> <?= $expiresAtDisplay !== '' ? e($expiresAtDisplay) : 'No closing date set' ?></p>
            <p><strong>Saved entries:</strong> <?= count($storedEntries) ?></p>
          </div>
        </div>
      </div>
    </section>

    <section class="card mb-3" id="raffle-settings">
      <div class="card-body">
        <div class="d-flex justify-between align-center mb-2" style="gap:1rem;flex-wrap:wrap;">
          <h3>Entry Form Settings</h3>
          <span class="badge"><?= e($entryStatusText) ?></span>
        </div>
        <div class="raffle-grid">
          <div>
            <form method="POST" action="/admin/raffle.php#raffle-settings">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="save_raffle_settings">
              <label class="raffle-checkbox">
                <input type="checkbox" name="entry_form_enabled" value="1" <?= $settingsValues['entry_form_enabled'] ? 'checked' : '' ?>>
                <span>Accept public raffle entries</span>
              </label>
              <label class="raffle-checkbox">
                <input type="checkbox" name="collect_email" value="1" <?= $settingsValues['collect_email'] ? 'checked' : '' ?>>
                <span>Show optional email address field</span>
              </label>
              <div class="form-group">
                <label class="form-label" for="raffle-title">Form title</label>
                <input id="raffle-title" type="text" name="title" class="form-control" maxlength="120" value="<?= e($settingsValues['title']) ?>">
              </div>
              <div class="form-group">
                <label class="form-label" for="raffle-description">Description</label>
                <textarea id="raffle-description" name="description" class="form-control" rows="4"><?= e($settingsValues['description']) ?></textarea>
              </div>
              <div class="form-group">
                <label class="form-label" for="raffle-opt-in-label">Newsletter opt-in label</label>
                <input id="raffle-opt-in-label" type="text" name="opt_in_label" class="form-control" maxlength="180" value="<?= e($settingsValues['opt_in_label']) ?>">
              </div>
              <div class="form-group">
                <label class="form-label" for="raffle-expires-at">Entry form closing date/time</label>
                <input id="raffle-expires-at" type="datetime-local" name="expires_at" class="form-control" value="<?= e($settingsValues['expires_at']) ?>">
              </div>
              <button type="submit" class="btn btn-primary">Save Entry Form Settings</button>
            </form>
          </div>

          <div class="raffle-share-box">
            <label class="form-label" for="raffle-share-url">Public URL</label>
            <input id="raffle-share-url" type="text" class="form-control" readonly value="<?= e($shareUrl) ?>">
            <label class="form-label mt-2" for="raffle-embed-code">Embeddable iframe</label>
            <textarea id="raffle-embed-code" class="form-control" rows="5" readonly><?= e($iframeCode) ?></textarea>
            <p class="form-hint">The public page now only shows active/inactive raffle status and the entry form when entries are open.</p>
          </div>
        </div>
      </div>
    </section>

    <section class="card mb-3" id="raffle-randomizer">
      <div class="card-body">
        <div class="section-title" style="margin-bottom:2rem;">
          <h2>Winner Randomizer</h2>
          <p>Build a draw pool from pasted names, uploaded files, and saved raffle submissions.</p>
        </div>

        <div class="raffle-grid">
          <div>
            <h3 class="mb-2">Prepare the Name Pool</h3>
            <?php if ($drawError !== ''): ?>
              <div class="alert alert-error"><?= e($drawError) ?></div>
            <?php endif; ?>
            <form method="POST" action="/admin/raffle.php#raffle-randomizer" enctype="multipart/form-data">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="prepare_raffle_draw">
              <div class="form-group">
                <label class="form-label" for="names_text">Paste names</label>
                <textarea id="names_text" name="names_text" class="form-control" rows="10" placeholder="One name per line"><?= e($drawValues['names_text']) ?></textarea>
                <div class="form-hint">Blank lines are ignored and duplicate names are removed automatically.</div>
              </div>
              <div class="form-group">
                <label class="form-label" for="names_file">Upload CSV or TXT</label>
                <input id="names_file" type="file" name="names_file" class="form-control" accept=".csv,.txt,text/plain,text/csv">
                <div class="form-hint">TXT files should use one name per line. CSV files use name cells and ignore email-only columns.</div>
              </div>
              <label class="raffle-checkbox">
                <input type="checkbox" name="include_form_entries" value="1" <?= $drawValues['include_form_entries'] ? 'checked' : '' ?>>
                <span>Include saved public entries (<?= count($storedEntries) ?>)</span>
              </label>
              <div class="d-flex gap-1 mt-2" style="flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary">Prepare List</button>
                <a href="/admin/raffle.php#raffle-randomizer" class="btn btn-outline">Reset</a>
              </div>
            </form>

            <?php if ($drawSummary['duplicates'] !== [] || $drawSummary['invalid'] !== []): ?>
              <div class="raffle-validation mt-2">
                <?php if ($drawSummary['duplicates'] !== []): ?>
                  <p><strong>Duplicates removed:</strong> <?= e(implode(', ', $drawSummary['duplicates'])) ?></p>
                <?php endif; ?>
                <?php if ($drawSummary['invalid'] !== []): ?>
                  <p><strong>Skipped for invalid characters:</strong> <?= e(implode(', ', $drawSummary['invalid'])) ?></p>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>

          <div>
            <div class="d-flex justify-between align-center mb-2" style="gap:1rem;flex-wrap:wrap;">
              <h3>Winner Picker</h3>
              <span class="badge"><?= count($preparedNames) ?> eligible name<?= count($preparedNames) === 1 ? '' : 's' ?></span>
            </div>
            <div class="raffle-winner">
              <span class="raffle-winner-label">Ready to draw</span>
              <strong id="raffle-winner-name"><?= $preparedNames !== [] ? 'Click “Draw Winner” to begin' : 'Prepare a valid name list first' ?></strong>
            </div>
            <label class="raffle-checkbox">
              <input type="checkbox" id="remove-after-draw" checked>
              <span>Remove each selected winner before the next draw</span>
            </label>
            <div class="d-flex gap-1 mt-2" style="flex-wrap:wrap;">
              <button type="button" class="btn btn-primary" id="draw-winner" <?= $preparedNames === [] ? 'disabled' : '' ?>>Draw Winner</button>
              <button type="button" class="btn btn-outline" id="reset-draw" <?= $preparedNames === [] ? 'disabled' : '' ?>>Restore Full List</button>
            </div>

            <details class="raffle-details" <?= $preparedNames !== [] ? 'open' : '' ?>>
              <summary>Current draw pool</summary>
              <ul class="raffle-name-list" id="raffle-current-pool">
                <?php foreach ($preparedNames as $name): ?>
                  <li><?= e($name) ?></li>
                <?php endforeach; ?>
              </ul>
            </details>

            <details class="raffle-details">
              <summary>Draw history</summary>
              <ul class="raffle-name-list" id="raffle-history">
                <li class="text-muted">No winners selected yet.</li>
              </ul>
            </details>
          </div>
        </div>
      </div>
    </section>

    <section class="card" id="raffle-entries">
      <div class="card-body">
        <div class="d-flex justify-between align-center mb-2" style="gap:1rem;flex-wrap:wrap;">
          <h3>Saved Entries</h3>
          <span class="badge"><?= count($storedEntries) ?> total</span>
        </div>
        <?php if ($storedEntries === []): ?>
          <p class="text-muted">No raffle entries have been submitted yet.</p>
        <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Opt-in</th>
                  <th>Submitted</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($storedEntries as $index => $storedEntry): ?>
                  <tr>
                    <td><?= e($storedEntry['name']) ?></td>
                    <td><?= $storedEntry['email'] !== '' ? e($storedEntry['email']) : '—' ?></td>
                    <td><?= $storedEntry['newsletter_opt_in'] ? 'Yes' : 'No' ?></td>
                    <td><?= $storedEntry['created_at'] !== '' ? e(formatDateOrFallback($storedEntry['created_at'], 'M j, Y g:ia', '—')) : '—' ?></td>
                    <td>
                      <form method="POST" action="/admin/raffle.php#raffle-entries" onsubmit="return confirm('Remove this raffle entry?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_raffle_entry">
                        <input type="hidden" name="entry_index" value="<?= $index ?>">
                        <button type="submit" class="btn btn-outline btn-sm">Remove</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <form method="POST" action="/admin/raffle.php#raffle-entries" class="mt-2" onsubmit="return confirm('Clear every saved raffle entry?');">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="clear_raffle_entries">
            <button type="submit" class="btn btn-outline">Clear All Entries</button>
          </form>
        <?php endif; ?>
      </div>
    </section>
  </main>
</div>

<script>
(() => {
  const originalPool = <?= json_encode($preparedNames, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const currentPoolElement = document.getElementById('raffle-current-pool');
  const historyElement = document.getElementById('raffle-history');
  const winnerNameElement = document.getElementById('raffle-winner-name');
  const drawButton = document.getElementById('draw-winner');
  const resetButton = document.getElementById('reset-draw');
  const removeAfterDraw = document.getElementById('remove-after-draw');

  if (!currentPoolElement || !historyElement || !winnerNameElement || !drawButton || !resetButton || !removeAfterDraw) {
    return;
  }

  let pool = [...originalPool];
  let history = [];

  const renderList = (element, items, emptyText) => {
    element.innerHTML = '';
    if (items.length === 0) {
      const item = document.createElement('li');
      item.className = 'text-muted';
      item.textContent = emptyText;
      element.appendChild(item);
      return;
    }

    items.forEach((itemText) => {
      const item = document.createElement('li');
      item.textContent = itemText;
      element.appendChild(item);
    });
  };

  const secureRandomIndex = (length) => {
    if (length <= 1) {
      return 0;
    }

    if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
      const buffer = new Uint32Array(1);
      const max = Math.floor(0x100000000 / length) * length;
      let randomValue = 0;
      do {
        window.crypto.getRandomValues(buffer);
        randomValue = buffer[0];
      } while (randomValue >= max);
      return randomValue % length;
    }

    return Math.floor(Math.random() * length);
  };

  const syncUi = () => {
    renderList(currentPoolElement, pool, 'No names are left in the draw pool.');
    renderList(historyElement, history, 'No winners selected yet.');
    drawButton.disabled = pool.length === 0;
    resetButton.disabled = originalPool.length === 0;
  };

  drawButton.addEventListener('click', () => {
    if (pool.length === 0) {
      winnerNameElement.textContent = 'No names are left in the draw pool.';
      return;
    }

    const index = secureRandomIndex(pool.length);
    const winner = pool[index];
    winnerNameElement.textContent = winner;
    history = [winner, ...history];

    if (removeAfterDraw.checked) {
      pool.splice(index, 1);
    }

    syncUi();
  });

  resetButton.addEventListener('click', () => {
    pool = [...originalPool];
    history = [];
    winnerNameElement.textContent = originalPool.length > 0 ? 'Click “Draw Winner” to begin' : 'Prepare a valid name list first';
    syncUi();
  });

  syncUi();
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
