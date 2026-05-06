# RedWater Entertainment — Installation Guide

## Requirements

- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache web server with `mod_rewrite` enabled
- PHP extensions: `pdo`, `pdo_mysql`, `fileinfo`, `mbstring`
- Writable `uploads/` directory

---

## Step 1: Create the Database

Log into MySQL (or use phpMyAdmin) and create a database and user:

```sql
CREATE DATABASE redwater CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'redwater_user'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON redwater.* TO 'redwater_user'@'localhost';
FLUSH PRIVILEGES;
```

RedWater now creates and updates its own tables automatically the first time a
trusted backend entry point touches the database, so you do **not** need to
manually import `db/schema.sql` during normal setup. Keep `db/schema.sql` as a
reference snapshot or manual recovery fallback.

---

## Step 2: Configure the Application

1. **Copy and edit the config file:**

   ```bash
   cp includes/config.php includes/config.local.php
   ```

   Edit `includes/config.local.php` and update:
   - `DB_HOST` — usually `localhost`
   - `DB_NAME` — `redwater`
   - `DB_USER` — `redwater_user`
   - `DB_PASS` — your database password
   - `APP_KEY` — a random 32+ character string (used for setup page security)

   > ⚠️ **Never commit `config.local.php` to version control.** It's already in `.gitignore`.

   RedWater now also reads `SITE_URL`, `SITE_NAME`, `MAIL_FROM`, `MAIL_FROM_NAME`,
   `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_ENCRYPTION`,
   and `SMTP_TIMEOUT` from environment variables, so SMTP credentials do not need
   to live in version-controlled PHP files.

   **Generate a strong APP_KEY:**
   ```bash
   php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
   ```

---

## Step 3: Set File Permissions

The `uploads/` directory and subdirectories need to be writable by the web server:

```bash
chmod 755 uploads/
chmod 755 uploads/gallery/
chmod 755 uploads/policies/
chmod 755 uploads/sponsors/
chmod 755 uploads/temp/
```

Or on some hosts:
```bash
find uploads/ -type d -exec chmod 755 {} \;
```

---

## Step 4: Web Server Configuration

### Apache
Make sure `mod_rewrite` is enabled:
```bash
a2enmod rewrite
```

Ensure `AllowOverride All` is set in your VirtualHost configuration:
```apache
<Directory /var/www/html/redwater>
    AllowOverride All
    Options +SymLinksIfOwnerMatch
    Require all granted
</Directory>
```

`mod_rewrite` needs symlink support from either the vhost/server config or the root `.htaccess`. This project uses `Options -Indexes +SymLinksIfOwnerMatch` in the root `.htaccess` to avoid hosts that reject `FollowSymLinks` in per-directory overrides.

### Nginx (if applicable)
Add to your Nginx server block:
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

# Block PHP execution in uploads
location ~ /uploads/.*\.php {
    deny all;
}
```

---

## Step 5: Enable HTTPS

1. Obtain an SSL certificate (Let's Encrypt is free).
2. Once HTTPS is working, uncomment the HTTPS redirect lines in `.htaccess`.

---

## Step 6: Email Configuration

RedWater can send password reset emails and site notifications through Mailjet's
SMTP relay when the SMTP environment variables are configured. If SMTP settings
are omitted, the site falls back to PHP's built-in `mail()` function.

### 1. Create a Mailjet account

1. Sign up at [Mailjet](https://www.mailjet.com/).
2. In the Mailjet dashboard, create or locate your SMTP credentials.
   - `SMTP_USERNAME` = your Mailjet API key
   - `SMTP_PASSWORD` = your Mailjet secret key
3. Use Mailjet's SMTP relay hostname:
   - `SMTP_HOST=in-v3.mailjet.com`
   - `SMTP_PORT=587`
   - `SMTP_ENCRYPTION=tls`

### 2. Authenticate your sending domain

1. In Mailjet, add your domain (for example `redwaterhaunt.com`) as a sender
   domain.
2. Publish the SPF and DKIM DNS records Mailjet provides.
3. Wait for Mailjet to verify both records before sending production email.
4. Use a real mailbox on that domain for `MAIL_FROM`, such as
   `admin@redwaterhaunt.com`, so outgoing mail matches your branded domain.

### 3. Add the required environment variables

Use your host, web server, or PHP-FPM pool to define environment variables, or
copy `.env.example` as a reference when configuring them elsewhere:

```bash
SITE_URL=https://redwaterhaunt.com
SITE_NAME="RedWater Entertainment"
MAIL_FROM=admin@redwaterhaunt.com
MAIL_FROM_NAME="RedWater Entertainment"
SMTP_HOST=in-v3.mailjet.com
SMTP_PORT=587
SMTP_USERNAME=your-mailjet-api-key
SMTP_PASSWORD=your-mailjet-secret-key
SMTP_ENCRYPTION=tls
SMTP_TIMEOUT=15
```

If you prefer to keep non-secret mail settings in `includes/config.local.php`,
you can still do that while leaving the SMTP credentials in environment
variables.

### 4. Verify the setup

1. Request a password reset from `/forgot-password.php`.
2. Confirm the message arrives from the `MAIL_FROM` address you configured.
3. Submit the contact or volunteer form and confirm the admin notification is
   received.
4. If a message lands in spam, confirm your SPF/DKIM records are passing in
   Mailjet and that the envelope sender matches your authenticated domain.

### 5. Troubleshooting

- **Authentication errors**: re-check `SMTP_USERNAME` and `SMTP_PASSWORD`.
- **TLS/connectivity errors**: ensure outbound connections to `in-v3.mailjet.com`
  on port `587` are allowed by your host/firewall.
- **Mail not arriving**: verify Mailjet domain authentication, inspect Mailjet's
  message activity logs, and confirm the `MAIL_FROM` mailbox exists.
- **Fallback behavior**: if `SMTP_HOST` is blank, RedWater uses PHP `mail()`
  instead of Mailjet SMTP.

---

## Step 7: Create the First Admin Account

1. Navigate to `https://yourdomain.com/setup.php` in your browser.
2. Enter the `APP_KEY` from your config file.
3. Fill in the admin email, display name, and password.
4. Click **Create Admin Account**.
5. The setup page will automatically create/update the schema before it checks
   for existing admins.
