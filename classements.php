<?php
// ============================================================
//  classements.php — Eons CMS | Arcanic Theme Enhanced
// ============================================================
require_once __DIR__ . '/config.php';

// ── Mappings WoW ─────────────────────────────────────────────
$races = [
    1=>'Humain',2=>'Orc',3=>'Nain',4=>'Elfe de la nuit',5=>'Mort-vivant',
    6=>'Tauren',7=>'Gnome',8=>'Troll',10=>'Elfe de sang',11=>'Draeneï',
];
$classes = [
    1=>'Guerrier',2=>'Paladin',3=>'Chasseur',4=>'Voleur',5=>'Prêtre',
    6=>'Chevalier de la mort',7=>'Chaman',8=>'Mage',9=>'Démoniste',
    10=>'Moine',11=>'Druide',
];
$classColors = [
    1=>'#C79C6E',2=>'#F58CBA',3=>'#ABD473',4=>'#FFF569',5=>'#FFFFFF',
    6=>'#C41F3B',7=>'#0070DE',8=>'#69CCF0',9=>'#9482C9',
    10=>'#00FF96',11=>'#FF7D0A',
];
$raceIcons = [
    1=>'👤',2=>'👹',3=>'⛏',4=>'🌙',5=>'💀',
    6=>'🐂',7=>'⚙',8=>'🥁',10=>'🌹',11=>'💎',
];
$classIcons = [
    1=>'⚔',2=>'🛡',3=>'🏹',4=>'🗡',5=>'✝',
    6=>'💀',7=>'⚡',8=>'🔥',9=>'🌑',10=>'☯',11=>'🍃',
];

function formatMoney(int $copper): string {
    $gold   = intdiv($copper, 10000);
    $silver = intdiv($copper % 10000, 100);
    $cop    = $copper % 100;
    $parts  = [];
    if ($gold)   $parts[] = "<span style='color:var(--gold-bright)'>{$gold}⬤</span>";
    if ($silver) $parts[] = "<span style='color:#c0c0c0'>{$silver}●</span>";
    if ($cop || empty($parts)) $parts[] = "<span style='color:#cd7f32'>{$cop}·</span>";
    return implode(' ', $parts);
}

function formatTime(int $secs): string {
    $d = intdiv($secs, 86400);
    $h = intdiv($secs % 86400, 3600);
    $m = intdiv($secs % 3600, 60);
    if ($d > 0) return "{$d}j {$h}h";
    if ($h > 0) return "{$h}h {$m}m";
    return "{$m}m";
}

// ── Requêtes DB ───────────────────────────────────────────────
$topLevels  = [];
$topPvP     = [];
$topGuilds  = [];
$topGold    = [];
$dbError    = false;

try {
    $dsn   = 'mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_CHARS_NAME.';charset=utf8mb4';
    $chars = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);

    // Top 25 — Niveau puis temps de jeu
    $topLevels = $chars->query(
        "SELECT name, race, class, level, totaltime, gender
         FROM characters
         WHERE deleteDate IS NULL AND name IS NOT NULL
         ORDER BY level DESC, totaltime DESC
         LIMIT 25"
    )->fetchAll();

    // Top 25 — PvP kills
    $topPvP = $chars->query(
        "SELECT name, race, class, totalKills, totalHonorPoints, gender
         FROM characters
         WHERE deleteDate IS NULL AND name IS NOT NULL AND totalKills > 0
         ORDER BY totalKills DESC
         LIMIT 25"
    )->fetchAll();

    // Top 25 — Or
    $topGold = $chars->query(
        "SELECT name, race, class, money, level, gender
         FROM characters
         WHERE deleteDate IS NULL AND name IS NOT NULL
         ORDER BY money DESC
         LIMIT 25"
    )->fetchAll();

    // Top 25 — Guildes par nombre de membres
    $topGuilds = $chars->query(
        "SELECT g.name AS guild_name, g.info, g.createdate,
                COUNT(gm.guildid) AS member_count,
                leader.name AS leader_name, leader.level AS leader_level
         FROM guild g
         LEFT JOIN guild_member gm ON gm.guildid = g.guildid
         LEFT JOIN characters leader ON leader.guid = g.leaderguid
         GROUP BY g.guildid
         ORDER BY member_count DESC
         LIMIT 25"
    )->fetchAll();

} catch (PDOException $e) {
    $dbError = true;
    error_log('[Classements] ' . $e->getMessage());
}

