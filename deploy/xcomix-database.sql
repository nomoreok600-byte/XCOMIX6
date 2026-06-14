-- XCOMIX one-shot database import (schema + social schema + demo seed).
-- In phpMyAdmin: select your DB, open the Import tab, choose this file, Go.

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
  -- Origin page URL on the source site (used by importers to re-sync).
  `source_url` VARCHAR(767)    NULL,
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mangas_slug` (`slug`),
  KEY `idx_mangas_source` (`source_url`),
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
  -- Source chapter page URL. The "chapter reader" fetches this to resolve pages.
  `source_url`     VARCHAR(767)    NULL,
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
--  Community wall — global forum-style posts + threaded replies
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `community_posts` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `parent_id`  BIGINT UNSIGNED NULL,
  `body`       TEXT NOT NULL,
  `image_url`  TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cposts_parent` (`parent_id`),
  KEY `idx_cposts_created` (`created_at`),
  CONSTRAINT `fk_cposts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `community_post_likes` (
  `post_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`post_id`, `user_id`),
  CONSTRAINT `fk_clike_post` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_clike_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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

-- =====================================================================
--  XCOMIX ENGINE — Demo Seed Data
--  Real manga metadata + real remote image source URLs.
--  Load AFTER schema.sql:  mysql xcomix < seed.sql
-- =====================================================================
SET NAMES utf8mb4;

-- Auto-generated demo seed harvested from live XCOMIX source API.
-- Real manga metadata + real remote image source URLs (served via /api/proxy/image).

INSERT INTO `mangas` (`id`,`slug`,`title`,`synopsis`,`cover_url`,`status`,`type`,`is_18_plus`) VALUES
(1, 'the-guy-she-was-interested-in-wasnt-a-guy-at-all-26626', 'The Guy She Was Interested in Wasn&#8217;t a Guy at All', 'A popular Twitter shorts series depicting the love story between a gyaru and her classmate who she mistakes as a guy outside of school, bonding over their mutual love for rock music.', 'https://mangakatana.com/imgs/cover/09c/22/c9e72.webp', 'Ongoing', 'Manga', 0),
(2, 'please-take-my-brother-away-20288', 'Please Take My Brother Away!', 'The conflicts and fights over daily trifles have never ceased between this pair of interesting siblings, the cool little sister &#8220;Secondra&#8221; and her funny elder brother &#8220;Minuto&#8221;, but as long as one of them is in need of help, the other will never stand aside and do nothing. What a weird match! Now let&#8217;s have a look at their friends. Joy Zhen, the old chum of Minuto, is a handsome boy with simple mind. Grace, the best friend of Secondra, is a warm-hearted girl who mistakenly regards Minuto as a bad boy. So here starts our story&#8230;', 'https://mangakatana.com/imgs/cover/09c/02/4e2bf.webp', 'Releasing', 'Manhwa', 0),
(3, 'i-want-to-teach-that-cheeky-asahi-chan-a-lesson-27603', 'I Want to Teach that Cheeky Asahi-chan a Lesson', 'Ryou is a third-year high school student who is enjoying his otaku life to the fullest, but one girl is giving him a headache. She&#8217;s Asahi-chan, a cheeky childhood friend two years younger than him who attends the same high school! Even though Asahi-chan is his junior, she always teases and teases Ryou! And yet, when someone teases her, she is at a loss for words and makes a defeated face. She is also a cute girl who has few friends, is shy, and not very honest. Today, I have to teach this cheeky Asahi-chan a lesson! A new type of teasing romantic comedy', 'https://mangakatana.com/imgs/cover/09c/27/3ed42.webp', 'Ongoing', 'Manga', 0),
(4, 'kyuuketsuki-sugu-shinu-19424', 'Kyuuketsuki Sugu Shinu', 'A vampire hunter learns of a mansion inhabited by a vampire who&#8217;s rumoured to have kidnapped children and goes there intending to take him down. But then it turns out that the vampire&#8217;s a wimp who keeps turning into ash at the smallest things&#8230; And that the kids aren&#8217;t being held captive, they&#8217;re just using the &#8220;haunted house&#8221; as their personal playground.', 'https://mangakatana.com/imgs/cover/04e/62/528d7.webp', 'Ongoing', 'Manga', 0);

INSERT INTO `chapters` (`id`,`manga_id`,`chapter_number`,`title`) VALUES
(1, 1, '0', 'Chapter 0: Rhythm A'),
(2, 1, '1', 'Chapter 1'),
(3, 1, '2', 'Chapter 2'),
(4, 2, '1', 'Chapter 1: Drinking Coke'),
(5, 2, '2', 'Chapter 2: Who told you you could have it all?!!'),
(6, 2, '3', 'Chapter 3: Mystery Cooking'),
(7, 3, '1', 'Chapter 1: Shall I become your Girlfriend for you?'),
(8, 3, '2', 'Chapter 2: Senpai, You Idiot'),
(9, 3, '3', 'Chapter 3: This Is Getting Interesting'),
(10, 4, '1', 'Chapter 1 : The hunter arrives, and he gets lost'),
(11, 4, '2', 'Chapter 2 : The idiot, the convenience store, and brutality'),
(12, 4, '3', 'Chapter 3: Attachment, Amoeba, Armadillo');

