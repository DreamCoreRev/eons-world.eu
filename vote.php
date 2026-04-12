<?php
// ============================================================
//  vote.php — Eons CMS | Arcanic Theme
//  Système de vote — récompenses en VP (Vote Points)
//
//  ── FLUX DE VOTE ──────────────────────────────────────────
//  1. Le joueur clique sur un site de vote
//  2. La page s'ouvre dans un nouvel onglet (lien externe)
//  3. Après la période de cooldown, un bouton "Réclamer"
//     apparaît et crédite les VP en DB
//  4. Log de l'action dans vp_vote_log
//
//  ── SQL REQUIS ────────────────────────────────────────────
//  CREATE TABLE IF NOT EXISTS `vp_vote_log` (
//    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
//    `account_id` INT UNSIGNED NOT NULL,
//    `site_id`    TINYINT UNSIGNED NOT NULL,
//    `vp_reward`  SMALLINT UNSIGNED NOT NULL,
//    `voted_at`   DATETIME NOT NULL DEFAULT NOW(),
//    PRIMARY KEY (`id`),
//    KEY `idx_acc_site` (`account_id`, `site_id`)
//  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
//
//  CREATE TABLE IF NOT EXISTS `vp_vote_pending` (
//    `account_id` INT UNSIGNED NOT NULL,
//    `site_id`    TINYINT UNSIGNED NOT NULL,
//    `clicked_at` DATETIME NOT NULL DEFAULT NOW(),
//    PRIMARY KEY (`account_id`, `site_id`)
//  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
// ============================================================
require_once __DIR__ . '/config.php';

$isLoggedIn = !empty($_SESSION['logged_in']) && !empty($_SESSION['account_id']);
$accountId  = $isLoggedIn ? (int)$_SESSION['account_id'] : 0;

// ── Catalogue des sites de vote ───────────────────────────────
// id         → identifiant unique (numérique, stable)
// name       → nom affiché
// url        → lien direct de vote
// vp_reward  → VP crédités au clic
// cooldown_h → délai avant de pouvoir re-voter (heures)
// logo       → chemin vers l'image logo
$voteSites = [
    [
        'id'         => 1,
        'name'       => 'RPG Paradize',
        'url'        => 'https://www.rpg-paradize.com/?page=vote&vote=112289',
        'vp_reward'  => 3,
        'cooldown_h' => 24,
        'logo'       => '/news/vote.gif',
    ],
    [
        'id'         => 2,
        'name'       => 'Serveur Privé',
        'url'        => 'https://serveur-prive.net/world-of-warcraft/azeroth-universe-vote/vote',
        'vp_reward'  => 2,
        'cooldown_h' => 20,
        'logo'       => '/news/logo_spc.png',
    ],
    [
        'id'         => 3,
        'name'       => 'GTop100',
        'url'        => 'https://gtop100.com/wow-private-servers/Azeroth-Universe-3-3-5-98279?vote=1',
        'vp_reward'  => 9,
        'cooldown_h' => 48,
        'logo'       => '/news/votebutton.jpg',
    ],
    [
        'id'         => 4,
        'name'       => 'Top100Arena',
        'url'        => 'https://www.top100arena.com/listing/101203/vote',
        'vp_reward'  => 24,
        'cooldown_h' => 96,
        'logo'       => '/news/101203.png',
    ],
];

// ── Données joueur ────────────────────────────────────────────
$playerVp   = 0;
$lastVotes  = []; // [site_id => voted_at DateTime]
$pendingVotes = []; // [site_id, ...] — a cliqué Voter, pas encore réclamé
$flashMsg   = '';
$flashType  = 'info';
$errors     = [];

if ($isLoggedIn) {
    try {
        $db   = getAuthDB();

        // Solde VP
        $s = $db->prepare("SELECT vp FROM account WHERE id = :id LIMIT 1");
        $s->execute([':id' => $accountId]);
        $r = $s->fetch();
        $playerVp = $r ? (int)($r['vp'] ?? 0) : 0;

        // Derniers votes par site
        $s2 = $db->prepare(
            "SELECT site_id, MAX(voted_at) AS last_vote
             FROM vp_vote_log
             WHERE account_id = :id
             GROUP BY site_id"
        );
        $s2->execute([':id' => $accountId]);
        foreach ($s2->fetchAll() as $row) {
            $lastVotes[(int)$row['site_id']] = new DateTime($row['last_vote']);
        }

        // Votes en attente (cliqué Voter mais pas encore réclamé)
        $s3 = $db->prepare(
            "SELECT site_id FROM vp_vote_pending WHERE account_id = :id"
        );
        $s3->execute([':id' => $accountId]);
        $pendingVotes = array_map('intval', array_column($s3->fetchAll(), 'site_id'));
    } catch (PDOException $e) {
        error_log('[Vote] Lecture: ' . $e->getMessage());
    }
}

