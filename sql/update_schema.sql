-- ============================================================
--  WiiEons — Migration VP + Tables boutique
--  À exécuter dans la base : eons_auth
--  Compatible MySQL 8.4 / MariaDB (XAMPP 7.4.15)
-- ============================================================

USE `eons_auth`;

-- ── 1. Colonne VP dans account ────────────────────────────────
ALTER TABLE `account`
  ADD COLUMN IF NOT EXISTS `vp` INT UNSIGNED NOT NULL DEFAULT 0
  AFTER `dp`;

-- ── 2. Colonne currency dans dp_shop_log ─────────────────────
--  (si la table existait déjà sans cette colonne)
ALTER TABLE `dp_shop_log`
  ADD COLUMN IF NOT EXISTS `currency` ENUM('dp','vp') NOT NULL DEFAULT 'dp'
  AFTER `cost`;

-- ── 3. Colonne currency dans dp_shop_queue ───────────────────
ALTER TABLE `dp_shop_queue`
  ADD COLUMN IF NOT EXISTS `currency` ENUM('dp','vp') NOT NULL DEFAULT 'dp'
  AFTER `quantity`;

-- ── 4. Table dp_shop_log (création si absente) ───────────────
CREATE TABLE IF NOT EXISTS `dp_shop_log` (
  `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `account_id`   INT UNSIGNED     NOT NULL,
  `char_name`    VARCHAR(64)      NOT NULL DEFAULT '',
  `item_id`      VARCHAR(64)      NOT NULL,
  `item_name`    VARCHAR(128)     NOT NULL,
  `game_item_id` INT UNSIGNED     NOT NULL DEFAULT 0,
  `cost`         INT UNSIGNED     NOT NULL,
  `currency`     ENUM('dp','vp')  NOT NULL DEFAULT 'dp',
  `soap_status`  ENUM('ok','failed','service','na') NOT NULL DEFAULT 'ok',
  `soap_error`   TEXT             NULL,
  `created_at`   DATETIME         NOT NULL DEFAULT NOW(),
  PRIMARY KEY (`id`),
  KEY `idx_account` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5. Table dp_shop_queue (création si absente) ─────────────
CREATE TABLE IF NOT EXISTS `dp_shop_queue` (
  `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `account_id`   INT UNSIGNED     NOT NULL,
  `char_name`    VARCHAR(64)      NOT NULL,
  `item_id`      VARCHAR(64)      NOT NULL,
  `item_name`    VARCHAR(128)     NOT NULL,
  `game_item_id` INT UNSIGNED     NOT NULL,
  `quantity`     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `currency`     ENUM('dp','vp')  NOT NULL DEFAULT 'dp',
  `status`       ENUM('pending','delivered','error') NOT NULL DEFAULT 'pending',
  `attempts`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`   DATETIME         NOT NULL DEFAULT NOW(),
  `delivered_at` DATETIME         NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 6. Table vp_shop_log ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `vp_shop_log` (
  `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `account_id`   INT UNSIGNED     NOT NULL,
  `char_name`    VARCHAR(64)      NOT NULL DEFAULT '',
  `item_id`      VARCHAR(64)      NOT NULL,
  `item_name`    VARCHAR(128)     NOT NULL,
  `game_item_id` INT UNSIGNED     NOT NULL DEFAULT 0,
  `cost`         INT UNSIGNED     NOT NULL,
  `soap_status`  ENUM('ok','failed','service','na') NOT NULL DEFAULT 'ok',
  `soap_error`   TEXT             NULL,
  `created_at`   DATETIME         NOT NULL DEFAULT NOW(),
  PRIMARY KEY (`id`),
  KEY `idx_account` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 7. Table vp_shop_queue ───────────────────────────────────
CREATE TABLE IF NOT EXISTS `vp_shop_queue` (
  `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `account_id`   INT UNSIGNED     NOT NULL,
  `char_name`    VARCHAR(64)      NOT NULL,
  `item_id`      VARCHAR(64)      NOT NULL,
  `item_name`    VARCHAR(128)     NOT NULL,
  `game_item_id` INT UNSIGNED     NOT NULL,
  `quantity`     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `status`       ENUM('pending','delivered','error') NOT NULL DEFAULT 'pending',
  `attempts`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`   DATETIME         NOT NULL DEFAULT NOW(),
  `delivered_at` DATETIME         NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vp_vote_log` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_id` INT UNSIGNED NOT NULL,
  `site_id`    TINYINT UNSIGNED NOT NULL,
  `vp_reward`  SMALLINT UNSIGNED NOT NULL,
  `voted_at`   DATETIME NOT NULL DEFAULT NOW(),
  PRIMARY KEY (`id`),
  KEY `idx_acc_site` (`account_id`, `site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `vp_vote_pending` (
  `account_id` INT UNSIGNED NOT NULL,
  `site_id`    TINYINT UNSIGNED NOT NULL,
  `clicked_at` DATETIME NOT NULL DEFAULT NOW(),
  PRIMARY KEY (`account_id`, `site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  Fin de la migration
-- ============================================================
