<?php
// ============================================================
//  don.php — Eons CMS | Arcanic Theme
//  Achat de Donor Points via Stripe Checkout
//
//  ── SETUP STRIPE ──────────────────────────────────────────
//  1. Installer le SDK Stripe via Composer :
//       composer require stripe/stripe-php
//     OU télécharger stripe-php et inclure le autoload.php
//
//  2. Remplacer les placeholders dans config.php :
//       define('STRIPE_PUBLIC_KEY', 'pk_live_XXXXXXXXXXXXXXXX');
//       define('STRIPE_SECRET_KEY', 'sk_live_XXXXXXXXXXXXXXXX');
//       define('STRIPE_WEBHOOK_SECRET', 'whsec_XXXXXXXXXXXXXXXX');
//
//  3. Dans le dashboard Stripe, configurer le webhook :
//       URL    : https://eons-world.eu/don_webhook.php
//       Événement : checkout.session.completed
//
//  ── SQL REQUIS ────────────────────────────────────────────
//  CREATE TABLE IF NOT EXISTS `dp_donations` (
//    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
//    `account_id`      INT UNSIGNED NOT NULL,
//    `stripe_session`  VARCHAR(128) NOT NULL,
//    `tier_id`         VARCHAR(32)  NOT NULL,
//    `amount_eur`      SMALLINT UNSIGNED NOT NULL,
//    `dp_granted`      INT UNSIGNED NOT NULL,
//    `status`          ENUM('pending','completed','failed') NOT NULL DEFAULT 'pending',
//    `created_at`      DATETIME NOT NULL DEFAULT NOW(),
//    `completed_at`    DATETIME NULL DEFAULT NULL,
//    PRIMARY KEY (`id`),
//    UNIQUE KEY `idx_session` (`stripe_session`),
//    KEY `idx_account` (`account_id`)
//  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
// ============================================================
require_once __DIR__ . '/config.php';

// ── Clés Stripe (à définir dans config.php) ──────────────────
if (!defined('STRIPE_PUBLIC_KEY'))    define('STRIPE_PUBLIC_KEY',    'pk_test_REMPLACER_PAR_VOTRE_CLE_PUBLIQUE');
if (!defined('STRIPE_SECRET_KEY'))    define('STRIPE_SECRET_KEY',    'sk_test_REMPLACER_PAR_VOTRE_CLE_SECRETE');
if (!defined('STRIPE_WEBHOOK_SECRET'))define('STRIPE_WEBHOOK_SECRET','whsec_REMPLACER_PAR_VOTRE_WEBHOOK_SECRET');

// ── Paliers de don ────────────────────────────────────────────
$tiers = [
    [
        'id'          => 'ecuyer',
        'name'        => 'Écuyer',
        'icon'        => '🛡️',
        'price_eur'   => 5,
        'dp'          => 500,
        'bonus_pct'   => 0,
        'color'       => 'rgba(168,180,208,0.7)',
        'border'      => 'rgba(168,180,208,0.25)',
        'glow'        => 'rgba(168,180,208,0.15)',
        'description' => 'Pour démarrer l\'aventure',
    ],
    [
        'id'          => 'chevalier',
        'name'        => 'Chevalier',
        'icon'        => '⚔️',
        'price_eur'   => 10,
        'dp'          => 1100,
        'bonus_pct'   => 10,
        'color'       => 'rgba(136,144,255,0.9)',
        'border'      => 'rgba(136,144,255,0.3)',
        'glow'        => 'rgba(136,144,255,0.2)',
        'description' => 'Le choix des aventuriers',
        'ribbon'      => 'Populaire',
    ],
    [
        'id'          => 'champion',
        'name'        => 'Champion',
        'icon'        => '🏆',
        'price_eur'   => 20,
        'dp'          => 2400,
        'bonus_pct'   => 20,
        'color'       => 'rgba(90,48,212,0.9)',
        'border'      => 'rgba(90,48,212,0.45)',
        'glow'        => 'rgba(90,48,212,0.25)',
        'description' => 'Pour les vrais guerriers',
    ],
    [
        'id'          => 'heros',
        'name'        => 'Héros',
        'icon'        => '🌟',
        'price_eur'   => 50,
        'dp'          => 6500,
        'bonus_pct'   => 30,
        'color'       => 'rgba(240,192,96,0.9)',
        'border'      => 'rgba(240,192,96,0.4)',
        'glow'        => 'rgba(240,192,96,0.25)',
        'description' => 'Maîtrisez le champ de bataille',
        'ribbon'      => 'Meilleure valeur',
    ],
    [
        'id'          => 'legende',
        'name'        => 'Légende',
        'icon'        => '👑',
        'price_eur'   => 100,
        'dp'          => 14000,
        'bonus_pct'   => 40,
        'color'       => 'rgba(255,140,80,0.9)',
        'border'      => 'rgba(255,140,80,0.45)',
        'glow'        => 'rgba(255,140,80,0.3)',
        'description' => 'L\'élite absolue d\'Eons',
        'ribbon'      => 'Premium',
    ],
];

