<?php
// ============================================================
//  header.php — Eons CMS | Arcanic Theme Enhanced
// ============================================================

$isLoggedIn  = !empty($_SESSION['logged_in']) && !empty($_SESSION['account_id']);
$navUsername = htmlspecialchars($_SESSION['account_name'] ?? '');
$isAdmin     = false;

if ($isLoggedIn) {
    // Utilise le cache session pour éviter une requête par page
    if (!isset($_SESSION['security_level'])) {
        try {
            $db   = getAuthDB();
            $stmt = $db->prepare(
                "SELECT COALESCE(aa.SecurityLevel, 0) AS lvl
                 FROM account a
                 LEFT JOIN account_access aa ON aa.AccountID = a.id AND aa.RealmID = -1
                 WHERE a.id = :id LIMIT 1"
            );
            $stmt->execute([':id' => (int)$_SESSION['account_id']]);
            $row = $stmt->fetch();
            $_SESSION['security_level'] = (int)($row['lvl'] ?? 0);
        } catch (\PDOException $e) {
            $_SESSION['security_level'] = 0;
        }
    }
    $isAdmin = $_SESSION['security_level'] >= 3;
}

if (!isset($pageTitle)) $pageTitle = 'Eons';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700;900&family=Cinzel:wght@400;600;700&family=Crimson+Pro:ital,wght@0,300;0,400;0,600;1,300;1,400&display=swap" rel="stylesheet">
    <style>
        /* ─── RESET & BASE ─────────────────────────────────────────── */
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --midnight:       #02030c;
            --deep-void:      #06081a;
            --abyss:          #090c22;
            --arcane-dark:    #0f1230;
            --arcane:         #1a1d5a;
            --arcane-mid:     #252880;
            --arcane-glow:    #4855d4;
            --arcane-bright:  #8890ff;
            --void-purple:    #5a30d4;
            --void-bright:    #a070ff;
            --gold:           #c89028;
            --gold-bright:    #f0c060;
            --gold-pale:      #e8d88a;
            --silver:         #a8b4d0;
            --silver-bright:  #d4dff0;
            --white:          #eef2ff;
            --error:          #ff5f5f;
            --success:        #5fffb0;
            --info:           #7b82ff;
            --rune-color:     rgba(136,144,255,0.12);
            --rune-glow:      rgba(136,144,255,0.05);
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Crimson Pro', Georgia, serif;
            background-color: var(--midnight);
            color: var(--silver-bright);
            overflow-x: hidden;
            cursor: url('/assets/cursor/gam372.cur'), auto;
        }

        /* Cursor : liens et éléments cliquables */
        a, button, [role="button"], label[for], input[type="submit"],
        input[type="button"], input[type="checkbox"], input[type="radio"],
        select, .btn, [onclick], [style*="cursor: pointer"] {
            cursor: url('/assets/cursor/gam375.cur'), pointer;
        }

        /* Cursor : éléments désactivés */
        [disabled], .disabled, input[disabled], button[disabled],
        select[disabled], textarea[disabled] {
            cursor: url('/assets/cursor/Unavailable.cur'), not-allowed;
        }

        /* ─── CANVAS LAYERS ─────────────────────────────────────────── */
        #starfield, #runeCanvas, #energyCanvas {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
        }
        #runeCanvas   { z-index: 1; }
        #energyCanvas { z-index: 2; }

        /* ─── NOISE FILM GRAIN ──────────────────────────────────────── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 3;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.06'/%3E%3C/svg%3E");
            pointer-events: none;
            opacity: 0.45;
            mix-blend-mode: overlay;
        }

        /* ─── CURSOR TRAIL CANVAS ───────────────────────────────────── */
        #cursorCanvas {
            position: fixed;
            inset: 0;
            z-index: 9999;
            pointer-events: none;
        }

        /* ─── NAVIGATION ────────────────────────────────────────────── */
        nav {
            position: fixed;
            top: 14px;
            left: 50%;
            transform: translateX(-50%);
            width: calc(100% - 3rem);
            max-width: 1280px;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            height: 58px;
            background: rgba(6, 7, 20, 0.82);
            border: 1px solid rgba(136,144,255,0.18);
            border-radius: 14px;
            backdrop-filter: blur(24px) saturate(1.4);
            box-shadow: 0 8px 40px rgba(0,0,0,0.55), 0 0 0 1px rgba(136,144,255,0.06) inset;
            gap: 1rem;
        }

        nav::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 10%;
            right: 10%;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(136,144,255,0.5), rgba(240,192,96,0.4), rgba(136,144,255,0.5), transparent);
            animation: navGlow 4s ease-in-out infinite;
        }

        @keyframes navGlow {
            0%, 100% { opacity: 0.5; }
            50%       { opacity: 1; }
        }

        .nav-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            flex-shrink: 0;
            z-index: 1001;
        }

        .nav-logo-icon { width: 38px; height: 38px; flex-shrink: 0; }

        .nav-logo svg { animation: logoSpin 20s linear infinite; transform-origin: center; }

        @keyframes logoSpin {
            from { filter: drop-shadow(0 0 6px rgba(136,144,255,0.4)); }
            50%  { filter: drop-shadow(0 0 14px rgba(240,192,96,0.6)) drop-shadow(0 0 28px rgba(136,144,255,0.3)); }
            to   { filter: drop-shadow(0 0 6px rgba(136,144,255,0.4)); }
        }

        .nav-logo-text {
            font-family: 'Cinzel', serif;
            font-weight: 700;
            font-size: 1rem;
            letter-spacing: 0.18em;
            color: var(--gold-bright);
            text-shadow: 0 0 24px rgba(240,192,96,0.55), 0 0 60px rgba(240,192,96,0.15);
            white-space: nowrap;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 1.8rem;
            list-style: none;
            flex: 1;
            justify-content: center;
        }

        .nav-links a {
            font-family: 'Cinzel', serif;
            font-size: 0.65rem;
            letter-spacing: 0.16em;
            color: var(--silver);
            text-decoration: none;
            text-transform: uppercase;
            transition: color 0.3s, text-shadow 0.3s;
            position: relative;
            white-space: nowrap;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 50%;
            right: 50%;
            height: 1px;
            background: var(--arcane-bright);
            transition: left 0.3s, right 0.3s, box-shadow 0.3s;
        }

        .nav-links a:hover {
            color: var(--arcane-bright);
            text-shadow: 0 0 14px rgba(136,144,255,0.7);
        }

        .nav-links a:hover::after {
            left: 0; right: 0;
            box-shadow: 0 0 8px rgba(136,144,255,0.8);
        }

        .nav-actions {
            display: flex;
            gap: 0.6rem;
            align-items: center;
            flex-shrink: 0;
        }

        /* ─── BOUTON ADMIN ──────────────────────────────────────────── */
        .btn-admin-nav {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-family: 'Cinzel', serif;
            font-size: 0.58rem;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            text-decoration: none;
            padding: 0.32rem 0.7rem;
            border: 1px solid rgba(240,192,96,0.35);
            border-radius: 6px;
            color: var(--gold-bright);
            background: rgba(200,144,40,0.08);
            transition: background 0.25s, border-color 0.25s, box-shadow 0.25s, transform 0.2s;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .btn-admin-nav:hover {
            background: rgba(200,144,40,0.18);
            border-color: var(--gold-bright);
            box-shadow: 0 0 14px rgba(240,192,96,0.3);
            transform: translateY(-1px);
        }
        .btn-admin-nav .admin-lock {
            font-size: 0.75rem;
            line-height: 1;
        }

        /* ─── GREETING ──────────────────────────────────────────────── */
        .nav-greeting {
            font-family: 'Cinzel', serif;
            font-size: 0.62rem;
            letter-spacing: 0.1em;
            color: var(--gold-bright);
            white-space: nowrap;
        }
        .nav-greeting span { color: var(--arcane-bright); }

        /* ─── BOUTONS GLOBAUX ───────────────────────────────────────── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-family: 'Cinzel', serif;
            font-size: 0.63rem;
            font-weight: 600;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            text-decoration: none;
            padding: 0.55rem 1.2rem;
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.3s;
            white-space: nowrap;
        }

        .btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.12), transparent);
            transform: translateX(-100%) skewX(-20deg);
            transition: transform 0.5s ease;
        }
        .btn:hover::before { transform: translateX(150%) skewX(-20deg); }

        .btn-outline {
            background: transparent;
            color: var(--arcane-bright);
            border: 1px solid rgba(136,144,255,0.45);
            clip-path: polygon(10px 0%, 100% 0%, calc(100% - 10px) 100%, 0% 100%);
        }
        .btn-outline:hover {
            border-color: var(--arcane-bright);
            box-shadow: 0 0 20px rgba(136,144,255,0.3), inset 0 0 20px rgba(136,144,255,0.06);
            transform: translateY(-1px);
        }

        .btn-gold {
            background: linear-gradient(135deg, #9a6418 0%, #d4a030 35%, #f0c060 50%, #d4a030 65%, #9a6418 100%);
            color: #1a0e00;
            clip-path: polygon(10px 0%, 100% 0%, calc(100% - 10px) 100%, 0% 100%);
            box-shadow: 0 2px 24px rgba(200,151,42,0.4), inset 0 1px 0 rgba(255,255,200,0.3);
            font-weight: 700;
        }
        .btn-gold:hover {
            box-shadow: 0 4px 36px rgba(200,151,42,0.65), inset 0 1px 0 rgba(255,255,200,0.3);
            transform: translateY(-2px);
        }

        .btn-arcane {
            background: linear-gradient(135deg, var(--arcane) 0%, var(--void-purple) 100%);
            color: var(--white);
            clip-path: polygon(10px 0%, 100% 0%, calc(100% - 10px) 100%, 0% 100%);
            box-shadow: 0 2px 24px rgba(90,48,212,0.5);
        }
        .btn-arcane:hover {
            box-shadow: 0 4px 36px rgba(160,112,255,0.6);
            transform: translateY(-2px);
        }

        .btn-lg { padding: 0.8rem 2rem; font-size: 0.72rem; letter-spacing: 0.18em; }

        /* ─── LOGOUT NAV ────────────────────────────────────────────── */
        .btn-logout-nav {
            font-family: 'Cinzel', serif;
            font-size: 0.6rem;
            letter-spacing: 0.12em;
            color: rgba(168,180,208,0.6);
            text-decoration: none;
            text-transform: uppercase;
            transition: color 0.3s;
        }
        .btn-logout-nav:hover { color: var(--error); }

        /* ─── BURGER ────────────────────────────────────────────────── */
        .nav-burger {
            display: none;
            flex-direction: column;
            gap: 5px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
            z-index: 1001;
        }
        .nav-burger span {
            display: block;
            width: 22px;
            height: 1.5px;
            background: var(--arcane-bright);
            transition: transform 0.3s, opacity 0.3s;
        }
        .nav-burger.is-open span:nth-child(1) { transform: translateY(6.5px) rotate(45deg); }
        .nav-burger.is-open span:nth-child(2) { opacity: 0; }
        .nav-burger.is-open span:nth-child(3) { transform: translateY(-6.5px) rotate(-45deg); }

        /* ─── MOBILE DRAWER ─────────────────────────────────────────── */
        .nav-mobile {
            display: none;
            position: fixed;
            top: 82px; left: 1.5rem; right: 1.5rem;
            background: rgba(6,7,20,0.97);
            border: 1px solid rgba(136,144,255,0.18);
            border-radius: 12px;
            backdrop-filter: blur(24px);
            z-index: 999;
            padding: 1.5rem;
            transform: translateY(-8px);
            opacity: 0;
            transition: opacity 0.3s, transform 0.3s;
            box-shadow: 0 12px 40px rgba(0,0,0,0.6);
        }
        .nav-mobile.is-open { opacity: 1; transform: translateY(0); }
        .nav-mobile-links { list-style: none; display: flex; flex-direction: column; gap: 0; }
        .nav-mobile-links li a {
            display: block; padding: 0.85rem 0;
            font-family: 'Cinzel', serif; font-size: 0.7rem;
            letter-spacing: 0.14em; color: var(--silver);
            text-decoration: none; text-transform: uppercase;
            border-bottom: 1px solid rgba(136,144,255,0.07);
            transition: color 0.3s;
        }
        .nav-mobile-links li a:hover { color: var(--arcane-bright); }
        .nav-mobile-account { margin-top: 1.2rem; display: flex; flex-direction: column; gap: 0.6rem; }
        .nav-mobile-greeting { font-family: 'Cinzel', serif; font-size: 0.7rem; color: var(--gold-bright); letter-spacing: 0.1em; }
        .nav-mobile-greeting span { color: var(--arcane-bright); }
        .btn-logout-mobile {
            font-family: 'Cinzel', serif; font-size: 0.62rem;
            letter-spacing: 0.12em; color: rgba(255,95,95,0.7);
            text-decoration: none; text-transform: uppercase;
            transition: color 0.3s;
        }
        .btn-logout-mobile:hover { color: var(--error); }

        /* ─── SECTIONS ──────────────────────────────────────────────── */
        .section {
            position: relative;
            z-index: 10;
            padding: 7rem 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-eyebrow {
            font-family: 'Cinzel', serif;
            font-size: 0.65rem;
            letter-spacing: 0.45em;
            color: var(--gold);
            text-transform: uppercase;
            margin-bottom: 1rem;
        }

        .section-title {
            font-family: 'Cinzel Decorative', serif;
            font-size: clamp(1.6rem, 3.5vw, 2.4rem);
            font-weight: 700;
            color: var(--white);
            text-shadow: 0 0 40px rgba(136,144,255,0.2);
            margin-bottom: 1.2rem;
        }

        .section-line {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }
        .section-line::before, .section-line::after {
            content: '';
            flex: 1;
            max-width: 120px;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(136,144,255,0.4));
        }
        .section-line::after { transform: scaleX(-1); }
        .section-line-diamond {
            width: 7px; height: 7px;
            background: var(--gold);
            transform: rotate(45deg);
            box-shadow: 0 0 10px rgba(240,192,96,0.6);
        }

        /* ─── FEATURES GRID ─────────────────────────────────────────── */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .feature-card {
            position: relative;
            background: rgba(9,12,34,0.75);
            border: 1px solid rgba(136,144,255,0.1);
            padding: 2rem;
            transition: transform 0.3s, border-color 0.3s, box-shadow 0.3s;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0; right: 0;
            width: 0; height: 0;
            border-style: solid;
            border-width: 0 36px 36px 0;
            border-color: transparent rgba(136,144,255,0.2) transparent transparent;
            transition: border-color 0.3s;
        }

        .feature-card::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at top left, rgba(136,144,255,0.04) 0%, transparent 60%);
            opacity: 0;
            transition: opacity 0.4s;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            border-color: rgba(136,144,255,0.28);
            box-shadow: 0 10px 50px rgba(90,48,212,0.25), 0 0 0 1px rgba(136,144,255,0.08) inset;
        }
        .feature-card:hover::after { opacity: 1; }
        .feature-card:hover::before { border-color: transparent rgba(240,192,96,0.4) transparent transparent; }

        .feature-icon {
            font-size: 2rem; margin-bottom: 1rem; display: block;
            filter: drop-shadow(0 0 10px rgba(136,144,255,0.6));
            position: relative; z-index: 1;
        }
        .feature-title {
            font-family: 'Cinzel', serif; font-size: 0.9rem; font-weight: 700;
            color: var(--gold-bright); margin-bottom: 0.8rem; letter-spacing: 0.06em;
            position: relative; z-index: 1;
        }
        .feature-text {
            font-size: 0.95rem; color: var(--silver); line-height: 1.75; font-weight: 300;
            position: relative; z-index: 1;
        }

        /* ─── ANIMATIONS GLOBALES ───────────────────────────────────── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(28px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        @keyframes runeFloat {
            0%, 100% { transform: translateY(0) rotate(0deg); opacity: 0.6; }
            33%       { transform: translateY(-12px) rotate(5deg); opacity: 1; }
            66%       { transform: translateY(-5px) rotate(-3deg); opacity: 0.8; }
        }

        @keyframes orbPulse {
            0%, 100% { transform: scale(1); opacity: 0.6; }
            50%       { transform: scale(1.1); opacity: 1; }
        }

        /* ─── SCROLL REVEAL ─────────────────────────────────────────── */
        .reveal {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.8s ease, transform 0.8s ease;
        }
        .reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* ─── FLASH MESSAGE ─────────────────────────────────────────── */
        .flash-banner {
            position: fixed;
            top: 70px;
            left: 50%;
            transform: translateX(-50%) translateY(-10px);
            z-index: 2000;
            background: rgba(9,12,34,0.95);
            border: 1px solid rgba(240,192,96,0.5);
            color: var(--gold-bright);
            font-family: 'Cinzel', serif;
            font-size: 0.72rem;
            letter-spacing: 0.12em;
            padding: 0.85rem 2rem;
            clip-path: polygon(12px 0%, 100% 0%, calc(100% - 12px) 100%, 0% 100%);
            box-shadow: 0 4px 30px rgba(240,192,96,0.2);
            opacity: 0;
            animation: flashIn 0.5s 0.2s forwards, flashOut 0.5s 4s forwards;
        }
        @keyframes flashIn  { to { opacity: 1; transform: translateX(-50%) translateY(0); } }
        @keyframes flashOut { to { opacity: 0; transform: translateX(-50%) translateY(-10px); } }

        /* ─── RESPONSIVE ────────────────────────────────────────────── */
        @media (max-width: 1100px) {
            .nav-links { gap: 1.2rem; }
        }
        @media (max-width: 900px) {
            nav { padding: 0 1.2rem; }
            .nav-links { display: none; }
        }
        @media (max-width: 600px) {
            nav { top: 10px; width: calc(100% - 1.5rem); padding: 0 1rem; height: 52px; border-radius: 10px; }
            .nav-mobile { top: 72px; left: 0.75rem; right: 0.75rem; }
            .nav-actions { display: none; }
            .nav-burger { display: flex; }
        }

        /* ─── FOOTER GLOBAL ─────────────────────────────────────────── */
        footer {
            position: relative;
            z-index: 10;
            text-align: center;
            padding: 4rem 2rem 3rem;
            border-top: 1px solid rgba(136,144,255,0.1);
            background: linear-gradient(180deg, transparent 0%, rgba(2,3,12,0.6) 100%);
        }
        .footer-logo {
            font-family: 'Cinzel Decorative', serif;
            font-size: 1.5rem;
            color: var(--gold-bright);
            text-shadow: 0 0 24px rgba(240,192,96,0.35);
            margin-bottom: 0.4rem;
        }
        .footer-tagline {
            font-size: 0.82rem;
            color: var(--silver);
            font-style: italic;
            margin-bottom: 2.2rem;
        }
        .footer-links {
            display: flex;
            gap: 2.2rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }
        .footer-links a {
            font-family: 'Cinzel', serif;
            font-size: 0.62rem;
            letter-spacing: 0.18em;
            color: var(--silver);
            text-decoration: none;
            text-transform: uppercase;
            transition: color 0.3s, text-shadow 0.3s;
        }
        .footer-links a:hover {
            color: var(--arcane-bright);
            text-shadow: 0 0 10px rgba(136,144,255,0.5);
        }
        .footer-sep {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
            margin-bottom: 1.8rem;
            opacity: 0.4;
        }
        .footer-sep::before, .footer-sep::after {
            content: '';
            flex: 1;
            max-width: 80px;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(136,144,255,0.4));
        }
        .footer-sep::after { transform: scaleX(-1); }
        .footer-gem {
            width: 5px; height: 5px;
            background: var(--gold);
            transform: rotate(45deg);
        }
        .footer-bottom {
            font-size: 0.72rem;
            color: rgba(168,180,208,0.35);
            letter-spacing: 0.06em;
        }
        .footer-bottom span { color: rgba(200,151,42,0.45); }
    </style>
