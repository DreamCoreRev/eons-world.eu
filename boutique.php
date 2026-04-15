<?php
// ============================================================
//  boutique.php — Eons CMS | Arcanic Theme
//  Boutique de Points — TrinityCore 3.3.5a + SOAP
//
//  FLUX D'ACHAT :
//    1. Vérification CSRF + solde DP
//    2. Déduction atomique du solde en DB
//    3. Envoi SOAP → .send items <char> "..." itemid[:qty]
//    4. Si SOAP KO → enregistrement en file dp_shop_queue (livraison diff.)
//    5. Log de tout dans dp_shop_log
//
//  ── SOAP ──────────────────────────────────────────────────────
//  Dans TrinityCore worldserver.conf :
//    SOAP.Enabled = 1
//    SOAP.IP      = 127.0.0.1
//    SOAP.Port    = 7878
//
//  Créer le compte SOAP (GM level 3 minimum) :
//    .account create soap_admin VotreMotDePasse
//    .account set gmlevel soap_admin 3 -1
//
//  Dans config.php, ajouter :
//    define('SOAP_HOST',     '127.0.0.1');
//    define('SOAP_PORT',     7878);
//    define('SOAP_USER',     'soap_admin');
//    define('SOAP_PASS',     'VotreMotDePasse');
//    define('SOAP_SENDER',   'Boutique');   // expéditeur du mail in-game
//
//  ── SQL REQUIS ────────────────────────────────────────────────
//  -- Colonne DP (si absente) :
//  ALTER TABLE `account`
//    ADD COLUMN `dp` INT UNSIGNED NOT NULL DEFAULT 0;
//
//  -- Colonne VP (à ajouter à côté de dp) :
//  ALTER TABLE `account`
//    ADD COLUMN `vp` INT UNSIGNED NOT NULL DEFAULT 0
//    AFTER `dp`;
//
//  CREATE TABLE IF NOT EXISTS `dp_shop_log` (
//    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
//    `account_id`  INT UNSIGNED NOT NULL,
//    `char_name`   VARCHAR(64)  NOT NULL DEFAULT '',
//    `item_id`     VARCHAR(64)  NOT NULL,
//    `item_name`   VARCHAR(128) NOT NULL,
//    `game_item_id`INT UNSIGNED NOT NULL DEFAULT 0,
//    `cost`        INT UNSIGNED NOT NULL,
//    `currency`    ENUM('dp','vp') NOT NULL DEFAULT 'dp',
//    `soap_status` ENUM('ok','failed','service','na') NOT NULL DEFAULT 'ok',
//    `soap_error`  TEXT         NULL,
//    `created_at`  DATETIME     NOT NULL DEFAULT NOW()
//  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
//
//  CREATE TABLE IF NOT EXISTS `dp_shop_queue` (
//    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
//    `account_id`  INT UNSIGNED NOT NULL,
//    `char_name`   VARCHAR(64)  NOT NULL,
//    `item_id`     VARCHAR(64)  NOT NULL,
//    `item_name`   VARCHAR(128) NOT NULL,
//    `game_item_id`INT UNSIGNED NOT NULL,
//    `quantity`    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
//    `currency`    ENUM('dp','vp') NOT NULL DEFAULT 'dp',
//    `status`      ENUM('pending','delivered','error') NOT NULL DEFAULT 'pending',
//    `attempts`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
//    `created_at`  DATETIME     NOT NULL DEFAULT NOW(),
//    `delivered_at`DATETIME     NULL
//  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
//
//  CREATE TABLE IF NOT EXISTS `vp_shop_log` (
//    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
//    `account_id`  INT UNSIGNED NOT NULL,
//    `char_name`   VARCHAR(64)  NOT NULL DEFAULT '',
//    `item_id`     VARCHAR(64)  NOT NULL,
//    `item_name`   VARCHAR(128) NOT NULL,
//    `game_item_id`INT UNSIGNED NOT NULL DEFAULT 0,
//    `cost`        INT UNSIGNED NOT NULL,
//    `soap_status` ENUM('ok','failed','service','na') NOT NULL DEFAULT 'ok',
//    `soap_error`  TEXT         NULL,
//    `created_at`  DATETIME     NOT NULL DEFAULT NOW()
//  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
//
//  CREATE TABLE IF NOT EXISTS `vp_shop_queue` (
//    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
//    `account_id`  INT UNSIGNED NOT NULL,
//    `char_name`   VARCHAR(64)  NOT NULL,
//    `item_id`     VARCHAR(64)  NOT NULL,
//    `item_name`   VARCHAR(128) NOT NULL,
//    `game_item_id`INT UNSIGNED NOT NULL,
//    `quantity`    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
//    `status`      ENUM('pending','delivered','error') NOT NULL DEFAULT 'pending',
//    `attempts`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
//    `created_at`  DATETIME     NOT NULL DEFAULT NOW(),
//    `delivered_at`DATETIME     NULL
//  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
// ============================================================
require_once __DIR__ . '/config.php';

// ── Constantes SOAP (fallback si absentes de config.php) ──────
if (!defined('SOAP_HOST'))   define('SOAP_HOST',   '127.0.0.1');
if (!defined('SOAP_PORT'))   define('SOAP_PORT',   7878);
if (!defined('SOAP_USER'))   define('SOAP_USER',   'soap_admin');
if (!defined('SOAP_PASS'))   define('SOAP_PASS',   'changeme');
if (!defined('SOAP_SENDER')) define('SOAP_SENDER', 'Boutique');

// ── Classe SOAP TrinityCore ───────────────────────────────────
class TCSoap
{
    private SoapClient $client;

    /**
     * @throws SoapFault|Exception
     */
    public function __construct()
    {
        if (!extension_loaded('soap')) {
            throw new \Exception('Extension PHP soap non chargée. Activez extension=soap dans php.ini.');
        }
        $wsdl = sprintf('http://%s:%d/RPC2', SOAP_HOST, SOAP_PORT);
        $this->client = new SoapClient(null, [
            'location'   => $wsdl,
            'uri'        => 'urn:TC',
            'style'      => SOAP_RPC,
            'use'        => SOAP_ENCODED,
            'login'      => SOAP_USER,
            'password'   => SOAP_PASS,
            'exceptions' => true,
            'connection_timeout' => 5,
            'trace'      => false,
        ]);
    }