$isLoggedIn = !empty($_SESSION['logged_in']) && !empty($_SESSION['account_id']);
$accountId  = $isLoggedIn ? (int)$_SESSION['account_id'] : 0;

// ── Solde DP actuel ───────────────────────────────────────────
$playerDp = 0;
if ($isLoggedIn) {
    try {
        $db = getAuthDB();
        $s  = $db->prepare("SELECT dp FROM account WHERE id = :id LIMIT 1");
        $s->execute([':id' => $accountId]);
        $r = $s->fetch();
        $playerDp = $r ? (int)($r['dp'] ?? 0) : 0;
    } catch (PDOException $e) {
        error_log('[Don] Lecture solde: ' . $e->getMessage());
    }
}

// ── Création session Stripe Checkout ─────────────────────────
$stripeError = '';
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tier_id'])) {

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $stripeError = 'Token de sécurité invalide. Rechargez la page.';
    } else {
        $tierId = $_POST['tier_id'];
        $tier   = null;
        foreach ($tiers as $t) { if ($t['id'] === $tierId) { $tier = $t; break; } }

        if (!$tier) {
            $stripeError = 'Palier de don invalide.';
        } else {
            // Vérifier que le SDK Stripe est disponible
            $stripeAutoload = __DIR__ . '/vendor/autoload.php';
            if (!file_exists($stripeAutoload)) {
                $stripeError = 'SDK Stripe non installé. Lancez <code>composer require stripe/stripe-php</code> sur le serveur.';
            } elseif (strpos(STRIPE_SECRET_KEY, 'REMPLACER') !== false) {
                $stripeError = 'Les clés Stripe ne sont pas encore configurées dans config.php.';
            } else {
                require_once $stripeAutoload;
                \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

                try {
                    $baseUrl = rtrim(SITE_URL, '/');
                    $session = \Stripe\Checkout\Session::create([
                        'payment_method_types' => ['card'],
                        'line_items'           => [[
                            'price_data' => [
                                'currency'     => 'eur',
                                'unit_amount'  => $tier['price_eur'] * 100, // centimes
                                'product_data' => [
                                    'name'        => 'Eons — ' . $tier['name'] . ' (' . number_format($tier['dp'], 0, ',', ' ') . ' DP)',
                                    'description' => $tier['description'],
                                ],
                            ],
                            'quantity'   => 1,
                        ]],
                        'mode'              => 'payment',
                        'success_url'       => $baseUrl . '/don.php?success=1&session_id={CHECKOUT_SESSION_ID}',
                        'cancel_url'        => $baseUrl . '/don.php?cancelled=1',
                        'metadata'          => [
                            'account_id' => $accountId,
                            'tier_id'    => $tier['id'],
                            'dp_amount'  => $tier['dp'],
                        ],
                        'customer_email'    => $_SESSION['account_email'] ?? null,
                    ]);

                    // Enregistrer la session en DB (status pending)
                    try {
                        $db = getAuthDB();
                        $db->prepare(
                            "INSERT INTO dp_donations (account_id, stripe_session, tier_id, amount_eur, dp_granted, status)
                             VALUES (:aid, :sess, :tier, :eur, :dp, 'pending')"
                        )->execute([
                            ':aid'  => $accountId,
                            ':sess' => $session->id,
                            ':tier' => $tier['id'],
                            ':eur'  => $tier['price_eur'],
                            ':dp'   => $tier['dp'],
                        ]);
                    } catch (PDOException $e) {
                        error_log('[Don] Insert pending: ' . $e->getMessage());
                        // Non bloquant — le webhook gérera la mise à jour
                    }

                    header('Location: ' . $session->url);
                    exit;

                } catch (\Stripe\Exception\ApiErrorException $e) {
                    error_log('[Don] Stripe API: ' . $e->getMessage());
                    $stripeError = 'Erreur Stripe : ' . htmlspecialchars($e->getMessage());
                }
            }
        }
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf_token'];

// ── Messages retour ───────────────────────────────────────────
$successMsg   = '';
$cancelledMsg = '';
if (isset($_GET['success']) && $_GET['success'] === '1') {
    $successMsg = '✦ Merci pour votre don ! Vos <strong>Donor Points</strong> seront crédités dans quelques instants.';
}
if (isset($_GET['cancelled']) && $_GET['cancelled'] === '1') {
    $cancelledMsg = 'Paiement annulé. Vous pouvez réessayer à tout moment.';
}

// ── Historique des dons du joueur ─────────────────────────────
$donHistory = [];
if ($isLoggedIn) {
    try {
        $db = getAuthDB();
        $s  = $db->prepare(
            "SELECT tier_id, amount_eur, dp_granted, status, created_at, completed_at
             FROM dp_donations
             WHERE account_id = :id
             ORDER BY created_at DESC
             LIMIT 10"
        );
        $s->execute([':id' => $accountId]);
        $donHistory = $s->fetchAll();
    } catch (PDOException $e) {
        // Silencieux — table peut ne pas encore exister
    }
}

$pageTitle = 'Donner — Eons';
require_once __DIR__ . '/header.php';
?>

<style>
/* ─── PAGE DON ──────────────────────────────────────────────── */
.don-page {
    position: relative; z-index: 10;
    padding: 5.5rem 2rem 5rem;
    max-width: 1100px;
    margin: 0 auto;
}

/* ─── HERO ──────────────────────────────────────────────────── */
.don-hero {
    text-align: center;
    padding: 3.5rem 1rem 3rem;
    position: relative;
    margin-bottom: 2.5rem;
}
.don-hero::before {
    content: '';
    position: absolute; top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 600px; height: 300px;
    background: radial-gradient(ellipse, rgba(240,192,96,0.1) 0%, rgba(90,48,212,0.08) 40%, transparent 70%);
    pointer-events: none;
}
.don-eyebrow {
    font-family: 'Cinzel', serif;
    font-size: 0.65rem; letter-spacing: 0.45em;
    color: var(--gold); text-transform: uppercase;
    margin-bottom: 1rem;
}
.don-title {
    font-family: 'Cinzel Decorative', serif;
    font-size: clamp(1.8rem, 4vw, 2.8rem);
    font-weight: 900; color: var(--white);
    text-shadow: 0 0 60px rgba(240,192,96,0.2), 0 0 120px rgba(90,48,212,0.15);
    margin-bottom: 1rem;
}
.don-title span {
    background: linear-gradient(135deg, var(--gold) 0%, var(--gold-bright) 50%, var(--gold) 100%);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}
.don-subtitle {
    font-family: 'Crimson Pro', serif;
    font-size: 1.05rem; font-style: italic;
    color: var(--silver); max-width: 580px;
    margin: 0 auto 2rem; line-height: 1.7;
}
.don-divider {
    display: flex; align-items: center; justify-content: center; gap: 1rem;
    margin-bottom: 0.5rem;
}
.don-divider::before, .don-divider::after {
    content: ''; width: 80px; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(240,192,96,0.4));
}
.don-divider::after { transform: scaleX(-1); }
.don-divider-gem {
    width: 8px; height: 8px;
    background: var(--gold); transform: rotate(45deg);
    box-shadow: 0 0 14px rgba(240,192,96,0.7);
}

