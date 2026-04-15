-- ============================================================
--  shop_catalog.sql — Eons CMS | eons_auth
--  Catalogue de la boutique stocké en base de données
--
--  À exécuter dans la DB : eons_auth
-- ============================================================

USE `eons_auth`;

-- ----------------------------
-- Table structure for shop_catalog
-- ----------------------------
CREATE TABLE IF NOT EXISTS `shop_catalog` (
  `id`           VARCHAR(64)   NOT NULL COMMENT 'Identifiant interne unique (ex: ability_mount_spectraltiger)',
  `name`         VARCHAR(128)  NOT NULL COMMENT 'Nom affiché dans la boutique',
  `description`  TEXT          NOT NULL COMMENT 'Description de l\'item',
  `icon`         VARCHAR(8)    NOT NULL DEFAULT '🎁' COMMENT 'Emoji affiché',
  `price`        INT UNSIGNED  NOT NULL DEFAULT 0 COMMENT 'Coût en points',
  `currency`     ENUM('dp','vp') NOT NULL DEFAULT 'dp' COMMENT 'dp = Donor Points | vp = Vote Points',
  `category`     ENUM('montures','pets','equipement','services') NOT NULL COMMENT 'Catégorie de l\'item',
  `game_item_id` INT UNSIGNED  NOT NULL DEFAULT 0 COMMENT 'ID TrinityCore/Wowhead (0 = service sans item)',
  `quantity`     SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Quantité envoyée par mail in-game',
  `soap_cmd`     VARCHAR(255)  NULL DEFAULT NULL COMMENT 'Commande GM pour les services (%char% = personnage cible)',
  `badge`        VARCHAR(32)   NULL DEFAULT NULL COMMENT 'Texte du badge (ex: Légendaire, Épique)',
  `badge_color`  VARCHAR(16)   NULL DEFAULT NULL COMMENT 'Couleur CSS du badge (ex: #f0c060)',
  `ribbon`       VARCHAR(32)   NULL DEFAULT NULL COMMENT 'Ruban (ex: Populaire, Nouveau, Solde)',
  `active`       TINYINT(1)    NOT NULL DEFAULT 1 COMMENT '1 = visible en boutique, 0 = masqué',
  `sort_order`   SMALLINT      NOT NULL DEFAULT 0 COMMENT 'Ordre d\'affichage (ASC)',
  `created_at`   DATETIME      NOT NULL DEFAULT NOW(),
  `updated_at`   DATETIME      NOT NULL DEFAULT NOW() ON UPDATE NOW(),
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_category` (`category`),
  INDEX `idx_currency` (`currency`),
  INDEX `idx_active`   (`active`),
  INDEX `idx_sort`     (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catalogue de la boutique Eons';


-- ----------------------------
-- Import des items existants
-- ----------------------------

-- MONTURES
INSERT INTO `shop_catalog`
  (`id`, `name`, `description`, `icon`, `price`, `currency`, `category`, `game_item_id`, `quantity`, `soap_cmd`, `badge`, `badge_color`, `ribbon`, `active`, `sort_order`)
VALUES
  ('ability_mount_spectraltiger_dp',
   'Rênes de tigre spectral',
   'Invoque et renvoie un tigre spectral.',
   '🐯', 800, 'dp', 'montures', 33224, 1, NULL,
   'Légendaire', '#f0c060', 'Populaire', 1, 10),

  ('ability_mount_spectraltiger_vp',
   'Rênes de tigre spectral',
   'Invoque et renvoie un tigre spectral.',
   '🐯', 1800, 'vp', 'montures', 33224, 1, NULL,
   'Légendaire', '#f0c060', 'Populaire', 1, 11),

  ('ability_mount_nightmarehorse',
   'Cheval de guerre noir de croisé',
   'Invoque et renvoie un cheval de guerre noir de croisé.',
   '🐎', 1200, 'vp', 'montures', 49098, 1, NULL,
   'Épique', '#a070ff', NULL, 1, 20),

  ('ability_mount_drake_proto',
   'Rênes de proto-drake bleu',
   'Invoque et renvoie un proto-drake bleu.',
   '🐉', 650, 'dp', 'montures', 44151, 1, NULL,
   'Rare', '#69ccf0', NULL, 1, 30),

-- PETS
  ('ability_hunter_pet_dragonhawk',
   'Jeune faucon-dragon bleu',
   'Vous apprend à invoquer votre jeune faucon-dragon.',
   '🐉', 250, 'vp', 'pets', 29958, 1, NULL,
   'Rare', '#69ccf0', 'Nouveau', 1, 10),

  ('inv_egg_02',
   'Oeuf de poule',
   'Vous apprend à invoquer votre poulet.',
   '🥚', 180, 'dp', 'pets', 11110, 1, NULL,
   'Commun', '#a8b4d0', NULL, 1, 20),

  ('inv_misc_bandage_16',
   'Laisse de familier en ruban rouge',
   'Une laisse pour familier faite d\'un ruban rouge.',
   '🐶', 320, 'dp', 'pets', 44820, 1, NULL,
   'Épique', '#a070ff', NULL, 1, 30),

-- ÉQUIPEMENTS
  ('inv_staff_13',
   'Grand bâton de Jordan',
   'Expérience gagnée en tuant des monstres et en accomplissant des quêtes augmentée de 10%.',
   '🧙', 80, 'vp', 'equipement', 44095, 1, NULL,
   'Héritage', '#f0c060', NULL, 1, 10),

  ('inv_chest_cloth_49',
   'Robe de Brume-funeste rapiécée',
   'Expérience gagnée en tuant des monstres et en accomplissant des quêtes augmentée de 10%.',
   '🥻', 320, 'vp', 'equipement', 48691, 1, NULL,
   'Héritage', '#a070ff', 'Solde', 1, 20),

  ('inv_sword_17',
   'Crève-cœur équilibré',
   'Expérience gagnée en tuant des monstres et en accomplissant des quêtes augmentée de 10%.',
   '🗡️', 90, 'vp', 'equipement', 42944, 1, NULL,
   'Héritage', '#69ccf0', NULL, 1, 30),

-- SERVICES
  ('service_name_change',
   'Changement de Nom',
   'Offrez une nouvelle identité à votre héros. Le changement prend effet lors de la prochaine connexion au jeu.',
   '✒️', 150, 'vp', 'services', 0, 1,
   '.character rename %char%',
   'Service', '#8890ff', NULL, 1, 10),

  ('service_race_change',
   'Changement de Race',
   'Réincarnez votre personnage dans une autre race. Votre histoire, vos équipements et votre niveau sont préservés.',
   '🧬', 250, 'dp', 'services', 0, 1,
   '.character changerace %char%',
   'Service', '#8890ff', NULL, 1, 20),

  ('service_faction_change',
   'Changement de Faction',
   'Changer votre Faction.',
   '🧬', 250, 'dp', 'services', 0, 1,
   '.character changefaction %char%',
   'Service', '#8890ff', NULL, 1, 30),

  ('service_boost_80',
   'Boost Niveau 80',
   'Votre héros atteint instantanément le niveau maximum. Équipement de départ Naxxramas fourni. Prêt pour les raids.',
   '⚡', 1000, 'dp', 'services', 0, 1,
   '.character level %char% 80',
   'Légendaire', '#f0c060', 'Populaire', 1, 40);
