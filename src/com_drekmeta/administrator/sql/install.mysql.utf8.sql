CREATE TABLE IF NOT EXISTS `#__drekmeta_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `menu_item_id` int NOT NULL DEFAULT 0,
  `title` varchar(255) NOT NULL DEFAULT '',
  `og_title` varchar(255) DEFAULT NULL,
  `og_description` text,
  `og_image` varchar(1024) DEFAULT NULL,
  `og_image_alt` varchar(255) DEFAULT NULL,
  `og_type` varchar(50) DEFAULT NULL,
  `og_site_name` varchar(255) DEFAULT NULL,
  `twitter_card` varchar(30) DEFAULT NULL,
  `theme_color` varchar(7) DEFAULT NULL,
  `custom_tags` mediumtext,
  `component_embed_enabled` tinyint NOT NULL DEFAULT 0,
  `component_embed` mediumtext,
  `state` tinyint NOT NULL DEFAULT 1,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  `checked_out` int unsigned,
  `checked_out_time` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_menu_item_id` (`menu_item_id`),
  KEY `idx_state` (`state`),
  KEY `idx_checkout` (`checked_out`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `#__drekmeta_items` (`menu_item_id`, `title`, `og_type`, `twitter_card`, `component_embed_enabled`, `state`, `created`, `modified`)
VALUES (0, 'Global defaults (all pages)', 'website', 'summary_large_image', 0, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);