    /**
     * Exécute une commande GM via SOAP.
     * Retourne la réponse texte du serveur.
     *
     * @throws SoapFault
     */
    public function execute(string $command): string
    {
        $result = $this->client->__soapCall('executeCommand', [new SoapParam($command, 'command')]);
        return (string)($result ?? '');
    }

    /**
     * Envoie un ou plusieurs items à un personnage via mail in-game.
     *
     * Commande : .send items <char> "<subject>" "<body>" <itemId>[:qty] ...
     * Max 12 stacks par mail — la méthode découpe automatiquement si nécessaire.
     *
     * @param  string $charName   Nom exact du personnage (casse libre)
     * @param  int    $gameItemId ID Wowhead de l'item
     * @param  int    $qty        Quantité (défaut 1)
     * @param  string $itemName   Nom lisible pour le sujet du mail
     * @return string             Réponse SOAP (vide = succès silencieux)
     * @throws SoapFault|\Exception
     */
    public function sendItem(string $charName, int $gameItemId, int $qty, string $itemName): string
    {
        $charName  = trim($charName);
        $subject   = addslashes('Boutique Eons — ' . $itemName);
        $body      = addslashes('Félicitations ! Votre achat a été livré. Merci de votre fidélité, héros.');

        // Construire la liste des items (max 12 stacks, 1 par défaut)
        $stacks    = max(1, (int)$qty);
        $itemArg   = $gameItemId . ($stacks > 1 ? ':' . $stacks : '');

        $cmd = sprintf('.send items %s "%s" "%s" %s', $charName, $subject, $body, $itemArg);
        return $this->execute($cmd);
    }

    /**
     * Envoie une commande de service (boost, renommage…).
     * Retourne la réponse brute.
     *
     * @throws SoapFault|\Exception
     */
    public function sendCommand(string $cmd): string
    {
        return $this->execute($cmd);
    }
}
$isLoggedIn = !empty($_SESSION['logged_in']) && !empty($_SESSION['account_id']);
$accountId  = $isLoggedIn ? (int)$_SESSION['account_id'] : 0;

// ── Catalogue de la boutique ──────────────────────────────────
// Champs obligatoires :
//   id           → identifiant interne unique
//   name         → nom affiché
//   desc         → description
//   icon         → emoji
//   price        → coût en points (DP ou VP selon currency)
//   currency     → 'dp' (Donor Points) | 'vp' (Vote Points)  — défaut 'dp'
//   category     → montures | pets | equipement | services
//   game_item_id → ID Wowhead/TrinityCore de l'item (0 = service sans item)
//   quantity     → quantité à envoyer (défaut 1)
//   soap_cmd     → commande GM pour les services (%char% = personnage cible) ; null = envoi d'item normal
// Champs optionnels : badge, badge_color, ribbon
function loadCatalog(): array
{
    try {
        $db   = getAuthDB();                        // PDO déjà dispo via config.php
        $stmt = $db->query(
            "SELECT * FROM `shop_catalog`
              WHERE `active` = 1
              ORDER BY `category`, `sort_order`, `price`"
        );
        $rows = $stmt->fetchAll();

        // Normalise vers le même format qu'avant pour ne rien casser
        return array_map(static function (array $r): array {
            return [
                'id'           => $r['id'],
                'name'         => $r['name'],
                'desc'         => $r['description'],   // clé 'desc' comme dans l'original
                'icon'         => $r['icon'],
                'price'        => (int) $r['price'],
                'currency'     => $r['currency'],
                'category'     => $r['category'],
                'game_item_id' => (int) $r['game_item_id'],
                'quantity'     => (int) $r['quantity'],
                'soap_cmd'     => $r['soap_cmd'],
                'badge'        => $r['badge']       ?: null,
                'badge_color'  => $r['badge_color'] ?: null,
                'ribbon'       => $r['ribbon']      ?: null,
            ];
        }, $rows);

    } catch (\PDOException $e) {
        error_log('[Boutique] Chargement catalogue : ' . $e->getMessage());
        return [];   // Boutique vide plutôt qu'une erreur fatale
    }
}

$catalog = loadCatalog();

// ── Catégories (inchangées) ───────────────────────────────────
$categories = [
    'tous'       => ['label' => 'Tout voir',    'icon' => '✦'],
    'montures'   => ['label' => 'Montures',     'icon' => '🐉'],
    'pets'       => ['label' => 'Familiers',    'icon' => '🔥'],
    'equipement' => ['label' => 'Équipements',  'icon' => '⚔️'],
    'services'   => ['label' => 'Services',     'icon' => '⚡'],
];

// ── Solde de points & personnages ─────────────────────────────
$playerDp    = 0;
$playerVp    = 0;
$playerChars = [];
$flashMsg    = '';
$flashType   = 'info';
$errors      = [];