/* ─── ALERTES ───────────────────────────────────────────────── */
.don-alert {
    padding: 1rem 1.4rem; margin-bottom: 1.8rem;
    border-left: 3px solid;
    font-family: 'Crimson Pro', serif; font-size: 0.96rem;
    display: flex; align-items: center; gap: 0.8rem;
    background: rgba(9,12,34,0.75); backdrop-filter: blur(10px);
}
.don-alert-success { border-color: var(--success); color: var(--success); }
.don-alert-error   { border-color: var(--error);   color: var(--error); }
.don-alert-info    { border-color: var(--info);     color: var(--info); }

/* ─── SOLDE DP ──────────────────────────────────────────────── */
.don-balance-bar {
    display: flex; align-items: center; justify-content: center;
    gap: 1.2rem; flex-wrap: wrap; margin-bottom: 2.8rem;
}
.don-dp-badge {
    display: inline-flex; align-items: center; gap: 0.7rem;
    padding: 0.7rem 1.8rem;
    background: rgba(9,12,34,0.85);
    border: 1px solid rgba(240,192,96,0.35);
    clip-path: polygon(12px 0%, 100% 0%, calc(100% - 12px) 100%, 0% 100%);
    backdrop-filter: blur(10px);
}
.don-dp-label {
    font-family: 'Cinzel', serif; font-size: 0.58rem;
    letter-spacing: 0.2em; color: var(--silver); text-transform: uppercase;
}
.don-dp-amount {
    font-family: 'Cinzel Decorative', serif;
    font-size: 1.35rem; font-weight: 700; color: var(--gold-bright);
    text-shadow: 0 0 20px rgba(240,192,96,0.5);
}
.don-dp-unit {
    font-family: 'Cinzel', serif; font-size: 0.62rem;
    letter-spacing: 0.15em; color: var(--gold); opacity: 0.8;
}

