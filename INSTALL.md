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

Then import the schema:

```bash
mysql -u redwater_user -p redwater < db/schema.sql
```

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
   - `SITE_URL` — your full domain (e.g., `https://redwaterentertainment.com`)
   - `MAIL_FROM` — the email address for outgoing emails
   - `APP_KEY` — a random 32+ character string (used for setup page security)

   > ⚠️ **Never commit `config.local.php` to version control.** It's already in `.gitignore`.

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

The site uses PHP's built-in `mail()` function for password reset emails.

For reliable email delivery, consider configuring your server's MTA (Postfix, etc.) or use an SMTP service:

- **SendGrid**, **Mailgun**, **Amazon SES**, or **SMTP2GO** are recommended.
- Alternatively, install a library like PHPMailer and update `sendPasswordResetEmail()` in `includes/auth.php`.

---

## Step 7: Create the First Admin Account

1. Navigate to `https://yourdomain.com/setup.php` in your browser.
2. Enter the `APP_KEY` from your config file.
3. Fill in the admin email, display name, and password.
4. Click **Create Admin Account**.
5. **Delete `setup.php` immediately after use:**

   ```bash
   rm setup.php
   ```

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
- **Tickets** — Paste your HauntPay embed code
- **Policies** — Add your policy text and/or image
- **Sponsors** — Create tiers and add sponsors
- **Members** — Create member accounts for your volunteers/staff

---

## Directory Structure

```
/
├── index.php              Home page
├── tickets.php            Tickets page
├── policies.php           Policies page
├── gallery.php            Gallery page
├── sponsors.php           Sponsors page
├── merch.php              Merch page (placeholder)
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
│   ├── tickets.php        Edit HauntPay embed
│   ├── policies.php       Edit policies
│   ├── gallery.php        Manage gallery & approvals
│   ├── sponsors.php       Manage sponsors & tiers
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