if ($isLoggedIn) {
    try {
        $db   = getAuthDB();
        $stmt = $db->prepare("SELECT dp, vp FROM account WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $accountId]);
        $row      = $stmt->fetch();
        $playerDp = $row ? (int)($row['dp'] ?? 0) : 0;
        $playerVp = $row ? (int)($row['vp'] ?? 0) : 0;
    } catch (PDOException $e) {
        error_log('[Boutique] Lecture dp/vp: ' . $e->getMessage());
    }

    try {
        $dsn   = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_CHARS_NAME . ';charset=utf8mb4';
        $charDb = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $stmt  = $charDb->prepare("SELECT name, level, class FROM characters WHERE account = :aid AND deleteDate IS NULL ORDER BY level DESC LIMIT 10");
        $stmt->execute([':aid' => $accountId]);
        $playerChars = $stmt->fetchAll();
    } catch (PDOException $e) { error_log('[Boutique] Chars: ' . $e->getMessage()); }
}

// ── Traitement achat avec SOAP ────────────────────────────────
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action']) && $_POST['action'] === 'buy') {

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF invalide.';
    } else {
        $itemId   = $_POST['item_id']  ?? '';
        $charName = trim($_POST['char_name'] ?? '');

        // ── 1. Trouver l'item dans le catalogue ───────────────
        $item = null;
        foreach ($catalog as $c) { if ($c['id'] === $itemId) { $item = $c; break; } }

        // ── 2. Validations ────────────────────────────────────
        $itemCurrency  = strtolower($item['currency'] ?? 'dp');
        $currencyLabel = ($itemCurrency === 'vp') ? 'VP' : 'DP';
        $playerBalance = ($itemCurrency === 'vp') ? $playerVp : $playerDp;

        if (!$item) {
            $errors[] = 'Article introuvable.';
        } elseif (empty($charName)) {
            $errors[] = 'Veuillez sélectionner un personnage destinataire.';
        } elseif ($playerBalance < $item['price']) {
            $errors[] = 'Points insuffisants. Il vous manque ' . ($item['price'] - $playerBalance) . ' ' . $currencyLabel . '.';
        } else {
            // ── 3. Déduction atomique du solde ────────────────
            try {
                $db  = getAuthDB();
                $col = ($itemCurrency === 'vp') ? 'vp' : 'dp';
                $upd = $db->prepare(
                    "UPDATE account SET {$col} = {$col} - :cost WHERE id = :id AND {$col} >= :cost2"
                );
                $upd->execute([
                    ':cost'  => $item['price'],
                    ':id'    => $accountId,
                    ':cost2' => $item['price'],
                ]);

                if ($upd->rowCount() !== 1) {
                    $errors[] = 'Transaction échouée : solde insuffisant ou conflit. Réessayez.';
                } else {
                    if ($itemCurrency === 'vp') { $playerVp -= $item['price']; }
                    else                        { $playerDp -= $item['price']; }

                    // ── 4. Envoi SOAP ─────────────────────────
                    $soapStatus = 'ok';
                    $soapError  = null;
                    $isService  = ($item['game_item_id'] === 0 && $item['soap_cmd'] !== null);

                    try {
                        $soap = new TCSoap();

                        if ($isService) {
                            // Service : exécuter la commande GM directement
                            $cmd = str_replace('%char%', $charName, $item['soap_cmd']);
                            $soap->sendCommand($cmd);
                            $soapStatus = 'service';
                        } else {
                            // Item : envoyer via .send items
                            $soap->sendItem(
                                $charName,
                                (int)$item['game_item_id'],
                                (int)($item['quantity'] ?? 1),
                                $item['name']
                            );
                        }

                        $flashMsg  = '✦ Achat réussi ! <strong>' . htmlspecialchars($item['name'])
                                   . '</strong> a été envoyé à <strong>' . htmlspecialchars($charName)
                                   . '</strong>.'
                                   . ($isService
                                      ? ' Le service peut prendre effet à la prochaine connexion du personnage.'
                                      : ' Vérifiez votre boîte mail en jeu.');
                        $flashType = 'success';

                    } catch (\SoapFault $e) {
                        // SOAP KO → mise en file d'attente
                        $soapStatus = 'failed';
                        $soapError  = $e->getMessage();
                        error_log('[Boutique] SOAP SoapFault: ' . $soapError);

                        // Enregistrer en queue pour livraison manuelle ou cron
                        try {
                            $q = $db->prepare(
                                "INSERT INTO dp_shop_queue
                                 (account_id, char_name, item_id, item_name, game_item_id, quantity, currency, status)
                                 VALUES (:aid, :char, :iid, :iname, :gid, :qty, :cur, 'pending')"
                            );
                            $q->execute([
                                ':aid'   => $accountId,
                                ':char'  => $charName,
                                ':iid'   => $item['id'],
                                ':iname' => $item['name'],
                                ':gid'   => (int)$item['game_item_id'],
                                ':qty'   => (int)($item['quantity'] ?? 1),
                                ':cur'   => $itemCurrency,
                            ]);
                        } catch (PDOException $qe) {
                            error_log('[Boutique] Queue insert: ' . $qe->getMessage());
                        }

                        $flashMsg  = '✦ Achat enregistré ! <strong>' . htmlspecialchars($item['name'])
                                   . '</strong> sera livré à <strong>' . htmlspecialchars($charName)
                                   . '</strong> dès que le serveur de jeu sera disponible. (Ref: SOAP_KO)';
                        $flashType = 'info';

                    } catch (\Exception $e) {
                        // Extension SOAP manquante ou autre erreur critique
                        $soapStatus = 'failed';
                        $soapError  = $e->getMessage();
                        error_log('[Boutique] SOAP Exception: ' . $soapError);

                        try {
                            $q = $db->prepare(
                                "INSERT INTO dp_shop_queue
                                 (account_id, char_name, item_id, item_name, game_item_id, quantity, currency, status)
                                 VALUES (:aid, :char, :iid, :iname, :gid, :qty, :cur, 'pending')"
                            );
                            $q->execute([
                                ':aid'   => $accountId,
                                ':char'  => $charName,
                                ':iid'   => $item['id'],
                                ':iname' => $item['name'],
                                ':gid'   => (int)$item['game_item_id'],
                                ':qty'   => (int)($item['quantity'] ?? 1),
                                ':cur'   => $itemCurrency,
                            ]);
                        } catch (PDOException $qe) {
                            error_log('[Boutique] Queue insert (exc): ' . $qe->getMessage());
                        }

                        $flashMsg  = '✦ Achat enregistré ! Livraison en attente — le serveur SOAP est momentanément indisponible.';
                        $flashType = 'info';
                    }

                    // ── 5. Log de l'achat ─────────────────────
                    try {
                        $log = $db->prepare(
                            "INSERT INTO dp_shop_log
                             (account_id, char_name, item_id, item_name, game_item_id, cost, currency, soap_status, soap_error)
                             VALUES (:aid, :char, :iid, :iname, :gid, :cost, :cur, :status, :err)"
                        );
                        $log->execute([
                            ':aid'    => $accountId,
                            ':char'   => $charName,
                            ':iid'    => $item['id'],
                            ':iname'  => $item['name'],
                            ':gid'    => (int)$item['game_item_id'],
                            ':cost'   => $item['price'],
                            ':cur'    => $itemCurrency,
                            ':status' => $soapStatus,
                            ':err'    => $soapError,
                        ]);
                    } catch (PDOException $le) {
                        error_log('[Boutique] Log insert: ' . $le->getMessage());
                    }
                }
            } catch (PDOException $e) {
                error_log('[Boutique] DB achat: ' . $e->getMessage());
                $errors[] = 'Erreur base de données lors de l\'achat. Réessayez.';
            }
        }
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf_token'];

$classColors = [1=>'#c79c6e',2=>'#f58cba',3=>'#abd473',4=>'#fff569',5=>'#ffffff',6=>'#c41f3b',7=>'#0070de',8=>'#69ccf0',9=>'#9482c9',11=>'#ff7d0a'];

$pageTitle = 'Boutique — Eons';
require_once __DIR__ . '/header.php';
?>

<style>
/* ─── BOUTIQUE LAYOUT ──────────────────────────────────────────── */
.shop-page {
    position: relative; z-index: 10;
    padding: 5.5rem 2rem 5rem;
    max-width: 1380px;
    margin: 0 auto;
}

/* ─── HERO BANNER ──────────────────────────────────────────────── */
.shop-hero {
    text-align: center;
    padding: 3.5rem 1rem 3rem;
    position: relative;
    margin-bottom: 2.5rem;
}

.shop-hero::before {
    content: '';
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 600px; height: 300px;
    background: radial-gradient(ellipse, rgba(90,48,212,0.18) 0%, transparent 70%);
    pointer-events: none;
}

.shop-eyebrow {
    font-family: 'Cinzel', serif;
    font-size: 0.65rem;
    letter-spacing: 0.45em;
    color: var(--gold);
    text-transform: uppercase;
    margin-bottom: 1rem;
}

.shop-title {
    font-family: 'Cinzel Decorative', serif;
    font-size: clamp(1.8rem, 4vw, 2.8rem);
    font-weight: 900;
    color: var(--white);
    text-shadow: 0 0 60px rgba(136,144,255,0.3), 0 0 120px rgba(90,48,212,0.15);
    margin-bottom: 1rem;
}

.shop-title span {
    background: linear-gradient(135deg, var(--gold) 0%, var(--gold-bright) 50%, var(--gold) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.shop-subtitle {
    font-family: 'Crimson Pro', serif;
    font-size: 1.05rem;
    font-style: italic;
    color: var(--silver);
    max-width: 580px;
    margin: 0 auto 2rem;
    line-height: 1.7;
}

.shop-divider {
    display: flex; align-items: center; justify-content: center; gap: 1rem;
    margin-bottom: 0.5rem;
}
.shop-divider::before, .shop-divider::after {
    content: '';
    width: 80px; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(136,144,255,0.4));
}
.shop-divider::after { transform: scaleX(-1); }
.shop-divider-gem {
    width: 8px; height: 8px;
    background: var(--gold);
    transform: rotate(45deg);
    box-shadow: 0 0 14px rgba(240,192,96,0.7);
}

/* ─── SOLDE ────────────────────────────────────────────────────── */
.dp-balance-bar {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1.2rem;
    flex-wrap: wrap;
    margin-bottom: 2.5rem;
}

.dp-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.7rem 1.6rem;
    background: rgba(9,12,34,0.85);
    border: 1px solid rgba(240,192,96,0.35);
    clip-path: polygon(12px 0%, 100% 0%, calc(100% - 12px) 100%, 0% 100%);
    backdrop-filter: blur(10px);
}

.vp-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.7rem 1.6rem;
    background: rgba(9,12,34,0.85);
    border: 1px solid rgba(100,200,120,0.35);
    clip-path: polygon(12px 0%, 100% 0%, calc(100% - 12px) 100%, 0% 100%);
    backdrop-filter: blur(10px);
}