// ── AJAX — Enregistrement du clic "Voter" ─────────────────────
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'mark_voted') {

    header('Content-Type: application/json');
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        echo json_encode(['ok' => false, 'error' => 'CSRF invalide']);
        exit;
    }
    $siteId = (int)($_POST['site_id'] ?? 0);
    $validIds = array_column($voteSites, 'id');
    if (!in_array($siteId, $validIds, true)) {
        echo json_encode(['ok' => false, 'error' => 'Site invalide']);
        exit;
    }
    try {
        $db = getAuthDB();
        $db->prepare(
            "INSERT INTO vp_vote_pending (account_id, site_id, clicked_at)
             VALUES (:aid, :sid, NOW())
             ON DUPLICATE KEY UPDATE clicked_at = NOW()"
        )->execute([':aid' => $accountId, ':sid' => $siteId]);
        echo json_encode(['ok' => true]);
    } catch (PDOException $e) {
        error_log('[Vote] mark_voted: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'DB error']);
    }
    exit;
}

// ── Traitement CSRF — Réclamation de VP ───────────────────────
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'claim') {

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF invalide.';
    } else {
        $siteId = (int)($_POST['site_id'] ?? 0);

        // Trouver le site dans le catalogue
        $site = null;
        foreach ($voteSites as $vs) { if ($vs['id'] === $siteId) { $site = $vs; break; } }

        if (!$site) {
            $errors[] = 'Site de vote introuvable.';
        } else {
            // Vérifier le cooldown
            $now       = new DateTime();
            $lastVote  = $lastVotes[$siteId] ?? null;
            $cooldownOk = true;

            if ($lastVote !== null) {
                $diff = $now->getTimestamp() - $lastVote->getTimestamp();
                $cooldownOk = ($diff >= $site['cooldown_h'] * 3600);
            }

            if (!$cooldownOk) {
                $remaining = ($site['cooldown_h'] * 3600) - ($now->getTimestamp() - $lastVote->getTimestamp());
                $h = floor($remaining / 3600);
                $m = floor(($remaining % 3600) / 60);
                $errors[] = "Vous avez déjà voté sur {$site['name']} récemment. Prochain vote dans {$h}h {$m}min.";
            } else {
                try {
                    $db = getAuthDB();
                    $db->beginTransaction();

                    // Créditer les VP
                    $db->prepare("UPDATE account SET vp = vp + :vp WHERE id = :id")
                       ->execute([':vp' => $site['vp_reward'], ':id' => $accountId]);

                    // Journaliser
                    $db->prepare(
                        "INSERT INTO vp_vote_log (account_id, site_id, vp_reward)
                         VALUES (:aid, :sid, :vp)"
                    )->execute([
                        ':aid' => $accountId,
                        ':sid' => $siteId,
                        ':vp'  => $site['vp_reward'],
                    ]);

                    $db->commit();

                    // Supprimer le pending (vote réclamé)
                    $db->prepare(
                        "DELETE FROM vp_vote_pending WHERE account_id = :aid AND site_id = :sid"
                    )->execute([':aid' => $accountId, ':sid' => $siteId]);

                    $playerVp += $site['vp_reward'];
                    $lastVotes[$siteId] = new DateTime();
                    $flashMsg  = '✦ Merci pour votre vote sur <strong>' . htmlspecialchars($site['name']) . '</strong> ! <strong>+' . $site['vp_reward'] . ' VP</strong> crédités sur votre compte.';
                    $flashType = 'success';

                } catch (PDOException $e) {
                    if ($db->inTransaction()) $db->rollBack();
                    error_log('[Vote] Claim: ' . $e->getMessage());
                    $errors[] = 'Erreur base de données. Réessayez.';
                }
            }
        }
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf_token'];

// ── Calcul état de chaque site ────────────────────────────────
// États : 'ready' (jamais voté), 'voted' (a cliqué Voter, peut Réclamer), 'cooldown' (déjà réclamé)
$now = new DateTime();
foreach ($voteSites as &$vs) {
    $lastVote  = $lastVotes[$vs['id']] ?? null;
    $hasPending = in_array($vs['id'], $pendingVotes, true);

    if ($lastVote === null) {
        // Jamais réclamé — prêt à voter (ou déjà cliqué Voter)
        $vs['state']        = $hasPending ? 'voted' : 'ready';
        $vs['remaining_s']  = 0;
        $vs['cooldown_pct'] = 100;
    } else {
        $elapsed   = $now->getTimestamp() - $lastVote->getTimestamp();
        $total     = $vs['cooldown_h'] * 3600;
        $remaining = max(0, $total - $elapsed);
        if ($remaining === 0) {
            // Cooldown terminé — prêt à revoter (ou déjà cliqué Voter)
            $vs['state']        = $hasPending ? 'voted' : 'ready';
            $vs['remaining_s']  = 0;
            $vs['cooldown_pct'] = 100;
        } else {
            $vs['state']        = 'cooldown';
            $vs['remaining_s']  = $remaining;
            $vs['cooldown_pct'] = round(($elapsed / $total) * 100);
        }
    }
}
unset($vs);

$totalVpPossible = array_sum(array_column($voteSites, 'vp_reward'));
$pageTitle = 'Voter — Eons';
require_once __DIR__ . '/header.php';
?>

<style>
/* ─── PAGE VOTE ─────────────────────────────────────────────── */
.vote-page {
    position: relative; z-index: 10;
    padding: 5.5rem 2rem 5rem;
    max-width: 1100px;
    margin: 0 auto;
}

/* ─── HERO ──────────────────────────────────────────────────── */
.vote-hero {
    text-align: center;
    padding: 3.5rem 1rem 3rem;
    position: relative;
    margin-bottom: 2.5rem;
}
.vote-hero::before {
    content: '';
    position: absolute; top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 500px; height: 250px;
    background: radial-gradient(ellipse, rgba(110,223,138,0.12) 0%, transparent 70%);
    pointer-events: none;
}
.vote-eyebrow {
    font-family: 'Cinzel', serif;
    font-size: 0.65rem; letter-spacing: 0.45em;
    color: #6edf8a; text-transform: uppercase;
    margin-bottom: 1rem;
}
.vote-title {
    font-family: 'Cinzel Decorative', serif;
    font-size: clamp(1.8rem, 4vw, 2.8rem);
    font-weight: 900; color: var(--white);
    text-shadow: 0 0 60px rgba(110,223,138,0.2), 0 0 120px rgba(90,48,212,0.15);
    margin-bottom: 1rem;
}
.vote-title span {
    background: linear-gradient(135deg, #6edf8a 0%, #a8f0b8 50%, #6edf8a 100%);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}
.vote-subtitle {
    font-family: 'Crimson Pro', serif;
    font-size: 1.05rem; font-style: italic;
    color: var(--silver); max-width: 560px;
    margin: 0 auto 2rem; line-height: 1.7;
}
.vote-divider {
    display: flex; align-items: center; justify-content: center; gap: 1rem;
    margin-bottom: 0.5rem;
}
.vote-divider::before, .vote-divider::after {
    content: ''; width: 80px; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(110,223,138,0.4));
}
.vote-divider::after { transform: scaleX(-1); }
.vote-divider-gem {
    width: 8px; height: 8px;
    background: #6edf8a; transform: rotate(45deg);
    box-shadow: 0 0 14px rgba(110,223,138,0.7);
}

/* ─── SOLDE VP ──────────────────────────────────────────────── */
.vp-balance-bar {
    display: flex; align-items: center; justify-content: center;
    gap: 1.2rem; flex-wrap: wrap; margin-bottom: 2.8rem;
}
.vp-balance-badge {
    display: inline-flex; align-items: center; gap: 0.7rem;
    padding: 0.7rem 1.8rem;
    background: rgba(9,12,34,0.85);
    border: 1px solid rgba(110,223,138,0.35);
    clip-path: polygon(12px 0%, 100% 0%, calc(100% - 12px) 100%, 0% 100%);
    backdrop-filter: blur(10px);
}
.vp-balance-label {
    font-family: 'Cinzel', serif; font-size: 0.58rem;
    letter-spacing: 0.2em; color: var(--silver); text-transform: uppercase;
}
.vp-balance-amount {
    font-family: 'Cinzel Decorative', serif;
    font-size: 1.35rem; font-weight: 700; color: #6edf8a;
    text-shadow: 0 0 20px rgba(110,223,138,0.5);
}
.vp-balance-unit {
    font-family: 'Cinzel', serif; font-size: 0.62rem;
    letter-spacing: 0.15em; color: #6edf8a; opacity: 0.8;
}
.vp-total-possible {
    font-family: 'Cinzel', serif; font-size: 0.58rem;
    letter-spacing: 0.12em; color: var(--silver); opacity: 0.55;
    align-self: center;
}
.vp-total-possible strong { color: #6edf8a; opacity: 1; }

/* ─── ALERTES ───────────────────────────────────────────────── */
.vote-alert {
    padding: 1rem 1.4rem; margin-bottom: 1.8rem;
    border-left: 3px solid;
    font-family: 'Crimson Pro', serif; font-size: 0.96rem;
    display: flex; align-items: center; gap: 0.8rem;
    background: rgba(9,12,34,0.75); backdrop-filter: blur(10px);
}
.vote-alert-success { border-color: var(--success); color: var(--success); }
.vote-alert-error   { border-color: var(--error);   color: var(--error); }
.vote-alert-info    { border-color: var(--info);     color: var(--info); }

/* ─── GRILLE DES SITES ──────────────────────────────────────── */
.vote-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(480px, 100%), 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
}

/* ─── CARTE SITE DE VOTE ────────────────────────────────────── */
.vote-card {
    position: relative;
    background: rgba(9,12,34,0.82);
    border: 1px solid rgba(136,144,255,0.1);
    overflow: hidden;
    transition: transform 0.35s cubic-bezier(.22,1,.36,1), border-color 0.3s, box-shadow 0.35s;
    display: flex;
    flex-direction: column;
}
.vote-card::before {
    content: '';
    position: absolute; top: 0; right: 0;
    border-style: solid; border-width: 0 44px 44px 0;
    border-color: transparent rgba(110,223,138,0.12) transparent transparent;
    z-index: 2; transition: border-color 0.3s;
}
.vote-card::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at 30% 0%, rgba(110,223,138,0.05) 0%, transparent 60%);
    opacity: 0; transition: opacity 0.4s; pointer-events: none;
}
.vote-card:hover {
    transform: translateY(-5px);
    border-color: rgba(110,223,138,0.25);
    box-shadow: 0 16px 50px rgba(110,223,138,0.12), 0 0 0 1px rgba(110,223,138,0.06) inset;
}
.vote-card:hover::before { border-color: transparent rgba(110,223,138,0.3) transparent transparent; }
.vote-card:hover::after  { opacity: 1; }