</head>
<body>

<!-- Canvas layers -->
<canvas id="starfield"></canvas>
<canvas id="runeCanvas"></canvas>
<canvas id="energyCanvas"></canvas>
<canvas id="cursorCanvas"></canvas>

<nav>
    <a class="nav-logo" href="index.php">
        <svg class="nav-logo-icon" width="38" height="38" viewBox="0 0 38 38" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="19" cy="19" r="17" stroke="#c89028" stroke-width="0.8" stroke-opacity="0.6"/>
            <circle cx="19" cy="19" r="12" stroke="#8890ff" stroke-width="0.7" stroke-opacity="0.45"/>
            <circle cx="19" cy="19" r="7"  stroke="#c89028" stroke-width="0.5" stroke-opacity="0.3"/>
            <!-- Rune cross -->
            <path d="M19 2 L20.5 12 L19 19 L17.5 12 Z" fill="#c89028" opacity="0.8"/>
            <path d="M19 36 L20.5 26 L19 19 L17.5 26 Z" fill="#c89028" opacity="0.5"/>
            <path d="M2 19 L12 17.5 L19 19 L12 20.5 Z" fill="#8890ff" opacity="0.8"/>
            <path d="M36 19 L26 17.5 L19 19 L26 20.5 Z" fill="#8890ff" opacity="0.5"/>
            <!-- Diagonal accents -->
            <path d="M7 7 L13.5 13.5" stroke="#8890ff" stroke-width="0.6" stroke-opacity="0.3"/>
            <path d="M31 7 L24.5 13.5" stroke="#8890ff" stroke-width="0.6" stroke-opacity="0.3"/>
            <path d="M7 31 L13.5 24.5" stroke="#c89028" stroke-width="0.6" stroke-opacity="0.3"/>
            <path d="M31 31 L24.5 24.5" stroke="#c89028" stroke-width="0.6" stroke-opacity="0.3"/>
            <!-- Center gem -->
            <circle cx="19" cy="19" r="3" fill="#f0c060" opacity="0.95"/>
            <circle cx="19" cy="19" r="1.5" fill="white" opacity="0.6"/>
        </svg>
        <span class="nav-logo-text">Eons</span>
    </a>

    <ul class="nav-links">
		<li><a href="client.php">Jeu</a></li>
		<li><a href="actualites.php">Actualités</a></li>
        <li><a href="royaumes.php">Royaumes</a></li>
        <li><a href="classements.php">Classements</a></li>
        <li><a href="boutique.php">Boutique</a></li>
		<li><a href="don.php">Don</a></li>
		<li><a href="vote.php">Vote</a></li>
    </ul>

    <div class="nav-actions">
        <?php if ($isLoggedIn): ?>
            <?php if ($isAdmin): ?>
                <a href="admin.php" class="btn-admin-nav" title="Panel Administrateur">
                    <span class="admin-lock">🔒</span><?= $navUsername ?>
                </a>
            <?php else: ?>
                <span class="nav-greeting">⚔ <span><?= $navUsername ?></span></span>
            <?php endif; ?>
            <a href="dashboard.php" class="btn btn-outline">⚗ Mon compte</a>
            <a href="logout.php" class="btn-logout-nav">Quitter</a>
        <?php else: ?>
            <a href="auth.php" class="btn btn-gold">⚔ Connexion</a>
        <?php endif; ?>
    </div>

    <button class="nav-burger" id="navBurger" aria-label="Menu" aria-expanded="false">
        <span></span><span></span><span></span>
    </button>
