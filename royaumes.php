<?php
// ============================================================
//  royaumes.php — Eons CMS | Arcanic Theme Enhanced
// ============================================================
require_once __DIR__ . '/config.php';

// ── Statut serveur (ping sur le port WoW 3724) ──────────────
function checkServerStatus(string $host = '127.0.0.1', int $port = 3724, float $timeout = 1.5): bool {
    $sock = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if ($sock) { fclose($sock); return true; }
    return false;
}

$serverOnline = checkServerStatus();

// ── Joueurs en ligne ─────────────────────────────────────────
$onlinePlayers = 0;
$totalAccounts = 0;
try {
    $chars = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_CHARS_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $onlinePlayers = (int)$chars->query("SELECT COUNT(*) FROM characters WHERE online = 1")->fetchColumn();

    // Joueurs en ligne pour la liste (toutes maps, avec zone)
    $onlineCharsList = $chars->query(
        "SELECT name, race, class, level, zone
         FROM characters
         WHERE online = 1
         ORDER BY level DESC
         LIMIT 50"
    )->fetchAll(PDO::FETCH_ASSOC);

    // Noms des zones
    $zoneNames = [];
    if (!empty($onlineCharsList)) {
        $zoneIds = array_unique(array_filter(array_column($onlineCharsList, 'zone')));
        if ($zoneIds) {
            $auth2 = getAuthDB();
            $placeholders = implode(',', array_fill(0, count($zoneIds), '?'));
            $zStmt = $auth2->prepare("SELECT id, zone_name FROM zones WHERE id IN ($placeholders)");
            $zStmt->execute($zoneIds);
            foreach ($zStmt->fetchAll(PDO::FETCH_ASSOC) as $z) {
                $zoneNames[$z['id']] = $z['zone_name'];
            }
        }
    }
} catch (PDOException $e) {
    error_log('[Royaumes] Chars DB: ' . $e->getMessage());
}
try {
    $auth = getAuthDB();
    $totalAccounts = (int)$auth->query("SELECT COUNT(*) FROM account")->fetchColumn();
} catch (PDOException $e) {
    error_log('[Royaumes] Auth DB: ' . $e->getMessage());
}

$pageTitle = 'Royaumes — Eons';
require_once __DIR__ . '/header.php';
?>
<style>
/* ─── PAGE LAYOUT ─────────────────────────────────────────── */
.royaumes-page {
    position: relative;
    z-index: 10;
    min-height: calc(100vh - 62px);
    margin-top: 62px;
    padding: 3rem 1.5rem 5rem;
}

/* ─── PAGE HEADER ─────────────────────────────────────────── */
.page-header {
    text-align: center;
    margin-bottom: 3.5rem;
    animation: fadeUp 0.8s 0.1s both;
}
.page-eyebrow {
    font-family: 'Cinzel', serif;
    font-size: 0.62rem;
    letter-spacing: 0.5em;
    color: var(--gold);
    text-transform: uppercase;
    margin-bottom: 1.2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
}
.eyebrow-line {
    display: inline-block;
    width: 50px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--gold));
    opacity: 0.5;
}
.eyebrow-line:last-child { transform: scaleX(-1); }
.page-title {
    font-family: 'Cinzel Decorative', serif;
    font-size: clamp(2rem, 5vw, 3.2rem);
    font-weight: 700;
    color: var(--white);
    text-shadow: 0 0 40px rgba(136,144,255,0.3);
    margin-bottom: 0.8rem;
}
.page-subtitle {
    font-size: 1rem;
    color: var(--silver);
    font-style: italic;
    max-width: 480px;
    margin: 0 auto;
}
.page-divider {
    display: flex; align-items: center; justify-content: center; gap: 1rem;
    margin-top: 1.8rem;
}
.page-divider-line {
    width: 80px; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(136,144,255,0.4));
}
.page-divider-line:last-child { transform: scaleX(-1); }
.page-divider-gem {
    width: 8px; height: 8px;
    background: var(--gold);
    transform: rotate(45deg);
    box-shadow: 0 0 12px rgba(240,192,96,0.8);
    animation: gemPulse 2.5s ease-in-out infinite;
}
@keyframes gemPulse {
    0%,100% { box-shadow: 0 0 12px rgba(240,192,96,0.8); }
    50%      { box-shadow: 0 0 24px rgba(240,192,96,1), 0 0 48px rgba(240,192,96,0.4); }
}

