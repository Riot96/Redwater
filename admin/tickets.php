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
$storedManualEvents = getTicketManualEvents();
$manualEventFormState = $storedManualEvents;
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
    $existingEventPhotos = postStringList('manual_event_existing_photo_url');
    $uploadedTicketImages = [];

    $manualEventFormState = [];
    $manualEventsToSave = [];
    $rowCount = max(
        count($eventNames),
        count($eventDescriptions),
        count($eventDates),
        count($eventTimes),
        count($eventCosts),
        count($eventPhotos),
        count($eventBookings),
        count($existingEventPhotos)
    );

    for ($i = 0; $i < $rowCount; $i++) {
        $existingPhotoPath = trim($existingEventPhotos[$i] ?? '');
        $imageUpload = uploadedFileAtIndex('manual_event_photo_upload', $i);
        $hasUpload = hasUploadedFile($imageUpload);
        $eventPhotoUrl = trim($eventPhotos[$i] ?? '');
        $finalPhotoPath = $existingPhotoPath;
        $formPhotoPath = $existingPhotoPath;
        $uploadedPhotoPath = '';
        $eventUploadError = '';

        if ($imageUpload !== null && $hasUpload) {
            $upload = handleFileUpload(
                $imageUpload,
                __DIR__ . '/../uploads/tickets',
                ALLOWED_IMAGE_TYPES
            );
            if (!$upload['success']) {
                $eventUploadError = $upload['error'];
            } else {
                $finalPhotoPath = '/uploads/tickets/' . $upload['filename'];
                $uploadedPhotoPath = $finalPhotoPath;
                $uploadedTicketImages[] = $uploadedPhotoPath;
            }
        }

        if (!$hasUpload && $eventPhotoUrl !== '') {
            $finalPhotoPath = $eventPhotoUrl;
        }

        $rawEvent = [
            'name' => trim($eventNames[$i] ?? ''),
            'description' => trim($eventDescriptions[$i] ?? ''),
            'date' => trim($eventDates[$i] ?? ''),
            'time' => trim($eventTimes[$i] ?? ''),
            'cost' => trim($eventCosts[$i] ?? ''),
            'photo_url' => $finalPhotoPath,
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

        $eventNumber = count($manualEventFormState) + 1;
        $normalizedEvent = normalizeTicketManualEvent($rawEvent);
        $hasErrors = false;

        if ($eventUploadError !== '') {
            $manualEventErrors[] = 'Manual event #' . $eventNumber . ' image upload failed: ' . $eventUploadError;
            $hasErrors = true;
        }

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
            $manualEventErrors[] = 'Manual event #' . $eventNumber . ' needs a photo URL or uploaded image.';
            $hasErrors = true;
        } elseif ($normalizedEvent['photo_url'] === '') {
            $manualEventErrors[] = 'Manual event #' . $eventNumber . ' must use a valid HTTPS photo URL or a valid uploaded image.';
            $hasErrors = true;
        }
        if ($rawEvent['booking_url'] !== '' && $normalizedEvent['booking_url'] === '') {
            $manualEventErrors[] = 'Manual event #' . $eventNumber . ' booking link must be a valid HTTPS URL.';
            $hasErrors = true;
        }

        if ($hasErrors && $uploadedPhotoPath !== '') {
            if ($eventPhotoUrl !== '') {
                $formPhotoPath = $eventPhotoUrl;
            }
            $rawEvent['photo_url'] = $formPhotoPath;
        }

        $manualEventFormState[] = $rawEvent;

        if (!$hasErrors) {
            $manualEventsToSave[] = $normalizedEvent;
        }
    }

    if (empty($manualEventErrors)) {
        setSetting('tickets_embed_code', $embedCode);
        saveTicketManualEvents($manualEventsToSave);

        $savedManagedPaths = [];
        foreach ($manualEventsToSave as $event) {
            if (isManagedTicketManualEventImagePath($event['photo_url'])) {
                $savedManagedPaths[] = $event['photo_url'];
            }
        }
        foreach ($storedManualEvents as $event) {
            $storedPhotoPath = $event['photo_url'];
            if (
                isManagedTicketManualEventImagePath($storedPhotoPath)
                && !in_array($storedPhotoPath, $savedManagedPaths, true)
            ) {
                deleteManagedTicketManualEventImage($storedPhotoPath);
            }
        }

        flashMessage('success', 'Ticket settings updated successfully.');
        redirect('/admin/tickets.php');
    }

    foreach ($uploadedTicketImages as $uploadedTicketImage) {
        deleteManagedTicketManualEventImage($uploadedTicketImage);
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

        <form method="POST" action="/admin/tickets.php" enctype="multipart/form-data">
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
                      <input type="url" name="manual_event_photo_url[]" class="form-control" value="<?= e(isManagedTicketManualEventImagePath($manualEvent['photo_url']) ? '' : $manualEvent['photo_url']) ?>" placeholder="https://...">
                      <input type="hidden" name="manual_event_existing_photo_url[]" value="<?= e($manualEvent['photo_url']) ?>">
                      <div class="form-hint">Use an HTTPS image URL or upload a local image below.</div>
                    </div>
                  </div>

                  <div class="form-group">
                    <label class="form-label">Photo Upload</label>
                    <input type="file" name="manual_event_photo_upload[]" class="form-control" accept="image/*">
                    <div class="form-hint">Upload a JPG, PNG, GIF, or WebP image. If you upload a file, it will be used instead of the photo URL.</div>
                  </div>

                  <?php if ($manualEvent['photo_url'] !== ''): ?>
                    <div class="merch-admin-image-preview mb-2 js-ticket-image-preview">
                      <img src="<?= e($manualEvent['photo_url']) ?>" alt="<?= e($manualEvent['name'] !== '' ? $manualEvent['name'] : 'Ticket event preview') ?>" class="merch-admin-thumb-lg">
                    </div>
                  <?php endif; ?>

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
          resetTicketEventCard(cards[0]);
          return;
        }

        button.closest('.tickets-admin-event')?.remove();
      };
    });
  };

  const resetTicketEventCard = (card) => {
    card.querySelectorAll('input, textarea').forEach((field) => {
      field.value = '';
    });
    card.querySelectorAll('.js-ticket-image-preview').forEach((preview) => {
      preview.remove();
    });
  };

  addButton.addEventListener('click', () => {
    const firstCard = list.querySelector('.tickets-admin-event');
    if (!firstCard) {
      return;
    }

    const clone = firstCard.cloneNode(true);
    resetTicketEventCard(clone);
    list.appendChild(clone);
    bindRemoveButtons();
  });

  bindRemoveButtons();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
