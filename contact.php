<?php
/**
 * RedWater Entertainment - Contact Page
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle    = 'Contact';
$seoDescription = 'Get in touch with RedWater Entertainment. We\'d love to hear from you!';

$errors  = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name))    $errors['name']    = 'Your name is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'A valid email address is required.';
    if (empty($message)) $errors['message'] = 'A message is required.';

    if (empty($errors)) {
        $db   = getDb();
        $stmt = $db->prepare('INSERT INTO contact_submissions (name, email, subject, message) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $email, $subject, $message]);

        // Try to send email notification
        $toEmail = getSetting('contact_email');
        if ($toEmail) {
            $from  = defined('MAIL_FROM') ? MAIL_FROM : 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'RedWater Entertainment';
            $mailSubject = 'New Contact Form Submission' . ($subject ? ': ' . $subject : '');
            $mailBody    = "New message from: {$name} <{$email}>\n\n";
            $mailBody   .= "Subject: {$subject}\n\n";
            $mailBody   .= "Message:\n{$message}\n";
            $headers     = "From: {$fromName} <{$from}>\r\nReply-To: {$name} <{$email}>";
            @mail($toEmail, $mailSubject, $mailBody, $headers);
        }

        $success = true;
    }
}

include __DIR__ . '/includes/header.php';

$phone   = getSetting('contact_phone');
$cEmail  = getSetting('contact_email');
$address = getSetting('contact_address');
$mapEmbed= getSetting('contact_map_embed');
?>

<main class="page-wrapper">
  <div class="page-header">
    <div class="container">
      <h1>Get in <span style="color:var(--blue)">Touch</span></h1>
      <p>Have a question, sponsorship inquiry, or just want to say hello? We'd love to hear from you.</p>
    </div>
  </div>

  <section class="section-sm">
    <div class="container">
      <?php if (isAdmin()): ?>
        <div class="mb-3 text-center">
          <a href="/admin/contact.php" class="btn btn-outline btn-sm">⚙️ Edit Contact Info</a>
        </div>
      <?php endif; ?>

      <div class="contact-grid">
        <!-- Contact Info -->
        <div>
          <h3 style="margin-bottom:1.5rem;">Contact Information</h3>

          <?php if ($phone): ?>
            <div class="contact-info-item">
              <div class="contact-info-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
              </div>
              <div>
                <div class="contact-info-label">Phone</div>
                <div class="contact-info-value"><a href="tel:<?= e(preg_replace('/\D/', '', $phone)) ?>"><?= e($phone) ?></a></div>
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

        <!-- Contact Form -->
        <div>
          <?php if ($success): ?>
            <div class="alert alert-inline alert-success">
              <strong>Message sent!</strong> Thank you for reaching out. We'll get back to you as soon as possible.
            </div>
          <?php else: ?>
            <h3 style="margin-bottom:1.5rem;">Send a Message</h3>
            <form method="POST" action="/contact.php">
              <?= csrfField() ?>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label" for="name">Your Name</label>
                  <input type="text" id="name" name="name" class="form-control" value="<?= e($_POST['name'] ?? '') ?>" required>
                  <?php if (isset($errors['name'])): ?><div class="form-error"><?= e($errors['name']) ?></div><?php endif; ?>
                </div>
                <div class="form-group">
                  <label class="form-label" for="email">Email Address</label>
                  <input type="email" id="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" required>
                  <?php if (isset($errors['email'])): ?><div class="form-error"><?= e($errors['email']) ?></div><?php endif; ?>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label" for="subject">Subject (Optional)</label>
                <input type="text" id="subject" name="subject" class="form-control" value="<?= e($_POST['subject'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label class="form-label" for="message">Message</label>
                <textarea id="message" name="message" class="form-control" rows="6" required><?= e($_POST['message'] ?? '') ?></textarea>
                <?php if (isset($errors['message'])): ?><div class="form-error"><?= e($errors['message']) ?></div><?php endif; ?>
              </div>
              <button type="submit" class="btn btn-primary">Send Message</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