/* ─── MAIN GRID ───────────────────────────────────────────── */
.royaumes-grid {
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem;
    align-items: start;
}

@keyframes ledBlink {
    0%,100% { opacity: 1; }
    50%      { opacity: 0.4; }
}

/* Infos de connexion */
.realm-connect-info {
    max-width: 1200px;
    margin: 0 auto 2rem;
    padding: 1.2rem 1.8rem;
    background: rgba(6,8,26,0.85);
    border: 1px solid rgba(136,144,255,0.15);
    backdrop-filter: blur(20px);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    animation: fadeUp 0.8s 0.2s both;
    position: relative;
}
.realm-connect-info::before {
    content: '';
    position: absolute;
    top: 0; left: 0;
    width: 20px; height: 20px;
    border-top: 1px solid var(--gold-bright);
    border-left: 1px solid var(--gold-bright);
}
.connect-label {
    font-family: 'Cinzel', serif;
    font-size: 0.55rem;
    letter-spacing: 0.2em;
    color: var(--silver);
    text-transform: uppercase;
    margin-bottom: 0.3rem;
}
.connect-value {
    font-family: 'Crimson Pro', serif;
    font-size: 0.95rem;
    color: var(--white);
    font-weight: 600;
    letter-spacing: 0.05em;
}
.connect-copy-btn {
    background: rgba(136,144,255,0.1);
    border: 1px solid rgba(136,144,255,0.25);
    color: var(--arcane-bright);
    font-family: 'Cinzel', serif;
    font-size: 0.52rem;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    padding: 0.4rem 0.9rem;
    cursor: pointer;
    transition: background 0.2s, box-shadow 0.2s;
    white-space: nowrap;
}
.connect-copy-btn:hover {
    background: rgba(136,144,255,0.2);
    box-shadow: 0 0 12px rgba(136,144,255,0.2);
}

/* ─── PANNEAU STATS ───────────────────────────────────────── */
.realm-stats-panel {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    animation: fadeUp 0.8s 0.35s both;
}

/* Carte de royaume */
.realm-card {
    background: rgba(6,8,26,0.85);
    border: 1px solid rgba(136,144,255,0.15);
    backdrop-filter: blur(20px);
    padding: 1.8rem;
    position: relative;
    overflow: hidden;
}
.realm-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0;
    width: 20px; height: 20px;
    border-top: 1px solid var(--gold-bright);
    border-left: 1px solid var(--gold-bright);
}

.realm-card-header {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    margin-bottom: 1.4rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(136,144,255,0.1);
}
.realm-card-icon {
    font-size: 1.6rem;
    filter: drop-shadow(0 0 10px rgba(240,192,96,0.5));
}
.realm-card-name {
    font-family: 'Cinzel Decorative', serif;
    font-size: 1.05rem;
    color: var(--white);
    margin-bottom: 0.15rem;
}
.realm-card-type {
    font-family: 'Cinzel', serif;
    font-size: 0.52rem;
    letter-spacing: 0.2em;
    color: var(--gold);
    text-transform: uppercase;
}