/* Carte en cooldown */
.vote-card.is-cooldown {
    border-color: rgba(136,144,255,0.07);
    opacity: 0.75;
}
.vote-card.is-cooldown:hover { transform: none; box-shadow: none; }

/* ─── HEADER CARTE ──────────────────────────────────────────── */
.vote-card-header {
    display: flex; align-items: center; gap: 1.2rem;
    padding: 1.4rem 1.4rem 1rem;
    border-bottom: 1px solid rgba(136,144,255,0.07);
}
.vote-site-logo {
    width: 64px; height: 48px; object-fit: contain;
    flex-shrink: 0;
    filter: brightness(0.9) saturate(0.9);
    transition: filter 0.3s;
    border: 1px solid rgba(136,144,255,0.1);
    background: rgba(15,18,48,0.6);
    padding: 4px;
}
.vote-card:hover .vote-site-logo { filter: brightness(1.05) saturate(1.1); }
.vote-site-logo-placeholder {
    width: 64px; height: 48px; flex-shrink: 0;
    background: rgba(15,18,48,0.6);
    border: 1px solid rgba(136,144,255,0.1);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.6rem;
}
.vote-site-info { flex: 1; min-width: 0; }
.vote-site-name {
    font-family: 'Cinzel', serif;
    font-size: 0.88rem; font-weight: 700;
    letter-spacing: 0.07em; color: var(--white);
    margin-bottom: 0.3rem;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.vote-reward-pill {
    display: inline-flex; align-items: center; gap: 0.3rem;
    background: rgba(110,223,138,0.1);
    border: 1px solid rgba(110,223,138,0.25);
    padding: 0.18rem 0.6rem;
    font-family: 'Cinzel', serif; font-size: 0.52rem;
    letter-spacing: 0.1em; color: #6edf8a;
    text-transform: uppercase;
}
.vote-cooldown-label {
    font-family: 'Cinzel', serif; font-size: 0.52rem;
    letter-spacing: 0.08em; color: var(--silver); opacity: 0.5;
    margin-top: 0.25rem;
}

/* ─── BARRE DE COOLDOWN ─────────────────────────────────────── */
.vote-progress-wrap {
    padding: 0.8rem 1.4rem 0;
}
.vote-progress-bar {
    height: 3px;
    background: rgba(136,144,255,0.1);
    position: relative; overflow: hidden;
}
.vote-progress-fill {
    height: 100%; position: absolute; left: 0; top: 0;
    background: linear-gradient(90deg, #3a8a50, #6edf8a);
    transition: width 1s linear;
    box-shadow: 0 0 8px rgba(110,223,138,0.5);
}
.vote-progress-timer {
    font-family: 'Cinzel', serif; font-size: 0.54rem;
    letter-spacing: 0.08em; color: var(--silver); opacity: 0.5;
    text-align: right; margin-top: 0.35rem;
}
.vote-progress-timer.ready { color: #6edf8a; opacity: 0.8; }

/* ─── FOOTER CARTE ──────────────────────────────────────────── */
.vote-card-footer {
    padding: 1rem 1.4rem 1.4rem;
    display: flex; align-items: center; justify-content: space-between;
    gap: 0.8rem; margin-top: auto;
}
.vote-site-url {
    font-family: 'Crimson Pro', serif; font-style: italic;
    font-size: 0.76rem; color: var(--silver); opacity: 0.4;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    max-width: 200px;
}

/* ─── BOUTON VOTER ──────────────────────────────────────────── */
.btn-vote-open {
    display: inline-flex; align-items: center; gap: 0.4rem;
    font-family: 'Cinzel', serif; font-size: 0.58rem;
    font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase;
    padding: 0.55rem 1.1rem; text-decoration: none;
    background: transparent; color: #6edf8a;
    border: 1px solid rgba(110,223,138,0.4);
    clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%);
    transition: all 0.25s; white-space: nowrap; position: relative; overflow: hidden;
}
.btn-vote-open::before {
    content: '';
    position: absolute; inset: 0;
    background: linear-gradient(90deg, transparent, rgba(110,223,138,0.08), transparent);
    transform: translateX(-100%) skewX(-20deg); transition: transform 0.5s;
}
.btn-vote-open:hover {
    background: rgba(110,223,138,0.08);
    border-color: rgba(110,223,138,0.7);
    box-shadow: 0 0 20px rgba(110,223,138,0.2);
}
.btn-vote-open:hover::before { transform: translateX(150%) skewX(-20deg); }

/* Bouton Voter déjà cliqué */
.btn-vote-open--done {
    color: #6edf8a;
    background: rgba(110,223,138,0.08);
    border-color: rgba(110,223,138,0.5);
    cursor: default;
    pointer-events: none;
    opacity: 0.75;
}

/* Bouton Voter désactivé (en cooldown) */
.btn-vote-open--disabled {
    display: inline-flex; align-items: center; gap: 0.4rem;
    font-family: 'Cinzel', serif; font-size: 0.58rem;
    font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase;
    padding: 0.55rem 1.1rem;
    background: rgba(30,33,70,0.5); color: rgba(168,180,208,0.35);
    border: 1px solid rgba(136,144,255,0.08);
    clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%);
    cursor: not-allowed; white-space: nowrap;
}

/* ─── BOUTON RÉCLAMER ───────────────────────────────────────── */
.btn-vote-claim {
    display: inline-flex; align-items: center; gap: 0.4rem;
    font-family: 'Cinzel', serif; font-size: 0.58rem;
    font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase;
    padding: 0.55rem 1.1rem;
    background: linear-gradient(135deg, #2a6a3a 0%, #3a9a50 40%, #6edf8a 50%, #3a9a50 60%, #2a6a3a 100%);
    color: #0a1a0e; border: none; cursor: pointer;
    clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%);
    box-shadow: 0 2px 20px rgba(110,223,138,0.35);
    transition: all 0.25s; white-space: nowrap; position: relative; overflow: hidden;
    animation: claimPulse 2.5s ease-in-out infinite;
}
.btn-vote-claim::before {
    content: '';
    position: absolute; inset: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transform: translateX(-100%) skewX(-20deg); transition: transform 0.5s;
}
.btn-vote-claim:hover { box-shadow: 0 4px 30px rgba(110,223,138,0.55); transform: translateY(-2px); animation: none; }
.btn-vote-claim:hover::before { transform: translateX(150%) skewX(-20deg); }

@keyframes claimPulse {
    0%, 100% { box-shadow: 0 2px 20px rgba(110,223,138,0.35); }
    50%       { box-shadow: 0 2px 35px rgba(110,223,138,0.65); }
}

/* Cooldown button (disabled) */
.btn-vote-cooldown {
    display: inline-flex; align-items: center; gap: 0.4rem;
    font-family: 'Cinzel', serif; font-size: 0.58rem;
    letter-spacing: 0.12em; text-transform: uppercase;
    padding: 0.55rem 1.1rem;
    background: rgba(30,33,70,0.5); color: rgba(168,180,208,0.35);
    border: 1px solid rgba(136,144,255,0.08);
    clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%);
    cursor: not-allowed; white-space: nowrap;
}