/* ─── GRILLE DES PALIERS ────────────────────────────────────── */
.don-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(340px, 100%), 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
}

/* ─── CARTE PALIER ──────────────────────────────────────────── */
.tier-card {
    position: relative;
    background: rgba(9,12,34,0.82);
    border: 1px solid var(--tier-border, rgba(136,144,255,0.1));
    overflow: hidden;
    transition: transform 0.35s cubic-bezier(.22,1,.36,1), border-color 0.3s, box-shadow 0.35s;
    display: flex; flex-direction: column;
}
.tier-card::before {
    content: '';
    position: absolute; top: 0; right: 0;
    border-style: solid; border-width: 0 52px 52px 0;
    border-color: transparent var(--tier-glow, rgba(136,144,255,0.1)) transparent transparent;
    z-index: 2; transition: border-color 0.3s;
}
.tier-card::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at 30% 0%, var(--tier-glow, rgba(136,144,255,0.05)) 0%, transparent 60%);
    opacity: 0; transition: opacity 0.4s; pointer-events: none;
}
.tier-card:hover {
    transform: translateY(-6px);
    border-color: var(--tier-color, rgba(136,144,255,0.4));
    box-shadow: 0 20px 60px var(--tier-glow, rgba(136,144,255,0.15)), 0 0 0 1px var(--tier-glow, rgba(136,144,255,0.08)) inset;
}
.tier-card:hover::after { opacity: 1; }

/* Ribbon */
.tier-ribbon {
    position: absolute; top: 14px; right: -22px;
    transform: rotate(35deg);
    background: var(--tier-color, var(--gold));
    color: #0a0a1a;
    font-family: 'Cinzel', serif; font-size: 0.45rem;
    font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase;
    padding: 0.22rem 2.2rem;
    z-index: 5;
    box-shadow: 0 2px 8px rgba(0,0,0,0.4);
}

/* Header palier */
.tier-header {
    padding: 1.6rem 1.6rem 1rem;
    border-bottom: 1px solid rgba(136,144,255,0.07);
    display: flex; align-items: center; gap: 1.1rem;
    position: relative; z-index: 3;
}
.tier-icon {
    width: 58px; height: 58px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.8rem;
    background: rgba(15,18,48,0.7);
    border: 1px solid var(--tier-border, rgba(136,144,255,0.15));
    box-shadow: 0 0 16px var(--tier-glow, rgba(136,144,255,0.1)) inset;
}
.tier-info { flex: 1; }
.tier-name {
    font-family: 'Cinzel', serif; font-size: 1rem;
    font-weight: 700; letter-spacing: 0.1em;
    color: var(--tier-color, var(--white));
    text-shadow: 0 0 20px var(--tier-glow, transparent);
    margin-bottom: 0.2rem;
}
.tier-desc {
    font-family: 'Crimson Pro', serif; font-size: 0.82rem;
    font-style: italic; color: var(--silver); opacity: 0.65;
}