/* Stats list */
.realm-stat-list {
    display: flex;
    flex-direction: column;
    gap: 0.7rem;
}
.realm-stat-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(136,144,255,0.05);
}
.realm-stat-row:last-child { border-bottom: none; }
.realm-stat-key {
    font-family: 'Cinzel', serif;
    font-size: 0.55rem;
    letter-spacing: 0.15em;
    color: var(--silver);
    text-transform: uppercase;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.realm-stat-key-icon { font-size: 0.8rem; opacity: 0.7; }
.realm-stat-val {
    font-family: 'Crimson Pro', serif;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--white);
}
.realm-stat-val.green { color: var(--success); text-shadow: 0 0 10px rgba(95,255,176,0.4); }
.realm-stat-val.gold  { color: var(--gold-bright); text-shadow: 0 0 10px rgba(240,192,96,0.4); }
.realm-stat-val.arcane { color: var(--arcane-bright); text-shadow: 0 0 10px rgba(136,144,255,0.4); }

/* Barre de population */
.pop-bar-wrap {
    margin-top: 1.2rem;
}
.pop-bar-label {
    display: flex;
    justify-content: space-between;
    font-family: 'Cinzel', serif;
    font-size: 0.52rem;
    letter-spacing: 0.15em;
    color: var(--silver);
    text-transform: uppercase;
    margin-bottom: 0.5rem;
}
.pop-bar {
    height: 4px;
    background: rgba(136,144,255,0.1);
    position: relative;
    overflow: hidden;
}
.pop-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--arcane-glow), var(--arcane-bright));
    box-shadow: 0 0 8px rgba(136,144,255,0.6);
    transition: width 1.5s cubic-bezier(0.4,0,0.2,1);
    width: 0%;
}

/* Mini stats grid */
.mini-stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.8rem;
    margin-top: 0.8rem;
}
.mini-stat {
    background: rgba(136,144,255,0.05);
    border: 1px solid rgba(136,144,255,0.1);
    padding: 0.9rem;
    text-align: center;
}
.mini-stat-val {
    font-family: 'Cinzel Decorative', serif;
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--white);
    display: block;
    margin-bottom: 0.2rem;
}
.mini-stat-val.gold   { color: var(--gold-bright); }
.mini-stat-val.arcane { color: var(--arcane-bright); }
.mini-stat-lbl {
    font-family: 'Cinzel', serif;
    font-size: 0.48rem;
    letter-spacing: 0.15em;
    color: var(--silver);
    text-transform: uppercase;
}

