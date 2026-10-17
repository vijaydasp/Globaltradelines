-- Global Trade Lines Laravel CMS. PHP 8.3+, MySQL 8+ / MariaDB 10.4+.
-- Import into an empty database. No production credentials are included.
SET NAMES utf8mb4;
CREATE TABLE `pages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `slug` VARCHAR(255) NOT NULL DEFAULT '',
  UNIQUE KEY `uq_pages_slug` (`slug`),
  `page_type` VARCHAR(255) NOT NULL DEFAULT 'content',
  `hero_heading` VARCHAR(255) NOT NULL DEFAULT '',
  `subtitle` MEDIUMTEXT NULL,
  `hero_image_id` BIGINT UNSIGNED NULL,
  INDEX (`hero_image_id`),
  `body` MEDIUMTEXT NULL,
  `seo_title` VARCHAR(255) NOT NULL DEFAULT '',
  `meta_description` MEDIUMTEXT NULL,
  `canonical_url` VARCHAR(2048) NOT NULL DEFAULT '',
  `og_title` VARCHAR(255) NOT NULL DEFAULT '',
  `og_description` MEDIUMTEXT NULL,
  `social_image_id` BIGINT UNSIGNED NULL,
  INDEX (`social_image_id`),
  `noindex` TINYINT(1) NOT NULL DEFAULT 0,
  `status` VARCHAR(255) NOT NULL DEFAULT 'draft',
  `publish_at` DATETIME NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL,
  INDEX `idx_pages_status` (`status`, `updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `page_sections` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `page_id` BIGINT UNSIGNED NULL,
  INDEX (`page_id`),
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `section_type` VARCHAR(255) NOT NULL DEFAULT 'rich_text',
  `eyebrow` VARCHAR(255) NOT NULL DEFAULT '',
  `heading` VARCHAR(255) NOT NULL DEFAULT '',
  `highlight` VARCHAR(255) NOT NULL DEFAULT '',
  `description` MEDIUMTEXT NULL,
  `body` MEDIUMTEXT NULL,
  `image_id` BIGINT UNSIGNED NULL,
  INDEX (`image_id`),
  `secondary_image_id` BIGINT UNSIGNED NULL,
  INDEX (`secondary_image_id`),
  `content_mode` VARCHAR(255) NOT NULL DEFAULT 'manual',
  `display_limit` INT NOT NULL DEFAULT 12,
  `slider_speed` INT NOT NULL DEFAULT 35,
  `autoplay` TINYINT(1) NOT NULL DEFAULT 1,
  `button_label` VARCHAR(255) NOT NULL DEFAULT '',
  `button_url` VARCHAR(2048) NOT NULL DEFAULT '',
  `secondary_label` VARCHAR(255) NOT NULL DEFAULT '',
  `secondary_url` VARCHAR(2048) NOT NULL DEFAULT '',
  `status` VARCHAR(255) NOT NULL DEFAULT 'draft',
  `publish_at` DATETIME NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL,
  INDEX `idx_page_sections_status` (`status`, `updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `section_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `section_id` BIGINT UNSIGNED NULL,
  INDEX (`section_id`),
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `description` MEDIUMTEXT NULL,
  `image_id` BIGINT UNSIGNED NULL,
  INDEX (`image_id`),
  `icon` VARCHAR(255) NOT NULL DEFAULT '',
  `brand_id` BIGINT UNSIGNED NULL,
  INDEX (`brand_id`),
  `category_id` BIGINT UNSIGNED NULL,
  INDEX (`category_id`),
  `stat_value` INT NOT NULL DEFAULT 0,
  `stat_source` VARCHAR(255) NOT NULL DEFAULT 'manual',
  `prefix` VARCHAR(255) NOT NULL DEFAULT '',
  `suffix` VARCHAR(255) NOT NULL DEFAULT '',
  `body` MEDIUMTEXT NULL,
  `button_label` VARCHAR(255) NOT NULL DEFAULT '',
  `button_url` VARCHAR(2048) NOT NULL DEFAULT '',
  `secondary_label` VARCHAR(255) NOT NULL DEFAULT '',
  `secondary_url` VARCHAR(2048) NOT NULL DEFAULT '',
  `status` VARCHAR(255) NOT NULL DEFAULT 'draft',
  `publish_at` DATETIME NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL,
  INDEX `idx_section_items_status` (`status`, `updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hero_slides` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `section_id` BIGINT UNSIGNED NULL,
  INDEX (`section_id`),
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `eyebrow` VARCHAR(255) NOT NULL DEFAULT '',
  `highlight` VARCHAR(255) NOT NULL DEFAULT '',
  `subtitle` VARCHAR(255) NOT NULL DEFAULT '',
  `description` MEDIUMTEXT NULL,
  `image_id` BIGINT UNSIGNED NULL,
  INDEX (`image_id`),
  `external_image_url` VARCHAR(2048) NOT NULL DEFAULT '',
  `video_id` BIGINT UNSIGNED NULL,
  INDEX (`video_id`),
  `video_url` VARCHAR(2048) NOT NULL DEFAULT '',
  `poster_id` BIGINT UNSIGNED NULL,
  INDEX (`poster_id`),
  `alignment` VARCHAR(255) NOT NULL DEFAULT 'left',
  `overlay_opacity` INT NOT NULL DEFAULT 0,
  `duration` INT NOT NULL DEFAULT 6,
  `button_label` VARCHAR(255) NOT NULL DEFAULT '',
  `button_url` VARCHAR(2048) NOT NULL DEFAULT '',
  `secondary_label` VARCHAR(255) NOT NULL DEFAULT '',
  `secondary_url` VARCHAR(2048) NOT NULL DEFAULT '',
  `status` VARCHAR(255) NOT NULL DEFAULT 'draft',
  `publish_at` DATETIME NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL,
  INDEX `idx_hero_slides_status` (`status`, `updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `navigation_menus` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `location` VARCHAR(255) NOT NULL DEFAULT 'header',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `navigation_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `menu_id` BIGINT UNSIGNED NULL,
  INDEX (`menu_id`),
  `parent_id` BIGINT UNSIGNED NULL,
  INDEX (`parent_id`),
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `menu_type` VARCHAR(255) NOT NULL DEFAULT 'link',
  `columns` INT NOT NULL DEFAULT 3,
  `display_style` VARCHAR(255) NOT NULL DEFAULT 'standard',
  `visibility` VARCHAR(255) NOT NULL DEFAULT 'all',
  `active_match` VARCHAR(255) NOT NULL DEFAULT 'exact',
  `link_type` VARCHAR(255) NOT NULL DEFAULT 'custom',
  `page_id` BIGINT UNSIGNED NULL,
  INDEX (`page_id`),
  `category_id` BIGINT UNSIGNED NULL,
  INDEX (`category_id`),
  `subcategory_id` BIGINT UNSIGNED NULL,
  INDEX (`subcategory_id`),
  `brand_id` BIGINT UNSIGNED NULL,
  INDEX (`brand_id`),
  `product_id` BIGINT UNSIGNED NULL,
  INDEX (`product_id`),
  `custom_url` VARCHAR(2048) NOT NULL DEFAULT '',
  `icon` VARCHAR(255) NOT NULL DEFAULT '',
  `target` VARCHAR(255) NOT NULL DEFAULT '_self',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mega_menu_groups` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `navigation_item_id` BIGINT UNSIGNED NULL,
  INDEX (`navigation_item_id`),
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `content_mode` VARCHAR(255) NOT NULL DEFAULT 'manual',
  `display_limit` INT NOT NULL DEFAULT 3,
  `image_id` BIGINT UNSIGNED NULL,
  INDEX (`image_id`),
  `icon` VARCHAR(255) NOT NULL DEFAULT '',
  `button_label` VARCHAR(255) NOT NULL DEFAULT '',
  `button_url` VARCHAR(2048) NOT NULL DEFAULT '',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mega_menu_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `group_id` BIGINT UNSIGNED NULL,
  INDEX (`group_id`),
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `description` MEDIUMTEXT NULL,
  `image_id` BIGINT UNSIGNED NULL,
  INDEX (`image_id`),
  `link_type` VARCHAR(255) NOT NULL DEFAULT 'custom',
  `page_id` BIGINT UNSIGNED NULL,
  INDEX (`page_id`),
  `category_id` BIGINT UNSIGNED NULL,
  INDEX (`category_id`),
  `subcategory_id` BIGINT UNSIGNED NULL,
  INDEX (`subcategory_id`),
  `brand_id` BIGINT UNSIGNED NULL,
  INDEX (`brand_id`),
  `product_id` BIGINT UNSIGNED NULL,
  INDEX (`product_id`),
  `custom_url` VARCHAR(2048) NOT NULL DEFAULT '',
  `icon` VARCHAR(255) NOT NULL DEFAULT '',
  `target` VARCHAR(255) NOT NULL DEFAULT '_self',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `brands` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `slug` VARCHAR(255) NOT NULL DEFAULT '',
  UNIQUE KEY `uq_brands_slug` (`slug`),
  `image_id` BIGINT UNSIGNED NULL,
  INDEX (`image_id`),
  `description` MEDIUMTEXT NULL,
  `website` VARCHAR(2048) NOT NULL DEFAULT '',
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `status` VARCHAR(255) NOT NULL DEFAULT 'draft',
  `publish_at` DATETIME NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL,
  INDEX `idx_brands_status` (`status`, `updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `categories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `slug` VARCHAR(255) NOT NULL DEFAULT '',
  UNIQUE KEY `uq_categories_slug` (`slug`),
  `image_id` BIGINT UNSIGNED NULL,
  INDEX (`image_id`),
  `description` MEDIUMTEXT NULL,
  `icon` VARCHAR(255) NOT NULL DEFAULT '',
  `status` VARCHAR(255) NOT NULL DEFAULT 'draft',
  `publish_at` DATETIME NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL,
  INDEX `idx_categories_status` (`status`, `updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `subcategories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `slug` VARCHAR(255) NOT NULL DEFAULT '',
  UNIQUE KEY `uq_subcategories_slug` (`slug`),
  `category_id` BIGINT UNSIGNED NULL,
  INDEX (`category_id`),
  `image_id` BIGINT UNSIGNED NULL,
  INDEX (`image_id`),
  `description` MEDIUMTEXT NULL,
  `icon` VARCHAR(255) NOT NULL DEFAULT '',
  `status` VARCHAR(255) NOT NULL DEFAULT 'draft',
  `publish_at` DATETIME NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL,
  INDEX `idx_subcategories_status` (`status`, `updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `products` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `slug` VARCHAR(255) NOT NULL DEFAULT '',
  UNIQUE KEY `uq_products_slug` (`slug`),
  `description` MEDIUMTEXT NULL,
  `brand_id` BIGINT UNSIGNED NULL,
  INDEX (`brand_id`),
  `category_id` BIGINT UNSIGNED NULL,
  INDEX (`category_id`),
  `subcategory_id` BIGINT UNSIGNED NULL,
  INDEX (`subcategory_id`),
  `image_id` BIGINT UNSIGNED NULL,
  INDEX (`image_id`),
  `badge` VARCHAR(255) NOT NULL DEFAULT '',
  `external_url` VARCHAR(2048) NOT NULL DEFAULT '',
  `button_label` VARCHAR(255) NOT NULL DEFAULT 'Learn More',
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `seo_title` VARCHAR(255) NOT NULL DEFAULT '',
  `meta_description` MEDIUMTEXT NULL,
  `canonical_url` VARCHAR(2048) NOT NULL DEFAULT '',
  `og_title` VARCHAR(255) NOT NULL DEFAULT '',
  `og_description` MEDIUMTEXT NULL,
  `social_image_id` BIGINT UNSIGNED NULL,
  INDEX (`social_image_id`),
  `noindex` TINYINT(1) NOT NULL DEFAULT 0,
  `status` VARCHAR(255) NOT NULL DEFAULT 'draft',
  `publish_at` DATETIME NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL,
  INDEX `idx_products_status` (`status`, `updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_images` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `product_id` BIGINT UNSIGNED NULL,
  INDEX (`product_id`),
  `image_id` BIGINT UNSIGNED NULL,
  INDEX (`image_id`),
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_specifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `product_id` BIGINT UNSIGNED NULL,
  INDEX (`product_id`),
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `value` VARCHAR(255) NOT NULL DEFAULT '',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `gallery_albums` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `slug` VARCHAR(255) NOT NULL DEFAULT '',
  UNIQUE KEY `uq_gallery_albums_slug` (`slug`),
  `description` MEDIUMTEXT NULL,
  `platform` VARCHAR(255) NOT NULL DEFAULT 'images',
  `profile_url` VARCHAR(2048) NOT NULL DEFAULT '',
  `status` VARCHAR(255) NOT NULL DEFAULT 'draft',
  `publish_at` DATETIME NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL,
  INDEX `idx_gallery_albums_status` (`status`, `updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `gallery_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `album_id` BIGINT UNSIGNED NULL,
  INDEX (`album_id`),
  `category_id` BIGINT UNSIGNED NULL,
  INDEX (`category_id`),
  `caption` MEDIUMTEXT NULL,
  `image_id` BIGINT UNSIGNED NULL,
  INDEX (`image_id`),
  `video_id` BIGINT UNSIGNED NULL,
  INDEX (`video_id`),
  `video_url` VARCHAR(2048) NOT NULL DEFAULT '',
  `permalink` VARCHAR(2048) NOT NULL DEFAULT '',
  `status` VARCHAR(255) NOT NULL DEFAULT 'draft',
  `publish_at` DATETIME NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL,
  INDEX `idx_gallery_items_status` (`status`, `updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `brochures` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `description` MEDIUMTEXT NULL,
  `image_id` BIGINT UNSIGNED NULL,
  INDEX (`image_id`),
  `file_id` BIGINT UNSIGNED NULL,
  INDEX (`file_id`),
  `brand_id` BIGINT UNSIGNED NULL,
  INDEX (`brand_id`),
  `category_id` BIGINT UNSIGNED NULL,
  INDEX (`category_id`),
  `status` VARCHAR(255) NOT NULL DEFAULT 'draft',
  `publish_at` DATETIME NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL,
  INDEX `idx_brochures_status` (`status`, `updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `career_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `department` VARCHAR(255) NOT NULL DEFAULT '',
  `location` VARCHAR(255) NOT NULL DEFAULT '',
  `employment_type` VARCHAR(255) NOT NULL DEFAULT '',
  `experience` VARCHAR(255) NOT NULL DEFAULT '',
  `description` MEDIUMTEXT NULL,
  `responsibilities` MEDIUMTEXT NULL,
  `qualifications` MEDIUMTEXT NULL,
  `apply_email` VARCHAR(255) NOT NULL DEFAULT '',
  `apply_url` VARCHAR(2048) NOT NULL DEFAULT '',
  `deadline` DATETIME NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'draft',
  `publish_at` DATETIME NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL,
  INDEX `idx_career_jobs_status` (`status`, `updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `enquiries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL DEFAULT '',
  `email` VARCHAR(255) NOT NULL DEFAULT '',
  `phone` VARCHAR(255) NOT NULL DEFAULT '',
  `company` VARCHAR(255) NOT NULL DEFAULT '',
  `requirement` VARCHAR(255) NOT NULL DEFAULT '',
  `message` MEDIUMTEXT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'new',
  `notification_status` VARCHAR(30) NOT NULL DEFAULT 'disabled',
  `ip_hash` CHAR(64) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL,
  INDEX `idx_enquiries_status` (`status`, `updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `enquiry_notes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `enquiry_id` BIGINT UNSIGNED NULL,
  INDEX (`enquiry_id`),
  `title` MEDIUMTEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `footer_columns` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `placement` VARCHAR(255) NOT NULL DEFAULT 'main',
  `content_mode` VARCHAR(255) NOT NULL DEFAULT 'manual',
  `display_limit` INT NOT NULL DEFAULT 12,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `footer_menu_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `column_id` BIGINT UNSIGNED NULL,
  INDEX (`column_id`),
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `link_type` VARCHAR(255) NOT NULL DEFAULT 'custom',
  `page_id` BIGINT UNSIGNED NULL,
  INDEX (`page_id`),
  `category_id` BIGINT UNSIGNED NULL,
  INDEX (`category_id`),
  `subcategory_id` BIGINT UNSIGNED NULL,
  INDEX (`subcategory_id`),
  `brand_id` BIGINT UNSIGNED NULL,
  INDEX (`brand_id`),
  `product_id` BIGINT UNSIGNED NULL,
  INDEX (`product_id`),
  `custom_url` VARCHAR(2048) NOT NULL DEFAULT '',
  `icon` VARCHAR(255) NOT NULL DEFAULT '',
  `target` VARCHAR(255) NOT NULL DEFAULT '_self',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `social_links` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `icon` VARCHAR(255) NOT NULL DEFAULT '',
  `custom_url` VARCHAR(2048) NOT NULL DEFAULT '',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `media_files` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `alt_text` VARCHAR(255) NOT NULL DEFAULT '',
  `path` VARCHAR(255) NOT NULL UNIQUE,
  `original_name` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `size_bytes` BIGINT UNSIGNED NOT NULL,
  `sha256` CHAR(64) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `roles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `is_super` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL DEFAULT '',
  `email` VARCHAR(255) NOT NULL DEFAULT '',
  UNIQUE KEY `uq_admin_users_email` (`email`),
  `role_id` BIGINT UNSIGNED NULL,
  INDEX (`role_id`),
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `password_hash` VARCHAR(255) NOT NULL,
  `auth_version` INT NOT NULL DEFAULT 1,
  `last_login_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_activity_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `actor_id` BIGINT UNSIGNED NULL,
  `action` VARCHAR(50) NOT NULL,
  `entity` VARCHAR(100) NOT NULL,
  `entity_id` BIGINT UNSIGNED NULL,
  `summary` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (permission_key VARCHAR(50) PRIMARY KEY, title VARCHAR(255) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE role_permissions (role_id BIGINT UNSIGNED NOT NULL, permission_key VARCHAR(50) NOT NULL, PRIMARY KEY(role_id,permission_key), FOREIGN KEY(role_id) REFERENCES roles(id) ON DELETE CASCADE, FOREIGN KEY(permission_key) REFERENCES permissions(permission_key) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE subcategory_brands (subcategory_id BIGINT UNSIGNED NOT NULL, brand_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY(subcategory_id,brand_id), FOREIGN KEY(subcategory_id) REFERENCES subcategories(id) ON DELETE CASCADE, FOREIGN KEY(brand_id) REFERENCES brands(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE site_settings (setting_key VARCHAR(100) PRIMARY KEY, value MEDIUMTEXT NOT NULL, group_name VARCHAR(50) NOT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE content_drafts (entity VARCHAR(80) NOT NULL, entity_id BIGINT UNSIGNED NOT NULL, payload MEDIUMTEXT NOT NULL, editor_id BIGINT UNSIGNED NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY(entity,entity_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE content_revisions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, entity VARCHAR(80) NOT NULL, entity_id BIGINT UNSIGNED NOT NULL, payload MEDIUMTEXT NOT NULL, editor_id BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(entity,entity_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE rate_limits (bucket CHAR(64) PRIMARY KEY, attempts INT NOT NULL DEFAULT 0, expires_at BIGINT NOT NULL, INDEX(expires_at)) ENGINE=InnoDB;
CREATE TABLE password_resets (token_hash CHAR(64) PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, expires_at DATETIME NOT NULL, FOREIGN KEY(user_id) REFERENCES admin_users(id) ON DELETE CASCADE) ENGINE=InnoDB;
CREATE TABLE installation (id INT PRIMARY KEY, version VARCHAR(50) NOT NULL, installed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;
ALTER TABLE `pages` ADD CONSTRAINT `fk_pages_hero_image_id` FOREIGN KEY (`hero_image_id`) REFERENCES `media_files` (`id`) ON DELETE SET NULL;
ALTER TABLE `pages` ADD CONSTRAINT `fk_pages_social_image_id` FOREIGN KEY (`social_image_id`) REFERENCES `media_files` (`id`) ON DELETE SET NULL;
ALTER TABLE `page_sections` ADD CONSTRAINT `fk_page_sections_page_id` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE RESTRICT;
ALTER TABLE `page_sections` ADD CONSTRAINT `fk_page_sections_image_id` FOREIGN KEY (`image_id`) REFERENCES `media_files` (`id`) ON DELETE SET NULL;
ALTER TABLE `page_sections` ADD CONSTRAINT `fk_page_sections_secondary_image_id` FOREIGN KEY (`secondary_image_id`) REFERENCES `media_files` (`id`) ON DELETE SET NULL;
ALTER TABLE `section_items` ADD CONSTRAINT `fk_section_items_section_id` FOREIGN KEY (`section_id`) REFERENCES `page_sections` (`id`) ON DELETE RESTRICT;
ALTER TABLE `section_items` ADD CONSTRAINT `fk_section_items_image_id` FOREIGN KEY (`image_id`) REFERENCES `media_files` (`id`) ON DELETE SET NULL;
ALTER TABLE `section_items` ADD CONSTRAINT `fk_section_items_brand_id` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL;
ALTER TABLE `section_items` ADD CONSTRAINT `fk_section_items_category_id` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;
ALTER TABLE `hero_slides` ADD CONSTRAINT `fk_hero_slides_section_id` FOREIGN KEY (`section_id`) REFERENCES `page_sections` (`id`) ON DELETE RESTRICT;
ALTER TABLE `hero_slides` ADD CONSTRAINT `fk_hero_slides_image_id` FOREIGN KEY (`image_id`) REFERENCES `media_files` (`id`) ON DELETE SET NULL;
ALTER TABLE `hero_slides` ADD CONSTRAINT `fk_hero_slides_video_id` FOREIGN KEY (`video_id`) REFERENCES `media_files` (`id`) ON DELETE SET NULL;
ALTER TABLE `hero_slides` ADD CONSTRAINT `fk_hero_slides_poster_id` FOREIGN KEY (`poster_id`) REFERENCES `media_files` (`id`) ON DELETE SET NULL;
ALTER TABLE `navigation_items` ADD CONSTRAINT `fk_navigation_items_menu_id` FOREIGN KEY (`menu_id`) REFERENCES `navigation_menus` (`id`) ON DELETE RESTRICT;
ALTER TABLE `navigation_items` ADD CONSTRAINT `fk_navigation_items_parent_id` FOREIGN KEY (`parent_id`) REFERENCES `navigation_items` (`id`) ON DELETE SET NULL;
ALTER TABLE `navigation_items` ADD CONSTRAINT `fk_navigation_items_page_id` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE SET NULL;
ALTER TABLE `navigation_items` ADD CONSTRAINT `fk_navigation_items_category_id` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;
ALTER TABLE `navigation_items` ADD CONSTRAINT `fk_navigation_items_subcategory_id` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories` (`id`) ON DELETE SET NULL;
ALTER TABLE `navigation_items` ADD CONSTRAINT `fk_navigation_items_brand_id` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL;
ALTER TABLE `navigation_items` ADD CONSTRAINT `fk_navigation_items_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;
ALTER TABLE `mega_menu_groups` ADD CONSTRAINT `fk_mega_menu_groups_navigation_item_id` FOREIGN KEY (`navigation_item_id`) REFERENCES `navigation_items` (`id`) ON DELETE RESTRICT;
ALTER TABLE `mega_menu_groups` ADD CONSTRAINT `fk_mega_menu_groups_image_id` FOREIGN KEY (`image_id`) REFERENCES `media_files` (`id`) ON DELETE SET NULL;
ALTER TABLE `mega_menu_items` ADD CONSTRAINT `fk_mega_menu_items_group_id` FOREIGN KEY (`group_id`) REFERENCES `mega_menu_groups` (`id`) ON DELETE RESTRICT;
ALTER TABLE `mega_menu_items` ADD CONSTRAINT `fk_mega_menu_items_image_id` FOREIGN KEY (`image_id`) REFERENCES `media_files` (`id`) ON DELETE SET NULL;
ALTER TABLE `mega_menu_items` ADD CONSTRAINT `fk_mega_menu_items_page_id` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE SET NULL;
ALTER TABLE `mega_menu_items` ADD CONSTRAINT `fk_mega_menu_items_category_id` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;
ALTER TABLE `mega_menu_items` ADD CONSTRAINT `fk_mega_menu_items_subcategory_id` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories` (`id`) ON DELETE SET NULL;
ALTER TABLE `mega_menu_items` ADD CONSTRAINT `fk_mega_menu_items_brand_id` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL;
ALTER TABLE `mega_menu_items` ADD CONSTRAINT `fk_mega_menu_items_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;
ALTER TABLE `brands` ADD CONSTRAINT `fk_brands_image_id` FOREIGN KEY (`image_id`) REFERENCES `media_files` (`id`) ON DELETE SET NULL;
ALTER TABLE `categories` ADD CONSTRAINT `fk_categories_image_id` FOREIGN KEY (`image_id`) REFERENCES `media_files` (`id`) ON DELETE SET NULL;
ALTER TABLE `subcategories` ADD CONSTRAINT `fk_subcategories_category_id` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT;
ALTER TABLE `subcategories` ADD CONSTRAINT `fk_subcategories_image_id` FOREIGN KEY (`image_id`) REFERENCES `media_files` (`id`) ON DELETE SET NULL;
ALTER TABLE `products` ADD CONSTRAINT `fk_products_brand_id` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL;
ALTER TABLE `products` ADD CONSTRAINT `fk_products_category_id` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;
ALTER TABLE `products` ADD CONSTRAINT `fk_products_subcategory_id` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories` (`id`) ON DELETE SET NULL;
ALTER TABLE `products` ADD CONSTRAINT `fk_products_image_id` FOREIGN KEY (`image_id`) REFERENCES `media_files` (`id`) ON DELETE SET NULL;
ALTER TABLE `products` ADD CONSTRAINT `fk_products_social_image_id` FOREIGN KEY (`social_image_id`) REFERENCES `media_files` (`id`) ON DELETE SET NULL;
ALTER TABLE `product_images` ADD CONSTRAINT `fk_product_images_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT;
ALTER TABLE `product_images` ADD CONSTRAINT `fk_product_images_image_id` FOREIGN KEY (`image_id`) REFERENCES `media_files` (`id`) ON DELETE RESTRICT;
ALTER TABLE `product_specifications` ADD CONSTRAINT `fk_product_specifications_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT;
ALTER TABLE `gallery_items` ADD CONSTRAINT `fk_gallery_items_album_id` FOREIGN KEY (`album_id`) REFERENCES `gallery_albums` (`id`) ON DELETE RESTRICT;
ALTER TABLE `gallery_items` ADD CONSTRAINT `fk_gallery_items_category_id` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;
ALTER TABLE `gallery_items` ADD CONSTRAINT `fk_gallery_items_image_id` FOREIGN KEY (`image_id`) REFERENCES `media_files` (`id`) ON DELETE SET NULL;
ALTER TABLE `gallery_items` ADD CONSTRAINT `fk_gallery_items_video_id` FOREIGN KEY (`video_id`) REFERENCES `media_files` (`id`) ON DELETE SET NULL;
ALTER TABLE `brochures` ADD CONSTRAINT `fk_brochures_image_id` FOREIGN KEY (`image_id`) REFERENCES `media_files` (`id`) ON DELETE SET NULL;
ALTER TABLE `brochures` ADD CONSTRAINT `fk_brochures_file_id` FOREIGN KEY (`file_id`) REFERENCES `media_files` (`id`) ON DELETE RESTRICT;
ALTER TABLE `brochures` ADD CONSTRAINT `fk_brochures_brand_id` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL;
ALTER TABLE `brochures` ADD CONSTRAINT `fk_brochures_category_id` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;
ALTER TABLE `enquiry_notes` ADD CONSTRAINT `fk_enquiry_notes_enquiry_id` FOREIGN KEY (`enquiry_id`) REFERENCES `enquiries` (`id`) ON DELETE RESTRICT;
ALTER TABLE `footer_menu_items` ADD CONSTRAINT `fk_footer_menu_items_column_id` FOREIGN KEY (`column_id`) REFERENCES `footer_columns` (`id`) ON DELETE RESTRICT;
ALTER TABLE `footer_menu_items` ADD CONSTRAINT `fk_footer_menu_items_page_id` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE SET NULL;
ALTER TABLE `footer_menu_items` ADD CONSTRAINT `fk_footer_menu_items_category_id` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;
ALTER TABLE `footer_menu_items` ADD CONSTRAINT `fk_footer_menu_items_subcategory_id` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories` (`id`) ON DELETE SET NULL;
ALTER TABLE `footer_menu_items` ADD CONSTRAINT `fk_footer_menu_items_brand_id` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL;
ALTER TABLE `footer_menu_items` ADD CONSTRAINT `fk_footer_menu_items_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;
ALTER TABLE `admin_users` ADD CONSTRAINT `fk_admin_users_role_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT;