.dp-badge-icon {
    font-size: 1.2rem;
}

.dp-badge-label {
    font-family: 'Cinzel', serif;
    font-size: 0.6rem;
    letter-spacing: 0.2em;
    color: var(--silver);
    text-transform: uppercase;
}

.dp-badge-amount {
    font-family: 'Cinzel Decorative', serif;
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--gold-bright);
    text-shadow: 0 0 20px rgba(240,192,96,0.5);
}

.vp-badge-amount {
    font-family: 'Cinzel Decorative', serif;
    font-size: 1.3rem;
    font-weight: 700;
    color: #6edf8a;
    text-shadow: 0 0 20px rgba(100,200,120,0.5);
}

.dp-badge-unit {
    font-family: 'Cinzel', serif;
    font-size: 0.62rem;
    letter-spacing: 0.15em;
    color: var(--gold);
    opacity: 0.8;
}

.vp-badge-unit {
    font-family: 'Cinzel', serif;
    font-size: 0.62rem;
    letter-spacing: 0.15em;
    color: #6edf8a;
    opacity: 0.8;
}

/* ─── BOUTON WOWHEAD ───────────────────────────────────────────── */
.btn-wowhead {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-family: 'Cinzel', serif;
    font-size: 0.55rem;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    padding: 0.5rem 0.9rem;
    background: transparent;
    color: #7ec8e3;
    border: 1px solid rgba(126,200,227,0.35);
    clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%);
    text-decoration: none;
    transition: all 0.25s;
    white-space: nowrap;
}
.btn-wowhead:hover {
    color: #b0dff0;
    border-color: rgba(126,200,227,0.7);
    box-shadow: 0 0 14px rgba(126,200,227,0.2);
}

.dp-recharge-link {
    font-family: 'Cinzel', serif;
    font-size: 0.6rem;
    letter-spacing: 0.14em;
    color: var(--arcane-bright);
    text-decoration: none;
    text-transform: uppercase;
    border-bottom: 1px solid rgba(136,144,255,0.3);
    transition: color 0.3s, border-color 0.3s;
}
.dp-recharge-link:hover { color: var(--void-bright); border-color: var(--void-bright); }

/* ─── FILTRES CATÉGORIES ───────────────────────────────────────── */
.shop-filters {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    flex-wrap: wrap;
    margin-bottom: 2.8rem;
}

.filter-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-family: 'Cinzel', serif;
    font-size: 0.6rem;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    padding: 0.55rem 1.1rem;
    background: rgba(9,12,34,0.7);
    border: 1px solid rgba(136,144,255,0.12);
    color: var(--silver);
    cursor: pointer;
    transition: all 0.3s;
    clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%);
    position: relative;
    overflow: hidden;
}
.filter-btn::before {
    content: '';
    position: absolute; inset: 0;
    background: linear-gradient(135deg, rgba(136,144,255,0.08), transparent);
    opacity: 0;
    transition: opacity 0.3s;
}
.filter-btn:hover { color: var(--arcane-bright); border-color: rgba(136,144,255,0.35); }
.filter-btn:hover::before { opacity: 1; }

