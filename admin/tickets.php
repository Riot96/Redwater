<?php
/**
 * RedWater Entertainment - Admin: Tickets
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$manualEventErrors = [];
$embedCode = getSetting('tickets_embed_code', '');
$manualEventFormState = getTicketManualEvents();
if ($manualEventFormState === []) {
    $manualEventFormState[] = normalizeTicketManualEvent([]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $embedCode = postString('embed_code');
    $eventNames = postStringList('manual_event_name');
    $eventDescriptions = postStringList('manual_event_description');
    $eventDates = postStringList('manual_event_date');
    $eventTimes = postStringList('manual_event_time');
    $eventCosts = postStringList('manual_event_cost');
    $eventPhotos = postStringList('manual_event_photo_url');
    $eventBookings = postStringList('manual_event_booking_url');

    $manualEventFormState = [];
    $manualEventsToSave = [];
    $rowCount = max(
        count($eventNames),
        count($eventDescriptions),
        count($eventDates),
        count($eventTimes),
        count($eventCosts),
        count($eventPhotos),
        count($eventBookings)
    );

    for ($i = 0; $i < $rowCount; $i++) {
        $rawEvent = [
            'name' => trim($eventNames[$i] ?? ''),
            'description' => trim($eventDescriptions[$i] ?? ''),
            'date' => trim($eventDates[$i] ?? ''),
            'time' => trim($eventTimes[$i] ?? ''),
            'cost' => trim($eventCosts[$i] ?? ''),
            'photo_url' => trim($eventPhotos[$i] ?? ''),
            'booking_url' => trim($eventBookings[$i] ?? ''),
        ];

        if (
            $rawEvent['name'] === ''
            && $rawEvent['description'] === ''
            && $rawEvent['date'] === ''
            && $rawEvent['time'] === ''
            && $rawEvent['cost'] === ''
            && $rawEvent['photo_url'] === ''
            && $rawEvent['booking_url'] === ''
        ) {
            continue;
        }

        $manualEventFormState[] = $rawEvent;
        $eventNumber = count($manualEventFormState);
        $normalizedEvent = normalizeTicketManualEvent($rawEvent);
        $hasErrors = false;

        if ($rawEvent['name'] === '') {
            $manualEventErrors[] = 'Manual event #' . $eventNumber . ' needs a name.';
            $hasErrors = true;
        }
        if ($rawEvent['description'] === '') {
            $manualEventErrors[] = 'Manual event #' . $eventNumber . ' needs a description.';
            $hasErrors = true;
        }
        if ($rawEvent['date'] === '') {
            $manualEventErrors[] = 'Manual event #' . $eventNumber . ' needs a date.';
            $hasErrors = true;
        }
        if ($rawEvent['time'] === '') {
            $manualEventErrors[] = 'Manual event #' . $eventNumber . ' needs a time.';
            $hasErrors = true;
        }
        if ($rawEvent['cost'] === '') {
            $manualEventErrors[] = 'Manual event #' . $eventNumber . ' needs a cost.';
            $hasErrors = true;
        }
        if ($rawEvent['photo_url'] === '') {
            $manualEventErrors[] = 'Manual event #' . $eventNumber . ' needs a photo URL.';
            $hasErrors = true;
        } elseif ($normalizedEvent['photo_url'] === '') {
            $manualEventErrors[] = 'Manual event #' . $eventNumber . ' must use a valid HTTPS photo URL.';
            $hasErrors = true;
        }
        if ($rawEvent['booking_url'] !== '' && $normalizedEvent['booking_url'] === '') {
            $manualEventErrors[] = 'Manual event #' . $eventNumber . ' booking link must be a valid HTTPS URL.';
            $hasErrors = true;
        }

        if (!$hasErrors) {
            $manualEventsToSave[] = $normalizedEvent;
        }
    }

    if (empty($manualEventErrors)) {
        setSetting('tickets_embed_code', $embedCode);
        saveTicketManualEvents($manualEventsToSave);
        flashMessage('success', 'Ticket settings updated successfully.');
        redirect('/admin/tickets.php');
    }

    if ($manualEventFormState === []) {
        $manualEventFormState[] = normalizeTicketManualEvent([]);
    }
}

$pageTitle = 'Tickets Settings';
include __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <main class="admin-main">
    <h1 class="admin-page-title">Tickets <span>Settings</span></h1>

    <div class="card">
      <div class="card-body">
        <p class="text-muted" style="margin-bottom:1.5rem;">
          Update your HauntPay embed and add standalone ticket events that should appear on the public Tickets page.
        </p>

        <?php if ($manualEventErrors): ?>
          <div class="alert-inline alert-error">
            <?php foreach ($manualEventErrors as $error): ?>
              <div><?= e($error) ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="/admin/tickets.php">
          <?= csrfField() ?>

          <div class="form-group">
            <label class="form-label" for="embed_code">HauntPay Embed Code</label>
            <textarea id="embed_code" name="embed_code" class="form-control"
                      rows="10" placeholder="Paste your HauntPay embed code here (e.g., <iframe ...></iframe> or <script ...></script>)"
                      style="font-family: monospace; font-size:0.85rem;"><?= e($embedCode) ?></textarea>
            <div class="form-hint">This is typically an &lt;iframe&gt; or &lt;script&gt; tag provided by HauntPay.</div>
          </div>

          <div class="divider"></div>
          <div class="d-flex justify-between align-center mb-2" style="gap:1rem;flex-wrap:wrap;">
            <div>
              <h4 style="margin-bottom:0.35rem;">Manual Ticket Events</h4>
              <p class="text-muted">Use these cards for events that are not configured in HauntPay. Each event needs a photo, name, description, date, time, and cost. Booking links are optional.</p>
            </div>
            <button type="button" class="btn btn-outline btn-sm" id="addManualTicketEvent">+ Add Event</button>
          </div>

          <div id="manualTicketEvents" class="tickets-admin-event-list">
            <?php foreach ($manualEventFormState as $manualEvent): ?>
              <div class="tickets-admin-event card">
                <div class="card-body">
                  <div class="tickets-admin-event-header">
                    <h5>Manual Event</h5>
                    <button type="button" class="btn btn-danger btn-sm js-remove-ticket-event">Remove</button>
                  </div>

                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label">Event Name</label>
                      <input type="text" name="manual_event_name[]" class="form-control" value="<?= e($manualEvent['name']) ?>">
                    </div>
                    <div class="form-group">
                      <label class="form-label">Photo URL</label>
                      <input type="url" name="manual_event_photo_url[]" class="form-control" value="<?= e($manualEvent['photo_url']) ?>" placeholder="https://...">
                    </div>
                  </div>

                  <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="manual_event_description[]" class="form-control" rows="4"><?= e($manualEvent['description']) ?></textarea>
                  </div>

                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label">Date</label>
                      <input type="text" name="manual_event_date[]" class="form-control" value="<?= e($manualEvent['date']) ?>" placeholder="October 25, 2026">
                    </div>
                    <div class="form-group">
                      <label class="form-label">Time</label>
                      <input type="text" name="manual_event_time[]" class="form-control" value="<?= e($manualEvent['time']) ?>" placeholder="7:00 PM - 10:00 PM">
                    </div>
                  </div>

                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label">Cost</label>
                      <input type="text" name="manual_event_cost[]" class="form-control" value="<?= e($manualEvent['cost']) ?>" placeholder="$25 per person">
                    </div>
                    <div class="form-group">
                      <label class="form-label">Optional Booking Link</label>
                      <input type="url" name="manual_event_booking_url[]" class="form-control" value="<?= e($manualEvent['booking_url']) ?>" placeholder="https://...">
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="/tickets.php" class="btn btn-outline" target="_blank">Preview Page</a>
          </div>
        </form>

        <?php if (!empty($embedCode)): ?>
          <div class="divider"></div>
          <h4 class="mb-2">Current Embed Preview</h4>
          <div class="tickets-embed-wrap" style="min-height:200px;">
            <?= $embedCode ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const list = document.getElementById('manualTicketEvents');
  const addButton = document.getElementById('addManualTicketEvent');

  if (!list || !addButton) {
    return;
  }

  const bindRemoveButtons = () => {
    list.querySelectorAll('.js-remove-ticket-event').forEach((button) => {
      button.onclick = () => {
        const cards = list.querySelectorAll('.tickets-admin-event');
        if (cards.length === 1) {
          cards[0].querySelectorAll('input, textarea').forEach((field) => {
            field.value = '';
          });
          return;
        }

        button.closest('.tickets-admin-event')?.remove();
      };
    });
  };

  addButton.addEventListener('click', () => {
    const firstCard = list.querySelector('.tickets-admin-event');
    if (!firstCard) {
      return;
    }

    const clone = firstCard.cloneNode(true);
    clone.querySelectorAll('input, textarea').forEach((field) => {
      field.value = '';
    });
    list.appendChild(clone);
    bindRemoveButtons();
  });

  bindRemoveButtons();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