6. **Delete `setup.php` immediately after use:**

   ```bash
   rm setup.php
   ```

---

## Automatic Database Migrations

Whenever `getDb()` opens a connection from a trusted backend context, RedWater
automatically:

- creates missing core tables with `CREATE TABLE IF NOT EXISTS`
- adds missing columns with guarded `ALTER TABLE ... ADD COLUMN`
- seeds default site settings idempotently and inserts or updates the default
  policies record with `INSERT ... ON DUPLICATE KEY UPDATE`

Trusted contexts are:

- authenticated admin sessions
- `setup.php`
- CLI/backend scripts
- custom trusted scripts that opt in by defining
  `REDWATER_ALLOW_DB_MIGRATIONS` before loading `includes/config.php`

Public/frontend visitors do not run migrations.

---

## Step 8: Upload Your Logo

Place your logo file at:
```
assets/images/logo.png
```

The site will display the logo in the navigation and footer automatically. If no logo is found, a text fallback is shown.

---

## Step 9: Configure Site Settings

Log in as admin and visit the **Admin Dashboard** to configure:

- **Contact Settings** — Phone, email, address, map embed, social media links, hero text, about text
- **Tickets** — Paste your HauntPay embed code and add manual ticket events
- **Policies** — Add your policy text and/or image
- **Sponsors** — Create tiers and add sponsors
- **Merch** — Configure PayPal-backed merch items with photos, per-product SEO fields, variants, shipping, local pickup, and cart checkout
- **Members** — Create member accounts for your volunteers/staff

When using the built-in merch checkout, manually verify each paid order against the merch catalog details (item id/options/fulfillment) before fulfillment. The current PayPal Standard flow does not lock pricing server-side, and it uses the store PayPal email instead of API keys.

For PayPal sandbox testing, use the email for a PayPal Developer sandbox business seller account in the merch settings and complete checkout with a separate sandbox personal buyer account. Using the seller login or a live PayPal account against sandbox checkout will trigger PayPal's generic "payment can't be completed" message.

---

## Directory Structure

```
/
├── index.php              Home page
├── tickets.php            Tickets page
├── policies.php           Policies page
├── gallery.php            Gallery page
├── sponsors.php           Sponsors page
├── merch.php              Merch storefront
├── merch-item.php         Individual merch product page
├── merch-cart.php         Merch cart & PayPal checkout
├── contact.php            Contact page
├── login.php              Login
├── forgot-password.php    Forgot password
├── reset-password.php     Password reset
├── logout.php             Logout
├── setup.php              Initial setup (delete after use!)
│
├── admin/                 Admin interface (admin users only)
│   ├── index.php          Dashboard
│   ├── profile.php        Admin profile
│   ├── tickets.php        Edit ticket embed and manual events
│   ├── policies.php       Edit policies
│   ├── gallery.php        Manage gallery & approvals
│   ├── sponsors.php       Manage sponsors & tiers
│   ├── merch.php          Manage merch catalog & PayPal settings
│   ├── contact.php        Contact settings & messages
│   └── members.php        Manage members
│
├── member/                Member interface (members only)
│   ├── index.php          Dashboard
│   ├── gallery.php        Upload & manage own gallery
│   └── profile.php        Update display name/password
│
├── includes/              Core PHP includes
│   ├── config.php         Database & app configuration
│   ├── config.local.php   Your LOCAL config (not tracked in git)
│   ├── auth.php           Authentication functions
│   ├── functions.php      Utility functions
│   ├── header.php         HTML header/nav
│   └── footer.php         HTML footer
│
├── assets/
│   ├── css/style.css      Main stylesheet
│   ├── js/main.js         JavaScript
│   └── images/            Static images (logo, etc.)
│
├── uploads/               User-uploaded files
│   ├── gallery/           Gallery photos & videos
│   ├── merch/             Merch product photos
│   ├── policies/          Policy images
│   └── sponsors/          Sponsor logos
│
└── db/
    └── schema.sql         Database schema
```

---

## Security Notes

- Change `APP_KEY` to a long random string.
- Use HTTPS in production.
- Delete `setup.php` after creating the first admin.
- Set strong database passwords.
- Keep PHP and MySQL updated.
- Regularly back up your database and uploads directory.
- Review `uploads/.htaccess` to ensure PHP execution is blocked in upload directories.

---

## Support

For questions or issues, contact the RedWater Entertainment development team.
