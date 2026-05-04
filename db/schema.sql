-- RedWater Entertainment Database Schema
-- Run this file to set up the database tables

SET FOREIGN_KEY_CHECKS = 0;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'member') NOT NULL DEFAULT 'member',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    bypass_approval TINYINT(1) NOT NULL DEFAULT 0,
    reset_token VARCHAR(64) NULL DEFAULT NULL,
    reset_token_expires DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Site settings (key-value store)
CREATE TABLE IF NOT EXISTS site_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value LONGTEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gallery items
CREATE TABLE IF NOT EXISTS gallery_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    type ENUM('photo', 'video') NOT NULL DEFAULT 'photo',
    file_path VARCHAR(500) NULL,
    video_url VARCHAR(500) NULL,
    link_url VARCHAR(500) NULL,
    source_type ENUM('upload', 'embed', 'link') NOT NULL DEFAULT 'upload',
    video_type ENUM('upload', 'embed') NOT NULL DEFAULT 'upload',
    title VARCHAR(255) NULL,
    description TEXT NULL,
    tags VARCHAR(500) NULL,
    alt_text VARCHAR(500) NULL,
    seo_title VARCHAR(255) NULL,
    seo_description TEXT NULL,
    is_approved TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sponsor tiers
CREATE TABLE IF NOT EXISTS sponsor_tiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    show_name TINYINT(1) NOT NULL DEFAULT 1,
    show_description TINYINT(1) NOT NULL DEFAULT 1,
    show_logo TINYINT(1) NOT NULL DEFAULT 1,
    show_link TINYINT(1) NOT NULL DEFAULT 1,
    cards_per_row INT NOT NULL DEFAULT 3,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sponsors
CREATE TABLE IF NOT EXISTS sponsors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tier_id INT NOT NULL,
    name VARCHAR(255) NULL,
    description TEXT NULL,
    logo_url VARCHAR(500) NULL,
    link_url VARCHAR(500) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tier_id) REFERENCES sponsor_tiers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Policies
CREATE TABLE IF NOT EXISTS policies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_html LONGTEXT NULL,
    image_path VARCHAR(500) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contact form submissions
CREATE TABLE IF NOT EXISTS contact_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone_number VARCHAR(50) NULL,
    preferred_contact_method ENUM('email', 'phone') NOT NULL DEFAULT 'email',
    location_address VARCHAR(255) NULL,
    subject VARCHAR(255) NULL,
    message TEXT NOT NULL,
    privacy_consent TINYINT(1) NOT NULL DEFAULT 0,
    converted_volunteer_id INT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volunteer submissions and profiles
CREATE TABLE IF NOT EXISTS volunteers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone_number VARCHAR(50) NULL,
    preferred_contact_method ENUM('email', 'phone') NOT NULL DEFAULT 'email',
    location_address VARCHAR(255) NULL,
    areas_of_interest TEXT NULL,
    availability TEXT NULL,
    message TEXT NULL,
    internal_notes TEXT NULL,
    privacy_consent TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('pending', 'active', 'inactive') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volunteer audit history
CREATE TABLE IF NOT EXISTS volunteer_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    volunteer_id INT NULL,
    volunteer_name VARCHAR(255) NOT NULL,
    actor_user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Default site settings
INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES
('site_name', 'RedWater Entertainment'),
('site_tagline', 'Where Fear Meets Wonder'),
('raffle_settings', '{"entry_form_enabled":false,"title":"RedWater Giveaway Entry","description":"","collect_email":false,"require_email":false,"opt_in_label":"I want to receive email updates about future promotions.","expires_at":""}'),
('raffle_entries', '[]'),
('tickets_embed_code', ''),
('contact_phone', ''),
('contact_email', ''),
('contact_address', ''),
('contact_map_embed', ''),
('home_hero_heading', 'Experience the Fear'),
('home_hero_subheading', 'RedWater Entertainment brings you unforgettable haunted experiences, educational events, and so much more.'),
('home_about_text', 'RedWater Entertainment is Highlands County&rsquo;s premier entertainment organization. We are best known for our spine-chilling &ldquo;Red Water Haunted Homestead&rdquo; each October, but we also offer educational events, workshops, and a variety of other live experiences throughout the year.'),
('merch_store_settings', '{"paypal_email":"","paypal_currency":"USD","paypal_use_sandbox":false,"shipping_notice":"","pickup_notice":""}'),
('merch_catalog', '[]'),
('social_facebook', ''),
('social_instagram', ''),
('social_twitter', ''),
('social_youtube', '');

-- Default policies record
INSERT IGNORE INTO policies (id, content_html, image_path) VALUES (1, '<p>Policies content coming soon. Please check back later.</p>', NULL);