.filter-btn.active {
    background: linear-gradient(135deg, rgba(26,29,90,0.9), rgba(90,48,212,0.4));
    border-color: rgba(136,144,255,0.5);
    color: var(--white);
    box-shadow: 0 0 20px rgba(90,48,212,0.3), inset 0 0 20px rgba(136,144,255,0.05);
}

/* ─── GRID PRODUITS ────────────────────────────────────────────── */
.shop-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
    gap: 1.6rem;
    align-items: start;
}

/* ─── ITEM CARD ────────────────────────────────────────────────── */
.item-card {
    position: relative;
    background: rgba(9,12,34,0.8);
    border: 1px solid rgba(136,144,255,0.1);
    overflow: hidden;
    transition: transform 0.35s cubic-bezier(.22,1,.36,1), border-color 0.3s, box-shadow 0.35s;
    display: flex;
    flex-direction: column;
}

/* Corner cut */
.item-card::before {
    content: '';
    position: absolute;
    top: 0; right: 0;
    width: 0; height: 0;
    border-style: solid;
    border-width: 0 48px 48px 0;
    border-color: transparent rgba(136,144,255,0.15) transparent transparent;
    transition: border-color 0.3s;
    z-index: 2;
}

/* Inner glow */
.item-card::after {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at 30% 0%, rgba(90,48,212,0.08) 0%, transparent 60%);
    opacity: 0;
    transition: opacity 0.4s;
    pointer-events: none;
}

.item-card:hover {
    transform: translateY(-6px);
    border-color: rgba(136,144,255,0.3);
    box-shadow: 0 16px 60px rgba(90,48,212,0.3), 0 0 0 1px rgba(136,144,255,0.08) inset;
}
.item-card:hover::before { border-color: transparent rgba(240,192,96,0.4) transparent transparent; }
.item-card:hover::after  { opacity: 1; }

/* ── RIBBON ── */
.item-ribbon {
    position: absolute;
    top: 16px; left: -28px;
    background: linear-gradient(135deg, #9a6418, #d4a030, #f0c060);
    color: #1a0e00;
    font-family: 'Cinzel', serif;
    font-size: 0.5rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    padding: 0.3rem 2.2rem;
    transform: rotate(-38deg);
    z-index: 5;
    box-shadow: 0 2px 12px rgba(200,144,40,0.5);
}

/* ── ICON AREA ── */
.item-icon-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 120px;
    background: linear-gradient(180deg, rgba(26,29,90,0.3) 0%, rgba(9,12,34,0) 100%);
    border-bottom: 1px solid rgba(136,144,255,0.07);
    position: relative;
    overflow: hidden;
}

.item-icon-wrap::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at center, rgba(90,48,212,0.12) 0%, transparent 70%);
}

.item-icon {
    font-size: 3.2rem;
    position: relative;
    z-index: 1;
    filter: drop-shadow(0 0 20px rgba(136,144,255,0.4));
    transition: transform 0.4s cubic-bezier(.22,1,.36,1), filter 0.4s;
}
.item-card:hover .item-icon {
    transform: scale(1.15) translateY(-4px);
    filter: drop-shadow(0 0 30px rgba(136,144,255,0.7));
}

/* ── BADGE (rareté) ── */
.item-badge {
    position: absolute;
    bottom: 10px; right: 10px;
    font-family: 'Cinzel', serif;
    font-size: 0.48rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    padding: 0.22rem 0.6rem;
    border: 1px solid currentColor;
    z-index: 3;
    opacity: 0.85;
}

/* ── CONTENT ── */
.item-content {
    padding: 1.4rem 1.4rem 0.8rem;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.item-name {
    font-family: 'Cinzel', serif;
    font-size: 0.88rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    color: var(--white);
    line-height: 1.3;
}

.item-desc {
    font-family: 'Crimson Pro', serif;
    font-size: 0.9rem;
    color: var(--silver);
    line-height: 1.65;
    font-style: italic;
    flex: 1;
}

/* ── FOOTER CARD ── */
.item-footer {
    padding: 1rem 1.4rem 1.4rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-top: 1px solid rgba(136,144,255,0.07);
    gap: 0.8rem;
}

.item-price {
    display: flex;
    align-items: baseline;
    gap: 0.3rem;
}

.item-price-amount {
    font-family: 'Cinzel Decorative', serif;
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--gold-bright);
    text-shadow: 0 0 20px rgba(240,192,96,0.4);
}

.item-price-unit {
    font-family: 'Cinzel', serif;
    font-size: 0.55rem;
    letter-spacing: 0.15em;
    color: var(--gold);
    opacity: 0.75;
    text-transform: uppercase;
}

.item-price-insufficient {
    color: var(--error) !important;
    text-shadow: 0 0 12px rgba(255,95,95,0.3) !important;
}

.btn-buy {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-family: 'Cinzel', serif;
    font-size: 0.58rem;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    padding: 0.55rem 1.1rem;
    background: linear-gradient(135deg, #9a6418 0%, #d4a030 40%, #f0c060 50%, #d4a030 60%, #9a6418 100%);
    color: #1a0e00;
    border: none;
    cursor: pointer;
    clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%);
    box-shadow: 0 2px 20px rgba(200,144,40,0.4);
    transition: box-shadow 0.3s, transform 0.2s;
    position: relative;
    overflow: hidden;
    white-space: nowrap;
}
.btn-buy::before {
    content: '';
    position: absolute; inset: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transform: translateX(-100%) skewX(-20deg);
    transition: transform 0.5s;
}
.btn-buy:hover { box-shadow: 0 4px 30px rgba(200,144,40,0.65); transform: translateY(-2px); }
.btn-buy:hover::before { transform: translateX(150%) skewX(-20deg); }

.btn-buy:disabled, .btn-buy.disabled {
    background: rgba(30,33,70,0.6);
    color: rgba(168,180,208,0.4);
    box-shadow: none;
    cursor: not-allowed;
    clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%);
}
.btn-buy:disabled:hover { transform: none; }

.btn-login-to-buy {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-family: 'Cinzel', serif;
    font-size: 0.58rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    padding: 0.55rem 1.1rem;
    background: transparent;
    color: var(--arcane-bright);
    border: 1px solid rgba(136,144,255,0.35);
    clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%);
    text-decoration: none;
    transition: all 0.3s;
    white-space: nowrap;
}
.btn-login-to-buy:hover {
    border-color: var(--arcane-bright);
    box-shadow: 0 0 18px rgba(136,144,255,0.2);
}

