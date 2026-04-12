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
    grid-template-columns: 1fr 380px;
    gap: 2rem;
    align-items: start;
}

/* ─── CARTE ÉPIQUE ────────────────────────────────────────── */
.realm-map-wrap {
    position: relative;
    animation: fadeUp 0.8s 0.2s both;
}

.realm-map-card {
    background: rgba(6,8,26,0.85);
    border: 1px solid rgba(136,144,255,0.15);
    backdrop-filter: blur(20px);
    position: relative;
    overflow: hidden;
    padding: 2.5rem;
}

/* Coins dorés */
.realm-map-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0;
    width: 28px; height: 28px;
    border-top: 2px solid var(--gold-bright);
    border-left: 2px solid var(--gold-bright);
    filter: drop-shadow(0 0 6px rgba(240,192,96,0.7));
    z-index: 2;
}
.realm-map-card::after {
    content: '';
    position: absolute;
    bottom: 0; right: 0;
    width: 28px; height: 28px;
    border-bottom: 2px solid var(--arcane-bright);
    border-right: 2px solid var(--arcane-bright);
    filter: drop-shadow(0 0 6px rgba(136,144,255,0.7));
    z-index: 2;
}

.realm-map-title {
    font-family: 'Cinzel', serif;
    font-size: 0.62rem;
    letter-spacing: 0.3em;
    color: var(--gold);
    text-transform: uppercase;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.8rem;
}
.realm-map-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background: linear-gradient(90deg, rgba(240,192,96,0.3), transparent);
}

/* SVG carte Azeroth stylisée */
.realm-map-svg-wrap {
    position: relative;
    width: 100%;
    aspect-ratio: 16/9;
    background:
        radial-gradient(ellipse 80% 60% at 35% 55%, rgba(20,30,100,0.5) 0%, transparent 60%),
        radial-gradient(ellipse 60% 50% at 70% 40%, rgba(60,20,120,0.3) 0%, transparent 55%),
        rgba(4,6,20,0.9);
    border: 1px solid rgba(136,144,255,0.1);
    overflow: hidden;
}

.realm-map-svg-wrap svg {
    width: 100%;
    height: 100%;
}

/* Points lumineux sur la carte */
.map-point {
    position: absolute;
    transform: translate(-50%, -50%);
}
.map-point-dot {
    width: 10px; height: 10px;
    border-radius: 50%;
    background: var(--gold-bright);
    box-shadow: 0 0 12px rgba(240,192,96,0.9), 0 0 24px rgba(240,192,96,0.4);
    animation: pointPulse 2s ease-in-out infinite;
    cursor: pointer;
    position: relative;
    z-index: 2;
}
.map-point-dot.arcane {
    background: var(--arcane-bright);
    box-shadow: 0 0 12px rgba(136,144,255,0.9), 0 0 24px rgba(136,144,255,0.4);
}
.map-point-ring {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 24px; height: 24px;
    border: 1px solid rgba(240,192,96,0.5);
    border-radius: 50%;
    animation: ringExpand 2s ease-out infinite;
}
.map-point-dot.arcane + .map-point-ring {
    border-color: rgba(136,144,255,0.5);
}
@keyframes pointPulse {
    0%,100% { transform: scale(1); }
    50%      { transform: scale(1.3); }
}
@keyframes ringExpand {
    0%   { transform: translate(-50%,-50%) scale(1); opacity: 0.8; }
    100% { transform: translate(-50%,-50%) scale(2.5); opacity: 0; }
}

.map-point-label {
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    margin-bottom: 8px;
    font-family: 'Cinzel', serif;
    font-size: 0.5rem;
    letter-spacing: 0.15em;
    color: var(--gold-bright);
    text-transform: uppercase;
    white-space: nowrap;
    text-shadow: 0 0 8px rgba(240,192,96,0.8);
    pointer-events: none;
}