$pageTitle = 'Classements — Eons';
require_once __DIR__ . '/header.php';
?>
<style>
/* ─── PAGE ────────────────────────────────────────────────── */
.rank-page {
    position: relative; z-index: 10;
    min-height: calc(100vh - 62px);
    margin-top: 62px;
    padding: 3rem 1.5rem 5rem;
    max-width: 1100px;
    margin-left: auto; margin-right: auto;
}

/* ─── EN-TÊTE ─────────────────────────────────────────────── */
.rank-header {
    text-align: center;
    margin-bottom: 2.8rem;
    animation: fadeUp 0.7s 0.1s both;
}
.rank-eyebrow {
    font-family: 'Cinzel', serif;
    font-size: 0.62rem; letter-spacing: 0.5em;
    color: var(--gold); text-transform: uppercase;
    margin-bottom: 1rem;
    display: flex; align-items: center; justify-content: center; gap: 1rem;
}
.rank-eyebrow-line {
    display: inline-block; width: 50px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--gold)); opacity: 0.5;
}
.rank-eyebrow-line:last-child { transform: scaleX(-1); }
.rank-title {
    font-family: 'Cinzel Decorative', serif;
    font-size: clamp(1.8rem, 4vw, 2.8rem); font-weight: 700;
    color: var(--white); text-shadow: 0 0 40px rgba(136,144,255,0.3);
    margin-bottom: 0.6rem;
}
.rank-subtitle {
    font-size: 0.95rem; color: var(--silver); font-style: italic;
}
.rank-divider {
    display: flex; align-items: center; justify-content: center; gap: 1rem;
    margin-top: 1.5rem;
}
.rank-divider-line {
    width: 70px; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(136,144,255,0.4));
}
.rank-divider-line:last-child { transform: scaleX(-1); }
.rank-divider-gem {
    width: 8px; height: 8px; background: var(--gold);
    transform: rotate(45deg);
    box-shadow: 0 0 14px rgba(240,192,96,0.9);
    animation: gemPulse 2.5s ease-in-out infinite;
}
@keyframes gemPulse {
    0%,100% { box-shadow: 0 0 14px rgba(240,192,96,0.8); }
    50%      { box-shadow: 0 0 28px rgba(240,192,96,1), 0 0 50px rgba(240,192,96,0.4); }
}

/* ─── ONGLETS ─────────────────────────────────────────────── */
.rank-tabs {
    display: flex; gap: 0; margin-bottom: 2rem;
    border-bottom: 1px solid rgba(136,144,255,0.15);
    animation: fadeUp 0.7s 0.2s both;
    overflow-x: auto;
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE/Edge */
}
.rank-tabs::-webkit-scrollbar { display: none; } /* Chrome/Safari */
.rank-tab {
    display: flex; align-items: center; gap: 0.5rem;
    font-family: 'Cinzel', serif;
    font-size: 0.58rem; letter-spacing: 0.18em; text-transform: uppercase;
    color: var(--silver); background: none; border: none;
    padding: 0.9rem 1.5rem; cursor: pointer;
    position: relative; transition: color 0.3s;
    white-space: nowrap;
}
.rank-tab::after {
    content: '';
    position: absolute; bottom: -1px; left: 0; right: 0; height: 2px;
    background: transparent; transition: background 0.3s, box-shadow 0.3s;
}
.rank-tab:hover { color: var(--silver-bright); }
.rank-tab.active { color: var(--gold-bright); }
.rank-tab.active::after {
    background: var(--gold-bright);
    box-shadow: 0 0 10px rgba(240,192,96,0.6);
}
.rank-tab-icon { font-size: 1rem; }

