<?php
/**
 * RedWater Entertainment - Public Raffle Entry Page
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Raffle';
$seoDescription = 'Enter the current RedWater Entertainment raffle when entries are open.';

$raffleSettings = getRaffleSettings();
$storedEntries = getRaffleEntries();

$entryValues = [
    'name' => '',
    'email' => '',
    'newsletter_opt_in' => false,
];
$entryError = '';

$expiresAtTimestamp = $raffleSettings['expires_at'] !== '' ? strtotime($raffleSettings['expires_at']) : false;
$entryFormClosed = !$raffleSettings['entry_form_enabled']
    || ($expiresAtTimestamp !== false && $expiresAtTimestamp < time());
$isActiveRaffle = $raffleSettings['entry_form_enabled'] && !$entryFormClosed;
$expiresAtDisplay = $expiresAtTimestamp !== false ? date('M j, Y g:ia', $expiresAtTimestamp) : '';
$inactiveRaffleMessage = 'There is no active raffle accepting entries right now. Please check back for the next giveaway.';
$emailRequired = raffleRequiresEmail($raffleSettings);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = postString('action');

    if ($action !== 'submit_raffle_entry') {
        redirectWithMessage('/raffle.php#raffle-entry-form', 'error', 'Invalid action. Only raffle entry submissions are accepted on this page.');
    }

    $entryValues = [
        'name' => normalizeRaffleName(postString('name')),
        'email' => trim(postString('email')),
        'newsletter_opt_in' => postBool('newsletter_opt_in'),
    ];
    $hasEmail = $entryValues['email'] !== '';

    if ($entryFormClosed) {
        if ($raffleSettings['entry_form_enabled']) {
            $entryError = 'This raffle is no longer accepting entries.';
        } else {
            $entryError = $inactiveRaffleMessage;
        }
    } elseif (!isValidRaffleName($entryValues['name'])) {
        $entryError = 'Please enter a valid participant name using letters, numbers, and basic punctuation only.';
    } elseif ($emailRequired && !$hasEmail) {
        $entryError = 'Please enter your email address to complete this raffle entry.';
    } elseif ($hasEmail && !filter_var($entryValues['email'], FILTER_VALIDATE_EMAIL)) {
        if ($emailRequired) {
            $entryError = 'Please enter a valid email address to complete this raffle entry.';
        } else {
            $entryError = 'Please enter a valid email address or leave the field blank.';
        }
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
        redirectWithMessage('/raffle.php#raffle-entry-form', 'success', 'Your raffle entry has been received.');
    }
}

if ($isActiveRaffle) {
    $entryStatusText = 'Open Now';
} elseif ($raffleSettings['entry_form_enabled']) {
    $entryStatusText = 'Closed';
} else {
    $entryStatusText = 'No Active Raffle';
}

include __DIR__ . '/includes/header.php';
?>

<main>
  <section class="section section-sm">
    <div class="container">
      <div class="raffle-hero">
        <div>
          <span class="badge badge-red">Current Promotion</span>
          <h1>
            <?php if ($isActiveRaffle): ?>
              <?= e($raffleSettings['title']) ?>
            <?php else: ?>
              Raffle <span>Entries</span>
            <?php endif; ?>
          </h1>
          <p class="raffle-lead">
            <?php if ($isActiveRaffle): ?>
              <?= $raffleSettings['description'] !== '' ? e($raffleSettings['description']) : 'Use the form below to enter the current raffle.' ?>
            <?php else: ?>
              <?= e($inactiveRaffleMessage) ?>
            <?php endif; ?>
          </p>
          <div class="d-flex gap-1" style="flex-wrap:wrap;">
            <a href="#raffle-entry-form" class="btn btn-primary"><?= $isActiveRaffle ? 'Enter the Raffle' : 'View Status' ?></a>
          </div>
        </div>
        <div class="raffle-status-card">
          <h3>Entry Status</h3>
          <div class="raffle-status-pill <?= $isActiveRaffle ? 'is-open' : 'is-closed' ?>"><?= e($entryStatusText) ?></div>
          <?php if ($expiresAtDisplay !== ''): ?>
            <p><strong>Closes:</strong> <?= e($expiresAtDisplay) ?></p>
          <?php endif; ?>
          <p>
            <?php if ($isActiveRaffle): ?>
              Submit one entry per participant using the form below.
            <?php else: ?>
              When a raffle is active, this page will show the public entry form here.
            <?php endif; ?>
          </p>
        </div>
      </div>
    </div>
  </section>

  <section class="section" id="raffle-entry-form">
    <div class="container">
      <div class="section-title">
        <h2><?= $isActiveRaffle ? e($raffleSettings['title']) : 'No Active Raffle' ?></h2>
        <p>
          <?php if ($isActiveRaffle): ?>
            <?= $raffleSettings['description'] !== '' ? e($raffleSettings['description']) : 'Complete the form below to join the raffle.' ?>
          <?php else: ?>
            This page only displays the public raffle entry form when entries are currently open.
          <?php endif; ?>
        </p>
      </div>

      <div class="raffle-grid">
        <div class="card">
          <div class="card-body">
            <div class="d-flex justify-between align-center mb-2" style="gap:1rem;flex-wrap:wrap;">
              <h3>Participant Entry Form</h3>
              <span class="raffle-status-pill <?= $isActiveRaffle ? 'is-open' : 'is-closed' ?>"><?= e($entryStatusText) ?></span>
            </div>

            <?php if ($entryError !== ''): ?>
              <div class="alert alert-error"><?= e($entryError) ?></div>
            <?php endif; ?>

            <?php if ($expiresAtDisplay !== ''): ?>
              <p class="text-muted">Entries close on <?= e($expiresAtDisplay) ?>.</p>
            <?php endif; ?>

            <?php if (!$isActiveRaffle): ?>
              <p class="text-muted"><?= e($inactiveRaffleMessage) ?></p>
            <?php else: ?>
              <form method="POST" action="/raffle.php#raffle-entry-form">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="submit_raffle_entry">
                <div class="form-group">
                  <label class="form-label" for="raffle-entry-name">Name <span class="text-red">*</span></label>
                  <input id="raffle-entry-name" type="text" name="name" class="form-control" maxlength="100" required value="<?= e($entryValues['name']) ?>">
                </div>
                <?php if ($raffleSettings['collect_email']): ?>
                  <div class="form-group">
                    <label class="form-label" for="raffle-entry-email">Email address<?= $emailRequired ? ' (required) <span class="text-red" aria-hidden="true">*</span>' : '' ?></label>
                    <input id="raffle-entry-email" type="email" name="email" class="form-control" <?= $emailRequired ? 'required' : '' ?> value="<?= e($entryValues['email']) ?>">
                    <div class="form-hint"><?= $emailRequired ? 'A valid email address is required to enter this raffle.' : 'Optional, but helpful if you need to contact the winner directly.' ?></div>
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
            <h3 class="mb-2">What to Expect</h3>
            <ol class="raffle-steps">
              <li>Check this page to see whether a raffle is currently accepting entries.</li>
              <li>
                <?php if ($emailRequired): ?>
                  Enter the participant name and required email address while the raffle is open.
                <?php elseif ($raffleSettings['collect_email']): ?>
                  Enter the participant name and optional email address while the raffle is open.
                <?php else: ?>
                  Enter the participant name while the raffle is open.
                <?php endif; ?>
              </li>
              <li>Watch RedWater channels for winner announcements and future giveaways.</li>
            </ol>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