/* Login invite */
.btn-vote-login {
    display: inline-flex; align-items: center; gap: 0.4rem;
    font-family: 'Cinzel', serif; font-size: 0.58rem;
    letter-spacing: 0.12em; text-transform: uppercase;
    padding: 0.55rem 1.1rem;
    background: transparent; color: var(--arcane-bright);
    border: 1px solid rgba(136,144,255,0.35);
    clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%);
    text-decoration: none; transition: all 0.25s; white-space: nowrap;
}
.btn-vote-login:hover { border-color: var(--arcane-bright); box-shadow: 0 0 18px rgba(136,144,255,0.2); }

/* ─── INFOS GÉNÉRALES ───────────────────────────────────────── */
.vote-info-section {
    margin-top: 1rem;
    padding: 2rem;
    background: rgba(9,12,34,0.7);
    border: 1px solid rgba(110,223,138,0.1);
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem; position: relative; overflow: hidden;
}
.vote-info-section::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at left, rgba(110,223,138,0.04) 0%, transparent 60%);
    pointer-events: none;
}
.vote-info-step { display: flex; align-items: flex-start; gap: 1rem; }
.vote-info-num {
    font-family: 'Cinzel Decorative', serif;
    font-size: 1.6rem; font-weight: 900;
    color: rgba(110,223,138,0.2); line-height: 1; flex-shrink: 0;
}
.vote-info-text h4 {
    font-family: 'Cinzel', serif; font-size: 0.68rem;
    letter-spacing: 0.1em; color: #6edf8a;
    margin-bottom: 0.35rem; text-transform: uppercase;
}
.vote-info-text p {
    font-family: 'Crimson Pro', serif; font-size: 0.88rem;
    color: var(--silver); line-height: 1.6; font-style: italic;
}