/* Body palier */
.tier-body {
    padding: 1.2rem 1.6rem;
    flex: 1; position: relative; z-index: 3;
}
.tier-dp-display {
    display: flex; align-items: baseline; gap: 0.4rem;
    margin-bottom: 0.6rem;
}
.tier-dp-amount {
    font-family: 'Cinzel Decorative', serif;
    font-size: 2rem; font-weight: 900;
    color: var(--tier-color, var(--gold-bright));
    text-shadow: 0 0 30px var(--tier-glow, transparent);
    line-height: 1;
}
.tier-dp-unit {
    font-family: 'Cinzel', serif; font-size: 0.65rem;
    letter-spacing: 0.15em; color: var(--silver); opacity: 0.6;
    text-transform: uppercase;
}
.tier-bonus {
    display: inline-flex; align-items: center; gap: 0.3rem;
    background: rgba(110,223,138,0.1);
    border: 1px solid rgba(110,223,138,0.25);
    padding: 0.15rem 0.55rem;
    font-family: 'Cinzel', serif; font-size: 0.5rem;
    letter-spacing: 0.1em; color: #6edf8a;
    text-transform: uppercase;
}

/* Footer palier */
.tier-footer {
    padding: 1rem 1.6rem 1.6rem;
    display: flex; align-items: center; justify-content: space-between;
    gap: 0.8rem; position: relative; z-index: 3;
}
.tier-price {
    font-family: 'Cinzel Decorative', serif;
    font-size: 1.6rem; font-weight: 900; color: var(--white);
}
.tier-price-currency {
    font-family: 'Cinzel', serif; font-size: 0.75rem;
    letter-spacing: 0.1em; color: var(--silver); opacity: 0.5;
}

/* Bouton payer */
.btn-pay {
    display: inline-flex; align-items: center; gap: 0.5rem;
    font-family: 'Cinzel', serif; font-size: 0.6rem;
    font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase;
    padding: 0.65rem 1.4rem;
    background: linear-gradient(135deg, rgba(26,29,90,0.9) 0%, rgba(90,48,212,0.6) 40%, var(--tier-color, rgba(136,144,255,0.8)) 50%, rgba(90,48,212,0.6) 60%, rgba(26,29,90,0.9) 100%);
    color: var(--white); border: none; cursor: pointer;
    clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%);
    box-shadow: 0 2px 20px var(--tier-glow, rgba(136,144,255,0.3));
    transition: all 0.25s; white-space: nowrap;
    position: relative; overflow: hidden;
}
.btn-pay::before {
    content: '';
    position: absolute; inset: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
    transform: translateX(-100%) skewX(-20deg); transition: transform 0.5s;
}
.btn-pay:hover {
    box-shadow: 0 4px 35px var(--tier-glow, rgba(136,144,255,0.5));
    transform: translateY(-2px);
}
.btn-pay:hover::before { transform: translateX(150%) skewX(-20deg); }

/* Bouton connexion */
.btn-login-tier {
    display: inline-flex; align-items: center; gap: 0.5rem;
    font-family: 'Cinzel', serif; font-size: 0.6rem;
    letter-spacing: 0.12em; text-transform: uppercase;
    padding: 0.65rem 1.4rem;
    background: transparent; color: var(--arcane-bright);
    border: 1px solid rgba(136,144,255,0.3);
    clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%);
    text-decoration: none; transition: all 0.25s; white-space: nowrap;
}
.btn-login-tier:hover {
    border-color: var(--arcane-bright);
    box-shadow: 0 0 18px rgba(136,144,255,0.2);
}