</nav>

<div class="nav-mobile" id="navMobile" role="navigation" aria-label="Menu mobile">
    <ul class="nav-mobile-links">
        <li><a href="client.php">Jeu</a></li>
		<li><a href="actualites.php">Actualités</a></li>
        <li><a href="royaumes.php">Royaumes</a></li>
        <li><a href="classements.php">Classements</a></li>
        <li><a href="boutique.php">Boutique</a></li>
		<li><a href="don.php">Don</a></li>
		<li><a href="vote.php">Vote</a></li>
    </ul>
    <div class="nav-mobile-account">
        <?php if ($isLoggedIn): ?>
            <p class="nav-mobile-greeting">Bienvenue, <span><?= $navUsername ?></span></p>
            <?php if ($isAdmin): ?>
                <a href="admin.php" class="btn-admin-nav" style="justify-content:center;">🔒 Panel Admin</a>
            <?php endif; ?>
            <a href="dashboard.php" class="btn btn-outline" style="justify-content:center;">⚗ Mon compte</a>
            <a href="logout.php" class="btn-logout-mobile">⚔ Déconnexion</a>
        <?php else: ?>
            <a href="auth.php" class="btn btn-gold" style="display:block;text-align:center;">⚔ Connexion</a>
        <?php endif; ?>
    </div>