/* Taux d'XP badges */
.rates-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.6rem;
    margin-top: 0.3rem;
}
.rate-badge {
    background: rgba(240,192,96,0.06);
    border: 1px solid rgba(240,192,96,0.15);
    padding: 0.6rem 0.4rem;
    text-align: center;
}
.rate-badge-val {
    font-family: 'Cinzel Decorative', serif;
    font-size: 0.9rem;
    color: var(--gold-bright);
    display: block;
    margin-bottom: 0.15rem;
}
.rate-badge-lbl {
    font-family: 'Cinzel', serif;
    font-size: 0.42rem;
    letter-spacing: 0.1em;
    color: var(--silver);
    text-transform: uppercase;
}

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(18px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ─── RESPONSIVE ──────────────────────────────────────────── */
@media (max-width: 900px) {
    .royaumes-grid {
        grid-template-columns: 1fr;
    }
    .realm-stats-panel {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }
}
@media (max-width: 600px) {
    .realm-stats-panel { grid-template-columns: 1fr; }
    .rates-grid { grid-template-columns: repeat(3, 1fr); }
    .mini-stats-grid { grid-template-columns: 1fr 1fr; }
}

/* ─── SECTION JOUEURS EN LIGNE ────────────────────────────── */
.online-players-section {
    max-width: 1200px;
    margin: 2.5rem auto 0;
    animation: fadeUp 0.8s 0.4s both;
}
.online-players-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.2rem;
    padding-bottom: 0.8rem;
    border-bottom: 1px solid rgba(136,144,255,0.12);
}
.online-players-title {
    font-family: 'Cinzel', serif;
    font-size: 0.65rem;
    letter-spacing: 0.35em;
    text-transform: uppercase;
    color: var(--gold);
    display: flex;
    align-items: center;
    gap: 0.7rem;
}
.online-players-title::before {
    content: '';
    display: inline-block;
    width: 8px; height: 8px;
    background: var(--success);
    border-radius: 50%;
    box-shadow: 0 0 8px rgba(95,255,176,0.9);
    animation: ledBlink 1.8s ease-in-out infinite;
}
.online-players-count {
    font-family: 'Cinzel', serif;
    font-size: 0.6rem;
    letter-spacing: 0.2em;
    color: var(--silver);
    background: rgba(136,144,255,0.08);
    border: 1px solid rgba(136,144,255,0.15);
    padding: 0.25rem 0.75rem;
    border-radius: 2px;
}
.online-players-count span { color: var(--arcane-bright); font-weight: 700; }
.online-table-wrap {
    background: rgba(6,8,26,0.75);
    border: 1px solid rgba(136,144,255,0.12);
    backdrop-filter: blur(16px);
    overflow: hidden;
    position: relative;
}
.online-table-wrap::before {
    content: '';
    position: absolute; top: 0; left: 0;
    width: 22px; height: 22px;
    border-top: 2px solid var(--gold-bright);
    border-left: 2px solid var(--gold-bright);
    filter: drop-shadow(0 0 4px rgba(240,192,96,0.5));
    z-index: 2;
}
.online-table-wrap::after {
    content: '';
    position: absolute; bottom: 0; right: 0;
    width: 22px; height: 22px;
    border-bottom: 2px solid var(--arcane-bright);
    border-right: 2px solid var(--arcane-bright);
    filter: drop-shadow(0 0 4px rgba(136,144,255,0.5));
    z-index: 2;
}
.online-table { width: 100%; border-collapse: collapse; }
.online-table thead tr {
    background: rgba(15,18,48,0.8);
    border-bottom: 1px solid rgba(136,144,255,0.15);
}
.online-table thead th {
    font-family: 'Cinzel', serif;
    font-size: 0.52rem;
    letter-spacing: 0.25em;
    text-transform: uppercase;
    color: var(--silver);
    padding: 0.75rem 1.2rem;
    text-align: left;
    white-space: nowrap;
}
.online-table tbody tr {
    border-bottom: 1px solid rgba(136,144,255,0.06);
    transition: background 0.2s;
}
.online-table tbody tr:last-child { border-bottom: none; }
.online-table tbody tr:hover { background: rgba(136,144,255,0.05); }
.online-table td { padding: 0.65rem 1.2rem; vertical-align: middle; }
.ot-name {
    font-family: 'Cinzel', serif;
    font-size: 0.68rem;
    letter-spacing: 0.1em;
    color: var(--white);
}
.ot-level {
    font-family: 'Cinzel', serif;
    font-size: 0.65rem;
    color: var(--gold-bright);
    text-shadow: 0 0 8px rgba(240,192,96,0.4);
    text-align: center;
}
.ot-race-icon, .ot-class-icon {
    width: 26px; height: 26px;
    border-radius: 3px;
    border: 1px solid rgba(136,144,255,0.2);
    image-rendering: pixelated;
    object-fit: cover;
}
.ot-class-cell { display: flex; align-items: center; gap: 0.5rem; }
.ot-class-name {
    font-family: 'Cinzel', serif;
    font-size: 0.58rem;
    letter-spacing: 0.08em;
}
.ot-zone {
    font-family: 'Crimson Pro', serif;
    font-size: 0.9rem;
    color: var(--silver);
}
.online-empty {
    text-align: center;
    padding: 2.5rem;
    font-family: 'Cinzel', serif;
    font-size: 0.6rem;
    letter-spacing: 0.2em;
    color: rgba(168,180,208,0.35);
    text-transform: uppercase;
}
</style>