INSERT INTO `pages` (`id`,`chapter_id`,`page_number`,`remote_source_url`) VALUES
(1, 1, 1, 'https://i1.mangakatana.com/token/0d92b35340101237186pn%3At%3A465.207p21-6c3o0w%3Ar%3A2q7%3A9qpp2n6o8p0/0.jpg'),
(2, 1, 2, 'https://i1.mangakatana.com/token/cfcfd3c040101237186on%3At%3A465.230p21-6c3q0w%3Ar%3A2q7%3A9q9p296o961/1.jpg'),
(3, 1, 3, 'https://i1.mangakatana.com/token/dc12aa31401012371864n%3At%3A465.255p21-6c360w%3Ar%3A2q7%3A9q5p266op22/2.jpg'),
(4, 1, 4, 'https://i1.mangakatana.com/token/8002ecde40101237186pn%3At%3A465.2rqp21-6c390w%3Ar%3A2q7%3A9q2p256ops3/3.jpg'),
(5, 2, 1, 'https://i1.mangakatana.com/token/efac4fdc10106847181s8%3At%3A460.279p27-6c200w%3An%3A2ss%3A9q402r6s740/0.jpg'),
(6, 2, 2, 'https://i1.mangakatana.com/token/ae6bb4cd1010684718138%3At%3A460.2q2p27-6c2r0w%3An%3A2ss%3A9q302s6s2o1/1.jpg'),
(7, 2, 3, 'https://i1.mangakatana.com/token/15d5c4441010684718128%3At%3A460.22rp27-6c260w%3An%3A2ss%3A9qs0246s6q2/2.jpg'),
(8, 2, 4, 'https://i1.mangakatana.com/token/a09a3ed91010684718168%3At%3A460.2ssp27-6c240w%3An%3A2ss%3A9q40226sr63/3.jpg'),
(9, 3, 1, 'https://i1.mangakatana.com/token/83a66da30010244718q92%3At%3A06n.2o9p25-6c260w%3Ap%3A200%3A9qqo2s65080/0.jpg'),
(10, 3, 2, 'https://i1.mangakatana.com/token/1e4b94670010244718q32%3At%3A06n.201p25-6c240w%3Ap%3A200%3A9q9o2865201/1.jpg'),
(11, 3, 3, 'https://i1.mangakatana.com/token/3c1c94be0010244718qr2%3At%3A06n.27sp25-6c2p0w%3Ap%3A200%3A9q6o2365792/2.jpg'),
(12, 3, 4, 'https://i1.mangakatana.com/token/12675d7c0010244718q32%3At%3A06n.263p25-6c2s0w%3Ap%3A200%3A9qro2765n33/3.jpg'),
(13, 4, 1, 'https://i1.mangakatana.com/token/4b458ae6501092371803s%3At%3As85.261p24-2c130w%3Ao%3A0rn%3A9qpp8p0q5p0/0.jpg'),
(14, 4, 2, 'https://i1.mangakatana.com/token/4d696c0f50109237180os%3At%3As85.2o2p24-2c190w%3Ao%3A0rn%3A9q4p890q5p1/1.jpg'),
(15, 5, 1, 'https://i1.mangakatana.com/token/c16533a380100837189q5%3At%3An89.2n5p27-2c150w%3A6%3A039%3A9q6p850r600/0.jpg'),
(16, 5, 2, 'https://i1.mangakatana.com/token/dc3356fb80100837189o5%3At%3An89.247p27-2c170w%3A6%3A039%3A9qqp8p0ror1/1.jpg'),
(17, 6, 1, 'https://i1.mangakatana.com/token/a506d5f53010403718qq8%3At%3A982.292p29-2c170w%3A8%3A079%3A9q868q0r4p0/0.jpg'),
(18, 7, 1, 'https://i1.mangakatana.com/token/518420cd4010483718801%3At%3A339.70op22-6c370w%3A3%3A26n%3A9qo4097r140/0.jpg'),
(19, 7, 2, 'https://i1.mangakatana.com/token/e09361ff40104837188r1%3At%3A339.7q0p22-6c390w%3A3%3A26n%3A9q44057r431/1.jpg'),
(20, 7, 3, 'https://i1.mangakatana.com/token/b51008a84010483718881%3At%3A339.708p22-6c3n0w%3A3%3A26n%3A9q44087rnq2/2.jpg'),
(21, 7, 4, 'https://i1.mangakatana.com/token/28f7e8fc4010483718851%3At%3A339.73sp22-6c350w%3A3%3A26n%3A9q04007r3o3/3.jpg'),
(22, 7, 5, 'https://i1.mangakatana.com/token/28ba074f40104837188n1%3At%3A339.772p22-6c380w%3A3%3A26n%3A9q940o7r1q4/4.jpg'),
(23, 7, 6, 'https://i1.mangakatana.com/token/41f9c90740104837188p1%3At%3A339.702p22-6c300w%3A3%3A26n%3A9q74047rp55/5.jpg'),
(24, 7, 7, 'https://i1.mangakatana.com/token/7b86328140104837188s1%3At%3A339.706p22-6c350w%3A3%3A26n%3A9q64007rr96/6.jpg'),
(25, 7, 8, 'https://i1.mangakatana.com/token/126188c54010483718851%3At%3A339.7q8p22-6c360w%3A3%3A26n%3A9qo4007r907/7.jpg'),
(26, 8, 1, 'https://i1.mangakatana.com/token/759ea0c210106847180o3%3At%3A932.757p25-6c3s0w%3A7%3A2s7%3A9qp40p789r0/0.jpg'),
(27, 8, 2, 'https://i1.mangakatana.com/token/196a73ad10106847180q3%3At%3A932.768p25-6c3r0w%3A7%3A2s7%3A9q240o78961/1.jpg'),
(28, 8, 3, 'https://i1.mangakatana.com/token/5a9894181010684718053%3At%3A932.7onp25-6c310w%3A7%3A2s7%3A9q7401788n2/2.jpg'),
(29, 8, 4, 'https://i1.mangakatana.com/token/cf9e16de1010684718023%3At%3A932.76pp25-6c370w%3A7%3A2s7%3A9qp40n78ss3/3.jpg'),
(30, 8, 5, 'https://i1.mangakatana.com/token/71fb6bf11010684718033%3At%3A932.7rsp25-6c320w%3A7%3A2s7%3A9q340o781r4/4.jpg'),
(31, 8, 6, 'https://i1.mangakatana.com/token/9f2555da1010684718003%3At%3A932.79np25-6c300w%3A7%3A2s7%3A9qn40678qs5/5.jpg'),
(32, 8, 7, 'https://i1.mangakatana.com/token/4cdb81c010106847180s3%3At%3A932.78rp25-6c300w%3A7%3A2s7%3A9qr40478sp6/6.jpg'),
(33, 8, 8, 'https://i1.mangakatana.com/token/0671e5f010106847180n3%3At%3A932.7o0p25-6c330w%3A7%3A2s7%3A9qr40978rn7/7.jpg'),
(34, 9, 1, 'https://i1.mangakatana.com/token/355758541010684718849%3At%3A73q.7nnp25-6c390w%3A3%3A2qr%3A9qnr05783s0/0.jpg'),
(35, 9, 2, 'https://i1.mangakatana.com/token/24d5535b10106847188p9%3At%3A73q.74rp25-6c320w%3A3%3A2qr%3A9q2r00788n1/1.jpg'),
(36, 9, 3, 'https://i1.mangakatana.com/token/1a92899110106847188q9%3At%3A73q.740p25-6c360w%3A3%3A2qr%3A9qnr03787s2/2.jpg'),
(37, 9, 4, 'https://i1.mangakatana.com/token/e88c73391010684718869%3At%3A73q.7s3p25-6c3p0w%3A3%3A2qr%3A9q5r0n78403/3.jpg'),
(38, 9, 5, 'https://i1.mangakatana.com/token/66c973fe1010684718889%3At%3A73q.7n4p25-6c3o0w%3A3%3A2qr%3A9q1r0q78374/4.jpg'),
(39, 9, 6, 'https://i1.mangakatana.com/token/07b058201010684718809%3At%3A73q.7q6p25-6c3q0w%3A3%3A2qr%3A9qrr0278505/5.jpg'),
(40, 9, 7, 'https://i1.mangakatana.com/token/cb4dfc2e1010684718839%3At%3A73q.73qp25-6c3q0w%3A3%3A2qr%3A9qnr0478956/6.jpg'),
(41, 9, 8, 'https://i1.mangakatana.com/token/fbf59df410106847188o9%3At%3A73q.7r7p25-6c300w%3A3%3A2qr%3A9q8r0p78rs7/7.jpg'),
(42, 10, 1, 'https://i1.mangakatana.com/token/51c5275f1010324718735%3At%3A64p.215r14-4c170w%3Ar%3A6s3%3A4qr82q95n20/0.jpg'),
(43, 10, 2, 'https://i1.mangakatana.com/token/392f1ec31010324718745%3At%3A64p.216r14-4c160w%3Ar%3A6s3%3A4q282795no1/1.jpg'),
(44, 10, 3, 'https://i1.mangakatana.com/token/3597b04d1010324718705%3At%3A64p.2npr14-4c130w%3Ar%3A6s3%3A4q282995582/2.jpg'),
(45, 10, 4, 'https://i1.mangakatana.com/token/30121e091010324718795%3At%3A64p.2n5r14-4c130w%3Ar%3A6s3%3A4q482q951r3/3.jpg'),
(46, 10, 5, 'https://i1.mangakatana.com/token/449080311010324718745%3At%3A64p.28qr14-4c130w%3Ar%3A6s3%3A4qp82295204/4.jpg'),
(47, 10, 6, 'https://i1.mangakatana.com/token/514e091b1010324718755%3At%3A64p.296r14-4c170w%3Ar%3A6s3%3A4qp82695n35/5.jpg'),
(48, 10, 7, 'https://i1.mangakatana.com/token/c87e333210103247187o5%3At%3A64p.272r14-4c170w%3Ar%3A6s3%3A4qr829952o6/6.jpg'),
(49, 10, 8, 'https://i1.mangakatana.com/token/21bea24210103247187s5%3At%3A64p.2qpr14-4c170w%3Ar%3A6s3%3A4qn82s955p7/7.jpg'),
(50, 11, 1, 'https://i1.mangakatana.com/token/2c52fed48010083718rq6%3At%3Ar45.22rr1q-4c140w%3Ar%3A62q%3A4qrp2199p40/0.jpg'),
(51, 11, 2, 'https://i1.mangakatana.com/token/d02985338010083718rn6%3At%3Ar45.298r1q-4c110w%3Ar%3A62q%3A4q6p2599n41/1.jpg'),
(52, 11, 3, 'https://i1.mangakatana.com/token/95224d2a8010083718r86%3At%3Ar45.2s0r1q-4c120w%3Ar%3A62q%3A4qsp2n99332/2.jpg'),
(53, 11, 4, 'https://i1.mangakatana.com/token/28375ace8010083718r76%3At%3Ar45.25qr1q-4c1n0w%3Ar%3A62q%3A4qop2199673/3.jpg'),
(54, 11, 5, 'https://i1.mangakatana.com/token/27c598a78010083718rn6%3At%3Ar45.2n1r1q-4c160w%3Ar%3A62q%3A4qrp2r99904/4.jpg'),
(55, 11, 6, 'https://i1.mangakatana.com/token/15f6bbe88010083718rs6%3At%3Ar45.24qr1q-4c140w%3Ar%3A62q%3A4q7p22994q5/5.jpg'),
(56, 11, 7, 'https://i1.mangakatana.com/token/6a0442528010083718ro6%3At%3Ar45.206r1q-4c1o0w%3Ar%3A62q%3A4q3p2499766/6.jpg'),
(57, 11, 8, 'https://i1.mangakatana.com/token/519ae8688010083718rn6%3At%3Ar45.291r1q-4c180w%3Ar%3A62q%3A4qqp2599r57/7.jpg'),
(58, 12, 1, 'https://i1.mangakatana.com/token/6050a9758010803718prp%3At%3A848.2s5r1o-4c110w%3A6%3A6ro%3A4q512o9pr70/0.jpg'),
(59, 12, 2, 'https://i1.mangakatana.com/token/3a38ff568010803718ppp%3At%3A848.2s5r1o-4c1s0w%3A6%3A6ro%3A4q21279ps11/1.jpg'),
(60, 12, 3, 'https://i1.mangakatana.com/token/288e3b338010803718pop%3At%3A848.20pr1o-4c1n0w%3A6%3A6ro%3A4qp12p9p8p2/2.jpg'),
(61, 12, 4, 'https://i1.mangakatana.com/token/4e74b4498010803718prp%3At%3A848.2p7r1o-4c120w%3A6%3A6ro%3A4q112r9pq73/3.jpg'),
(62, 12, 5, 'https://i1.mangakatana.com/token/359d57f08010803718pnp%3At%3A848.283r1o-4c140w%3A6%3A6ro%3A4q31299p344/4.jpg'),
(63, 12, 6, 'https://i1.mangakatana.com/token/3a8f6cdb8010803718pnp%3At%3A848.27rr1o-4c1s0w%3A6%3A6ro%3A4qn1249pp65/5.jpg'),
(64, 12, 7, 'https://i1.mangakatana.com/token/dfd0e3278010803718p6p%3At%3A848.221r1o-4c100w%3A6%3A6ro%3A4qq1249p236/6.jpg'),
(65, 12, 8, 'https://i1.mangakatana.com/token/edfbfb0f8010803718p6p%3At%3A848.26or1o-4c120w%3A6%3A6ro%3A4q612n9prs7/7.jpg');
