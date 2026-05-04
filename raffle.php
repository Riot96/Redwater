<?php
/**
 * RedWater Entertainment - Raffle Randomizer
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Raffle Randomizer';
$seoDescription = 'Run a fair raffle draw, upload name lists, and collect giveaway entries with a shareable form.';

$user = currentUser();
$isAdmin = $user !== null && $user['role'] === 'admin';
$showEntryFormFirst = getString('entry') === '1';

$raffleSettings = getRaffleSettings();
$storedEntries = getRaffleEntries();

$buildShareUrl = static function (): string {
    $baseUrl = defined('SITE_URL') ? rtrim(stringValue(SITE_URL), '/') : '';
    if ($baseUrl === '' || $baseUrl === 'https://yourdomain.com') {
        $scheme = serverString('HTTPS') !== '' && serverString('HTTPS') !== 'off' ? 'https' : 'http';
        $host = serverString('HTTP_HOST');
        if ($host !== '') {
            $baseUrl = $scheme . '://' . $host;
        }
    }

    return ($baseUrl !== '' ? $baseUrl : '') . '/raffle.php?entry=1#raffle-entry-form';
};

$shareUrl = $buildShareUrl();
$iframeCode = '<iframe src="' . $shareUrl . '" title="Raffle entry form" loading="lazy" style="width:100%;min-height:720px;border:0;"></iframe>';

$entryValues = [
    'name' => '',
    'email' => '',
    'newsletter_opt_in' => false,
];
$settingsValues = $raffleSettings;
$drawValues = [
    'names_text' => '',
    'include_form_entries' => $isAdmin,
];
$preparedNames = [];
$drawSummary = [
    'duplicates' => [],
    'invalid' => [],
];
$drawError = '';
$entryError = '';

$expiresAtTimestamp = $raffleSettings['expires_at'] !== '' ? strtotime($raffleSettings['expires_at']) : false;
$entryFormClosed = !$raffleSettings['entry_form_enabled']
    || ($expiresAtTimestamp !== false && $expiresAtTimestamp < time());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = postString('action');

    if ($action === 'save_raffle_settings') {
        if (!$isAdmin) {
            redirectWithMessage('/raffle.php', 'error', 'Only admins can update raffle settings.');
        }

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
            redirectWithMessage('/raffle.php#raffle-admin', 'success', 'Raffle entry form settings saved.');
        }
    }

    if ($action === 'delete_raffle_entry') {
        if (!$isAdmin) {
            redirectWithMessage('/raffle.php', 'error', 'Only admins can remove raffle entries.');
        }

        $entryIndex = postInt('entry_index', -1);
        if (isset($storedEntries[$entryIndex])) {
            unset($storedEntries[$entryIndex]);
            saveRaffleEntries(array_values($storedEntries));
            redirectWithMessage('/raffle.php#raffle-admin', 'success', 'Raffle entry removed.');
        }

        redirectWithMessage('/raffle.php#raffle-admin', 'error', 'The selected raffle entry could not be found.');
    }

    if ($action === 'clear_raffle_entries') {
        if (!$isAdmin) {
            redirectWithMessage('/raffle.php', 'error', 'Only admins can clear raffle entries.');
        }

        saveRaffleEntries([]);
        redirectWithMessage('/raffle.php#raffle-admin', 'success', 'All raffle entries were cleared.');
    }

    if ($action === 'submit_raffle_entry') {
        $entryValues = [
            'name' => normalizeRaffleName(postString('name')),
            'email' => trim(postString('email')),
            'newsletter_opt_in' => postBool('newsletter_opt_in'),
        ];

        if ($entryFormClosed) {
            $entryError = $raffleSettings['entry_form_enabled']
                ? 'This raffle entry form is closed.'
                : 'This raffle entry form is not accepting submissions right now.';
        } elseif (!isValidRaffleName($entryValues['name'])) {
            $entryError = 'Please enter a valid participant name using letters, numbers, and basic punctuation only.';
        } elseif ($entryValues['email'] !== '' && !filter_var($entryValues['email'], FILTER_VALIDATE_EMAIL)) {
            $entryError = 'Please enter a valid email address or leave the field blank.';
        } else {
            $entryEmailKey = $entryValues['email'] !== '' ? strtolower($entryValues['email']) : '';
            $entryNameKey = raffleNameKey($entryValues['name']);
            foreach ($storedEntries as $storedEntry) {
                $storedEmail = trim($storedEntry['email']);
                $storedEmailKey = $storedEmail !== '' ? strtolower($storedEmail) : '';
                $sameEmail = $entryEmailKey !== '' && $storedEmailKey === $entryEmailKey;
                $sameNameWithoutEmail = $entryEmailKey === ''
                    && $storedEmailKey === ''
                    && raffleNameKey($storedEntry['name']) === $entryNameKey;

                if ($sameEmail || $sameNameWithoutEmail) {
                    $entryError = 'That participant is already in the raffle list.';
                    break;
                }
            }
        }

        if ($entryError === '') {
            $storedEntries[] = [
                'name' => $entryValues['name'],
                'email' => $entryValues['email'],
                'newsletter_opt_in' => $entryValues['newsletter_opt_in'],
                'created_at' => date('Y-m-d H:i:s'),
            ];
            saveRaffleEntries($storedEntries);
            redirectWithMessage('/raffle.php?entry=1#raffle-entry-form', 'success', 'Your raffle entry has been received.');
        }
    }

    if ($action === 'prepare_raffle_draw') {
        $drawValues = [
            'names_text' => trim(postString('names_text')),
            'include_form_entries' => $isAdmin && postBool('include_form_entries'),
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

$entryStatusText = $entryFormClosed
    ? ($raffleSettings['entry_form_enabled'] ? 'Closed' : 'Not accepting entries')
    : 'Open';

include __DIR__ . '/includes/header.php';
?>

<main>
  <section class="section section-sm">
    <div class="container">
      <div class="raffle-hero">
        <div>
          <span class="badge badge-red">Fair &amp; Simple</span>
          <h1>Raffle <span>Randomizer</span></h1>
          <p class="raffle-lead">Paste names, upload a CSV/TXT list, and run repeatable winner draws without duplicate winners when you choose to remove each selection from the pool.</p>
          <div class="d-flex gap-1" style="flex-wrap:wrap;">
            <a href="#raffle-randomizer" class="btn btn-primary">Start a Draw</a>
            <a href="#raffle-entry-form" class="btn btn-secondary"><?= $showEntryFormFirst ? 'Enter the Raffle' : 'Share Entry Form' ?></a>
          </div>
        </div>
        <div class="raffle-status-card">
          <h3>Entry Form Status</h3>
          <div class="raffle-status-pill <?= $entryFormClosed ? 'is-closed' : 'is-open' ?>"><?= e($entryStatusText) ?></div>
          <p><strong>Title:</strong> <?= e($raffleSettings['title']) ?></p>
          <p><strong>Closes:</strong> <?= $raffleSettings['expires_at'] !== '' ? e(date('M j, Y g:ia', (int) strtotime($raffleSettings['expires_at']))) : 'No closing date set' ?></p>
          <p><strong>Saved entries:</strong> <?= count($storedEntries) ?></p>
          <?php if ($isAdmin): ?>
            <p><strong>Share URL:</strong><br><a href="<?= e($shareUrl) ?>"><?= e($shareUrl) ?></a></p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <section class="section" id="raffle-randomizer">
    <div class="container">
      <div class="section-title">
        <h2>Draw a Winner</h2>
        <p>Upload a clean list of participants, review validation notes, then draw as many winners as you need.</p>
      </div>

      <div class="raffle-grid">
        <div class="card">
          <div class="card-body">
            <h3 class="mb-2">Prepare the Name Pool</h3>
            <?php if ($drawError !== ''): ?>
              <div class="alert alert-error"><?= e($drawError) ?></div>
            <?php endif; ?>
            <form method="POST" action="/raffle.php#raffle-randomizer" enctype="multipart/form-data">
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
              <?php if ($isAdmin): ?>
                <label class="raffle-checkbox">
                  <input type="checkbox" name="include_form_entries" value="1" <?= $drawValues['include_form_entries'] ? 'checked' : '' ?>>
                  <span>Include saved entry form submissions (<?= count($storedEntries) ?>)</span>
                </label>
              <?php endif; ?>
              <div class="d-flex gap-1 mt-2" style="flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary">Prepare List</button>
                <a href="/raffle.php#raffle-randomizer" class="btn btn-outline">Reset</a>
              </div>
            </form>

            <?php if ($drawSummary['duplicates'] !== [] || $drawSummary['invalid'] !== []): ?>
              <div class="raffle-validation">
                <?php if ($drawSummary['duplicates'] !== []): ?>
                  <p><strong>Duplicates removed:</strong> <?= e(implode(', ', $drawSummary['duplicates'])) ?></p>
                <?php endif; ?>
                <?php if ($drawSummary['invalid'] !== []): ?>
                  <p><strong>Skipped for invalid characters:</strong> <?= e(implode(', ', $drawSummary['invalid'])) ?></p>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="card">
          <div class="card-body">
            <div class="d-flex justify-between align-center mb-2" style="gap:1rem;flex-wrap:wrap;">
              <h3>Winner Picker</h3>
              <span class="badge"><?= count($preparedNames) ?> eligible name<?= count($preparedNames) === 1 ? '' : 's' ?></span>
            </div>
            <div class="raffle-winner" id="raffle-winner">
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
    </div>
  </section>

  <section class="section" id="raffle-entry-form">
    <div class="container">
      <div class="section-title">
        <h2><?= e($raffleSettings['title']) ?></h2>
        <p><?= $raffleSettings['description'] !== '' ? e($raffleSettings['description']) : 'Use this shareable form to collect entries automatically for your next raffle draw.' ?></p>
      </div>

      <div class="raffle-grid">
        <div class="card">
          <div class="card-body">
            <div class="d-flex justify-between align-center mb-2" style="gap:1rem;flex-wrap:wrap;">
              <h3>Participant Entry Form</h3>
              <span class="raffle-status-pill <?= $entryFormClosed ? 'is-closed' : 'is-open' ?>"><?= e($entryStatusText) ?></span>
            </div>
            <?php if ($entryError !== ''): ?>
              <div class="alert alert-error"><?= e($entryError) ?></div>
            <?php endif; ?>
            <?php if ($raffleSettings['expires_at'] !== ''): ?>
              <p class="text-muted">Entries close on <?= e(date('M j, Y g:ia', (int) strtotime($raffleSettings['expires_at']))) ?>.</p>
            <?php endif; ?>
            <?php if ($entryFormClosed): ?>
              <p class="text-muted">This form is currently unavailable for new entries.</p>
            <?php else: ?>
              <form method="POST" action="/raffle.php?entry=1#raffle-entry-form">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="submit_raffle_entry">
                <div class="form-group">
                  <label class="form-label" for="raffle-entry-name">Name <span class="text-red">*</span></label>
                  <input id="raffle-entry-name" type="text" name="name" class="form-control" maxlength="100" required value="<?= e($entryValues['name']) ?>">
                </div>
                <?php if ($raffleSettings['collect_email']): ?>
                  <div class="form-group">
                    <label class="form-label" for="raffle-entry-email">Email address</label>
                    <input id="raffle-entry-email" type="email" name="email" class="form-control" value="<?= e($entryValues['email']) ?>">
                    <div class="form-hint">Optional, but helpful if you need to contact the winner directly.</div>
                  </div>
                <?php endif; ?>
                <label class="raffle-checkbox">
                  <input type="checkbox" name="newsletter_opt_in" value="1" <?= $entryValues['newsletter_opt_in'] ? 'checked' : '' ?>>
                  <span><?= e($raffleSettings['opt_in_label']) ?></span>
                </label>
                <div class="mt-2">
                  <button type="submit" class="btn btn-primary">Submit Entry</button>
                </div>
              </form>
            <?php endif; ?>
          </div>
        </div>

        <div class="card">
          <div class="card-body">
            <h3 class="mb-2">How It Works</h3>
            <ol class="raffle-steps">
              <li>Share the direct form link or iframe with participants.</li>
              <li>Each valid submission is saved to the raffle entry list automatically.</li>
              <li>When you are ready, include saved entries in the randomizer and draw a winner.</li>
            </ol>
            <?php if ($isAdmin): ?>
              <div class="raffle-share-box">
                <label class="form-label" for="raffle-share-url">Direct URL</label>
                <input id="raffle-share-url" type="text" class="form-control" readonly value="<?= e($shareUrl) ?>">
                <label class="form-label mt-2" for="raffle-embed-code">Embeddable iframe</label>
                <textarea id="raffle-embed-code" class="form-control" rows="5" readonly><?= e($iframeCode) ?></textarea>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </section>

  <?php if ($isAdmin): ?>
    <section class="section" id="raffle-admin">
      <div class="container">
        <div class="section-title">
          <h2>Admin Controls</h2>
          <p>Configure the shareable form and manage saved participant entries from one place.</p>
        </div>

        <div class="raffle-grid">
          <div class="card">
            <div class="card-body">
              <h3 class="mb-2">Entry Form Settings</h3>
              <form method="POST" action="/raffle.php#raffle-admin">
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
          </div>

          <div class="card">
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
                            <form method="POST" action="/raffle.php#raffle-admin" onsubmit="return confirm('Remove this raffle entry?');">
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
                <form method="POST" action="/raffle.php#raffle-admin" class="mt-2" onsubmit="return confirm('Clear every saved raffle entry?');">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="clear_raffle_entries">
                  <button type="submit" class="btn btn-outline">Clear All Entries</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </section>
  <?php endif; ?>
</main>

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

<?php include __DIR__ . '/includes/footer.php'; ?>