/* ─── FLASH MESSAGES ───────────────────────────────────────────── */
.shop-alert {
    padding: 1rem 1.4rem;
    margin-bottom: 1.8rem;
    border-left: 3px solid;
    font-family: 'Crimson Pro', serif;
    font-size: 0.96rem;
    display: flex;
    align-items: center;
    gap: 0.8rem;
    background: rgba(9,12,34,0.75);
    backdrop-filter: blur(10px);
}
.shop-alert-success { border-color: var(--success); color: var(--success); }
.shop-alert-error   { border-color: var(--error);   color: var(--error); }
.shop-alert-info    { border-color: var(--info);     color: var(--info); }

/* ─── EMPTY STATE ──────────────────────────────────────────────── */
.shop-empty {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--silver);
    font-family: 'Crimson Pro', serif;
    font-size: 1rem;
    font-style: italic;
    opacity: 0.6;
    display: none;
}
.shop-empty.visible { display: block; }

/* ─── MODAL ACHAT ──────────────────────────────────────────────── */
.modal-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(2,3,12,0.85);
    backdrop-filter: blur(8px);
    z-index: 5000;
    align-items: center;
    justify-content: center;
}
.modal-overlay.open { display: flex; }

.modal-box {
    position: relative;
    background: rgba(9,12,34,0.97);
    border: 1px solid rgba(136,144,255,0.25);
    max-width: 480px;
    width: 92%;
    padding: 2.4rem 2.4rem 2rem;
    box-shadow: 0 30px 100px rgba(0,0,0,0.8), 0 0 60px rgba(90,48,212,0.25);
    animation: modalIn 0.3s cubic-bezier(.22,1,.36,1);
}

@keyframes modalIn {
    from { opacity: 0; transform: scale(0.94) translateY(12px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}

.modal-box::before {
    content: '';
    position: absolute;
    top: 0; right: 0;
    width: 0; height: 0;
    border-style: solid;
    border-width: 0 56px 56px 0;
    border-color: transparent rgba(240,192,96,0.2) transparent transparent;
}

.modal-close {
    position: absolute;
    top: 1rem; right: 1.2rem;
    background: none; border: none;
    color: var(--silver);
    font-size: 1.4rem;
    cursor: pointer;
    opacity: 0.5;
    transition: opacity 0.2s, color 0.2s;
    line-height: 1;
}
.modal-close:hover { opacity: 1; color: var(--error); }

.modal-title {
    font-family: 'Cinzel Decorative', serif;
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--white);
    margin-bottom: 0.4rem;
}

.modal-item-name {
    font-family: 'Cinzel', serif;
    font-size: 0.75rem;
    letter-spacing: 0.08em;
    color: var(--gold-bright);
    margin-bottom: 1.4rem;
}

.modal-cost-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.9rem 1rem;
    background: rgba(26,29,90,0.3);
    border: 1px solid rgba(136,144,255,0.1);
    margin-bottom: 1.2rem;
}

.modal-cost-label {
    font-family: 'Cinzel', serif;
    font-size: 0.6rem;
    letter-spacing: 0.14em;
    color: var(--silver);
    text-transform: uppercase;
}

.modal-cost-value {
    font-family: 'Cinzel Decorative', serif;
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--gold-bright);
    text-shadow: 0 0 16px rgba(240,192,96,0.4);
}

.modal-balance {
    font-family: 'Cinzel', serif;
    font-size: 0.58rem;
    letter-spacing: 0.1em;
    color: var(--silver);
    text-align: right;
    margin-top: -0.8rem;
    margin-bottom: 1.2rem;
    opacity: 0.65;
}
.modal-balance span { color: var(--gold-bright); opacity: 1; }

.modal-char-group {
    margin-bottom: 1.4rem;
}
.modal-label {
    display: block;
    font-family: 'Cinzel', serif;
    font-size: 0.58rem;
    letter-spacing: 0.14em;
    color: var(--silver);
    text-transform: uppercase;
    margin-bottom: 0.5rem;
}
.modal-select {
    width: 100%;
    background: rgba(15,18,48,0.9);
    border: 1px solid rgba(136,144,255,0.2);
    color: var(--silver-bright);
    font-family: 'Cinzel', serif;
    font-size: 0.7rem;
    padding: 0.65rem 0.9rem;
    outline: none;
    transition: border-color 0.3s;
    -webkit-appearance: none;
    appearance: none;
}
.modal-select:focus { border-color: rgba(136,144,255,0.5); }
.modal-select option { background: #0f1230; }

.modal-actions {
    display: flex;
    gap: 0.7rem;
    justify-content: flex-end;
}

.btn-cancel {
    font-family: 'Cinzel', serif;
    font-size: 0.6rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    padding: 0.6rem 1.2rem;
    background: transparent;
    border: 1px solid rgba(136,144,255,0.2);
    color: var(--silver);
    cursor: pointer;
    clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%);
    transition: all 0.2s;
}
.btn-cancel:hover { border-color: rgba(136,144,255,0.4); color: var(--white); }

.btn-confirm {
    font-family: 'Cinzel', serif;
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    padding: 0.6rem 1.4rem;
    background: linear-gradient(135deg, #9a6418, #d4a030, #f0c060, #d4a030, #9a6418);
    color: #1a0e00;
    border: none;
    cursor: pointer;
    clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%);
    box-shadow: 0 2px 20px rgba(200,144,40,0.4);
    transition: all 0.2s;
}
.btn-confirm:hover { box-shadow: 0 4px 30px rgba(200,144,40,0.65); transform: translateY(-1px); }

/* ─── INFO RECHARGEMENT ────────────────────────────────────────── */
.recharge-info {
    margin-top: 3.5rem;
    padding: 2rem;
    background: rgba(9,12,34,0.7);
    border: 1px solid rgba(240,192,96,0.12);
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    position: relative;
    overflow: hidden;
}

.recharge-info::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at left, rgba(200,144,40,0.05) 0%, transparent 60%);
    pointer-events: none;
}

.recharge-step {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}

.recharge-num {
    font-family: 'Cinzel Decorative', serif;
    font-size: 1.6rem;
    font-weight: 900;
    color: rgba(240,192,96,0.2);
    line-height: 1;
    flex-shrink: 0;
}