/* ─── PANNEAU ─────────────────────────────────────────────── */
.rank-panel { display: none; animation: fadeUp 0.4s both; }
.rank-panel.active { display: block; }

/* ─── TABLEAU ─────────────────────────────────────────────── */
.rank-table-wrap {
    background: rgba(6,8,26,0.82);
    border: 1px solid rgba(136,144,255,0.13);
    backdrop-filter: blur(20px);
    position: relative;
    overflow: hidden;
}
.rank-table-wrap::before {
    content: '';
    position: absolute; top: 0; left: 0;
    width: 24px; height: 24px;
    border-top: 1px solid var(--gold-bright);
    border-left: 1px solid var(--gold-bright);
    pointer-events: none; z-index: 2;
}
.rank-table-wrap::after {
    content: '';
    position: absolute; bottom: 0; right: 0;
    width: 24px; height: 24px;
    border-bottom: 1px solid var(--arcane-bright);
    border-right: 1px solid var(--arcane-bright);
    pointer-events: none; z-index: 2;
}

.rank-table {
    width: 100%; border-collapse: collapse;
}
.rank-table thead tr {
    border-bottom: 1px solid rgba(136,144,255,0.12);
}
.rank-table th {
    font-family: 'Cinzel', serif;
    font-size: 0.5rem; letter-spacing: 0.2em;
    color: var(--silver); text-transform: uppercase;
    padding: 0.9rem 1.2rem; text-align: left;
    font-weight: 600;
}
.rank-table th.center { text-align: center; }

.rank-table tbody tr {
    border-bottom: 1px solid rgba(136,144,255,0.05);
    transition: background 0.2s;
}
.rank-table tbody tr:last-child { border-bottom: none; }
.rank-table tbody tr:hover { background: rgba(136,144,255,0.04); }

/* Top 3 highlight */
.rank-table tbody tr.rank-1 { background: rgba(240,192,96,0.05); }
.rank-table tbody tr.rank-2 { background: rgba(200,210,220,0.04); }
.rank-table tbody tr.rank-3 { background: rgba(180,100,40,0.04); }
.rank-table tbody tr.rank-1:hover { background: rgba(240,192,96,0.09); }
.rank-table tbody tr.rank-2:hover { background: rgba(200,210,220,0.08); }
.rank-table tbody tr.rank-3:hover { background: rgba(180,100,40,0.08); }

.rank-table td {
    padding: 0.75rem 1.2rem;
    font-size: 0.9rem; color: var(--silver-bright);
    vertical-align: middle;
}
.rank-table td.center { text-align: center; }

