  ALTER TABLE `account`
    ADD COLUMN `dp` INT UNSIGNED NOT NULL DEFAULT 0;
	
	  ALTER TABLE `account`
    ADD COLUMN `vp` INT UNSIGNED NOT NULL DEFAULT 0;

  CREATE TABLE IF NOT EXISTS `dp_shop_log` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `account_id`  INT UNSIGNED NOT NULL,
    `char_name`   VARCHAR(64)  NOT NULL DEFAULT '',
    `item_id`     VARCHAR(64)  NOT NULL,
    `item_name`   VARCHAR(128) NOT NULL,
    `game_item_id`INT UNSIGNED NOT NULL DEFAULT 0,
    `cost`        INT UNSIGNED NOT NULL,
    `soap_status` ENUM('ok','failed','service','na') NOT NULL DEFAULT 'ok',
    `soap_error`  TEXT         NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT NOW()
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

  CREATE TABLE IF NOT EXISTS `dp_shop_queue` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `account_id`  INT UNSIGNED NOT NULL,
    `char_name`   VARCHAR(64)  NOT NULL,
    `item_id`     VARCHAR(64)  NOT NULL,
    `item_name`   VARCHAR(128) NOT NULL,
    `game_item_id`INT UNSIGNED NOT NULL,
    `quantity`    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `status`      ENUM('pending','delivered','error') NOT NULL DEFAULT 'pending',
    `attempts`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  DATETIME     NOT NULL DEFAULT NOW(),
    `delivered_at`DATETIME     NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;