.recharge-text h4 {
    font-family: 'Cinzel', serif;
    font-size: 0.68rem;
    letter-spacing: 0.1em;
    color: var(--gold-bright);
    margin-bottom: 0.35rem;
    text-transform: uppercase;
}

.recharge-text p {
    font-family: 'Crimson Pro', serif;
    font-size: 0.88rem;
    color: var(--silver);
    line-height: 1.6;
    font-style: italic;
}

/* ─── TOTAL ITEMS BADGE ────────────────────────────────────────── */
.filter-count {
    font-family: 'Cinzel', serif;
    font-size: 0.56rem;
    letter-spacing: 0.1em;
    color: var(--silver);
    opacity: 0.5;
    text-align: right;
    margin-bottom: 1rem;
}

/* ─── REVEAL ANIMATIONS ────────────────────────────────────────── */
.reveal {
    opacity: 0;
    transform: translateY(18px);
    transition: opacity 0.55s ease, transform 0.55s ease;
}
.reveal.visible { opacity: 1; transform: translateY(0); }

@media (max-width: 640px) {
    .shop-page { padding: 5rem 1rem 4rem; }
    .shop-grid { grid-template-columns: 1fr; }
    .modal-box { padding: 1.8rem 1.4rem 1.4rem; }
}
</style>

<main>
<div class="shop-page">

    <!-- ── HERO ─────────────────────────────────────────────── -->
    <div class="shop-hero reveal">
        <p class="shop-eyebrow">✦ Marchands de l'Éther ✦</p>
        <h1 class="shop-title">La <span>Boutique</span> des Héros</h1>
        <p class="shop-subtitle">Montures légendaires, familiers enchantés, équipements rares... Le néant a ses marchands, et ils acceptent les Eons Points.</p>
        <div class="shop-divider"><div class="shop-divider-gem"></div></div>
    </div>

    <!-- ── ALERTES ───────────────────────────────────────────── -->
    <?php if ($flashMsg): ?>
    <div class="shop-alert shop-alert-<?= htmlspecialchars($flashType) ?>">
        <span><?= $flashMsg ?></span>
    </div>
    <?php endif; ?>
    <?php foreach ($errors as $err): ?>
    <div class="shop-alert shop-alert-error">
        <span>✖ <?= htmlspecialchars($err) ?></span>
    </div>
    <?php endforeach; ?>

    <!-- ── SOLDE ─────────────────────────────────────────────── -->
    <div class="dp-balance-bar reveal">
        <?php if ($isLoggedIn): ?>
        <div class="dp-badge">
            <span class="dp-badge-icon">💎</span>
            <div>
                <div class="dp-badge-label">Donor Points</div>
                <div class="dp-badge-amount"><?= number_format($playerDp, 0, ',', ' ') ?> <span class="dp-badge-unit">DP</span></div>
            </div>
        </div>
        <div class="vp-badge">
            <span class="dp-badge-icon">🗳️</span>
            <div>
                <div class="dp-badge-label">Vote Points</div>
                <div class="vp-badge-amount"><?= number_format($playerVp, 0, ',', ' ') ?> <span class="vp-badge-unit">VP</span></div>
            </div>
        </div>
        <a href="#recharge" class="dp-recharge-link">+ Recharger des points</a>
        <?php else: ?>
        <div class="dp-badge">
            <span class="dp-badge-icon">🔒</span>
            <div>
                <div class="dp-badge-label">Connectez-vous pour acheter</div>
                <div style="font-family:'Cinzel',serif;font-size:.7rem;color:var(--silver);margin-top:.2rem;">
                    <a href="auth.php" style="color:var(--arcane-bright);text-decoration:none;">Se connecter</a>
                    &nbsp;·&nbsp;
                    <a href="auth.php#register" style="color:var(--gold);text-decoration:none;">Créer un compte</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── FILTRES ───────────────────────────────────────────── -->
    <div class="shop-filters reveal">
        <?php foreach ($categories as $catKey => $cat): ?>
        <button class="filter-btn <?= $catKey === 'tous' ? 'active' : '' ?>"
                onclick="filterShop('<?= $catKey ?>', this)">
            <?= $cat['icon'] ?> <?= htmlspecialchars($cat['label']) ?>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- ── GRID ──────────────────────────────────────────────── -->
    <div class="filter-count" id="items-count"><?= count($catalog) ?> articles disponibles</div>

    <div class="shop-grid" id="shop-grid">
        <?php foreach ($catalog as $item):
            $itemCur     = strtolower($item['currency'] ?? 'dp');
            $balance     = ($itemCur === 'vp') ? $playerVp : $playerDp;
            $curLabel    = ($itemCur === 'vp') ? 'VP' : 'DP';
            $insufficient = $isLoggedIn && $balance < $item['price'];
            $wowheadUrl  = ($item['game_item_id'] > 0)
                           ? 'https://www.wowhead.com/wotlk/fr/item=' . (int)$item['game_item_id']
                           : null;
        ?>
        <div class="item-card reveal" data-category="<?= htmlspecialchars($item['category']) ?>">

            <?php if (!empty($item['ribbon'])): ?>
            <div class="item-ribbon"><?= htmlspecialchars($item['ribbon']) ?></div>
            <?php endif; ?>

            <div class="item-icon-wrap">
                <span class="item-icon"><?= $item['icon'] ?></span>
                <?php if (!empty($item['badge'])): ?>
                <span class="item-badge" style="color:<?= htmlspecialchars($item['badge_color'] ?? '#a8b4d0') ?>;border-color:<?= htmlspecialchars($item['badge_color'] ?? '#a8b4d0') ?>44">
                    <?= htmlspecialchars($item['badge']) ?>
                </span>
                <?php endif; ?>
            </div>

            <div class="item-content">
                <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                <div class="item-desc"><?= htmlspecialchars($item['desc']) ?></div>
            </div>

            <div class="item-footer">
                <div class="item-price">
                    <span class="item-price-amount <?= $insufficient ? 'item-price-insufficient' : '' ?>">
                        <?= number_format($item['price'], 0, ',', ' ') ?>
                    </span>
                    <span class="item-price-unit" style="<?= $itemCur === 'vp' ? 'color:#6edf8a;' : '' ?>"><?= $curLabel ?></span>
                </div>

                <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                    <?php if ($wowheadUrl): ?>
                    <a href="<?= htmlspecialchars($wowheadUrl) ?>" target="_blank" rel="noopener" class="btn-wowhead"
                       title="Voir sur Wowhead">🔍 Wowhead</a>
                    <?php endif; ?>

                    <?php if ($isLoggedIn): ?>
                        <button class="btn-buy <?= $insufficient ? 'disabled' : '' ?>"
                                <?= $insufficient ? 'disabled' : '' ?>
                                onclick="openModal(
                                    '<?= htmlspecialchars(addslashes($item['id'])) ?>',
                                    '<?= htmlspecialchars(addslashes($item['name'])) ?>',
                                    <?= (int)$item['price'] ?>,
                                    '<?= $curLabel ?>'
                                )">
                            ✦ Acheter
                        </button>
                    <?php else: ?>
                        <a href="auth.php" class="btn-login-to-buy">🔒 Connexion</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="shop-empty" id="shop-empty">
        <p>Aucun article dans cette catégorie pour l'instant.</p>
    </div>

    <!-- ── RECHARGE INFO ─────────────────────────────────────── -->
    <div class="recharge-info reveal" id="recharge">
        <div class="recharge-step">
            <span class="recharge-num">01</span>
            <div class="recharge-text">
                <h4>Rejoindre la communauté</h4>
                <p>Participez à nos événements Discord, soutenez le serveur via Patreon, ou suivez nos annonces pour obtenir des Eons Points gratuitement.</p>
            </div>
        </div>
        <div class="recharge-step">
            <span class="recharge-num">02</span>
            <div class="recharge-text">
                <h4>Points crédités</h4>
                <p>Vos DP sont ajoutés manuellement par l'équipe Eons. Un e-mail de confirmation vous est envoyé après validation.</p>
            </div>
        </div>
        <div class="recharge-step">
            <span class="recharge-num">03</span>
            <div class="recharge-text">
                <h4>Boutique & Jeu</h4>
                <p>Achetez ici, reconnectez-vous au jeu, et profitez de votre récompense. Simple, rapide, magique.</p>
            </div>
        </div>
    </div>