/* Médaille de rang */
.rank-medal {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px;
    font-family: 'Cinzel', serif; font-size: 0.65rem; font-weight: 700;
    border-radius: 50%;
}
.rank-medal.m1 {
    background: linear-gradient(135deg, #9a6418, #f0c060);
    color: #1a0e00;
    box-shadow: 0 0 12px rgba(240,192,96,0.6);
}
.rank-medal.m2 {
    background: linear-gradient(135deg, #606878, #c8d4e0);
    color: #1a1e28;
    box-shadow: 0 0 8px rgba(200,212,224,0.4);
}
.rank-medal.m3 {
    background: linear-gradient(135deg, #6e3010, #c87840);
    color: #fff;
    box-shadow: 0 0 8px rgba(200,120,60,0.4);
}
.rank-medal.mn {
    background: rgba(136,144,255,0.08);
    color: rgba(168,180,208,0.6);
    border: 1px solid rgba(136,144,255,0.1);
    font-size: 0.55rem;
}

/* Nom joueur */
.player-name {
    font-family: 'Cinzel', serif;
    font-size: 0.82rem; font-weight: 600;
    letter-spacing: 0.05em;
}
.player-meta {
    font-size: 0.72rem; color: var(--silver);
    font-style: italic; margin-top: 0.1rem;
}

/* Badge classe */
.class-badge {
    display: inline-flex; align-items: center; gap: 0.35rem;
    font-family: 'Cinzel', serif;
    font-size: 0.5rem; letter-spacing: 0.1em;
    padding: 0.2rem 0.5rem;
    background: rgba(136,144,255,0.06);
    border: 1px solid rgba(136,144,255,0.1);
    white-space: nowrap;
}

/* Valeur stat */
.stat-val {
    font-family: 'Cinzel Decorative', serif;
    font-size: 1rem; font-weight: 700;
    color: var(--white);
}
.stat-val.gold-col   { color: var(--gold-bright); text-shadow: 0 0 10px rgba(240,192,96,0.4); }
.stat-val.green-col  { color: var(--success);     text-shadow: 0 0 10px rgba(95,255,176,0.3); }
.stat-val.arcane-col { color: var(--arcane-bright);text-shadow: 0 0 10px rgba(136,144,255,0.3); }
.stat-val.red-col    { color: #ff8080;             text-shadow: 0 0 10px rgba(255,80,80,0.3); }

/* Barre de progression mini */
.mini-bar-wrap { display: flex; align-items: center; gap: 0.5rem; }
.mini-bar {
    flex: 1; height: 3px;
    background: rgba(136,144,255,0.1);
    max-width: 80px;
}
.mini-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--arcane-glow), var(--arcane-bright));
    box-shadow: 0 0 6px rgba(136,144,255,0.5);
}

/* Guildes — emblème */
.guild-emblem {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--arcane-dark), var(--arcane));
    border: 1px solid rgba(136,144,255,0.2);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1rem;
}

/* Message vide / erreur */
.rank-empty {
    text-align: center; padding: 3rem;
    font-family: 'Cinzel', serif;
    font-size: 0.7rem; letter-spacing: 0.2em;
    color: var(--silver); text-transform: uppercase;
    opacity: 0.5;
}

