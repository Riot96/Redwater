<?php
/**
 * RedWater Entertainment - Contact & Volunteer Page
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Contact & Volunteer';
$seoDescription = 'Send an inquiry or sign up to volunteer with RedWater Entertainment.';

$errors = [];
$successMessage = '';
$activeForm = getString('form', 'inquiry') === 'volunteer' ? 'volunteer' : 'inquiry';

$inquiryValues = [
    'name' => '',
    'email' => '',
    'phone_number' => '',
    'preferred_contact_method' => 'email',
    'location_address' => '',
    'subject' => '',
    'message' => '',
    'privacy_consent' => false,
];

$volunteerValues = [
    'full_name' => '',
    'email' => '',
    'phone_number' => '',
    'preferred_contact_method' => 'email',
    'location_address' => '',
    'areas_of_interest' => '',
    'availability' => '',
    'message' => '',
    'privacy_consent' => false,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $activeForm = postString('form_type') === 'volunteer' ? 'volunteer' : 'inquiry';
    $honeypot = trim(postString('website'));

    if ($activeForm === 'volunteer') {
        $volunteerValues = [
            'full_name' => trim(postString('full_name')),
            'email' => trim(postString('email')),
            'phone_number' => trim(postString('phone_number')),
            'preferred_contact_method' => normalizePreferredContactMethod(postString('preferred_contact_method', 'email')),
            'location_address' => trim(postString('location_address')),
            'areas_of_interest' => trim(postString('areas_of_interest')),
            'availability' => trim(postString('availability')),
            'message' => trim(postString('message')),
            'privacy_consent' => postBool('privacy_consent'),
        ];

        if ($honeypot === '') {
            if ($volunteerValues['full_name'] === '') {
                $errors['full_name'] = 'Your full name is required.';
            }
            if ($volunteerValues['email'] === '' || !filter_var($volunteerValues['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'A valid email address is required.';
            }
            if ($volunteerValues['preferred_contact_method'] === 'phone' && $volunteerValues['phone_number'] === '') {
                $errors['phone_number'] = 'A phone number is required if phone is your preferred contact method.';
            }
            if ($volunteerValues['areas_of_interest'] === '') {
                $errors['areas_of_interest'] = 'Please share your areas of interest or skills.';
            }
            if ($volunteerValues['availability'] === '') {
                $errors['availability'] = 'Please share your availability.';
            }
            if (!$volunteerValues['privacy_consent']) {
                $errors['privacy_consent'] = 'Please confirm that we may store your details to follow up.';
            }

            if ($errors === []) {
                $db = getDb();
                $stmt = $db->prepare(
                    'INSERT INTO volunteers (
                        full_name, email, phone_number, preferred_contact_method, location_address,
                        areas_of_interest, availability, message, privacy_consent, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $volunteerValues['full_name'],
                    $volunteerValues['email'],
                    $volunteerValues['phone_number'] !== '' ? $volunteerValues['phone_number'] : null,
                    $volunteerValues['preferred_contact_method'],
                    $volunteerValues['location_address'] !== '' ? $volunteerValues['location_address'] : null,
                    $volunteerValues['areas_of_interest'],
                    $volunteerValues['availability'],
                    $volunteerValues['message'] !== '' ? $volunteerValues['message'] : null,
                    1,
                    'pending',
                ]);
                $volunteerId = (int)$db->lastInsertId();
                logVolunteerAudit($db, $volunteerId, $volunteerValues['full_name'], null, 'submitted', 'Volunteer sign-up received from website.');

                $adminEmail = getSetting('contact_email');
                if ($adminEmail !== '') {
                    $body = "New volunteer sign-up received.\n\n";
                    $body .= "Name: {$volunteerValues['full_name']}\n";
                    $body .= "Email: {$volunteerValues['email']}\n";
                    $body .= "Phone: " . ($volunteerValues['phone_number'] !== '' ? $volunteerValues['phone_number'] : '—') . "\n";
                    $body .= "Preferred Contact: " . ucfirst($volunteerValues['preferred_contact_method']) . "\n";
                    $body .= "Location: " . ($volunteerValues['location_address'] !== '' ? $volunteerValues['location_address'] : '—') . "\n";
                    $body .= "Areas of Interest / Skills:\n{$volunteerValues['areas_of_interest']}\n\n";
                    $body .= "Availability:\n{$volunteerValues['availability']}\n\n";
                    $body .= "Additional Notes:\n" . ($volunteerValues['message'] !== '' ? $volunteerValues['message'] : '—') . "\n";
                    sendSiteMail($adminEmail, 'New Volunteer Sign-Up', $body, $volunteerValues['email'], $volunteerValues['full_name']);
                }

                $successMessage = 'Volunteer sign-up received! Our team will follow up soon.';
                $volunteerValues = [
                    'full_name' => '',
                    'email' => '',
                    'phone_number' => '',
                    'preferred_contact_method' => 'email',
                    'location_address' => '',
                    'areas_of_interest' => '',
                    'availability' => '',
                    'message' => '',
                    'privacy_consent' => false,
                ];
            }
        } else {
            $successMessage = 'Volunteer sign-up received! Our team will follow up soon.';
        }
    } else {
        $inquiryValues = [
            'name' => trim(postString('name')),
            'email' => trim(postString('email')),
            'phone_number' => trim(postString('phone_number')),
            'preferred_contact_method' => normalizePreferredContactMethod(postString('preferred_contact_method', 'email')),
            'location_address' => trim(postString('location_address')),
            'subject' => trim(postString('subject')),
            'message' => trim(postString('message')),
            'privacy_consent' => postBool('privacy_consent'),
        ];

        if ($honeypot === '') {
            if ($inquiryValues['name'] === '') {
                $errors['name'] = 'Your full name is required.';
            }
            if ($inquiryValues['email'] === '' || !filter_var($inquiryValues['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'A valid email address is required.';
            }
            if ($inquiryValues['preferred_contact_method'] === 'phone' && $inquiryValues['phone_number'] === '') {
                $errors['phone_number'] = 'A phone number is required if phone is your preferred contact method.';
            }
            if ($inquiryValues['message'] === '') {
                $errors['message'] = 'A message is required.';
            }
            if (!$inquiryValues['privacy_consent']) {
                $errors['privacy_consent'] = 'Please confirm that we may store your details to respond.';
            }

            if ($errors === []) {
                $db = getDb();
                $stmt = $db->prepare(
                    'INSERT INTO contact_submissions (
                        name, email, phone_number, preferred_contact_method, location_address,
                        subject, message, privacy_consent
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $inquiryValues['name'],
                    $inquiryValues['email'],
                    $inquiryValues['phone_number'] !== '' ? $inquiryValues['phone_number'] : null,
                    $inquiryValues['preferred_contact_method'],
                    $inquiryValues['location_address'] !== '' ? $inquiryValues['location_address'] : null,
                    $inquiryValues['subject'] !== '' ? $inquiryValues['subject'] : null,
                    $inquiryValues['message'],
                    1,
                ]);

                $adminEmail = getSetting('contact_email');
                if ($adminEmail !== '') {
                    $body = "New inquiry received.\n\n";
                    $body .= "Name: {$inquiryValues['name']}\n";
                    $body .= "Email: {$inquiryValues['email']}\n";
                    $body .= "Phone: " . ($inquiryValues['phone_number'] !== '' ? $inquiryValues['phone_number'] : '—') . "\n";
                    $body .= "Preferred Contact: " . ucfirst($inquiryValues['preferred_contact_method']) . "\n";
                    $body .= "Location: " . ($inquiryValues['location_address'] !== '' ? $inquiryValues['location_address'] : '—') . "\n";
                    $body .= "Subject: " . ($inquiryValues['subject'] !== '' ? $inquiryValues['subject'] : '—') . "\n\n";
                    $body .= "Message:\n{$inquiryValues['message']}\n";
                    sendSiteMail($adminEmail, 'New Inquiry Submission', $body, $inquiryValues['email'], $inquiryValues['name']);
                }

                $successMessage = 'Message sent! Thank you for reaching out.';
                $inquiryValues = [
                    'name' => '',
                    'email' => '',
                    'phone_number' => '',
                    'preferred_contact_method' => 'email',
                    'location_address' => '',
                    'subject' => '',
                    'message' => '',
                    'privacy_consent' => false,
                ];
            }
        } else {
            $successMessage = 'Message sent! Thank you for reaching out.';
        }
    }
}

include __DIR__ . '/includes/header.php';

$phone = getSetting('contact_phone');
$cEmail = getSetting('contact_email');
$address = getSetting('contact_address');
$mapEmbed = getSetting('contact_map_embed');
$phoneHref = preg_replace('/\D/', '', $phone) ?? '';
?>

<main class="page-wrapper">
  <div class="page-header">
    <div class="container">
      <h1>Contact &amp; <span style="color:var(--blue)">Volunteer</span></h1>
      <p>Send a general inquiry or join our volunteer roster. We securely store submissions so our team can follow up quickly.</p>
    </div>
  </div>

  <section class="section-sm">
    <div class="container">
      <?php if (isAdmin()): ?>
        <div class="mb-3 text-center">
          <a href="/admin/contact.php" class="btn btn-outline btn-sm">⚙️ Manage Inquiries</a>
          <a href="/admin/volunteers.php" class="btn btn-outline btn-sm">🤝 Manage Volunteers</a>
        </div>
      <?php endif; ?>

      <div class="contact-grid">
        <div>
          <h3 style="margin-bottom:1.5rem;">Contact Information</h3>

          <?php if ($phone): ?>
            <div class="contact-info-item">
              <div class="contact-info-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
              </div>
              <div>
                <div class="contact-info-label">Phone</div>
                <div class="contact-info-value"><a href="tel:<?= e($phoneHref) ?>"><?= e($phone) ?></a></div>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($cEmail): ?>
            <div class="contact-info-item">
              <div class="contact-info-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              </div>
              <div>
                <div class="contact-info-label">Email</div>
                <div class="contact-info-value"><a href="mailto:<?= e($cEmail) ?>"><?= e($cEmail) ?></a></div>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($address): ?>
            <div class="contact-info-item">
              <div class="contact-info-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
              </div>
              <div>
                <div class="contact-info-label">Location</div>
                <div class="contact-info-value"><?= nl2br(e($address)) ?></div>
              </div>
            </div>
          <?php endif; ?>

          <?php if (!$phone && !$cEmail && !$address): ?>
            <p class="text-muted">Contact information coming soon.</p>
          <?php endif; ?>

          <div class="card mt-3">
            <div class="card-body">
              <h3 style="font-size:1rem;margin-bottom:0.75rem;">Privacy &amp; Response Notes</h3>
              <p class="text-muted">Only authorized admins can access volunteer and inquiry records. We use your details only to respond to your message or coordinate volunteer opportunities.</p>
              <p class="text-muted">To reduce spam, suspicious automated submissions are ignored.</p>
            </div>
          </div>

          <?php if ($mapEmbed): ?>
            <div class="map-embed">
              <?php
              // NOTE: $mapEmbed is admin-only content (typically a Google Maps <iframe>) stored
              // in site_settings and managed via the admin contact settings page.
              // Only trusted admin users can modify this embed code.
              ?>
              <?= $mapEmbed ?>
            </div>
          <?php endif; ?>
        </div>

        <div>
          <?php if ($successMessage !== ''): ?>
            <div class="alert alert-inline alert-success mb-3">
              <strong>Thank you!</strong> <?= e($successMessage) ?>
            </div>
          <?php endif; ?>

          <div class="submission-grid">
            <div class="card">
              <div class="card-body">
                <h3 style="margin-bottom:1rem;">General Inquiry</h3>
                <form method="POST" action="/contact.php?form=inquiry">
                  <?= csrfField() ?>
                  <input type="hidden" name="form_type" value="inquiry">
                  <div class="spam-trap" aria-hidden="true">
                    <label for="website-inquiry">Leave this field blank</label>
                    <input type="text" id="website-inquiry" name="website" tabindex="-1" autocomplete="off">
                  </div>
                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label" for="inquiry-name">Full Name</label>
                      <input type="text" id="inquiry-name" name="name" class="form-control" value="<?= e($inquiryValues['name']) ?>" required>
                      <?php if ($activeForm === 'inquiry' && isset($errors['name'])): ?><div class="form-error"><?= e($errors['name']) ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                      <label class="form-label" for="inquiry-email">Email Address</label>
                      <input type="email" id="inquiry-email" name="email" class="form-control" value="<?= e($inquiryValues['email']) ?>" required>
                      <?php if ($activeForm === 'inquiry' && isset($errors['email'])): ?><div class="form-error"><?= e($errors['email']) ?></div><?php endif; ?>
                    </div>
                  </div>
                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label" for="inquiry-phone">Phone Number</label>
                      <input type="text" id="inquiry-phone" name="phone_number" class="form-control" value="<?= e($inquiryValues['phone_number']) ?>">
                      <?php if ($activeForm === 'inquiry' && isset($errors['phone_number'])): ?><div class="form-error"><?= e($errors['phone_number']) ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                      <label class="form-label" for="inquiry-contact-method">Preferred Contact Method</label>
                      <select id="inquiry-contact-method" name="preferred_contact_method" class="form-control">
                        <option value="email" <?= $inquiryValues['preferred_contact_method'] === 'email' ? 'selected' : '' ?>>Email</option>
                        <option value="phone" <?= $inquiryValues['preferred_contact_method'] === 'phone' ? 'selected' : '' ?>>Phone</option>
                      </select>
                    </div>
                  </div>
                  <div class="form-group">
                    <label class="form-label" for="inquiry-location">Location / Address</label>
                    <input type="text" id="inquiry-location" name="location_address" class="form-control" value="<?= e($inquiryValues['location_address']) ?>">
                  </div>
                  <div class="form-group">
                    <label class="form-label" for="inquiry-subject">Subject</label>
                    <input type="text" id="inquiry-subject" name="subject" class="form-control" value="<?= e($inquiryValues['subject']) ?>">
                  </div>
                  <div class="form-group">
                    <label class="form-label" for="inquiry-message">Message</label>
                    <textarea id="inquiry-message" name="message" class="form-control" rows="6" required><?= e($inquiryValues['message']) ?></textarea>
                    <?php if ($activeForm === 'inquiry' && isset($errors['message'])): ?><div class="form-error"><?= e($errors['message']) ?></div><?php endif; ?>
                  </div>
                  <div class="form-group">
                    <label class="form-check">
                      <input type="checkbox" name="privacy_consent" value="1" <?= $inquiryValues['privacy_consent'] ? 'checked' : '' ?>>
                      I consent to RedWater storing this information so staff can respond to my inquiry.
                    </label>
                    <?php if ($activeForm === 'inquiry' && isset($errors['privacy_consent'])): ?><div class="form-error"><?= e($errors['privacy_consent']) ?></div><?php endif; ?>
                  </div>
                  <button type="submit" class="btn btn-primary">Send Message</button>
                </form>
              </div>
            </div>

            <div class="card">
              <div class="card-body">
                <h3 style="margin-bottom:1rem;">Volunteer Sign-Up</h3>
                <form method="POST" action="/contact.php?form=volunteer">
                  <?= csrfField() ?>
                  <input type="hidden" name="form_type" value="volunteer">
                  <div class="spam-trap" aria-hidden="true">
                    <label for="website-volunteer">Leave this field blank</label>
                    <input type="text" id="website-volunteer" name="website" tabindex="-1" autocomplete="off">
                  </div>
                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label" for="volunteer-full-name">Full Name</label>
                      <input type="text" id="volunteer-full-name" name="full_name" class="form-control" value="<?= e($volunteerValues['full_name']) ?>" required>
                      <?php if ($activeForm === 'volunteer' && isset($errors['full_name'])): ?><div class="form-error"><?= e($errors['full_name']) ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                      <label class="form-label" for="volunteer-email">Email Address</label>
                      <input type="email" id="volunteer-email" name="email" class="form-control" value="<?= e($volunteerValues['email']) ?>" required>
                      <?php if ($activeForm === 'volunteer' && isset($errors['email'])): ?><div class="form-error"><?= e($errors['email']) ?></div><?php endif; ?>
                    </div>
                  </div>
                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label" for="volunteer-phone">Phone Number</label>
                      <input type="text" id="volunteer-phone" name="phone_number" class="form-control" value="<?= e($volunteerValues['phone_number']) ?>">
                      <?php if ($activeForm === 'volunteer' && isset($errors['phone_number'])): ?><div class="form-error"><?= e($errors['phone_number']) ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                      <label class="form-label" for="volunteer-contact-method">Preferred Contact Method</label>
                      <select id="volunteer-contact-method" name="preferred_contact_method" class="form-control">
                        <option value="email" <?= $volunteerValues['preferred_contact_method'] === 'email' ? 'selected' : '' ?>>Email</option>
                        <option value="phone" <?= $volunteerValues['preferred_contact_method'] === 'phone' ? 'selected' : '' ?>>Phone</option>
                      </select>
                    </div>
                  </div>
                  <div class="form-group">
                    <label class="form-label" for="volunteer-location">Location / Address</label>
                    <input type="text" id="volunteer-location" name="location_address" class="form-control" value="<?= e($volunteerValues['location_address']) ?>">
                  </div>
                  <div class="form-group">
                    <label class="form-label" for="volunteer-interests">Areas of Interest or Skills</label>
                    <textarea id="volunteer-interests" name="areas_of_interest" class="form-control" rows="4" required><?= e($volunteerValues['areas_of_interest']) ?></textarea>
                    <?php if ($activeForm === 'volunteer' && isset($errors['areas_of_interest'])): ?><div class="form-error"><?= e($errors['areas_of_interest']) ?></div><?php endif; ?>
                  </div>
                  <div class="form-group">
                    <label class="form-label" for="volunteer-availability">Availability</label>
                    <textarea id="volunteer-availability" name="availability" class="form-control" rows="3" required><?= e($volunteerValues['availability']) ?></textarea>
                    <?php if ($activeForm === 'volunteer' && isset($errors['availability'])): ?><div class="form-error"><?= e($errors['availability']) ?></div><?php endif; ?>
                  </div>
                  <div class="form-group">
                    <label class="form-label" for="volunteer-message">Additional Notes</label>
                    <textarea id="volunteer-message" name="message" class="form-control" rows="4"><?= e($volunteerValues['message']) ?></textarea>
                  </div>
                  <div class="form-group">
                    <label class="form-check">
                      <input type="checkbox" name="privacy_consent" value="1" <?= $volunteerValues['privacy_consent'] ? 'checked' : '' ?>>
                      I consent to RedWater storing this information to coordinate volunteer opportunities with me.
                    </label>
                    <?php if ($activeForm === 'volunteer' && isset($errors['privacy_consent'])): ?><div class="form-error"><?= e($errors['privacy_consent']) ?></div><?php endif; ?>
                  </div>
                  <button type="submit" class="btn btn-primary">Sign Up to Volunteer</button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