</div>

<script>
// ─── BURGER ──────────────────────────────────────────────────────────────────
(function() {
    const burger = document.getElementById('navBurger');
    const drawer = document.getElementById('navMobile');
    if (!burger || !drawer) return;
    burger.addEventListener('click', function() {
        const isOpen = burger.classList.toggle('is-open');
        burger.setAttribute('aria-expanded', isOpen);
        if (isOpen) {
            drawer.style.display = 'block';
            drawer.offsetHeight;
            drawer.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        } else {
            drawer.classList.remove('is-open');
            document.body.style.overflow = '';
            drawer.addEventListener('transitionend', function hide() {
                if (!drawer.classList.contains('is-open')) drawer.style.display = 'none';
                drawer.removeEventListener('transitionend', hide);
            });
        }
    });
    document.addEventListener('click', function(e) {
        if (burger.classList.contains('is-open') && !burger.contains(e.target) && !drawer.contains(e.target)) burger.click();
    });
    window.addEventListener('resize', function() {
        if (window.innerWidth > 600 && burger.classList.contains('is-open')) burger.click();
    });
})();

// ─── STARFIELD CANVAS ────────────────────────────────────────────────────────
(function() {
    const canvas = document.getElementById('starfield');
    const ctx = canvas.getContext('2d');
    let W, H, stars = [], nebulas = [], shootingStars = [];

    function resize() { W = canvas.width = window.innerWidth; H = canvas.height = window.innerHeight; }

    function initStars() {
        stars = [];
        const palette = [
            'rgba(136,144,255,', 'rgba(180,180,255,', 'rgba(220,225,255,',
            'rgba(240,200,120,', 'rgba(200,160,255,'
        ];
        for (let i = 0; i < 280; i++) {
            stars.push({
                x: Math.random() * W, y: Math.random() * H,
                r: Math.random() * 1.3 + 0.15,
                a: Math.random() * 0.7 + 0.2,
                speed: Math.random() * 0.25 + 0.04,
                twinkle: Math.random() * Math.PI * 2,
                color: palette[Math.floor(Math.random() * palette.length)],
                glowFactor: Math.random() > 0.7 ? 4 : 2.5
            });
        }
        nebulas = [];
        for (let i = 0; i < 6; i++) {
            nebulas.push({
                x: Math.random() * W, y: Math.random() * H * 0.85,
                r: Math.random() * 220 + 100,
                c: Math.random() > 0.5 ? [60,45,180] : [25,28,90],
                a: Math.random() * 0.055 + 0.018,
                drift: { x: (Math.random() - 0.5) * 0.03, y: (Math.random() - 0.5) * 0.015 }
            });
        }
    }

    function spawnShootingStar() {
        const side = Math.random() > 0.5 ? 0 : 1;
        shootingStars.push({
            x: side === 0 ? -50 : W + 50,
            y: Math.random() * H * 0.5,
            vx: side === 0 ? (Math.random() * 6 + 4) : -(Math.random() * 6 + 4),
            vy: Math.random() * 3 + 1,
            len: Math.random() * 120 + 60,
            a: 1, life: 1,
            color: Math.random() > 0.6 ? 'rgba(240,200,100,' : 'rgba(136,144,255,'
        });
    }

    let t = 0;
    function draw() {
        ctx.clearRect(0, 0, W, H);

        // Nebulas drift
        nebulas.forEach(n => {
            n.x += n.drift.x; n.y += n.drift.y;
            if (n.x < -n.r) n.x = W + n.r;
            if (n.x > W + n.r) n.x = -n.r;
            if (n.y < -n.r) n.y = H + n.r;
            if (n.y > H + n.r) n.y = -n.r;
            const g = ctx.createRadialGradient(n.x, n.y, 0, n.x, n.y, n.r);
            g.addColorStop(0, `rgba(${n.c[0]},${n.c[1]},${n.c[2]},${n.a})`);
            g.addColorStop(1, 'transparent');
            ctx.fillStyle = g;
            ctx.beginPath(); ctx.arc(n.x, n.y, n.r, 0, Math.PI * 2); ctx.fill();
        });

        // Stars
        stars.forEach(s => {
            const f = Math.sin(t * s.speed + s.twinkle) * 0.35 + 0.65;
            ctx.beginPath(); ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
            ctx.fillStyle = s.color + (s.a * f) + ')'; ctx.fill();
            if (s.r > 0.8) {
                const g = ctx.createRadialGradient(s.x, s.y, 0, s.x, s.y, s.r * s.glowFactor);
                g.addColorStop(0, s.color + (s.a * 0.22 * f) + ')');
                g.addColorStop(1, 'transparent');
                ctx.fillStyle = g;
                ctx.beginPath(); ctx.arc(s.x, s.y, s.r * s.glowFactor, 0, Math.PI * 2); ctx.fill();
            }
        });

        // Shooting stars
        if (Math.random() < 0.004) spawnShootingStar();
        shootingStars = shootingStars.filter(ss => ss.a > 0.01);
        shootingStars.forEach(ss => {
            ctx.save();
            ctx.globalAlpha = ss.a;
            const g = ctx.createLinearGradient(ss.x, ss.y, ss.x - ss.vx * 12, ss.y - ss.vy * 12);
            g.addColorStop(0, ss.color + '0.9)');
            g.addColorStop(1, ss.color + '0)');
            ctx.strokeStyle = g;
            ctx.lineWidth = 1.5;
            ctx.beginPath(); ctx.moveTo(ss.x, ss.y); ctx.lineTo(ss.x - ss.vx * 10, ss.y - ss.vy * 10); ctx.stroke();
            ctx.restore();
            ss.x += ss.vx; ss.y += ss.vy;
            ss.a -= 0.012;
        });

        t += 0.01;
        requestAnimationFrame(draw);
    }

    window.addEventListener('resize', () => { resize(); initStars(); });
    resize(); initStars(); draw();
})();