/* ─── RESPONSIVE ──────────────────────────────────────────── */
@media (max-width: 768px) {
    .rank-page { padding: 2rem 1rem 4rem; }
    .rank-tab { padding: 0.75rem 1rem; font-size: 0.52rem; }
    .rank-table th, .rank-table td { padding: 0.6rem 0.8rem; }
    .col-hide { display: none; }
    .rank-table { font-size: 0.82rem; }
}

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(14px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>

<main class="rank-page">

    <!-- EN-TÊTE -->
    <div class="rank-header">
        <p class="rank-eyebrow">
            <span class="rank-eyebrow-line"></span>
            Serveur Eons — WotLK 3.3.5a
            <span class="rank-eyebrow-line"></span>
        </p>
        <h1 class="rank-title">Classements</h1>
        <p class="rank-subtitle">Les légendes forgées dans la nuit arcanique d'Azeroth.</p>
        <div class="rank-divider">
            <div class="rank-divider-line"></div>
            <div class="rank-divider-gem"></div>
            <div class="rank-divider-line"></div>
        </div>
    </div>

    <!-- ONGLETS -->
    <div class="rank-tabs" role="tablist">
        <button class="rank-tab active" onclick="showTab('levels')" id="tab-levels" role="tab">
            <span class="rank-tab-icon">⚔</span> Niveau & Prestige
        </button>
        <button class="rank-tab" onclick="showTab('pvp')" id="tab-pvp" role="tab">
            <span class="rank-tab-icon">🏹</span> PvP — Kills
        </button>
        <button class="rank-tab" onclick="showTab('guilds')" id="tab-guilds" role="tab">
            <span class="rank-tab-icon">🏰</span> Guildes
        </button>
        <button class="rank-tab" onclick="showTab('gold')" id="tab-gold" role="tab">
            <span class="rank-tab-icon">💰</span> Richesse
        </button>
    </div>

    <?php if ($dbError): ?>
    <div class="rank-empty">⚠ Impossible de contacter la base de données. Réessayez plus tard.</div>
    <?php else: ?>

    <!-- ══ ONGLET 1 : NIVEAU ══════════════════════════════════ -->
    <div class="rank-panel active" id="panel-levels">
        <?php if (empty($topLevels)): ?>
            <div class="rank-empty">Aucun héros enregistré pour l'instant.</div>
        <?php else: ?>
        <div class="rank-table-wrap">
            <table class="rank-table">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Héros</th>
                        <th class="center">Classe</th>
                        <th class="center">Race</th>
                        <th class="center">Niveau</th>
                        <th class="center col-hide">Temps de jeu</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($topLevels as $i => $row):
                    $rank = $i + 1;
                    $medalClass = $rank <= 3 ? "m{$rank}" : "mn";
                    $cls  = $row['class'];
                    $rc   = $row['race'];
                    $clsColor = $classColors[$cls] ?? '#a8b4d0';
                    $clsName  = $classes[$cls]     ?? '?';
                    $clsIcon  = $classIcons[$cls]  ?? '⚔';
                    $rcName   = $races[$rc]        ?? '?';
                    $rcIcon   = $raceIcons[$rc]    ?? '👤';
                    $lvlPct   = round(($row['level'] / 80) * 100);
                ?>
                <tr class="<?= $rank <= 3 ? "rank-{$rank}" : '' ?>">
                    <td class="center">
                        <span class="rank-medal <?= $medalClass ?>"><?= $rank <= 3 ? ['🥇','🥈','🥉'][$rank-1] : $rank ?></span>
                    </td>
                    <td>
                        <div class="player-name" style="color:<?= $clsColor ?>"><?= htmlspecialchars($row['name']) ?></div>
                        <div class="player-meta"><?= $rcIcon ?> <?= $rcName ?></div>
                    </td>
                    <td class="center">
                        <span class="class-badge" style="color:<?= $clsColor ?>;border-color:<?= $clsColor ?>22">
                            <?= $clsIcon ?> <?= $clsName ?>
                        </span>
                    </td>
                    <td class="center">
                        <span style="color:var(--silver);font-size:0.82rem"><?= $rcIcon ?></span>
                    </td>
                    <td class="center">
                        <div class="mini-bar-wrap">
                            <span class="stat-val gold-col"><?= $row['level'] ?></span>
                            <div class="mini-bar">
                                <div class="mini-bar-fill" style="width:<?= $lvlPct ?>%"></div>
                            </div>
                        </div>
                    </td>
                    <td class="center col-hide">
                        <span style="font-size:0.82rem;color:var(--silver)"><?= formatTime((int)$row['totaltime']) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ══ ONGLET 2 : PVP ════════════════════════════════════ -->
    <div class="rank-panel" id="panel-pvp">
        <?php if (empty($topPvP)): ?>
            <div class="rank-empty">Aucun kill enregistré pour l'instant.</div>
        <?php else: ?>
        <div class="rank-table-wrap">
            <table class="rank-table">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Combattant</th>
                        <th class="center">Classe</th>
                        <th class="center">Kills totaux</th>
                        <th class="center col-hide">Points d'honneur</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $maxKills = !empty($topPvP) ? (int)$topPvP[0]['totalKills'] : 1;
                foreach ($topPvP as $i => $row):
                    $rank = $i + 1;
                    $medalClass = $rank <= 3 ? "m{$rank}" : "mn";
                    $cls  = $row['class'];
                    $clsColor = $classColors[$cls] ?? '#a8b4d0';
                    $clsName  = $classes[$cls]     ?? '?';
                    $clsIcon  = $classIcons[$cls]  ?? '⚔';
                    $rcIcon   = $raceIcons[$row['race']] ?? '👤';
                    $rcName   = $races[$row['race']]     ?? '?';
                    $killPct  = $maxKills > 0 ? round(((int)$row['totalKills'] / $maxKills) * 100) : 0;
                ?>
                <tr class="<?= $rank <= 3 ? "rank-{$rank}" : '' ?>">
                    <td class="center">
                        <span class="rank-medal <?= $medalClass ?>"><?= $rank <= 3 ? ['🥇','🥈','🥉'][$rank-1] : $rank ?></span>
                    </td>
                    <td>
                        <div class="player-name" style="color:<?= $clsColor ?>"><?= htmlspecialchars($row['name']) ?></div>
                        <div class="player-meta"><?= $rcIcon ?> <?= $rcName ?></div>
                    </td>
                    <td class="center">
                        <span class="class-badge" style="color:<?= $clsColor ?>;border-color:<?= $clsColor ?>22">
                            <?= $clsIcon ?> <?= $clsName ?>
                        </span>
                    </td>
                    <td class="center">
                        <div class="mini-bar-wrap" style="justify-content:center">
                            <span class="stat-val red-col"><?= number_format((int)$row['totalKills'], 0, ',', ' ') ?></span>
                            <div class="mini-bar">
                                <div class="mini-bar-fill" style="width:<?= $killPct ?>%;background:linear-gradient(90deg,#c41f3b,#ff6080);box-shadow:0 0 6px rgba(196,31,59,0.5)"></div>
                            </div>
                        </div>
                    </td>
                    <td class="center col-hide">
                        <span style="font-size:0.82rem;color:var(--gold)"><?= number_format((int)$row['totalHonorPoints'], 0, ',', ' ') ?> pts</span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ══ ONGLET 3 : GUILDES ════════════════════════════════ -->
    <div class="rank-panel" id="panel-guilds">
        <?php if (empty($topGuilds)): ?>
            <div class="rank-empty">Aucune guilde enregistrée pour l'instant.</div>
        <?php else: ?>
        <div class="rank-table-wrap">
            <table class="rank-table">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Guilde</th>
                        <th class="center">Chef</th>
                        <th class="center">Membres</th>
                        <th class="center col-hide">Fondée le</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $maxMembers = !empty($topGuilds) ? (int)$topGuilds[0]['member_count'] : 1;
                foreach ($topGuilds as $i => $row):
                    $rank = $i + 1;
                    $medalClass = $rank <= 3 ? "m{$rank}" : "mn";
                    $memberPct  = $maxMembers > 0 ? round(((int)$row['member_count'] / $maxMembers) * 100) : 0;
                    $founded    = $row['createdate'] ? date('d/m/Y', (int)$row['createdate']) : '—';
                ?>
                <tr class="<?= $rank <= 3 ? "rank-{$rank}" : '' ?>">
                    <td class="center">
                        <span class="rank-medal <?= $medalClass ?>"><?= $rank <= 3 ? ['🥇','🥈','🥉'][$rank-1] : $rank ?></span>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:0.7rem">
                            <div class="guild-emblem">🏰</div>
                            <div>
                                <div class="player-name" style="color:var(--gold-pale)"><?= htmlspecialchars($row['guild_name']) ?></div>
                                <?php if (!empty($row['info'])): ?>
                                <div class="player-meta" style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                    <?= htmlspecialchars(mb_strimwidth($row['info'], 0, 45, '…')) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="center">
                        <span style="font-family:'Cinzel',serif;font-size:0.72rem;color:var(--arcane-bright)">
                            <?= htmlspecialchars($row['leader_name'] ?? '—') ?>
                        </span>
                        <?php if (!empty($row['leader_level'])): ?>
                        <div style="font-size:0.62rem;color:var(--silver)">Niv. <?= $row['leader_level'] ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="center">
                        <div class="mini-bar-wrap" style="justify-content:center">
                            <span class="stat-val arcane-col"><?= (int)$row['member_count'] ?></span>
                            <div class="mini-bar">
                                <div class="mini-bar-fill" style="width:<?= $memberPct ?>%"></div>
                            </div>
                        </div>
                    </td>
                    <td class="center col-hide">
                        <span style="font-size:0.78rem;color:var(--silver)"><?= $founded ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ══ ONGLET 4 : OR ══════════════════════════════════════ -->
    <div class="rank-panel" id="panel-gold">
        <?php if (empty($topGold)): ?>
            <div class="rank-empty">Aucune donnée de richesse disponible.</div>
        <?php else: ?>
        <div class="rank-table-wrap">
            <table class="rank-table">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Personnage</th>
                        <th class="center">Classe</th>
                        <th class="center col-hide">Niveau</th>
                        <th class="center">Fortune</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $maxGold = !empty($topGold) ? (int)$topGold[0]['money'] : 1;
                foreach ($topGold as $i => $row):
                    $rank = $i + 1;
                    $medalClass = $rank <= 3 ? "m{$rank}" : "mn";
                    $cls  = $row['class'];
                    $clsColor = $classColors[$cls] ?? '#a8b4d0';
                    $clsName  = $classes[$cls]     ?? '?';
                    $clsIcon  = $classIcons[$cls]  ?? '⚔';
                    $rcIcon   = $raceIcons[$row['race']] ?? '👤';
                    $rcName   = $races[$row['race']]     ?? '?';
                    $goldPct  = $maxGold > 0 ? round(((int)$row['money'] / $maxGold) * 100) : 0;
                    $goldVal  = intdiv((int)$row['money'], 10000);
                ?>
                <tr class="<?= $rank <= 3 ? "rank-{$rank}" : '' ?>">
                    <td class="center">
                        <span class="rank-medal <?= $medalClass ?>"><?= $rank <= 3 ? ['🥇','🥈','🥉'][$rank-1] : $rank ?></span>
                    </td>
                    <td>
                        <div class="player-name" style="color:<?= $clsColor ?>"><?= htmlspecialchars($row['name']) ?></div>
                        <div class="player-meta"><?= $rcIcon ?> <?= $rcName ?></div>
                    </td>
                    <td class="center">
                        <span class="class-badge" style="color:<?= $clsColor ?>;border-color:<?= $clsColor ?>22">
                            <?= $clsIcon ?> <?= $clsName ?>
                        </span>
                    </td>
                    <td class="center col-hide">
                        <span style="font-size:0.82rem;color:var(--silver)">Niv. <?= $row['level'] ?></span>
                    </td>
                    <td class="center">
                        <div class="mini-bar-wrap" style="justify-content:center">
                            <span class="stat-val gold-col"><?= number_format($goldVal, 0, ',', ' ') ?> ⬤</span>
                            <div class="mini-bar">
                                <div class="mini-bar-fill" style="width:<?= $goldPct ?>%;background:linear-gradient(90deg,#9a6418,#f0c060);box-shadow:0 0 6px rgba(240,192,96,0.5)"></div>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php endif; // $dbError ?>

</main>

<!-- FOOTER -->
<footer>
    <p class="footer-logo">Eons</p>
    <p class="footer-tagline">World of Warcraft 3.3.5a — Powered by TrinityCore</p>
    <div class="footer-links">
        <a href="index.php">Accueil</a>
        <a href="royaumes.php">Royaumes</a>
        <a href="classements.php" style="color:var(--arcane-bright)">Classements</a>
        <a href="#">Discord</a>
    </div>
    <div class="footer-sep"><div class="footer-gem"></div></div>
    <p class="footer-bottom">
        &copy; <?= date('Y') ?> Eons — Projet non officiel, sans affiliation avec <span>Blizzard Entertainment</span>.
    </p>
</footer>

<script>
function showTab(name) {
    // Désactiver tous les onglets et panneaux
    document.querySelectorAll('.rank-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.rank-panel').forEach(p => p.classList.remove('active'));
    // Activer le bon
    document.getElementById('tab-' + name).classList.add('active');
    document.getElementById('panel-' + name).classList.add('active');
}
</script>
</body>
</html>