/* ─── RESPONSIVE ────────────────────────────────────────────── */
@media (max-width: 640px) {
    .vote-page { padding: 5rem 1rem 4rem; }
    .vote-grid { grid-template-columns: 1fr; }
    .vote-card-footer { flex-direction: column; align-items: flex-start; gap: 0.7rem; }
    .vote-site-url { display: none; }
}
@media (max-width: 359px) {
    .vote-title { font-size: 1.5rem; }
    .vp-balance-amount { font-size: 1.1rem; }
}
</style>

<main>
<div class="vote-page">

    <!-- ── HERO ─────────────────────────────────────────────── -->
    <div class="vote-hero reveal">
        <p class="vote-eyebrow">✦ Soutenez le serveur ✦</p>
        <h1 class="vote-title">Votez & Réclamez des <span>VP</span></h1>
        <p class="vote-subtitle">Chaque vote soutient la communauté Eons et vous récompense en Vote Points, échangeables à la boutique.</p>
        <div class="vote-divider"><div class="vote-divider-gem"></div></div>
    </div>

    <!-- ── ALERTES ───────────────────────────────────────────── -->
    <?php if ($flashMsg): ?>
    <div class="vote-alert vote-alert-<?= htmlspecialchars($flashType) ?>">
        <span><?= $flashMsg ?></span>
    </div>
    <?php endif; ?>
    <?php foreach ($errors as $err): ?>
    <div class="vote-alert vote-alert-error">
        <span>✖ <?= htmlspecialchars($err) ?></span>
    </div>
    <?php endforeach; ?>

    <!-- ── SOLDE VP ──────────────────────────────────────────── -->
    <div class="vp-balance-bar reveal">
        <?php if ($isLoggedIn): ?>
        <div class="vp-balance-badge">
            <span style="font-size:1.2rem">🗳️</span>
            <div>
                <div class="vp-balance-label">Vos Vote Points</div>
                <div class="vp-balance-amount"><?= number_format($playerVp, 0, ',', ' ') ?> <span class="vp-balance-unit">VP</span></div>
            </div>
        </div>
        <span class="vp-total-possible">Jusqu'à <strong>+<?= $totalVpPossible ?> VP</strong> disponibles aujourd'hui</span>
        <?php else: ?>
        <div class="vp-balance-badge">
            <span style="font-size:1.2rem">🔒</span>
            <div>
                <div class="vp-balance-label">Connectez-vous pour voter</div>
                <div style="font-family:'Cinzel',serif;font-size:.7rem;color:var(--silver);margin-top:.2rem;">
                    <a href="auth.php" style="color:var(--arcane-bright);text-decoration:none;">Se connecter</a>
                    &nbsp;·&nbsp;
                    <a href="auth.php#register" style="color:#6edf8a;text-decoration:none;">Créer un compte</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── GRILLE DES SITES ──────────────────────────────────── -->
    <div class="vote-grid">
        <?php foreach ($voteSites as $vs):
            $isCooldown = ($vs['state'] === 'cooldown');
            $remaining  = (int)$vs['remaining_s'];
            $pct        = (int)$vs['cooldown_pct'];
            $h = floor($remaining / 3600);
            $m = floor(($remaining % 3600) / 60);
            $s = $remaining % 60;
        ?>
        <div class="vote-card reveal <?= $isCooldown ? 'is-cooldown' : '' ?>"
             data-remaining="<?= $remaining ?>"
             data-site="<?= $vs['id'] ?>">

            <!-- Header -->
            <div class="vote-card-header">
                <?php if (!empty($vs['logo'])): ?>
                <img src="<?= htmlspecialchars($vs['logo']) ?>"
                     alt="<?= htmlspecialchars($vs['name']) ?>"
                     class="vote-site-logo"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <div class="vote-site-logo-placeholder" style="display:none">🗳️</div>
                <?php else: ?>
                <div class="vote-site-logo-placeholder">🗳️</div>
                <?php endif; ?>

                <div class="vote-site-info">
                    <div class="vote-site-name"><?= htmlspecialchars($vs['name']) ?></div>
                    <div class="vote-reward-pill">🗳️ +<?= $vs['vp_reward'] ?> VP</div>
                    <div class="vote-cooldown-label">Cooldown : <?= $vs['cooldown_h'] ?>h</div>
                </div>
            </div>

            <!-- Barre de progression cooldown -->
            <div class="vote-progress-wrap">
                <div class="vote-progress-bar">
                    <div class="vote-progress-fill" style="width:<?= $pct ?>%"></div>
                </div>
                <div class="vote-progress-timer <?= !$isCooldown ? 'ready' : '' ?>" data-timer="<?= $vs['id'] ?>">
                    <?php if (!$isCooldown): ?>
                    ✦ Prêt à voter
                    <?php else: ?>
                    <?= sprintf('%02dh %02dmin %02ds', $h, $m, $s) ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Footer -->
            <div class="vote-card-footer">
                <span class="vote-site-url"><?= htmlspecialchars(parse_url($vs['url'], PHP_URL_HOST)) ?></span>

                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                    <!-- Bouton ouvrir le site de vote -->
                    <?php if ($isCooldown): ?>
                        <button class="btn-vote-open btn-vote-open--disabled" disabled title="Cooldown en cours">↗ Voter</button>
                    <?php else: ?>
                        <a href="<?= htmlspecialchars($vs['url']) ?>"
                           target="_blank" rel="noopener noreferrer"
                           class="btn-vote-open<?= ($vs['state'] === 'voted') ? ' btn-vote-open--done' : '' ?>"
                           data-vote-btn="<?= $vs['id'] ?>"
                           data-csrf="<?= htmlspecialchars($csrfToken) ?>">
                            <?= ($vs['state'] === 'voted') ? '✔ Voté' : '↗ Voter' ?>
                        </a>
                    <?php endif; ?>

                    <!-- Bouton réclamer / cooldown / connexion -->
                    <?php if (!$isLoggedIn): ?>
                        <a href="auth.php" class="btn-vote-login">🔒 Connexion</a>
                    <?php elseif ($isCooldown): ?>
                        <button class="btn-vote-cooldown" disabled title="Cooldown en cours">⏳ Cooldown</button>
                    <?php elseif ($vs['state'] === 'voted'): ?>
                        <form method="POST" action="vote.php" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="action"     value="claim">
                            <input type="hidden" name="site_id"    value="<?= $vs['id'] ?>">
                            <button type="submit" class="btn-vote-claim">✦ Réclamer +<?= $vs['vp_reward'] ?> VP</button>
                        </form>
                    <?php else: ?>
                        <button class="btn-vote-cooldown" disabled title="Votez d'abord pour débloquer">🔒 Votez d'abord</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── GUIDE ─────────────────────────────────────────────── -->
    <div class="vote-info-section reveal">
        <div class="vote-info-step">
            <span class="vote-info-num">01</span>
            <div class="vote-info-text">
                <h4>Votez sur le site</h4>
                <p>Cliquez sur « Voter » pour ouvrir le site dans un nouvel onglet et compléter le vote.</p>
            </div>
        </div>
        <div class="vote-info-step">
            <span class="vote-info-num">02</span>
            <div class="vote-info-text">
                <h4>Réclamez vos VP</h4>
                <p>Une fois votre vote confirmé sur le site, revenez ici et cliquez sur « Réclamer » pour créditer vos points.</p>
            </div>
        </div>
        <div class="vote-info-step">
            <span class="vote-info-num">03</span>
            <div class="vote-info-text">
                <h4>Échangez à la boutique</h4>
                <p>Utilisez vos VP pour obtenir des montures, familiers et services exclusifs sur la page Boutique.</p>
            </div>
        </div>
    </div>