// ─── RUNE CANVAS ─────────────────────────────────────────────────────────────
(function() {
    const canvas = document.getElementById('runeCanvas');
    const ctx = canvas.getContext('2d');
    let W, H;
    function resize() { W = canvas.width = window.innerWidth; H = canvas.height = window.innerHeight; }

    // Floating arcane rune symbols
    const RUNE_CHARS = ['ᚠ','ᚢ','ᚦ','ᚨ','ᚱ','ᚲ','ᚷ','ᚹ','ᚺ','ᚾ','ᛁ','ᛃ','ᛇ','ᛈ','ᛉ','ᛊ','ᛏ','ᛒ','ᛖ','ᛗ','ᛚ','ᛜ','ᛞ','ᛟ'];
    let runes = [];

    function initRunes() {
        runes = [];
        const count = Math.floor(W / 120);
        for (let i = 0; i < count; i++) {
            runes.push({
                x: Math.random() * W,
                y: Math.random() * H,
                char: RUNE_CHARS[Math.floor(Math.random() * RUNE_CHARS.length)],
                size: Math.random() * 14 + 8,
                a: Math.random() * 0.06 + 0.02,
                phase: Math.random() * Math.PI * 2,
                speed: Math.random() * 0.008 + 0.003,
                vy: (Math.random() - 0.5) * 0.08,
                vx: (Math.random() - 0.5) * 0.04,
                rotSpeed: (Math.random() - 0.5) * 0.004,
                rot: Math.random() * Math.PI * 2,
                color: Math.random() > 0.7
                    ? `rgba(240,192,96,`
                    : `rgba(136,144,255,`
            });
        }
    }

    let t = 0;
    function draw() {
        ctx.clearRect(0, 0, W, H);
        runes.forEach(r => {
            const pulse = Math.sin(t * r.speed * 60 + r.phase) * 0.4 + 0.6;
            ctx.save();
            ctx.translate(r.x, r.y);
            ctx.rotate(r.rot);
            ctx.font = `${r.size}px serif`;
            ctx.fillStyle = r.color + (r.a * pulse) + ')';
            ctx.fillText(r.char, 0, 0);
            ctx.restore();
            r.y += r.vy; r.x += r.vx; r.rot += r.rotSpeed;
            if (r.y > H + 40) r.y = -40;
            if (r.y < -40) r.y = H + 40;
            if (r.x > W + 40) r.x = -40;
            if (r.x < -40) r.x = W + 40;
        });
        t += 0.016;
        requestAnimationFrame(draw);
    }

    window.addEventListener('resize', () => { resize(); initRunes(); });
    resize(); initRunes(); draw();
})();

