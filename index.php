<?php
// ============================================================
//  index.php — Eons CMS | Arcanic Theme Enhanced
// ============================================================
require_once __DIR__ . '/config.php';

$onlinePlayers = 0;
try {
    $dsn   = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_CHARS_NAME . ';charset=utf8mb4';
    $chars = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $onlinePlayers = (int)$chars->query("SELECT COUNT(*) FROM characters WHERE online = 1")->fetchColumn();
} catch (PDOException $e) {
    error_log('[AU Index] Chars DB error: ' . $e->getMessage());
}

$pageTitle = 'Eons — Serveur Privé WoW 3.3.5a';
require_once __DIR__ . '/header.php';
?>
<style>
    /* ─── HERO ──────────────────────────────────────────────────── */
    .hero {
        position: relative;
        z-index: 10;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 120px 2rem 4rem;
        overflow: hidden;
    }

    /* Vignette overlay */
    .hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(ellipse 65% 55% at 50% 50%, rgba(20,24,80,0.5) 0%, transparent 65%),
            radial-gradient(ellipse 100% 40% at 50% 0%, rgba(2,3,12,0.85) 0%, transparent 50%),
            radial-gradient(ellipse 100% 50% at 50% 100%, rgba(2,3,12,0.95) 0%, transparent 50%);
        z-index: 1;
        pointer-events: none;
    }

    /* Arcane orb background */
    .hero-orb {
        position: absolute;
        border-radius: 50%;
        pointer-events: none;
    }

    .hero-orb-1 {
        width: 500px; height: 500px;
        top: 50%; left: 50%;
        transform: translate(-50%, -52%);
        background: radial-gradient(circle at 40% 35%,
            rgba(90,48,212,0.12) 0%,
            rgba(40,30,120,0.06) 40%,
            transparent 70%);
        box-shadow:
            0 0 100px rgba(136,144,255,0.14),
            0 0 250px rgba(90,48,212,0.08),
            inset 0 0 80px rgba(136,144,255,0.05);
        border: 1px solid rgba(136,144,255,0.08);
        animation: orbPulse1 7s ease-in-out infinite;
        z-index: 1;
    }

    .hero-orb-2 {
        width: 280px; height: 280px;
        top: 50%; left: 50%;
        transform: translate(-50%, -55%);
        background: radial-gradient(circle,
            rgba(240,192,96,0.06) 0%,
            transparent 65%);
        border: 1px solid rgba(240,192,96,0.06);
        animation: orbPulse2 5s ease-in-out infinite;
        z-index: 1;
    }

    /* Rotating rune circle around the orb */
    .hero-rune-ring {
        position: absolute;
        top: 50%; left: 50%;
        transform: translate(-50%, -54%);
        width: 460px; height: 460px;
        border: 1px solid rgba(136,144,255,0.06);
        border-radius: 50%;
        animation: ringRotate 40s linear infinite;
        z-index: 1;
    }
    .hero-rune-ring::before {
        content: '';
        position: absolute;
        inset: -1px;
        border-radius: 50%;
        border: 1px dashed rgba(136,144,255,0.1);
        animation: ringRotate 25s linear infinite reverse;
    }

    /* Small gems on the ring */
    .ring-gem {
        position: absolute;
        width: 6px; height: 6px;
        background: var(--arcane-bright);
        border-radius: 50%;
        box-shadow: 0 0 10px rgba(136,144,255,0.9);
        top: -3px; left: 50%;
        transform: translateX(-50%);
    }
    .ring-gem-gold {
        background: var(--gold-bright);
        box-shadow: 0 0 10px rgba(240,192,96,0.9);
        top: auto; bottom: -3px;
        left: 50%;
    }

    @keyframes orbPulse1 {
        0%, 100% {
            box-shadow: 0 0 100px rgba(136,144,255,0.14), 0 0 250px rgba(90,48,212,0.08);
        }
        50% {
            box-shadow: 0 0 150px rgba(136,144,255,0.22), 0 0 350px rgba(90,48,212,0.14);
        }
    }
    @keyframes orbPulse2 {
        0%, 100% { transform: translate(-50%, -55%) scale(1); }
        50%       { transform: translate(-50%, -55%) scale(1.12); }
    }
    @keyframes ringRotate { from { transform: translate(-50%, -54%) rotate(0deg); } to { transform: translate(-50%, -54%) rotate(360deg); } }

    .hero-content {
        position: relative;
        z-index: 5;
    }

    .hero-eyebrow {
        font-family: 'Cinzel', serif;
        font-size: 0.65rem;
        letter-spacing: 0.5em;
        color: var(--gold);
        text-transform: uppercase;
        margin-bottom: 1.8rem;
        opacity: 0;
        animation: fadeUp 1s 0.3s forwards;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1rem;
    }
    .hero-eyebrow-line {
        display: inline-block;
        width: 40px; height: 1px;
        background: linear-gradient(90deg, transparent, var(--gold));
        opacity: 0.5;
    }
    .hero-eyebrow-line:last-child { background: linear-gradient(90deg, var(--gold), transparent); }

    .hero-title {
        font-family: 'Cinzel Decorative', serif;
        font-weight: 900;
        font-size: clamp(3.5rem, 8.5vw, 7rem);
        line-height: 1;
        letter-spacing: 0.08em;
        color: transparent;
        background: linear-gradient(180deg,
            #ffffff 0%,
            #e0e5ff 20%,
            #b0b8ff 45%,
            #8890ff 65%,
            #5a48d0 82%,
            #2a1890 100%);
        -webkit-background-clip: text;
        background-clip: text;
        filter: drop-shadow(0 0 40px rgba(136,144,255,0.4));
        margin-bottom: 0.5rem;
        opacity: 0;
        animation: fadeUp 1.2s 0.55s cubic-bezier(0.16,1,0.3,1) forwards;
    }

    .hero-subtitle {
        font-family: 'Cinzel Decorative', serif;
        font-weight: 400;
        font-size: clamp(0.9rem, 2.2vw, 1.4rem);
        letter-spacing: 0.4em;
        color: var(--gold-bright);
        text-shadow: 0 0 30px rgba(240,192,96,0.55);
        text-transform: uppercase;
        margin-bottom: 2.2rem;
        opacity: 0;
        animation: fadeUp 1s 0.75s forwards;
    }

    .hero-divider {
        display: flex; align-items: center; justify-content: center; gap: 1rem;
        margin-bottom: 2rem;
        opacity: 0; animation: fadeUp 1s 0.9s forwards;
    }
    .hero-divider-line {
        width: 100px; height: 1px;
        background: linear-gradient(90deg, transparent, rgba(136,144,255,0.5));
    }
    .hero-divider-line:last-child { transform: scaleX(-1); }
    .hero-divider-gem {
        width: 9px; height: 9px;
        background: var(--arcane-bright);
        transform: rotate(45deg);
        box-shadow: 0 0 16px rgba(136,144,255,0.9), 0 0 32px rgba(136,144,255,0.4);
        animation: gemPulse 2.5s ease-in-out infinite;
    }
    @keyframes gemPulse {
        0%, 100% { box-shadow: 0 0 16px rgba(136,144,255,0.9), 0 0 32px rgba(136,144,255,0.4); }
        50%       { box-shadow: 0 0 24px rgba(136,144,255,1),   0 0 50px rgba(136,144,255,0.6); }
    }

    .hero-desc {
        font-size: 1.15rem;
        font-weight: 300;
        line-height: 1.9;
        color: var(--silver);
        max-width: 560px;
        margin: 0 auto 2.8rem;
        font-style: italic;
        opacity: 0;
        animation: fadeUp 1s 1.1s forwards;
    }
    .hero-desc strong { color: var(--arcane-bright); font-style: normal; font-weight: 600; }
    .hero-desc em     { color: var(--gold-bright); font-style: italic; }

    .hero-cta {
        display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;
        opacity: 0; animation: fadeUp 1s 1.3s forwards;
    }

    /* ─── STATS BAR ─────────────────────────────────────────────── */
    .hero-stats {
        position: relative; z-index: 5;
        display: flex; align-items: stretch; justify-content: center;
        flex-wrap: wrap;
        margin-top: 4rem;
        background: rgba(6,8,26,0.7);
        border: 1px solid rgba(136,144,255,0.14);
        backdrop-filter: blur(16px);
        max-width: 760px;
        clip-path: polygon(14px 0%, 100% 0%, calc(100% - 14px) 100%, 0% 100%);
        opacity: 0; animation: fadeUp 1s 1.6s forwards;
    }

    .stat-item {
        flex: 1; min-width: 140px;
        padding: 1.4rem 1.6rem;
        text-align: center;
        position: relative;
    }
    .stat-item::after {
        content: '';
        position: absolute;
        right: 0; top: 20%; bottom: 20%;
        width: 1px;
        background: linear-gradient(180deg, transparent, rgba(136,144,255,0.2), transparent);
    }
    .stat-item:last-child::after { display: none; }

    .stat-value {
        font-family: 'Cinzel Decorative', serif;
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--white);
        display: block;
        margin-bottom: 0.3rem;
        text-shadow: 0 0 20px rgba(136,144,255,0.4);
        transition: color 0.4s;
    }
    .stat-value.online-value { color: var(--success); text-shadow: 0 0 20px rgba(95,255,176,0.5); }

    .stat-label {
        font-family: 'Cinzel', serif;
        font-size: 0.58rem;
        letter-spacing: 0.2em;
        color: var(--silver);
        text-transform: uppercase;
        opacity: 0.7;
    }
    .stat-dot {
        display: inline-block;
        width: 6px; height: 6px;
        border-radius: 50%;
        background: var(--success);
        box-shadow: 0 0 8px rgba(95,255,176,0.8);
        margin-right: 0.4rem;
        animation: dotBlink 2s ease-in-out infinite;
        vertical-align: middle;
    }
    @keyframes dotBlink {
        0%, 100% { opacity: 1; box-shadow: 0 0 8px rgba(95,255,176,0.8); }
        50%       { opacity: 0.5; box-shadow: 0 0 3px rgba(95,255,176,0.3); }
    }

    /* ─── SECTION FEATURES ──────────────────────────────────────── */
    .section-wrap {
        position: relative; z-index: 10;
        padding: 7rem 2rem;
    }
    .section-inner {
        max-width: 1200px;
        margin: 0 auto;
    }

    /* ─── LORE BANNER ───────────────────────────────────────────── */
    .lore-banner {
        position: relative; z-index: 10;
        padding: 6rem 2rem;
        overflow: hidden;
    }
    .lore-banner::before {
        content: '';
        position: absolute; inset: 0;
        background:
            linear-gradient(135deg, rgba(20,24,80,0.55) 0%, rgba(90,48,212,0.25) 50%, rgba(10,14,40,0.75) 100%);
        border-top: 1px solid rgba(136,144,255,0.15);
        border-bottom: 1px solid rgba(136,144,255,0.15);
    }
    .lore-banner::after {
        content: '';
        position: absolute; inset: 0;
        background: radial-gradient(ellipse 50% 80% at 50% 50%, rgba(136,144,255,0.05) 0%, transparent 65%);
    }
    .lore-inner {
        position: relative; z-index: 2;
        text-align: center;
        max-width: 680px; margin: 0 auto;
    }
    .lore-title {
        font-family: 'Cinzel Decorative', serif;
        font-size: clamp(1.5rem, 3vw, 2.3rem);
        color: var(--white);
        margin-bottom: 1.2rem;
        text-shadow: 0 0 40px rgba(136,144,255,0.2);
    }
    .lore-text {
        font-size: 1.1rem; color: var(--silver); margin-bottom: 2.2rem;
        font-weight: 300; font-style: italic; line-height: 1.8;
    }
    .lore-btns { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }

    /* ─── FOOTER ────────────────────────────────────────────────── */
    footer {
        position: relative; z-index: 10;
        background: rgba(2,3,12,0.98);
        border-top: 1px solid rgba(136,144,255,0.1);
        padding: 3.5rem 2rem 2rem;
        text-align: center;
        overflow: hidden;
    }
    footer::before {
        content: '';
        position: absolute; top: 0; left: 10%; right: 10%; height: 1px;
        background: linear-gradient(90deg, transparent, rgba(136,144,255,0.4), rgba(240,192,96,0.3), rgba(136,144,255,0.4), transparent);
    }
    .footer-logo {
        font-family: 'Cinzel Decorative', serif; font-size: 1.5rem;
        color: var(--gold-bright); text-shadow: 0 0 24px rgba(240,192,96,0.35);
        margin-bottom: 0.4rem;
    }
    .footer-tagline {
        font-size: 0.82rem; color: var(--silver); font-style: italic; margin-bottom: 2.2rem;
    }
    .footer-links {
        display: flex; gap: 2.2rem; justify-content: center; flex-wrap: wrap; margin-bottom: 2rem;
    }
    .footer-links a {
        font-family: 'Cinzel', serif; font-size: 0.62rem;
        letter-spacing: 0.18em; color: var(--silver); text-decoration: none;
        text-transform: uppercase; transition: color 0.3s, text-shadow 0.3s;
    }
    .footer-links a:hover { color: var(--arcane-bright); text-shadow: 0 0 10px rgba(136,144,255,0.5); }
    .footer-sep {
        display: flex; align-items: center; justify-content: center; gap: 0.8rem;
        margin-bottom: 1.8rem; opacity: 0.4;
    }
    .footer-sep::before, .footer-sep::after {
        content: ''; flex: 1; max-width: 80px; height: 1px;
        background: linear-gradient(90deg, transparent, rgba(136,144,255,0.4));
    }
    .footer-sep::after { transform: scaleX(-1); }
    .footer-gem { width: 5px; height: 5px; background: var(--gold); transform: rotate(45deg); }
    .footer-bottom {
        font-size: 0.72rem; color: rgba(168,180,208,0.35); letter-spacing: 0.06em;
    }
    .footer-bottom span { color: rgba(200,151,42,0.45); }

    /* ─── DISCORD WIDGET ARCANIC ────────────────────────────────── */
    .discord-section {
        position: relative; z-index: 10;
        padding: 5rem 2rem; overflow: hidden;
    }
    .discord-section::before {
        content: ''; position: absolute; inset: 0;
        background: radial-gradient(ellipse 60% 70% at 50% 50%, rgba(88,101,242,0.07) 0%, transparent 70%);
        pointer-events: none;
    }
    .discord-inner { max-width: 820px; margin: 0 auto; position: relative; z-index: 2; }
    .discord-header {
        display: flex; align-items: flex-start; justify-content: space-between;
        margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.8rem;
    }
    .discord-title-block h2 {
        font-family: 'Cinzel Decorative', serif;
        font-size: clamp(1.2rem, 3vw, 1.8rem); color: var(--white); margin-bottom: 0.35rem;
    }
    .discord-title-block h2 span { color: #5865f2; }
    .discord-title-block p { font-size: 0.88rem; color: var(--silver); opacity: 0.75; }
    .discord-join-link {
        font-family: 'Cinzel', serif; font-size: 0.6rem; letter-spacing: 0.2em;
        text-transform: uppercase; color: #8087ff; text-decoration: none;
        transition: color 0.3s, text-shadow 0.3s; white-space: nowrap; padding-top: 0.3rem;
    }
    .discord-join-link:hover { color: var(--arcane-bright); text-shadow: 0 0 10px rgba(136,144,255,0.5); }
    .discord-card {
        background: rgba(30,31,60,0.85);
        border: 1px solid rgba(88,101,242,0.22); border-radius: 10px;
        padding: 1.5rem 1.8rem; backdrop-filter: blur(14px);
        box-shadow: 0 0 40px rgba(88,101,242,0.08), inset 0 0 30px rgba(88,101,242,0.04);
        transition: border-color 0.3s, box-shadow 0.3s;
    }
    .discord-card:hover {
        border-color: rgba(88,101,242,0.4);
        box-shadow: 0 0 60px rgba(88,101,242,0.14), inset 0 0 30px rgba(88,101,242,0.06);
    }
    .discord-label {
        font-family: 'Cinzel', serif; font-size: 0.55rem; letter-spacing: 0.25em;
        text-transform: uppercase; color: #5865f2; margin-bottom: 0.8rem;
        display: flex; align-items: center; gap: 0.5rem;
    }
    .discord-label svg { width: 14px; height: 14px; fill: #5865f2; }
    .discord-card-top { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.2rem; flex-wrap: wrap; }
    .discord-server-icon {
        width: 54px; height: 54px; border-radius: 50%;
        background: linear-gradient(135deg, #5865f2 0%, #3a42c5 100%);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.4rem; flex-shrink: 0; box-shadow: 0 0 20px rgba(88,101,242,0.4);
        overflow: hidden;
    }
    .discord-server-icon img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
    .discord-server-info { flex: 1; }
    .discord-server-name {
        font-family: 'Cinzel', serif; font-size: 1rem;
        color: var(--white); margin-bottom: 0.2rem; letter-spacing: 0.05em;
    }
    .discord-server-desc { font-size: 0.8rem; color: var(--silver); opacity: 0.65; margin-bottom: 0.4rem; }
    .discord-online-badge {
        display: inline-flex; align-items: center; gap: 0.4rem;
        font-family: 'Cinzel', serif; font-size: 0.58rem; letter-spacing: 0.12em;
        color: var(--success); background: rgba(95,255,176,0.08);
        border: 1px solid rgba(95,255,176,0.2); border-radius: 20px; padding: 0.22rem 0.7rem;
    }
    .discord-online-badge-top {
        display: flex; align-items: center; gap: 0.4rem;
        font-family: 'Cinzel', serif; font-size: 0.58rem; letter-spacing: 0.12em;
        color: var(--success); background: rgba(95,255,176,0.08);
        border: 1px solid rgba(95,255,176,0.2); border-radius: 20px;
        padding: 0.25rem 0.75rem; white-space: nowrap; margin-left: auto;
    }
    .discord-online-dot {
        width: 7px; height: 7px; border-radius: 50%;
        background: var(--success); box-shadow: 0 0 8px rgba(95,255,176,0.9);
        animation: dotBlink 2s ease-in-out infinite;
    }
    .discord-sep { height: 1px; background: linear-gradient(90deg, transparent, rgba(88,101,242,0.25), transparent); margin: 1.2rem 0; }
    .discord-members-list { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1.2rem; min-height: 2rem; }
    .discord-member-chip {
        display: inline-flex; align-items: center; gap: 0.4rem;
        font-size: 0.75rem; color: var(--silver);
        background: rgba(136,144,255,0.06); border: 1px solid rgba(136,144,255,0.1);
        border-radius: 20px; padding: 0.18rem 0.6rem; opacity: 0.85;
    }
    .discord-member-avatar {
        width: 18px; height: 18px; border-radius: 50%;
        background: linear-gradient(135deg, rgba(88,101,242,0.6), rgba(136,144,255,0.4));
        font-size: 0.6rem; display: flex; align-items: center; justify-content: center;
        color: var(--white); flex-shrink: 0;
    }
    .discord-member-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--success); box-shadow: 0 0 5px rgba(95,255,176,0.8); flex-shrink: 0; }
    .discord-members-empty { font-size: 0.8rem; color: var(--silver); opacity: 0.45; font-style: italic; }
    .discord-cta-btn {
        display: block; width: 100%; text-align: center;
        font-family: 'Cinzel', serif; font-size: 0.72rem; letter-spacing: 0.2em;
        text-transform: uppercase; color: var(--white);
        background: linear-gradient(135deg, #5865f2 0%, #3a42c5 100%);
        border: none; border-radius: 6px; padding: 0.9rem 1rem;
        text-decoration: none; cursor: pointer;
        transition: filter 0.3s, box-shadow 0.3s, transform 0.2s;
        box-shadow: 0 4px 20px rgba(88,101,242,0.35);
    }
    .discord-cta-btn:hover { filter: brightness(1.12); box-shadow: 0 6px 30px rgba(88,101,242,0.55); transform: translateY(-1px); }
    .discord-error { font-size: 0.82rem; color: rgba(168,180,208,0.45); font-style: italic; text-align: center; padding: 1rem 0; }
    @media (max-width: 640px) {
        .discord-section { padding: 3.5rem 1rem; }
        .discord-card { padding: 1.2rem 1.2rem; }
        .discord-header { flex-direction: column; }
    }

    @media (max-width: 768px) {
        .hero-stats { max-width: 100%; clip-path: none; }
        .stat-item::after { display: none; }
        .stat-item { border-bottom: 1px solid rgba(136,144,255,0.07); }
        .stat-item:last-child { border-bottom: none; }
        .hero-orb-1 { width: 300px; height: 300px; }
        .hero-rune-ring { width: 280px; height: 280px; }
    }
</style>

<!-- HERO -->
<section class="hero">
    <!-- Animated orbs -->
    <div class="hero-orb hero-orb-1"></div>
    <div class="hero-orb hero-orb-2"></div>
    <div class="hero-rune-ring">
        <div class="ring-gem"></div>
        <div class="ring-gem ring-gem-gold"></div>
    </div>

    <div class="hero-content">
        <p class="hero-eyebrow">
            <span class="hero-eyebrow-line"></span>
            Serveur Privé World of Warcraft
            <span class="hero-eyebrow-line"></span>
        </p>

        <h1 class="hero-title">EONS</h1>
        <p class="hero-subtitle">Wrath of the Lich King</p>

        <div class="hero-divider">
            <div class="hero-divider-line"></div>
            <div class="hero-divider-gem"></div>
            <div class="hero-divider-line"></div>
        </div>

        <p class="hero-desc">
            Plongez dans l'ère de <strong>Wrath of the Lich King</strong>.<br>
            Un serveur forgé dans l'ombre de la <strong>nuit arcanique</strong>,<br>
            où chaque rune chuchote des <em>secrets ancestraux</em>.
        </p>

        <div class="hero-cta">
            <?php if ($isLoggedIn): ?>
                <a href="dashboard.php" class="btn btn-gold btn-lg">⚗ &nbsp;Mon Tableau de Bord</a>
            <?php else: ?>
                <a href="auth.php" class="btn btn-gold btn-lg">⚔ &nbsp;Entrer dans le Royaume</a>
                <a href="auth.php" class="btn btn-outline btn-lg" style="pointer-events:auto" onclick="setTimeout(()=>document.getElementById('panelRegister')&&(window.showTab&&showTab('register')),100)">✦ &nbsp;Créer un Compte</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats bar -->
    <div class="hero-stats">
        <div class="stat-item">
            <span class="stat-value">3.3.5a</span>
            <span class="stat-label">Version</span>
        </div>
        <div class="stat-item">
            <span class="stat-value online-value" id="online-count">
                <span class="stat-dot"></span><?= $onlinePlayers ?>
            </span>
            <span class="stat-label">Joueurs en ligne</span>
        </div>
        <div class="stat-item">
            <span class="stat-value">×1</span>
            <span class="stat-label">Taux d'XP</span>
        </div>
        <div class="stat-item">
            <span class="stat-value">PvE</span>
            <span class="stat-label">Mode de jeu</span>
        </div>
        <div class="stat-item">
            <span class="stat-value" style="color:var(--gold-bright);text-shadow:0 0 20px rgba(240,192,96,0.4)">FR</span>
            <span class="stat-label">Communauté</span>
        </div>
    </div>
</section>

<!-- FEATURES SECTION -->
<div class="section-wrap">
    <div class="section-inner">
        <div class="section-header reveal">
            <p class="section-eyebrow" style="font-family:'Cinzel',serif;font-size:.65rem;letter-spacing:.45em;color:var(--gold);text-transform:uppercase;margin-bottom:1rem;">Pourquoi nous rejoindre</p>
            <h2 class="section-title" style="font-family:'Cinzel Decorative',serif;font-size:clamp(1.5rem,3.5vw,2.3rem);font-weight:700;color:var(--white);text-shadow:0 0 40px rgba(136,144,255,.2);margin-bottom:1.2rem;">L'Héritage des Anciens</h2>
            <div style="display:flex;align-items:center;justify-content:center;gap:1rem;">
                <div style="flex:1;max-width:100px;height:1px;background:linear-gradient(90deg,transparent,rgba(136,144,255,.4))"></div>
                <div style="width:7px;height:7px;background:var(--gold);transform:rotate(45deg);box-shadow:0 0 10px rgba(240,192,96,.6)"></div>
                <div style="flex:1;max-width:100px;height:1px;background:linear-gradient(270deg,transparent,rgba(136,144,255,.4))"></div>
            </div>
        </div>

        <div class="features-grid">
            <?php
            $features = [
                ['🌑', 'Midnight Authentique',   'Un serveur WotLK 3.3.5a sous TrinityCore, fidèle au lore de World of Warcraft. Corrections continues et stabilité éprouvée pour une expérience sans faille.'],
                ['⚗',  'Contenu Personnalisé',   'Events exclusifs, donjons retravaillés et quêtes uniques plongent chaque aventurier dans un Azeroth revisité, mystérieux et profond.'],
                ['🏰', 'Raids & Guildes',         'Tous les raids emblématiques de Wrath sont actifs. Formez vos guildes, organisez vos assauts et gravez votre nom dans les annales d\'Azeroth.'],
                ['⚔',  'PvP & Arènes',           'Battlegrounds, arènes et World PvP. Montez dans les classements et prouvez votre valeur sous la lune de Nordrassil.'],
                ['🌠', 'Communauté Active',       'Une communauté francophone passionnée, un Discord vivant et une équipe de GM dédiée à votre expérience de jeu à tout moment.'],
                ['🔮', 'Boutique Équilibrée',     'Cosmétiques et services exclusifs sans impact sur le gameplay. L\'aventure reste équitable pour tous les héros d\'Azeroth.'],
            ];
            foreach ($features as $i => [$icon, $title, $text]):
            ?>
            <div class="feature-card reveal" style="transition-delay: <?= $i * 0.08 ?>s">
                <span class="feature-icon"><?= $icon ?></span>
                <h3 class="feature-title"><?= $title ?></h3>
                <p class="feature-text"><?= $text ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- LORE / CTA BANNER -->
<div class="lore-banner">
    <div class="lore-inner reveal">
        <?php if ($isLoggedIn): ?>
            <h2 class="lore-title">Bienvenue de retour, <?= htmlspecialchars($_SESSION['account_name'] ?? 'Héros') ?> !</h2>
            <p class="lore-text">Votre aventure continue. Gérez vos héros, suivez vos progrès et retournez au combat dans les terres gelées du Nord.</p>
            <div class="lore-btns">
                <a href="dashboard.php" class="btn btn-gold btn-lg">⚗ &nbsp;Mon Tableau de Bord</a>
            </div>
        <?php else: ?>
            <h2 class="lore-title">Votre Destin Vous Attend</h2>
            <p class="lore-text">Rejoignez des centaines d'aventuriers qui ont déjà répondu à l'appel de la nuit arcanique. L'épopée d'Azeroth n'attend que vous.</p>
            <div class="lore-btns">
                <a href="auth.php" class="btn btn-gold btn-lg">⚔ &nbsp;Rejoindre Eons</a>
                <a href="#" class="btn btn-outline btn-lg">📖 &nbsp;En savoir plus</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- DISCORD WIDGET -->
<div class="discord-section reveal">
    <div class="discord-inner">
        <div class="discord-header">
            <div class="discord-title-block">
                <h2>Rejoins-nous sur <span>Discord</span></h2>
                <p>Discute en direct avec la communauté Eons</p>
            </div>
            <a href="https://discord.com/invite/KrQsUdUz8W" target="_blank" rel="noopener" class="discord-join-link">Rejoindre →</a>
        </div>

        <div class="discord-card" id="discordWidget">
            <div class="discord-label">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057c.002.022.015.043.03.056a19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03z"/>
                </svg>
                Discord
            </div>

            <div class="discord-card-top">
                <div class="discord-server-icon" id="discordIcon">⚗</div>
                <div class="discord-server-info">
                    <div class="discord-server-name" id="discordName">Eons</div>
                    <div class="discord-server-desc">La communauté francophone WoW</div>
                </div>
                <div class="discord-online-badge-top" id="discordOnlineTop">
                    <span class="discord-online-dot"></span>
                    <span id="discordOnlineCountTop">…</span> en ligne
                </div>
            </div>

            <div class="discord-sep"></div>

            <div class="discord-members-list" id="discordMembers">
                <span class="discord-members-empty">Chargement des membres…</span>
            </div>

            <a href="https://discord.com/invite/KrQsUdUz8W" target="_blank" rel="noopener" class="discord-cta-btn">
                Rejoindre Eons Discord →
            </a>
        </div>
    </div>
</div>

<!-- FOOTER -->
<footer>
    <p class="footer-logo">Eons</p>
    <p class="footer-tagline">World of Warcraft 3.3.5a — Powered by TrinityCore</p>
    <div class="footer-links">
        <a href="index.php">Accueil</a>
		<a href="actualites.php">Actualités</a>
		<a href="royaumes.php">Royaumes</a>
        <a href="classements.php">Classements</a>
        <a href="https://discord.com/invite/KrQsUdUz8W">Discord</a>
    </div>
    <div class="footer-sep"><div class="footer-gem"></div></div>
    <p class="footer-bottom">
        &copy; <?= date('Y') ?> Eons — Projet non officiel, sans affiliation avec <span>Blizzard Entertainment</span>.
    </p>
</footer>

<script>
(function() {
    const el = document.getElementById('online-count');
    if (!el) return;
    function refresh() {
        fetch('api/online.php')
            .then(r => r.json())
            .then(d => {
                if (typeof d.count === 'number') {
                    el.innerHTML = '<span class="stat-dot"></span>' + d.count;
                }
            })
            .catch(() => {});
    }
    setInterval(refresh, 30000);
})();

// ─── Discord Widget ────────────────────────────────────────────
(function() {
    const GUILD_ID = '1492541933359206450';
    const API_URL  = 'https://discord.com/api/guilds/' + GUILD_ID + '/widget.json';

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function initWidget(data) {
        // Nom du serveur
        const nameEl = document.getElementById('discordName');
        if (nameEl && data.name) nameEl.textContent = data.name;

        // Compteur en ligne
        const count = data.members ? data.members.length : 0;
        const el1 = document.getElementById('discordOnlineCount');
        const el2 = document.getElementById('discordOnlineCountTop');
        if (el1) el1.textContent = count + ' en ligne';
        if (el2) el2.textContent = count;

        // Liste membres
        const list = document.getElementById('discordMembers');
        if (!list) return;
        if (!data.members || data.members.length === 0) {
            list.innerHTML = '<span class="discord-members-empty">Aucun membre visible pour l\'instant.</span>';
            return;
        }
        const max = Math.min(data.members.length, 12);
        let html = '';
        for (let i = 0; i < max; i++) {
            const m = data.members[i];
            const initiale = (m.username || '?')[0].toUpperCase();
            const avatarHtml = m.avatar_url
                ? '<img src="' + m.avatar_url + '" style="width:18px;height:18px;border-radius:50%;object-fit:cover;" alt="">'
                : '<span class="discord-member-avatar">' + initiale + '</span>';
            html += '<span class="discord-member-chip">'
                + avatarHtml
                + '<span class="discord-member-dot"></span>'
                + escHtml(m.username || '?')
                + '</span>';
        }
        if (data.members.length > max) {
            html += '<span class="discord-members-empty">+' + (data.members.length - max) + ' autres…</span>';
        }
        list.innerHTML = html;
    }

    fetch(API_URL)
        .then(function(r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(initWidget)
        .catch(function() {
            const list = document.getElementById('discordMembers');
            if (list) list.innerHTML = '<span class="discord-members-empty">Widget temporairement indisponible.</span>';
            const el1 = document.getElementById('discordOnlineCount');
            const el2 = document.getElementById('discordOnlineCountTop');
            if (el1) el1.textContent = '— en ligne';
            if (el2) el2.textContent = '—';
        });
})();
</script>
</body>
</html>
