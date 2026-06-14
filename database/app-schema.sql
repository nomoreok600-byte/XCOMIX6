-- =====================================================================
--  XCOMIX ENGINE — Application / Social Schema (additive)
--  Load AFTER schema.sql:  mysql xcomix < database/app-schema.sql
--  Adds: extra manga/chapter columns, genres, users, library, history,
--  reviews, comments + reactions, notifications, messages, follows.
--  Idempotent: safe to re-run (uses IF NOT EXISTS everywhere).
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

-- ---------------------------------------------------------------------
--  Extra metadata columns on the existing catalog tables.
-- ---------------------------------------------------------------------
ALTER TABLE `mangas`
  ADD COLUMN IF NOT EXISTS `alt_title`  VARCHAR(512) NULL AFTER `title`,
  ADD COLUMN IF NOT EXISTS `author`     VARCHAR(255) NULL AFTER `synopsis`,
  ADD COLUMN IF NOT EXISTS `views`      BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `is_18_plus`,
  ADD COLUMN IF NOT EXISTS `source`     VARCHAR(40) NULL AFTER `source_url`,
  ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

ALTER TABLE `mangas`
  ADD KEY IF NOT EXISTS `idx_mangas_views` (`views`),
  ADD KEY IF NOT EXISTS `idx_mangas_updated` (`updated_at`);

ALTER TABLE `chapters`
  ADD COLUMN IF NOT EXISTS `views` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `title`;

-- ---------------------------------------------------------------------
--  Genres + manga<->genre join
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `genres` (
  `id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80)  NOT NULL,
  `slug` VARCHAR(80)  NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_genres_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `manga_genres` (
  `manga_id` BIGINT UNSIGNED NOT NULL,
  `genre_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`manga_id`, `genre_id`),
  KEY `idx_mg_genre` (`genre_id`),
  CONSTRAINT `fk_mg_manga` FOREIGN KEY (`manga_id`) REFERENCES `mangas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mg_genre` FOREIGN KEY (`genre_id`) REFERENCES `genres` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Users
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`        VARCHAR(60)  NOT NULL,
  `email`          VARCHAR(191) NOT NULL,
  `password_hash`   VARCHAR(255) NOT NULL,
  `role`            VARCHAR(20)  NOT NULL DEFAULT 'user',     -- user | admin
  `avatar_url`      TEXT NULL,
  `banner_url`      TEXT NULL,
  `bio`             VARCHAR(500) NULL,
  `theme`           VARCHAR(30)  NOT NULL DEFAULT 'neon',
  `profile_public`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Custom library folders (besides the built-in statuses)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `folders` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `name`       VARCHAR(80) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_folder_per_user` (`user_id`, `name`),
  CONSTRAINT `fk_folders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Library / bookmarks — one row per (user, manga) with a status + folder
--  status: reading | plan | completed | on_hold | dropped
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `library` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `manga_id`   BIGINT UNSIGNED NOT NULL,
  `status`     VARCHAR(20) NOT NULL DEFAULT 'plan',
  `folder_id`  BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_library_user_manga` (`user_id`, `manga_id`),
  KEY `idx_library_status` (`status`),
  KEY `idx_library_folder` (`folder_id`),
  CONSTRAINT `fk_library_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_library_manga` FOREIGN KEY (`manga_id`) REFERENCES `mangas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_library_folder` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Reading history — last chapter read per (user, manga)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reading_history` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `manga_id`    BIGINT UNSIGNED NOT NULL,
  `chapter_id`  BIGINT UNSIGNED NULL,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_history_user_manga` (`user_id`, `manga_id`),
  KEY `idx_history_user_time` (`user_id`, `updated_at`),
  CONSTRAINT `fk_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_history_manga` FOREIGN KEY (`manga_id`) REFERENCES `mangas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Reviews / star ratings — one per (user, manga)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reviews` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `manga_id`   BIGINT UNSIGNED NOT NULL,
  `rating`     TINYINT UNSIGNED NOT NULL,    -- 1..5
  `body`       TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_review_user_manga` (`user_id`, `manga_id`),
  KEY `idx_reviews_manga` (`manga_id`),
  CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_manga` FOREIGN KEY (`manga_id`) REFERENCES `mangas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Comments — on a manga and/or a chapter, threaded, spoiler + image
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comments` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `manga_id`   BIGINT UNSIGNED NULL,
  `chapter_id` BIGINT UNSIGNED NULL,
  `parent_id`  BIGINT UNSIGNED NULL,
  `body`       TEXT NOT NULL,
  `image_url`  TEXT NULL,
  `is_spoiler` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_comments_manga` (`manga_id`),
  KEY `idx_comments_chapter` (`chapter_id`),
  KEY `idx_comments_parent` (`parent_id`),
  CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `comment_reactions` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `comment_id` BIGINT UNSIGNED NOT NULL,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `emoji`      VARCHAR(16) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reaction` (`comment_id`, `user_id`, `emoji`),
  CONSTRAINT `fk_reaction_comment` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reaction_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Notifications — e.g. new chapter on a bookmarked manga
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `type`       VARCHAR(40) NOT NULL DEFAULT 'new_chapter',
  `manga_id`   BIGINT UNSIGNED NULL,
  `chapter_id` BIGINT UNSIGNED NULL,
  `message`    VARCHAR(255) NOT NULL,
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`, `is_read`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Direct messages between users
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `messages` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sender_id`    BIGINT UNSIGNED NOT NULL,
  `recipient_id` BIGINT UNSIGNED NOT NULL,
  `body`         TEXT NOT NULL,
  `is_read`      TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_msg_pair` (`sender_id`, `recipient_id`),
  KEY `idx_msg_recipient` (`recipient_id`, `is_read`),
  CONSTRAINT `fk_msg_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Follows — user social graph (powers community + leaderboard)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `follows` (
  `follower_id`  BIGINT UNSIGNED NOT NULL,
  `following_id` BIGINT UNSIGNED NOT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`follower_id`, `following_id`),
  KEY `idx_follows_following` (`following_id`),
  CONSTRAINT `fk_follow_follower` FOREIGN KEY (`follower_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_follow_following` FOREIGN KEY (`following_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