// ─── CURSOR TRAIL ────────────────────────────────────────────────────────────
(function() {
    const canvas = document.getElementById('cursorCanvas');
    const ctx = canvas.getContext('2d');
    let W, H;
    function resize() { W = canvas.width = window.innerWidth; H = canvas.height = window.innerHeight; }
    window.addEventListener('resize', resize); resize();

    let trail = [], mouse = { x: -999, y: -999 };
    document.addEventListener('mousemove', e => { mouse.x = e.clientX; mouse.y = e.clientY; });

    function draw() {
        ctx.clearRect(0, 0, W, H);
        trail.push({ x: mouse.x, y: mouse.y, a: 1, r: 3 + Math.random() * 2 });
        if (trail.length > 24) trail.shift();

        trail.forEach((p, i) => {
            const prog = i / trail.length;
            p.a = prog * 0.55;
            const g = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, p.r * 3);
            g.addColorStop(0, `rgba(136,144,255,${p.a * 0.9})`);
            g.addColorStop(0.5, `rgba(90,48,212,${p.a * 0.4})`);
            g.addColorStop(1, 'transparent');
            ctx.fillStyle = g;
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r * 3, 0, Math.PI * 2);
            ctx.fill();

            // Tiny dot
            ctx.fillStyle = `rgba(200,210,255,${p.a * 1.2})`;
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r * 0.4, 0, Math.PI * 2);
            ctx.fill();
        });

        requestAnimationFrame(draw);
    }
    draw();
})();

// ─── SCROLL REVEAL ───────────────────────────────────────────────────────────
(function() {
    const obs = new IntersectionObserver(entries => {
        entries.forEach(e => {
            if (e.isIntersecting) {
                e.target.classList.add('visible');
                obs.unobserve(e.target);
            }
        });
    }, { threshold: 0.12 });

    document.querySelectorAll('.reveal').forEach(el => obs.observe(el));

    // Re-observe dynamically added elements
    const mo = new MutationObserver(() => {
        document.querySelectorAll('.reveal:not(.visible)').forEach(el => obs.observe(el));
    });
    mo.observe(document.body, { childList: true, subtree: true });
})();
</script>