<main class="royaumes-page">

    <!-- EN-TÊTE PAGE -->
    <div class="page-header">
        <p class="page-eyebrow">
            <span class="eyebrow-line"></span>
            Serveur Privé World of Warcraft
            <span class="eyebrow-line"></span>
        </p>
        <h1 class="page-title">Royaumes d'Eons</h1>
        <p class="page-subtitle">Plongez dans les terres mystiques d'Azeroth, sous la bannière de la nuit arcanique.</p>
        <div class="page-divider">
            <div class="page-divider-line"></div>
            <div class="page-divider-gem"></div>
            <div class="page-divider-line"></div>
        </div>
    </div>

    <!-- ── ADRESSE DU SERVEUR ────────────────────────────────── -->
    <div class="realm-connect-info">
        <div>
            <div class="connect-label">Adresse du serveur</div>
            <div class="connect-value" id="realm-host">realm.eons-world.eu</div>
        </div>
        <div>
            <div class="connect-label">Port</div>
            <div class="connect-value">3724</div>
        </div>
        <div>
            <div class="connect-label">Version</div>
            <div class="connect-value" style="color:var(--gold-bright)">3.3.5a</div>
        </div>
        <button class="connect-copy-btn" onclick="copyRealmlist()">⎘ Copier le realmlist</button>
    </div>

    <div class="royaumes-grid">

        <!-- ── PANNEAU STATS ───────────────────────────────────── -->
        <div class="realm-stats-panel">

            <!-- Carte du royaume -->
            <div class="realm-card">
                <div class="realm-card-header">
                    <span class="realm-card-icon">🏰</span>
                    <div>
                        <div class="realm-card-name">Eons</div>
                        <div class="realm-card-type">Wrath of the Lich King — 3.3.5a</div>
                    </div>
                </div>

                <div class="realm-stat-list">
                    <div class="realm-stat-row">
                        <span class="realm-stat-key"><span class="realm-stat-key-icon">⚡</span> Statut</span>
                        <span class="realm-stat-val <?= $serverOnline ? 'green' : '' ?>" style="<?= !$serverOnline ? 'color:var(--error)' : '' ?>">
                            <?= $serverOnline ? '● En ligne' : '● Hors ligne' ?>
                        </span>
                    </div>
                    <div class="realm-stat-row">
                        <span class="realm-stat-key"><span class="realm-stat-key-icon">👥</span> Joueurs en ligne</span>
                        <span class="realm-stat-val green" id="online-count-realm"><?= $onlinePlayers ?></span>
                    </div>
                    <div class="realm-stat-row">
                        <span class="realm-stat-key"><span class="realm-stat-key-icon">📜</span> Comptes inscrits</span>
                        <span class="realm-stat-val arcane"><?= number_format($totalAccounts, 0, ',', ' ') ?></span>
                    </div>
                    <div class="realm-stat-row">
                        <span class="realm-stat-key"><span class="realm-stat-key-icon">⚔</span> Type</span>
                        <span class="realm-stat-val">PvE</span>
                    </div>
                    <div class="realm-stat-row">
                        <span class="realm-stat-key"><span class="realm-stat-key-icon">🌍</span> Langue</span>
                        <span class="realm-stat-val gold">Français</span>
                    </div>
                    <div class="realm-stat-row">
                        <span class="realm-stat-key"><span class="realm-stat-key-icon">⚙</span> Core</span>
                        <span class="realm-stat-val">TrinityCore</span>
                    </div>
                </div>

                <!-- Barre de population -->
                <div class="pop-bar-wrap">
                    <div class="pop-bar-label">
                        <span>Population</span>
                        <span id="pop-pct">—</span>
                    </div>
                    <div class="pop-bar">
                        <div class="pop-bar-fill" id="pop-bar-fill"></div>
                    </div>
                </div>
            </div>

            <!-- Taux du serveur -->
            <div class="realm-card">
                <div class="realm-card-header">
                    <span class="realm-card-icon">⚗</span>
                    <div>
                        <div class="realm-card-name">Taux du serveur</div>
                        <div class="realm-card-type">Rates officiels</div>
                    </div>
                </div>

                <div class="rates-grid">
                    <div class="rate-badge">
                        <span class="rate-badge-val">×1</span>
                        <span class="rate-badge-lbl">Expérience</span>
                    </div>
                    <div class="rate-badge">
                        <span class="rate-badge-val">×1</span>
                        <span class="rate-badge-lbl">Or</span>
                    </div>
                    <div class="rate-badge">
                        <span class="rate-badge-val">×1</span>
                        <span class="rate-badge-lbl">Drop</span>
                    </div>
                    <div class="rate-badge">
                        <span class="rate-badge-val">×1</span>
                        <span class="rate-badge-lbl">Honneur</span>
                    </div>
                    <div class="rate-badge">
                        <span class="rate-badge-val">×1</span>
                        <span class="rate-badge-lbl">Réputation</span>
                    </div>
                    <div class="rate-badge">
                        <span class="rate-badge-val">×1</span>
                        <span class="rate-badge-lbl">Profession</span>
                    </div>
                </div>

                <div class="mini-stats-grid">
                    <div class="mini-stat">
                        <span class="mini-stat-val gold">80</span>
                        <span class="mini-stat-lbl">Niveau max</span>
                    </div>
                    <div class="mini-stat">
                        <span class="mini-stat-val arcane">PvE</span>
                        <span class="mini-stat-lbl">Mode jeu</span>
                    </div>
                </div>
            </div>

        </div><!-- /realm-stats-panel -->
    </div><!-- /royaumes-grid -->

    <!-- ── JOUEURS EN LIGNE ───────────────────────────────────── -->
    <section class="online-players-section reveal">
        <div class="online-players-header">
            <div class="online-players-title">Joueurs en ligne</div>
            <div class="online-players-count">
                <span><?= $onlinePlayers ?></span> aventurier<?= $onlinePlayers > 1 ? 's' : '' ?> connecté<?= $onlinePlayers > 1 ? 's' : '' ?>
            </div>
        </div>

        <div class="online-table-wrap">
            <?php if (empty($onlineCharsList)): ?>
                <div class="online-empty">✦ Aucun aventurier en ligne pour le moment ✦</div>
            <?php else: ?>
                <table class="online-table">
                    <thead>
                        <tr>
                            <th>✦ Nom</th>
                            <th style="text-align:center">Niveau</th>
                            <th>Race</th>
                            <th>Classe</th>
                            <th>Zone</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $raceIcons = [
                        1  => '/assets/images/races/human.png',
                        2  => '/assets/images/races/orc.png',
                        3  => '/assets/images/races/dwarf.png',
                        4  => '/assets/images/races/night_elf.png',
                        5  => '/assets/images/races/undead.png',
                        6  => '/assets/images/races/tauren.png',
                        7  => '/assets/images/races/gnome.png',
                        8  => '/assets/images/races/troll.png',
                        10 => '/assets/images/races/blood_elf.png',
                        11 => '/assets/images/races/draenei.png',
                    ];
                    $classIconFiles = [
                        1  => '/assets/images/class/IconeWarrior.png',
                        2  => '/assets/images/class/IconePaladin.png',
                        3  => '/assets/images/class/IconeHunter.png',
                        4  => '/assets/images/class/IconeRogue.png',
                        5  => '/assets/images/class/IconePriest.png',
                        6  => '/assets/images/class/IconeDK.png',
                        7  => '/assets/images/class/IconeChaman.png',
                        8  => '/assets/images/class/IconeMage.png',
                        9  => '/assets/images/class/IconeWarlock.png',
                        11 => '/assets/images/class/IconeDruid.png',
                    ];
                    $classNamesOnline = [
                        1=>'Guerrier',2=>'Paladin',3=>'Chasseur',4=>'Voleur',5=>'Prêtre',
                        6=>'Chevalier de la Mort',7=>'Chaman',8=>'Mage',9=>'Démoniste',11=>'Druide'
                    ];
                    $classColorsOnline = [
                        1=>'#C79C6E',2=>'#F58CBA',3=>'#ABD473',4=>'#FFF569',5=>'#FFFFFF',
                        6=>'#C41F3B',7=>'#0070DE',8=>'#69CCF0',9=>'#9482C9',11=>'#FF7D0A'
                    ];
                    foreach ($onlineCharsList as $char):
                        $classId = (int)$char['class'];
                        $raceId  = (int)$char['race'];
                        $color   = $classColorsOnline[$classId] ?? '#8890ff';
                        $clsName = $classNamesOnline[$classId] ?? 'Inconnu';
                        $zoneName = $zoneNames[$char['zone']] ?? 'Azeroth';
                        $raceIcon = $raceIcons[$raceId] ?? '';
                        $clsIcon  = $classIconFiles[$classId] ?? '';
                    ?>
                        <tr>
                            <td class="ot-name"><?= htmlspecialchars($char['name']) ?></td>
                            <td class="ot-level"><?= (int)$char['level'] ?></td>
                            <td>
                                <?php if ($raceIcon): ?>
                                    <img src="<?= $raceIcon ?>" class="ot-race-icon" alt="">
                                <?php else: ?>
                                    <span style="color:var(--silver);font-size:0.7rem">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="display:flex;align-items:center;gap:0.5rem;padding-top:0.8rem">
                                <?php if ($clsIcon): ?>
                                    <img src="<?= $clsIcon ?>" class="ot-class-icon" alt="">
                                <?php endif; ?>
                                <span class="ot-class-name" style="color:<?= $color ?>"><?= htmlspecialchars($clsName) ?></span>
                            </td>
                            <td class="ot-zone"><?= htmlspecialchars($zoneName) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>

