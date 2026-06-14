-- =====================================================================
--  XCOMIX ENGINE — MySQL Blueprint Schema
--  Run directly inside phpMyAdmin (or `mysql < schema.sql`).
--  Decoupled backend lives at www.a3555bet.com; this DB feeds it.
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

-- ---------------------------------------------------------------------
--  mangas — top level series catalog
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mangas` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`       VARCHAR(191)    NOT NULL,
  `title`      VARCHAR(512)    NOT NULL,
  `synopsis`   MEDIUMTEXT      NULL,
  `cover_url`  TEXT            NULL,
  `status`     VARCHAR(40)     NOT NULL DEFAULT 'Ongoing',
  `type`       VARCHAR(40)     NOT NULL DEFAULT 'Manga',
  `is_18_plus` TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mangas_slug` (`slug`),
  KEY `idx_mangas_status` (`status`),
  KEY `idx_mangas_type` (`type`),
  KEY `idx_mangas_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  chapters — ordered chapters per manga (no duplicates per series)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chapters` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `manga_id`       BIGINT UNSIGNED NOT NULL,
  `chapter_number` VARCHAR(40)     NOT NULL,
  `title`          VARCHAR(512)    NULL,
  `created_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chapter_per_manga` (`manga_id`, `chapter_number`),
  KEY `idx_chapters_manga` (`manga_id`),
  CONSTRAINT `fk_chapters_manga`
    FOREIGN KEY (`manga_id`) REFERENCES `mangas` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  pages — externally hosted image targets (never stored locally).
--  `remote_source_url` is what the /api/proxy/image wrapper streams.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pages` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `chapter_id`        BIGINT UNSIGNED NOT NULL,
  `page_number`       INT UNSIGNED    NOT NULL,
  `remote_source_url` TEXT            NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_page_per_chapter` (`chapter_id`, `page_number`),
  KEY `idx_pages_chapter` (`chapter_id`),
  CONSTRAINT `fk_pages_chapter`
    FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