/* Légende statut serveur dans la carte */
.map-status-badge {
    position: absolute;
    top: 1rem; right: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(6,8,26,0.9);
    border: 1px solid rgba(136,144,255,0.2);
    padding: 0.4rem 0.8rem;
    font-family: 'Cinzel', serif;
    font-size: 0.55rem;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--silver);
    backdrop-filter: blur(8px);
}
.status-led {
    width: 7px; height: 7px;
    border-radius: 50%;
    background: var(--success);
    box-shadow: 0 0 8px rgba(95,255,176,0.9);
    animation: ledBlink 1.8s ease-in-out infinite;
}
.status-led.offline {
    background: var(--error);
    box-shadow: 0 0 8px rgba(255,95,95,0.9);
    animation: none;
}
@keyframes ledBlink {
    0%,100% { opacity: 1; }
    50%      { opacity: 0.4; }
}

/* Infos de connexion sous la carte */
.realm-connect-info {
    margin-top: 1.2rem;
    padding: 1rem 1.4rem;
    background: rgba(6,8,26,0.6);
    border: 1px solid rgba(136,144,255,0.1);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
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

/* CTA rejoindre */
.realm-cta-card {
    background: linear-gradient(135deg, rgba(26,29,90,0.6) 0%, rgba(90,48,212,0.15) 100%);
    border: 1px solid rgba(136,144,255,0.2);
    padding: 1.8rem;
    text-align: center;
    position: relative;
    overflow: hidden;
}
.realm-cta-card::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at 50% 0%, rgba(136,144,255,0.08) 0%, transparent 60%);
    pointer-events: none;
}
.realm-cta-title {
    font-family: 'Cinzel Decorative', serif;
    font-size: 1rem;
    color: var(--white);
    margin-bottom: 0.6rem;
}
.realm-cta-text {
    font-size: 0.88rem;
    color: var(--silver);
    font-style: italic;
    margin-bottom: 1.3rem;
    line-height: 1.6;
}
.realm-cta-card .btn {
    width: 100%;
    justify-content: center;
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
    .realm-cta-card { grid-column: 1 / -1; }
}
@media (max-width: 600px) {
    .realm-stats-panel { grid-template-columns: 1fr; }
    .rates-grid { grid-template-columns: repeat(3, 1fr); }
    .mini-stats-grid { grid-template-columns: 1fr 1fr; }
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

    <div class="royaumes-grid">

        <!-- ── CARTE ──────────────────────────────────────────── -->
        <div class="realm-map-wrap">
            <div class="realm-map-card">
                <div class="realm-map-title">✦ Carte du Royaume</div>

                <div class="realm-map-svg-wrap">
                    <!-- Badge statut -->
                    <div class="map-status-badge">
                        <div class="status-led <?= $serverOnline ? '' : 'offline' ?>"></div>
                        <?= $serverOnline ? 'En ligne' : 'Hors ligne' ?>
                    </div>

                    <!-- Carte SVG stylisée Azeroth/fantasy -->
                    <svg viewBox="0 0 800 450" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid slice">
                        <defs>
                            <radialGradient id="oceanGrad" cx="50%" cy="50%" r="70%">
                                <stop offset="0%"   stop-color="#0a0f3a" stop-opacity="1"/>
                                <stop offset="100%" stop-color="#020512" stop-opacity="1"/>
                            </radialGradient>
                            <filter id="glow">
                                <feGaussianBlur stdDeviation="3" result="blur"/>
                                <feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
                            </filter>
                            <filter id="softGlow">
                                <feGaussianBlur stdDeviation="6" result="blur"/>
                                <feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
                            </filter>
                        </defs>

                        <!-- Fond océan -->
                        <rect width="800" height="450" fill="url(#oceanGrad)"/>

                        <!-- Lignes de grille subtiles -->
                        <g stroke="rgba(136,144,255,0.04)" stroke-width="1">
                            <?php for($i=0;$i<18;$i++): ?>
                            <line x1="<?=$i*47?>" y1="0" x2="<?=$i*47?>" y2="450"/>
                            <?php endfor; ?>
                            <?php for($i=0;$i<10;$i++): ?>
                            <line x1="0" y1="<?=$i*50?>" x2="800" y2="<?=$i*50?>"/>
                            <?php endfor; ?>
                        </g>

                        <!-- Continents / terres (silhouettes stylisées) -->
                        <!-- Continent Kalimdor (gauche) -->
                        <path d="M 80,60 C 100,40 160,35 200,55 C 240,75 260,90 270,130
                                 C 280,170 285,200 275,240 C 265,280 240,310 220,340
                                 C 200,370 185,385 170,390 C 145,395 120,380 105,355
                                 C 85,325 70,290 65,255 C 55,210 50,175 55,135
                                 C 60,100 65,78 80,60 Z"
                              fill="rgba(20,40,80,0.7)" stroke="rgba(136,144,255,0.2)" stroke-width="1.5"/>
                        <!-- Détails relief Kalimdor -->
                        <path d="M 130,100 C 150,90 170,95 185,110 C 165,130 145,125 130,100 Z"
                              fill="rgba(136,144,255,0.06)"/>
                        <path d="M 160,200 C 180,185 210,190 220,210 C 200,230 175,228 160,200 Z"
                              fill="rgba(136,144,255,0.05)"/>
                        <!-- Montagnes Kalimdor -->
                        <g fill="rgba(100,120,200,0.15)" stroke="rgba(136,144,255,0.12)" stroke-width="0.8">
                            <polygon points="120,150 135,120 150,150"/>
                            <polygon points="145,155 158,128 172,155"/>
                            <polygon points="190,280 205,252 220,280"/>
                        </g>

                        <!-- Continent Royaumes de l'Est (droite) -->
                        <path d="M 380,50 C 410,35 470,38 510,60 C 550,82 575,110 590,145
                                 C 610,185 615,220 605,258 C 595,295 570,325 545,348
                                 C 520,370 490,382 460,380 C 430,378 405,362 385,338
                                 C 360,308 348,275 345,240 C 340,200 345,160 360,125
                                 C 368,97 355,68 380,50 Z"
                              fill="rgba(25,35,85,0.7)" stroke="rgba(136,144,255,0.2)" stroke-width="1.5"/>
                        <!-- Détails Royaumes -->
                        <path d="M 420,100 C 445,85 475,92 488,115 C 462,138 435,132 420,100 Z"
                              fill="rgba(136,144,255,0.06)"/>
                        <path d="M 440,250 C 465,235 495,240 505,262 C 482,282 455,278 440,250 Z"
                              fill="rgba(136,144,255,0.05)"/>
                        <!-- Montagnes Royaumes -->
                        <g fill="rgba(100,120,200,0.15)" stroke="rgba(136,144,255,0.12)" stroke-width="0.8">
                            <polygon points="450,130 465,100 480,130"/>
                            <polygon points="475,135 490,108 505,135"/>
                            <polygon points="420,300 435,272 450,300"/>
                        </g>

                        <!-- Northrend (haut centre) -->
                        <path d="M 310,15 C 335,5 380,8 410,20 C 435,32 445,50 440,68
                                 C 432,85 415,90 390,88 C 365,86 340,80 320,65
                                 C 300,50 290,28 310,15 Z"
                              fill="rgba(180,200,255,0.12)" stroke="rgba(200,220,255,0.25)" stroke-width="1.5"/>
                        <!-- Glace Northrend -->
                        <path d="M 330,30 C 350,22 375,25 390,38 C 370,50 348,48 330,30 Z"
                              fill="rgba(200,220,255,0.08)"/>
                        <!-- Label Northrend -->
                        <text x="375" y="55" font-family="serif" font-size="7" fill="rgba(200,220,255,0.5)"
                              text-anchor="middle" letter-spacing="2" transform="rotate(-5,375,55)">NORTHREND</text>

                        <!-- Outreterre (bas centre-droit) -->
                        <path d="M 580,300 C 598,285 630,288 648,305 C 665,320 668,342 655,358
                                 C 640,374 615,378 598,365 C 578,350 565,328 580,300 Z"
                              fill="rgba(120,40,160,0.2)" stroke="rgba(160,80,200,0.3)" stroke-width="1.5"/>
                        <text x="615" y="335" font-family="serif" font-size="6" fill="rgba(160,100,220,0.5)"
                              text-anchor="middle" letter-spacing="1">OUTRETERRE</text>

                        <!-- Océan / Mer -->
                        <!-- Lignes de vague -->
                        <g stroke="rgba(50,70,160,0.12)" stroke-width="1" fill="none">
                            <path d="M 285,150 C 295,145 305,155 315,150"/>
                            <path d="M 285,170 C 295,165 305,175 315,170"/>
                            <path d="M 285,190 C 295,185 305,195 315,190"/>
                            <path d="M 285,210 C 295,205 305,215 315,210"/>
                            <path d="M 285,230 C 295,225 305,235 315,230"/>
                            <path d="M 285,250 C 295,245 305,255 315,250"/>
                            <path d="M 285,270 C 295,265 305,275 315,270"/>
                        </g>

                        <!-- Boussole décorative -->
                        <g transform="translate(720, 60)">
                            <circle r="28" fill="rgba(6,8,26,0.8)" stroke="rgba(240,192,96,0.3)" stroke-width="1"/>
                            <circle r="22" fill="none" stroke="rgba(136,144,255,0.15)" stroke-width="0.5" stroke-dasharray="3,3"/>
                            <polygon points="0,-18 3,-4 0,-8 -3,-4" fill="rgba(255,100,100,0.7)"/>
                            <polygon points="0,18 3,4 0,8 -3,4" fill="rgba(200,210,255,0.5)"/>
                            <polygon points="-18,0 -4,3 -8,0 -4,-3" fill="rgba(200,210,255,0.4)"/>
                            <polygon points="18,0 4,3 8,0 4,-3" fill="rgba(200,210,255,0.4)"/>
                            <circle r="3" fill="rgba(240,192,96,0.8)"/>
                            <text x="0" y="-24" font-family="serif" font-size="7" fill="rgba(240,192,96,0.7)"
                                  text-anchor="middle">N</text>
                        </g>

                        <!-- Lignes de navigation stylisées -->
                        <line x1="200" y1="220" x2="380" y2="220"
                              stroke="rgba(240,192,96,0.08)" stroke-width="1" stroke-dasharray="4,4"/>
                        <line x1="380" y1="220" x2="540" y2="220"
                              stroke="rgba(240,192,96,0.08)" stroke-width="1" stroke-dasharray="4,4"/>

                        <!-- Glyphe central dans l'océan -->
                        <g transform="translate(320,220)" opacity="0.15">
                            <circle r="15" fill="none" stroke="rgba(136,144,255,0.6)" stroke-width="0.8"/>
                            <line x1="0" y1="-15" x2="0" y2="15" stroke="rgba(136,144,255,0.6)" stroke-width="0.5"/>
                            <line x1="-15" y1="0" x2="15" y2="0" stroke="rgba(136,144,255,0.6)" stroke-width="0.5"/>
                        </g>

                        <!-- Noms des continents -->
                        <text x="165" y="230" font-family="serif" font-size="8" fill="rgba(136,144,255,0.35)"
                              text-anchor="middle" letter-spacing="3" transform="rotate(-5,165,230)">KALIMDOR</text>
                        <text x="470" y="220" font-family="serif" font-size="7" fill="rgba(136,144,255,0.3)"
                              text-anchor="middle" letter-spacing="2">ROYAUMES DE L'EST</text>

                        <!-- Points d'intérêt lumineux -->
                        <!-- Orgrimmar -->
                        <g filter="url(#glow)">
                            <circle cx="155" cy="155" r="4" fill="rgba(255,80,80,0.9)"/>
                            <circle cx="155" cy="155" r="8" fill="none" stroke="rgba(255,80,80,0.4)" stroke-width="1">
                                <animate attributeName="r" values="8;16;8" dur="2s" repeatCount="indefinite"/>
                                <animate attributeName="stroke-opacity" values="0.4;0;0.4" dur="2s" repeatCount="indefinite"/>
                            </circle>
                        </g>
                        <text x="155" y="145" font-family="serif" font-size="6" fill="rgba(255,150,100,0.7)"
                              text-anchor="middle" letter-spacing="1">Orgrimmar</text>

                        <!-- Stormwind -->
                        <g filter="url(#glow)">
                            <circle cx="450" cy="180" r="4" fill="rgba(100,150,255,0.9)"/>
                            <circle cx="450" cy="180" r="8" fill="none" stroke="rgba(100,150,255,0.4)" stroke-width="1">
                                <animate attributeName="r" values="8;16;8" dur="2.4s" repeatCount="indefinite"/>
                                <animate attributeName="stroke-opacity" values="0.4;0;0.4" dur="2.4s" repeatCount="indefinite"/>
                            </circle>
                        </g>
                        <text x="450" y="170" font-family="serif" font-size="6" fill="rgba(150,180,255,0.7)"
                              text-anchor="middle" letter-spacing="1">Hurlevent</text>

                        <!-- Dalaran (Northrend) -->
                        <g filter="url(#glow)">
                            <circle cx="375" cy="45" r="3.5" fill="rgba(200,160,255,0.9)"/>
                            <circle cx="375" cy="45" r="7" fill="none" stroke="rgba(200,160,255,0.4)" stroke-width="1">
                                <animate attributeName="r" values="7;14;7" dur="3s" repeatCount="indefinite"/>
                                <animate attributeName="stroke-opacity" values="0.4;0;0.4" dur="3s" repeatCount="indefinite"/>
                            </circle>
                        </g>
                        <text x="375" y="36" font-family="serif" font-size="6" fill="rgba(220,180,255,0.7)"
                              text-anchor="middle" letter-spacing="1">Dalaran</text>

                        <!-- Eons Server point (centre océan, doré) -->
                        <g filter="url(#softGlow)">
                            <circle cx="320" cy="310" r="5" fill="rgba(240,192,96,0.95)"/>
                            <circle cx="320" cy="310" r="10" fill="none" stroke="rgba(240,192,96,0.5)" stroke-width="1.2">
                                <animate attributeName="r" values="10;22;10" dur="2s" repeatCount="indefinite"/>
                                <animate attributeName="stroke-opacity" values="0.5;0;0.5" dur="2s" repeatCount="indefinite"/>
                            </circle>
                            <circle cx="320" cy="310" r="18" fill="none" stroke="rgba(240,192,96,0.2)" stroke-width="0.8">
                                <animate attributeName="r" values="18;32;18" dur="2s" begin="0.5s" repeatCount="indefinite"/>
                                <animate attributeName="stroke-opacity" values="0.2;0;0.2" dur="2s" begin="0.5s" repeatCount="indefinite"/>
                            </circle>
                        </g>
                        <text x="320" y="295" font-family="serif" font-size="7" fill="rgba(240,192,96,0.9)"
                              text-anchor="middle" letter-spacing="2" font-weight="bold">✦ EONS</text>

                    </svg>
                </div>

                <!-- Infos de connexion -->
                <div class="realm-connect-info">
                    <div>
                        <div class="connect-label">Adresse du serveur</div>
                        <div class="connect-value" id="realm-host">eons-world.eu</div>
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
            </div>
        </div>

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

            <!-- CTA rejoindre -->
            <div class="realm-cta-card">
                <div class="realm-cta-title">Prêt à rejoindre ?</div>
                <p class="realm-cta-text">Créez votre compte et entrez dans la nuit arcanique d'Azeroth dès maintenant.</p>
                <?php if ($isLoggedIn): ?>
                    <a href="dashboard.php" class="btn btn-gold">⚗ &nbsp;Mon Tableau de Bord</a>
                <?php else: ?>
                    <a href="auth.php" class="btn btn-gold" style="margin-bottom:0.6rem">⚔ &nbsp;Créer un compte</a>
                    <a href="#" class="btn btn-outline" style="display:flex;justify-content:center;margin-top:0.6rem">📖 &nbsp;Guide de connexion</a>
                <?php endif; ?>
            </div>

        </div><!-- /realm-stats-panel -->
    </div><!-- /royaumes-grid -->
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
    const text = 'set realmlist eons-world.eu';
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
        prompt('Copiez cette ligne dans votre realmlist.wtf :', 'set realmlist eons-world.eu');
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