/* ─── STRIPE BADGE ──────────────────────────────────────────── */
.stripe-trust {
    display: flex; align-items: center; justify-content: center;
    gap: 0.8rem; flex-wrap: wrap;
    margin-bottom: 2.5rem;
    padding: 1rem;
    background: rgba(9,12,34,0.6);
    border: 1px solid rgba(136,144,255,0.07);
}
.stripe-trust-item {
    display: flex; align-items: center; gap: 0.4rem;
    font-family: 'Cinzel', serif; font-size: 0.52rem;
    letter-spacing: 0.1em; color: var(--silver); opacity: 0.55;
    text-transform: uppercase;
}
.stripe-trust-item span { font-size: 0.9rem; opacity: 1; }

/* ─── HISTORIQUE ────────────────────────────────────────────── */
.don-history {
    background: rgba(9,12,34,0.7);
    border: 1px solid rgba(136,144,255,0.08);
    padding: 1.8rem 2rem;
    margin-bottom: 2rem;
    position: relative; overflow: hidden;
}
.don-history::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at left, rgba(240,192,96,0.04) 0%, transparent 60%);
    pointer-events: none;
}
.don-history-title {
    font-family: 'Cinzel', serif; font-size: 0.62rem;
    letter-spacing: 0.25em; color: var(--gold);
    text-transform: uppercase; margin-bottom: 1.2rem;
    display: flex; align-items: center; gap: 0.6rem;
}
.don-history-title::after {
    content: ''; flex: 1; height: 1px;
    background: linear-gradient(90deg, rgba(240,192,96,0.2), transparent);
}
.don-history-table {
    width: 100%; border-collapse: collapse;
}
.don-history-table th {
    font-family: 'Cinzel', serif; font-size: 0.5rem;
    letter-spacing: 0.12em; color: var(--silver); opacity: 0.45;
    text-transform: uppercase; text-align: left;
    padding: 0 0 0.7rem 0; border-bottom: 1px solid rgba(136,144,255,0.06);
}
.don-history-table td {
    font-family: 'Crimson Pro', serif; font-size: 0.88rem;
    color: var(--silver); padding: 0.6rem 0;
    border-bottom: 1px solid rgba(136,144,255,0.04);
}
.don-history-table tr:last-child td { border-bottom: none; }
.history-status-completed {
    font-family: 'Cinzel', serif; font-size: 0.5rem;
    letter-spacing: 0.1em; text-transform: uppercase;
    color: var(--success); padding: 0.15rem 0.5rem;
    background: rgba(95,255,176,0.08); border: 1px solid rgba(95,255,176,0.2);
}
.history-status-pending {
    font-family: 'Cinzel', serif; font-size: 0.5rem;
    letter-spacing: 0.1em; text-transform: uppercase;
    color: var(--gold); padding: 0.15rem 0.5rem;
    background: rgba(240,192,96,0.08); border: 1px solid rgba(240,192,96,0.2);
}
.history-status-failed {
    font-family: 'Cinzel', serif; font-size: 0.5rem;
    letter-spacing: 0.1em; text-transform: uppercase;
    color: var(--error); padding: 0.15rem 0.5rem;
    background: rgba(255,95,95,0.08); border: 1px solid rgba(255,95,95,0.2);
}
.don-history-empty {
    font-family: 'Crimson Pro', serif; font-style: italic;
    font-size: 0.9rem; color: var(--silver); opacity: 0.4;
    text-align: center; padding: 1.5rem 0;
}

/* ─── SECTION INFO ──────────────────────────────────────────── */
.don-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem; margin-bottom: 2rem;
}
.don-info-item {
    background: rgba(9,12,34,0.7);
    border: 1px solid rgba(136,144,255,0.07);
    padding: 1.2rem 1.4rem;
    display: flex; align-items: flex-start; gap: 0.8rem;
}
.don-info-icon { font-size: 1.4rem; flex-shrink: 0; margin-top: 0.1rem; }
.don-info-text h4 {
    font-family: 'Cinzel', serif; font-size: 0.6rem;
    letter-spacing: 0.1em; color: var(--gold);
    text-transform: uppercase; margin-bottom: 0.3rem;
}
.don-info-text p {
    font-family: 'Crimson Pro', serif; font-size: 0.86rem;
    color: var(--silver); line-height: 1.6; font-style: italic;
}

