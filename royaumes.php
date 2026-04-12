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
$onlineChars   = [];
try {
    $chars = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_CHARS_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $onlinePlayers = (int)$chars->query("SELECT COUNT(*) FROM characters WHERE online = 1")->fetchColumn();
    // Positions des joueurs en ligne sur les cartes du monde (map 0 = Royaumes de l'Est, 1 = Kalimdor)
    $onlineChars = $chars->query(
        "SELECT name, race, class, level, map, position_x, position_y
         FROM characters
         WHERE online = 1 AND map IN (0, 1)
         LIMIT 100"
    )->fetchAll(PDO::FETCH_ASSOC);
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

/* Carte PNG monde */
.realm-map-svg-wrap {
    position: relative;
    width: 100%;
    aspect-ratio: 149/100;
    border: 1px solid rgba(136,144,255,0.15);
    overflow: hidden;
    background: #02030c;
}
.realm-map-svg-wrap img.world-map-img {
    width: 100%; height: 100%;
    object-fit: cover;
    display: block;
    filter: brightness(0.85) saturate(0.9);
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

/* Overlay vignette carte */
.map-vignette {
    position: absolute; inset: 0; pointer-events: none;
    background:
        linear-gradient(180deg, rgba(2,3,12,0.35) 0%, transparent 15%, transparent 85%, rgba(2,3,12,0.5) 100%),
        linear-gradient(90deg,  rgba(2,3,12,0.3)  0%, transparent 10%, transparent 90%, rgba(2,3,12,0.3) 100%);
    z-index: 2;
}

/* Canvas joueurs */
#playerMapCanvas {
    position: absolute; inset: 0;
    width: 100%; height: 100%;
    pointer-events: none;
    z-index: 3;
}

/* Boussole */
.map-compass {
    position: absolute; bottom: 1rem; right: 1rem;
    z-index: 5; opacity: 0.85;
    filter: drop-shadow(0 0 8px rgba(240,192,96,0.3));
}

/* Tooltip joueur */
.map-player-tooltip {
    position: absolute;
    background: rgba(6,8,26,0.96);
    border: 1px solid rgba(136,144,255,0.3);
    color: var(--white);
    font-family: 'Cinzel', serif;
    font-size: 0.58rem;
    letter-spacing: 0.12em;
    padding: 0.5rem 0.8rem;
    pointer-events: none;
    z-index: 10;
    display: none;
    white-space: nowrap;
    backdrop-filter: blur(8px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.6);
}
.map-player-tooltip .tip-name { color: var(--gold-bright); margin-bottom: 0.2rem; font-size: 0.65rem; }
.map-player-tooltip .tip-class { opacity: 0.75; }

/* Légende joueurs */
.map-legend {
    position: absolute; bottom: 1rem; left: 1rem;
    z-index: 5;
    font-family: 'Cinzel', serif; font-size: 0.5rem;
    letter-spacing: 0.15em; color: var(--silver);
    background: rgba(6,8,26,0.8);
    border: 1px solid rgba(136,144,255,0.15);
    padding: 0.4rem 0.7rem;
    display: flex; align-items: center; gap: 0.5rem;
    backdrop-filter: blur(6px);
}
.map-legend-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: var(--gold-bright);
    box-shadow: 0 0 8px rgba(240,192,96,0.8);
    animation: pointPulse 2s ease-in-out infinite;
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

                    <!-- Boussole -->
                    <div class="map-compass">
                        <svg width="52" height="52" viewBox="0 0 52 52">
                            <circle cx="26" cy="26" r="24" fill="rgba(6,8,26,0.85)" stroke="rgba(240,192,96,0.35)" stroke-width="1"/>
                            <circle cx="26" cy="26" r="18" fill="none" stroke="rgba(136,144,255,0.15)" stroke-width="0.5" stroke-dasharray="3,3"/>
                            <polygon points="26,8 28.5,22 26,18 23.5,22" fill="rgba(255,100,100,0.85)"/>
                            <polygon points="26,44 28.5,30 26,34 23.5,30" fill="rgba(180,190,255,0.55)"/>
                            <polygon points="8,26 22,23.5 18,26 22,28.5" fill="rgba(180,190,255,0.45)"/>
                            <polygon points="44,26 30,23.5 34,26 30,28.5" fill="rgba(180,190,255,0.45)"/>
                            <circle cx="26" cy="26" r="3" fill="rgba(240,192,96,0.9)"/>
                            <text x="26" y="5.5" font-family="serif" font-size="6" fill="rgba(240,192,96,0.8)" text-anchor="middle">N</text>
                        </svg>
                    </div>

                    <!-- Image de la carte -->
                    <img class="world-map-img"
                         src="https://eons-world.eu/assets/map/world/world.png"
                         alt="Carte du monde d'Azeroth" />

                    <!-- Overlay sombre sur les bords -->
                    <div class="map-vignette"></div>

                    <!-- Joueurs en ligne (canvas overlay) -->
                    <canvas id="playerMapCanvas"></canvas>

                    <!-- Tooltip joueur -->
                    <div class="map-player-tooltip" id="mapTooltip"></div>

                    <!-- Légende -->
                    <?php if (!empty($onlineChars)): ?>
                    <div class="map-legend">
                        <div class="map-legend-dot"></div>
                        <?= count($onlineChars) ?> joueur<?= count($onlineChars) > 1 ? 's' : '' ?> en ligne
                    </div>
                    <?php endif; ?>

                    <?php
                    // Coordonnées WoW → pourcentage sur la carte PNG
                    // Map 0 (Royaumes de l'Est) : x de -17600 à 17600, y de -13500 à 13500
                    // Map 1 (Kalimdor)            : x de -17600 à 17600, y de -13500 à 13500
                    // Le PNG world.png est 1090×730 (ratio ~1.49:1)
                    // Kalimdor occupe la moitié gauche, Royaumes de l'Est la droite
                    // Northrend est en haut centre (~30% largeur, 0–25% hauteur)

                    // Bornes approx. WoW pour le PNG fourni (calées visuellement)
                    // Map 0 (EK)      : x [-17000, 17000] → [50%, 100%] de la largeur
                    //                   y [-13000, 11600] → [0%, 100%] de la hauteur (y inversé)
                    // Map 1 (Kalimdor): x [-17000, 17000] → [0%, 50%] de la largeur
                    //                   y [-13000, 11600] → [0%, 100%] de la hauteur

                    $classes = [
                        1=>'Guerrier',2=>'Paladin',3=>'Chasseur',4=>'Voleur',5=>'Prêtre',
                        6=>'Chevalier de la mort',7=>'Chaman',8=>'Mage',9=>'Démoniste',
                        10=>'Moine',11=>'Druide'
                    ];
                    $classColors = [
                        1=>'#C79C6E',2=>'#F58CBA',3=>'#ABD473',4=>'#FFF569',5=>'#FFFFFF',
                        6=>'#C41F3B',7=>'#0070DE',8=>'#69CCF0',9=>'#9482C9',
                        10=>'#00FF96',11=>'#FF7D0A'
                    ];

                    // Conversion coordonnées WoW → pourcentage sur le PNG world.png (1002×668)
                    // Chaque continent a son propre espace de coordonnées en WoW.
                    // Bornes calées sur le PNG officiel d'Azeroth (WotLK) :
                    //
                    // Map 0 — Royaumes de l'Est
                    //   X : [-17239, 228]  → zone du continent EK dans le PNG
                    //   Y : [-11900, 16822] → WoW Y inversé (négatif = nord)
                    //   Sur le PNG : EK occupe environ x=[54%,93%], y=[8%,92%]
                    //
                    // Map 1 — Kalimdor
                    //   X : [-13866, 153]   → zone du continent Kalimdor dans le PNG
                    //   Y : [-12766, 16500] → WoW Y inversé
                    //   Sur le PNG : Kalimdor occupe environ x=[5%,43%], y=[8%,92%]
                    //
                    // Axe WoW : X croît vers le SUD, Y croît vers l'OUEST
                    // Sur le PNG : en haut=nord, à gauche=ouest
                    // Donc : pctX ∝ -Y_wow (Y wow négatif = est = droite)
                    //         pctY ∝  X_wow (X wow positif = sud = bas)

                    function wowToMapPct(float $x, float $y, int $map): array {
                        if ($map === 0) {
                            // Royaumes de l'Est
                            // WoW X: [-17239 (nord) → 228 (sud)] → PNG Y: [8% → 92%]
                            // WoW Y: [16822 (ouest) → -11900 (est)] → PNG X: [54% → 93%]
                            $pctY = ($x - (-17239)) / (228 - (-17239));           // 0=nord,1=sud
                            $pctX = (16822 - $y)   / (16822 - (-11900));          // 0=ouest,1=est
                            // Mapper dans les zones du PNG
                            $pctX = 0.54 + $pctX * (0.93 - 0.54);
                            $pctY = 0.08 + $pctY * (0.92 - 0.08);
                        } else {
                            // Kalimdor
                            // WoW X: [-13866 (nord) → 153 (sud)] → PNG Y: [8% → 92%]
                            // WoW Y: [16500 (ouest) → -12766 (est)] → PNG X: [5% → 43%]
                            $pctY = ($x - (-13866)) / (153 - (-13866));
                            $pctX = (16500 - $y)    / (16500 - (-12766));
                            $pctX = 0.05 + $pctX * (0.43 - 0.05);
                            $pctY = 0.08 + $pctY * (0.92 - 0.08);
                        }
                        return [
                            'x' => round(max(0, min(100, $pctX * 100)), 2),
                            'y' => round(max(0, min(100, $pctY * 100)), 2),
                        ];
                    }

                    // Préparer les données joueurs pour JS
                    $playersJson = [];
                    foreach ($onlineChars as $char) {
                        $pct = wowToMapPct((float)$char['position_x'], (float)$char['position_y'], (int)$char['map']);
                        $playersJson[] = [
                            'name'  => htmlspecialchars($char['name']),
                            'class' => $classes[$char['class']] ?? '?',
                            'color' => $classColors[$char['class']] ?? '#8890ff',
                            'level' => (int)$char['level'],
                            'map'   => (int)$char['map'],
                            'x'     => $pct['x'],
                            'y'     => $pct['y'],
                        ];
                    }
                    ?>
                    <script>
                    window._eonPlayers = <?= json_encode($playersJson) ?>;
                    </script>

                </div><!-- /realm-map-svg-wrap -->

                <!-- Infos de connexion -->
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

// ── Carte des joueurs ────────────────────────────────────────
(function() {
    const players = window._eonPlayers || [];
    const canvas  = document.getElementById('playerMapCanvas');
    const tooltip = document.getElementById('mapTooltip');
    const wrap    = canvas ? canvas.closest('.realm-map-svg-wrap') : null;
    if (!canvas || !wrap) return;

    function resize() {
        canvas.width  = wrap.offsetWidth;
        canvas.height = wrap.offsetHeight;
        draw();
    }

    function draw() {
        const ctx = canvas.getContext('2d');
        const W = canvas.width, H = canvas.height;
        ctx.clearRect(0, 0, W, H);
        players.forEach(p => {
            const px = (p.x / 100) * W;
            const py = (p.y / 100) * H;
            const color = p.color;

            // Anneau pulsant (sera animé via rAF)
            ctx.beginPath();
            ctx.arc(px, py, 10, 0, Math.PI * 2);
            ctx.strokeStyle = color.replace(')', ',0.25)').replace('rgb(', 'rgba(').replace('#', '');
            // Simple ring
            const ring = ctx.createRadialGradient(px, py, 4, px, py, 12);
            ring.addColorStop(0, color + '33');
            ring.addColorStop(1, color + '00');
            ctx.fillStyle = ring;
            ctx.fill();

            // Point central
            ctx.beginPath();
            ctx.arc(px, py, 5, 0, Math.PI * 2);
            ctx.fillStyle = color;
            ctx.shadowColor = color;
            ctx.shadowBlur = 12;
            ctx.fill();
            ctx.shadowBlur = 0;

            // Petit point blanc central
            ctx.beginPath();
            ctx.arc(px, py, 1.8, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(255,255,255,0.9)';
            ctx.fill();
        });
    }

    // Animation pulsante
    let t = 0;
    function animate() {
        const ctx = canvas.getContext('2d');
        const W = canvas.width, H = canvas.height;
        ctx.clearRect(0, 0, W, H);
        players.forEach(p => {
            const px = (p.x / 100) * W;
            const py = (p.y / 100) * H;
            const color = p.color;
            const pulse = (Math.sin(t * 2 + px * 0.01) * 0.5 + 0.5); // 0..1

            // Anneau pulsant externe
            const ringR = 10 + pulse * 8;
            const ringAlpha = (1 - pulse) * 0.5;
            ctx.beginPath();
            ctx.arc(px, py, ringR, 0, Math.PI * 2);
            ctx.strokeStyle = color;
            ctx.globalAlpha = ringAlpha;
            ctx.lineWidth = 1;
            ctx.stroke();
            ctx.globalAlpha = 1;

            // Halo soft
            const grad = ctx.createRadialGradient(px, py, 0, px, py, 14);
            grad.addColorStop(0, color + '55');
            grad.addColorStop(1, 'transparent');
            ctx.fillStyle = grad;
            ctx.beginPath();
            ctx.arc(px, py, 14, 0, Math.PI * 2);
            ctx.fill();

            // Point central
            ctx.beginPath();
            ctx.arc(px, py, 5, 0, Math.PI * 2);
            ctx.fillStyle = color;
            ctx.shadowColor = color;
            ctx.shadowBlur = 14;
            ctx.fill();
            ctx.shadowBlur = 0;

            // Éclat blanc
            ctx.beginPath();
            ctx.arc(px, py, 2, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(255,255,255,0.95)';
            ctx.fill();
        });
        t += 0.03;
        requestAnimationFrame(animate);
    }

    // Tooltip au survol
    wrap.addEventListener('mousemove', (e) => {
        if (!tooltip) return;
        const rect = wrap.getBoundingClientRect();
        const mx = e.clientX - rect.left;
        const my = e.clientY - rect.top;
        const W = wrap.offsetWidth, H = wrap.offsetHeight;
        let found = null;
        players.forEach(p => {
            const px = (p.x / 100) * W;
            const py = (p.y / 100) * H;
            const dist = Math.sqrt((mx - px) ** 2 + (my - py) ** 2);
            if (dist < 16) found = p;
        });
        if (found) {
            tooltip.innerHTML =
                '<div class="tip-name">' + found.name + '</div>' +
                '<div class="tip-class" style="color:' + found.color + '">' + found.class + ' — Niv. ' + found.level + '</div>';
            tooltip.style.display = 'block';
            tooltip.style.left = (mx + 14) + 'px';
            tooltip.style.top  = (my - 10) + 'px';
        } else {
            tooltip.style.display = 'none';
        }
    });
    wrap.addEventListener('mouseleave', () => {
        if (tooltip) tooltip.style.display = 'none';
    });

    window.addEventListener('resize', resize);
    resize();
    animate();
})();
</script>
</body>
</html>