</div>
</main>

<script>
// ─── AJAX — BOUTON VOTER ──────────────────────────────────────
document.querySelectorAll('[data-vote-btn]').forEach(link => {
    if (link.classList.contains('btn-vote-open--done')) return; // déjà voté

    link.addEventListener('click', function() {
        const siteId = this.dataset.voteBtn;
        const csrf   = this.dataset.csrf;

        // Bloquer visuellement immédiatement
        this.textContent = '✔ Voté';
        this.classList.add('btn-vote-open--done');

        // Débloquer le bouton Réclamer dans la même carte
        const card = this.closest('.vote-card');
        if (card) {
            const locked = card.querySelector('.btn-vote-cooldown[disabled]');
            if (locked && locked.title === 'Votez d\'abord pour débloquer') {
                // Remplacer par le formulaire de réclamation
                const vp = card.querySelector('.vote-reward-pill')?.textContent.match(/\d+/)?.[0] || '?';
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'vote.php';
                form.style.display = 'inline';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="${csrf}">
                    <input type="hidden" name="action"     value="claim">
                    <input type="hidden" name="site_id"    value="${siteId}">
                    <button type="submit" class="btn-vote-claim">✦ Réclamer +${vp} VP</button>`;
                locked.replaceWith(form);
            }
        }

        // Enregistrer en DB
        fetch('vote.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({action: 'mark_voted', site_id: siteId, csrf_token: csrf})
        }).catch(() => {}); // silencieux — la carte se rechargera
    });
});

// ─── COUNTDOWN TIMERS ─────────────────────────────────────────
(function() {
    const cards = document.querySelectorAll('.vote-card[data-remaining]');

    cards.forEach(card => {
        let remaining = parseInt(card.dataset.remaining, 10);
        if (remaining <= 0) return;

        const siteId  = card.dataset.site;
        const timerEl = document.querySelector(`[data-timer="${siteId}"]`);
        const fillEl  = card.querySelector('.vote-progress-fill');
        const totalH  = parseFloat(card.querySelector('.vote-cooldown-label')?.textContent.match(/[\d.]+/)?.[0] || 0);
        const totalS  = totalH * 3600;

        function fmt(s) {
            const h = Math.floor(s / 3600);
            const m = Math.floor((s % 3600) / 60);
            const sec = s % 60;
            return String(h).padStart(2,'0') + 'h ' + String(m).padStart(2,'0') + 'min ' + String(sec).padStart(2,'0') + 's';
        }

        const interval = setInterval(() => {
            remaining--;
            if (remaining <= 0) {
                clearInterval(interval);
                if (timerEl) {
                    timerEl.textContent = '✦ Prêt à voter';
                    timerEl.classList.add('ready');
                }
                if (fillEl) fillEl.style.width = '100%';
                card.classList.remove('is-cooldown');
                // Recharger pour afficher les bons boutons
                setTimeout(() => location.reload(), 800);
                return;
            }
            if (timerEl) timerEl.textContent = fmt(remaining);
            if (fillEl && totalS > 0) {
                const elapsed = totalS - remaining;
                fillEl.style.width = Math.round((elapsed / totalS) * 100) + '%';
            }
        }, 1000);
    });
})();
</script>

<?php
// ── FOOTER ────────────────────────────────────────────────────
?>
<footer>
    <div class="footer-logo">Eons</div>
    <p class="footer-tagline">Forgé dans les étoiles. Joué par des légendes.</p>
    <div class="footer-links">
        <a href="index.php">Accueil</a>
        <a href="royaumes.php">Royaumes</a>
        <a href="boutique.php">Boutique</a>
        <a href="vote.php">Voter</a>
        <a href="classements.php">Classements</a>
    </div>
    <div class="footer-sep"><div class="footer-gem"></div></div>
    <p class="footer-bottom">© <?= date('Y') ?> <span>Eons</span> · World of Warcraft 3.3.5a · Tous droits réservés</p>
</footer>

</body>
</html>