/* ─── RESPONSIVE ────────────────────────────────────────────── */
@media (max-width: 640px) {
    .don-page { padding: 5rem 1rem 4rem; }
    .don-grid { grid-template-columns: 1fr; }
    .don-history { padding: 1.2rem; }
    .don-history-table th:nth-child(3),
    .don-history-table td:nth-child(3) { display: none; }
}
</style>

<main>
<div class="don-page">

    <!-- ── HERO ─────────────────────────────────────────────── -->
    <div class="don-hero reveal">
        <p class="don-eyebrow">✦ Soutenez le serveur ✦</p>
        <h1 class="don-title">Obtenir des <span>Donor Points</span></h1>
        <p class="don-subtitle">Chaque don soutient directement le serveur Eons et vous récompense en Donor Points, échangeables à la boutique.</p>
        <div class="don-divider"><div class="don-divider-gem"></div></div>
    </div>

    <!-- ── ALERTES ──────────────────────────────────────────── -->
    <?php if ($successMsg): ?>
    <div class="don-alert don-alert-success">
        <span>✦</span><span><?= $successMsg ?></span>
    </div>
    <?php endif; ?>
    <?php if ($cancelledMsg): ?>
    <div class="don-alert don-alert-info">
        <span>ℹ</span><span><?= htmlspecialchars($cancelledMsg) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($stripeError): ?>
    <div class="don-alert don-alert-error">
        <span>✖</span><span><?= $stripeError ?></span>
    </div>
    <?php endif; ?>

    <!-- ── SOLDE DP ──────────────────────────────────────────── -->
    <?php if ($isLoggedIn): ?>
    <div class="don-balance-bar reveal">
        <div class="don-dp-badge">
            <span style="font-size:1.2rem">💎</span>
            <div>
                <div class="don-dp-label">Vos Donor Points</div>
                <div class="don-dp-amount">
                    <?= number_format($playerDp, 0, ',', ' ') ?>
                    <span class="don-dp-unit">DP</span>
                </div>
            </div>
        </div>
        <a href="boutique.php" style="font-family:'Cinzel',serif;font-size:0.58rem;letter-spacing:0.14em;color:var(--gold);text-decoration:none;text-transform:uppercase;border-bottom:1px solid rgba(240,192,96,0.3);transition:all 0.3s;">
            ↗ Dépenser à la Boutique
        </a>
    </div>
    <?php endif; ?>

    <!-- ── GRILLE DES PALIERS ────────────────────────────────── -->
    <div class="don-grid">
        <?php foreach ($tiers as $tier): ?>
        <div class="tier-card reveal"
             style="--tier-color:<?= $tier['color'] ?>;--tier-border:<?= $tier['border'] ?>;--tier-glow:<?= $tier['glow'] ?>;">

            <?php if (!empty($tier['ribbon'])): ?>
            <div class="tier-ribbon" style="background:<?= $tier['color'] ?>;color:#0a0a1a;">
                <?= htmlspecialchars($tier['ribbon']) ?>
            </div>
            <?php endif; ?>

            <!-- Header -->
            <div class="tier-header">
                <div class="tier-icon"><?= $tier['icon'] ?></div>
                <div class="tier-info">
                    <div class="tier-name"><?= htmlspecialchars($tier['name']) ?></div>
                    <div class="tier-desc"><?= htmlspecialchars($tier['description']) ?></div>
                </div>
            </div>

            <!-- Body -->
            <div class="tier-body">
                <div class="tier-dp-display">
                    <span class="tier-dp-amount"><?= number_format($tier['dp'], 0, ',', ' ') ?></span>
                    <span class="tier-dp-unit">Donor Points</span>
                </div>
                <?php if ($tier['bonus_pct'] > 0): ?>
                <span class="tier-bonus">🎁 +<?= $tier['bonus_pct'] ?>% bonus inclus</span>
                <?php endif; ?>
            </div>

            <!-- Footer -->
            <div class="tier-footer">
                <div>
                    <span class="tier-price-currency">EUR </span>
                    <span class="tier-price"><?= $tier['price_eur'] ?>€</span>
                </div>

                <?php if (!$isLoggedIn): ?>
                    <a href="auth.php" class="btn-login-tier">🔒 Connexion</a>
                <?php else: ?>
                    <form method="POST" action="don.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="tier_id"    value="<?= htmlspecialchars($tier['id']) ?>">
                        <button type="submit" class="btn-pay">
                            💳 Payer avec Stripe
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── BADGES DE CONFIANCE ───────────────────────────────── -->
    <div class="stripe-trust reveal">
        <div class="stripe-trust-item"><span>🔒</span> Paiement sécurisé SSL</div>
        <div class="stripe-trust-item"><span>💳</span> Powered by Stripe</div>
        <div class="stripe-trust-item"><span>🛡️</span> Aucune donnée bancaire stockée</div>
        <div class="stripe-trust-item"><span>⚡</span> DP crédités automatiquement</div>
    </div>

    <!-- ── INFOS ─────────────────────────────────────────────── -->
    <div class="don-info reveal">
        <div class="don-info-item">
            <div class="don-info-icon">💎</div>
            <div class="don-info-text">
                <h4>Donor Points</h4>
                <p>Les DP s'ajoutent instantanément à votre compte après confirmation du paiement par Stripe.</p>
            </div>
        </div>
        <div class="don-info-item">
            <div class="don-info-icon">🎁</div>
            <div class="don-info-text">
                <h4>Bonus inclus</h4>
                <p>Plus votre don est élevé, plus le bonus est généreux. Jusqu'à +40% de DP offerts.</p>
            </div>
        </div>
        <div class="don-info-item">
            <div class="don-info-icon">🔒</div>
            <div class="don-info-text">
                <h4>Sécurisé par Stripe</h4>
                <p>Vos données bancaires ne transitent jamais par nos serveurs. Stripe gère l'intégralité du paiement.</p>
            </div>
        </div>
        <div class="don-info-item">
            <div class="don-info-icon">🛒</div>
            <div class="don-info-text">
                <h4>Dépensez à la Boutique</h4>
                <p>Utilisez vos DP pour acquérir montures, familiers et services exclusifs sur la <a href="boutique.php" style="color:var(--gold);">page Boutique</a>.</p>
            </div>
        </div>
    </div>

    <!-- ── HISTORIQUE ────────────────────────────────────────── -->
    <?php if ($isLoggedIn): ?>
    <div class="don-history reveal">
        <div class="don-history-title">✦ Historique de vos dons</div>
        <?php if (empty($donHistory)): ?>
            <div class="don-history-empty">Aucun don effectué pour le moment.</div>
        <?php else: ?>
        <table class="don-history-table">
            <thead>
                <tr>
                    <th>Palier</th>
                    <th>Montant</th>
                    <th>DP octroyés</th>
                    <th>Date</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($donHistory as $row): ?>
                <tr>
                    <td><?= htmlspecialchars(ucfirst($row['tier_id'])) ?></td>
                    <td><?= (int)$row['amount_eur'] ?>€</td>
                    <td style="color:var(--gold-bright);font-family:'Cinzel',serif;font-size:0.82rem;">
                        +<?= number_format((int)$row['dp_granted'], 0, ',', ' ') ?> DP
                    </td>
                    <td style="opacity:0.55;font-size:0.8rem;">
                        <?= date('d/m/Y H:i', strtotime($row['created_at'])) ?>
                    </td>
                    <td>
                        <?php
                        $statusLabels = ['completed' => 'Complété', 'pending' => 'En attente', 'failed' => 'Échoué'];
                        $label = $statusLabels[$row['status']] ?? $row['status'];
                        ?>
                        <span class="history-status-<?= htmlspecialchars($row['status']) ?>">
                            <?= htmlspecialchars($label) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>
</main>

<footer>
    <div class="footer-logo">Eons</div>
    <p class="footer-tagline">Forgé dans les étoiles. Joué par des légendes.</p>
    <div class="footer-links">
        <a href="index.php">Accueil</a>
		<a href="actualites.php">Actualités</a>
		<a href="royaumes.php">Royaumes</a>
        <a href="classements.php">Classements</a>
        <a href="https://discord.com/invite/KrQsUdUz8W">Discord</a>
    </div>
    <div class="footer-sep"><div class="footer-gem"></div></div>
    <p class="footer-bottom">© <?= date('Y') ?> <span>Eons</span> · World of Warcraft 3.3.5a · Tous droits réservés</p>
</footer>

</body>
</html>