</main>

<!-- FOOTER -->
<footer>
    <p class="footer-logo">Eons</p>
    <p class="footer-tagline">World of Warcraft 3.3.5a — Powered by TrinityCore</p>
    <div class="footer-links">
        <a href="index.php">Accueil</a>
        <a href="royaumes.php" style="color:var(--arcane-bright)">Royaumes</a>
        <a href="#">Règlement</a>
        <a href="#">Discord</a>
    </div>
    <div class="footer-sep"><div class="footer-gem"></div></div>
    <p class="footer-bottom">
        &copy; <?= date('Y') ?> Eons — Projet non officiel, sans affiliation avec <span>Blizzard Entertainment</span>.
    </p>
</footer>

<script>
// ── Copier le realmlist ──────────────────────────────────────
function copyRealmlist() {
    const text = 'set realmlist realm.eons-world.eu';
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.querySelector('.connect-copy-btn');
        const orig = btn.textContent;
        btn.textContent = '✓ Copié !';
        btn.style.color = 'var(--success)';
        btn.style.borderColor = 'rgba(95,255,176,0.4)';
        setTimeout(() => {
            btn.textContent = orig;
            btn.style.color = '';
            btn.style.borderColor = '';
        }, 2000);
    }).catch(() => {
        prompt('Copiez cette ligne dans votre realmlist.wtf :', 'set realmlist realm.eons-world.eu');
    });
}

// ── Barre de population ──────────────────────────────────────
(function() {
    const MAX_POP = 500; // joueurs max attendus
    const online  = <?= $onlinePlayers ?>;
    const pct     = Math.min(100, Math.round((online / MAX_POP) * 100));
    const fill    = document.getElementById('pop-bar-fill');
    const label   = document.getElementById('pop-pct');

    let popText = 'Déserte';
    if (pct >= 80)      popText = 'Élevée';
    else if (pct >= 50) popText = 'Normale';
    else if (pct >= 20) popText = 'Faible';

    if (label) label.textContent = popText;

    // Animer la barre après chargement
    setTimeout(() => {
        if (fill) fill.style.width = pct + '%';
    }, 600);
})();

// ── Refresh joueurs en ligne ─────────────────────────────────
(function() {
    const el = document.getElementById('online-count-realm');
    if (!el) return;
    function refresh() {
        fetch('api/online.php')
            .then(r => r.json())
            .then(d => {
                if (typeof d.count === 'number') el.textContent = d.count;
            })
            .catch(() => {});
    }
    setInterval(refresh, 30000);
})();
</script>
</body>
</html>