</div>
</main>

<!-- ── MODAL CONFIRMATION ACHAT ──────────────────────────────────── -->
<div class="modal-overlay" id="buy-modal" onclick="closeModalOnOverlay(event)">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()">✕</button>

        <div class="modal-title">Confirmer l'achat</div>
        <div class="modal-item-name" id="modal-item-name">—</div>

        <div class="modal-cost-row">
            <span class="modal-cost-label">Coût</span>
            <span class="modal-cost-value" id="modal-item-cost">—</span>
        </div>
        <div class="modal-balance">
            Solde après achat : <span id="modal-balance-after">—</span>
        </div>

        <form method="POST" action="boutique.php" id="buy-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="buy">
            <input type="hidden" name="item_id" id="modal-item-id" value="">

            <?php if (!empty($playerChars)): ?>
            <div class="modal-char-group">
                <label class="modal-label" for="modal-char">Personnage destinataire <span style="color:var(--error)">*</span></label>
                <select name="char_name" id="modal-char" class="modal-select" required>
                    <option value="" disabled selected>— Choisir un personnage —</option>
                    <?php foreach ($playerChars as $ch):
                        $clr = $classColors[(int)$ch['class']] ?? '#a8b4d0';
                    ?>
                    <option value="<?= htmlspecialchars($ch['name']) ?>">
                        <?= htmlspecialchars($ch['name']) ?> (Niv. <?= (int)$ch['level'] ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <p style="font-family:'Crimson Pro',serif;font-size:.78rem;color:var(--silver);opacity:.6;margin-top:.4rem;font-style:italic;">
                    L'item sera envoyé par mail in-game à ce personnage.
                </p>
            </div>
            <?php else: ?>
            <div class="modal-char-group">
                <p style="font-family:'Crimson Pro',serif;font-size:.85rem;color:var(--error);font-style:italic;">
                    ⚠ Aucun personnage trouvé. Connectez-vous au jeu pour en créer un avant d'acheter.
                </p>
            </div>
            <input type="hidden" name="char_name" value="">
            <?php endif; ?>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">Annuler</button>
                <button type="submit" class="btn-confirm">⚔ Confirmer</button>
            </div>
        </form>
    </div>
</div>

<script>
// ─── FILTRE CATÉGORIES ────────────────────────────────────────────
function filterShop(cat, btn) {
    // Update active button
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const cards = document.querySelectorAll('#shop-grid .item-card');
    let visible = 0;

    cards.forEach(card => {
        const match = cat === 'tous' || card.dataset.category === cat;
        card.style.display = match ? '' : 'none';
        if (match) visible++;
    });

    document.getElementById('items-count').textContent = visible + ' article' + (visible > 1 ? 's' : '') + ' disponible' + (visible > 1 ? 's' : '');
    document.getElementById('shop-empty').classList.toggle('visible', visible === 0);
}

// ─── MODAL ───────────────────────────────────────────────────────
const playerDp = <?= $isLoggedIn ? (int)$playerDp : 0 ?>;
const playerVp = <?= $isLoggedIn ? (int)$playerVp : 0 ?>;

function openModal(itemId, itemName, itemCost, currency) {
    document.getElementById('modal-item-id').value   = itemId;
    document.getElementById('modal-item-name').textContent = itemName;
    document.getElementById('modal-item-cost').textContent = itemCost.toLocaleString('fr-FR') + ' ' + currency;
    const balance = (currency === 'VP') ? playerVp : playerDp;
    const after   = balance - itemCost;
    const afterEl = document.getElementById('modal-balance-after');
    afterEl.textContent = after.toLocaleString('fr-FR') + ' ' + currency;
    afterEl.style.color = after >= 0 ? (currency === 'VP' ? '#6edf8a' : 'var(--gold-bright)') : 'var(--error)';
    document.getElementById('buy-modal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('buy-modal').classList.remove('open');
    document.body.style.overflow = '';
}

function closeModalOnOverlay(e) {
    if (e.target === document.getElementById('buy-modal')) closeModal();
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

// ─── SCROLL REVEAL ───────────────────────────────────────────────
(function() {
    const obs = new IntersectionObserver(entries => {
        entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); obs.unobserve(e.target); }});
    }, { threshold: 0.08 });
    document.querySelectorAll('.reveal').forEach(el => obs.observe(el));
})();
</script>

</body>
</html>
