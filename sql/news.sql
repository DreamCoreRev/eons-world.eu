-- ============================================================
--  news.sql — Table des actualités Eons CMS
--  À exécuter dans la base : eons_auth
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `news` (
  `id`         INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`      VARCHAR(255)     NOT NULL,
  `slug`       VARCHAR(255)     NOT NULL DEFAULT '',
  `category`   ENUM('patch','event','maintenance','annonce','hotfix') NOT NULL DEFAULT 'annonce',
  `excerpt`    TEXT             NOT NULL,
  `content`    LONGTEXT         NOT NULL,
  `image_url`  VARCHAR(512)     NOT NULL DEFAULT '',
  `author`     VARCHAR(64)      NOT NULL DEFAULT 'Équipe Eons',
  `pinned`     TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `published`  TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_slug` (`slug`),
  KEY `idx_published_pinned` (`published`, `pinned`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Actualités du serveur';

-- Données de démonstration
INSERT INTO `news` (`title`, `slug`, `category`, `excerpt`, `content`, `image_url`, `author`, `pinned`) VALUES
(
  'Bienvenue sur Eons — Serveur WoW 3.3.5a',
  'bienvenue-eons',
  'annonce',
  'Le serveur Eons ouvre officiellement ses portes ! Découvrez notre univers Arcanic unique sur AzerothCore 3.3.5a.',
  '<p>C\'est avec une immense fierté que nous vous accueillons sur <strong>Eons</strong>, votre nouveau serveur privé World of Warcraft 3.3.5a basé sur AzerothCore.</p><p>Nous avons travaillé dur pour vous offrir une expérience de jeu stable, équilibrée et immersive. Notre équipe de GM est disponible quotidiennement pour vous accompagner.</p><h3>Ce qui vous attend :</h3><ul><li>Taux d\'expérience x3 pour un leveling agréable</li><li>Boutique de Donation Points équitable</li><li>Événements hebdomadaires exclusifs</li><li>Support francophone réactif</li></ul><p>Rejoignez notre communauté et écrivez votre légende !</p>',
  '',
  'Équipe Eons',
  1
),
(
  'Patch 1.1 — Équilibrage des classes & corrections',
  'patch-1-1-equilibrage',
  'patch',
  'Premier patch correctif : ajustements sur les Paladins et Chasseurs, corrections de bugs et optimisations serveur.',
  '<p>Suite à vos retours, nous déployons le <strong>Patch 1.1</strong> qui apporte plusieurs ajustements importants.</p><h3>Équilibrage</h3><ul><li>Paladin Sacré : soins de Lumière divine augmentés de 5 %</li><li>Chasseur : dégâts de tir automatique légèrement réduits en JcJ</li><li>Mage Givre : Shatter Combo recalibré</li></ul><h3>Corrections de bugs</h3><ul><li>Téléporteur de Dalaran : trajet Orgrimmar corrigé</li><li>Quêtes du Bassin d\'Arathi : compteur de kills réinitialisé correctement</li></ul><h3>Performance</h3><p>Optimisation de la gestion des combats de masse en donjon héroïque. Les temps de réponse sont réduits d\'environ 15 %.</p>',
  '',
  'Équipe Eons',
  0
),
(
  'Événement — La Nuit du Feu Arcanique',
  'evenement-nuit-feu-arcanique',
  'event',
  'Du 19 au 26 avril : doublons de VP, boss world spéciaux et récompenses exclusives vous attendent !',
  '<p>L\'obscurité se déchire et les énergies arcaniques envahissent Azeroth ! <strong>La Nuit du Feu Arcanique</strong> commence !</p><h3>Durée</h3><p>Du <strong>19 avril</strong> au <strong>26 avril</strong> inclus.</p><h3>Avantages actifs</h3><ul><li>x2 Vote Points sur tous les votes</li><li>x1.5 expérience sur les donjons héroïques</li><li>Apparition de Boss World Arcaniques toutes les 6h</li></ul><h3>Récompenses exclusives</h3><p>Les 3 premiers guildes à vaincre le Boss World reçoivent un titre de guilde unique et 500 DP.</p><p>Bonne chasse, aventuriers !</p>',
  '',
  'Équipe Eons',
  0
);
