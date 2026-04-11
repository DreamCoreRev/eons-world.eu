<?php
// ============================================================
//  header.php — Eons CMS
//  Navigation globale partagée entre toutes les pages
// ============================================================

$isLoggedIn = !empty($_SESSION['logged_in']) && !empty($_SESSION['account_id']);
$navUsername = htmlspecialchars($_SESSION['account_name'] ?? '');

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
            --midnight:     #04050f;
            --deep-void:    #07091a;
            --abyss:        #0b0d24;
            --arcane-dark:  #111433;
            --arcane:       #1e2160;
            --arcane-mid:   #2a2d80;
            --arcane-glow:  #4a4fd4;
            --arcane-bright:#7b82ff;
            --void-purple:  #6236d4;
            --void-bright:  #9b6fff;
            --gold:         #c8972a;
            --gold-bright:  #f0c060;
            --gold-pale:    #e8d88a;
            --silver:       #a8b4d0;
            --silver-bright:#d4dff0;
            --white:        #eef2ff;
            --error:        #ff5f5f;
            --success:      #5fffb0;
            --info:         #7b82ff;
            --moon-glow:    rgba(123,130,255,0.08);
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Crimson Pro', Georgia, serif;
            background-color: var(--midnight);
            color: var(--silver-bright);
            overflow-x: hidden;
            cursor: default;
        }

        body { cursor: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 20 20'%3E%3Ccircle cx='10' cy='10' r='4' fill='%237b82ff' fill-opacity='0.9'/%3E%3Ccircle cx='10' cy='10' r='8' fill='none' stroke='%237b82ff' stroke-width='1' stroke-opacity='0.4'/%3E%3C/svg%3E") 10 10, crosshair; }

        /* ─── CANVAS PARTICLES ──────────────────────────────────────── */
        #starfield {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
        }

        /* ─── NOISE OVERLAY ─────────────────────────────────────────── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 1;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
            pointer-events: none;
            opacity: 0.35;
        }

        /* ─── NAVIGATION ────────────────────────────────────────────── */
        nav {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.8rem;
            height: 60px;
            background: linear-gradient(180deg, rgba(4,5,15,0.98) 0%, rgba(4,5,15,0.80) 100%);
            border-bottom: 1px solid rgba(123,130,255,0.15);
            backdrop-filter: blur(12px);
            gap: 1rem;
        }

        .nav-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            flex-shrink: 0;
            z-index: 101;
        }

        .nav-logo-icon { width: 36px; height: 36px; }

        .nav-logo-text {
            font-family: 'Cinzel', serif;
            font-weight: 700;
            font-size: 0.95rem;
            letter-spacing: 0.1em;
            color: var(--gold-bright);
            text-shadow: 0 0 20px rgba(240,192,96,0.5);
            white-space: nowrap;
        }

        /* ─── LIENS DESKTOP ─────────────────────────────────────────── */
        .nav-links {
            display: flex;
            align-items: center;
            gap: 1.6rem;
            list-style: none;
            flex: 1;
            justify-content: center;
        }

        .nav-links a {
            font-family: 'Cinzel', serif;
            font-size: 0.68rem;
            letter-spacing: 0.12em;
            color: var(--silver);
            text-decoration: none;
            text-transform: uppercase;
            transition: color 0.3s, text-shadow 0.3s;
            white-space: nowrap;
        }

        .nav-links a:hover {
            color: var(--arcane-bright);
            text-shadow: 0 0 12px rgba(123,130,255,0.6);
        }

        /* ─── ACTIONS DESKTOP ───────────────────────────────────────── */
        .nav-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-shrink: 0;
        }

        /* ─── BOUTONS GLOBAUX ───────────────────────────────────────── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-family: 'Cinzel', serif;
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            text-decoration: none;
            padding: 0.5rem 1.1rem;
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
            background: rgba(255,255,255,0.08);
            transform: translateX(-100%) skewX(-15deg);
            transition: transform 0.4s ease;
        }
        .btn:hover::before { transform: translateX(120%) skewX(-15deg); }

        .btn-outline {
            background: transparent;
            color: var(--arcane-bright);
            border: 1px solid rgba(123,130,255,0.5);
            clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%);
        }
        .btn-outline:hover {
            border-color: var(--arcane-bright);
            box-shadow: 0 0 18px rgba(123,130,255,0.3), inset 0 0 18px rgba(123,130,255,0.05);
            transform: translateY(-1px);
        }

        .btn-gold {
            background: linear-gradient(135deg, #b07820 0%, #e8b840 50%, #b07820 100%);
            color: #1a1000;
            clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%);
            box-shadow: 0 2px 20px rgba(200,151,42,0.3);
        }
        .btn-gold:hover {
            box-shadow: 0 4px 30px rgba(200,151,42,0.55);
            transform: translateY(-2px);
        }

        .btn-arcane {
            background: linear-gradient(135deg, var(--arcane) 0%, var(--void-purple) 100%);
            color: var(--white);
            clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%);
            box-shadow: 0 2px 20px rgba(98,54,212,0.4);
        }
        .btn-arcane:hover {
            box-shadow: 0 4px 30px rgba(155,111,255,0.5);
            transform: translateY(-2px);
        }

        .btn-lg {
            font-size: 0.85rem;
            padding: 0.9rem 2.4rem;
            clip-path: polygon(12px 0%, 100% 0%, calc(100% - 12px) 100%, 0% 100%);
        }

        .btn-logout-nav {
            font-family: 'Cinzel', serif;
            font-size: 0.58rem;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--error);
            text-decoration: none;
            border: 1px solid rgba(255,95,95,0.25);
            padding: 0.3rem 0.7rem;
            transition: all 0.3s;
            clip-path: polygon(5px 0%, 100% 0%, calc(100% - 5px) 100%, 0% 100%);
            white-space: nowrap;
        }
        .btn-logout-nav:hover {
            background: rgba(255,95,95,0.08);
            border-color: rgba(255,95,95,0.5);
        }

        .nav-greeting {
            font-family: 'Cinzel', serif;
            font-size: 0.62rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--silver);
            white-space: nowrap;
            /* Tronquer si vraiment long */
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .nav-greeting span { color: var(--gold-bright); }

        /* ─── HAMBURGER BUTTON ──────────────────────────────────────── */
        .nav-burger {
            display: none;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 5px;
            width: 40px;
            height: 40px;
            cursor: pointer;
            background: transparent;
            border: 1px solid rgba(123,130,255,0.3);
            clip-path: polygon(5px 0%, 100% 0%, calc(100% - 5px) 100%, 0% 100%);
            z-index: 101;
            flex-shrink: 0;
            transition: border-color 0.3s;
        }
        .nav-burger:hover { border-color: rgba(123,130,255,0.7); }

        .nav-burger span {
            display: block;
            width: 20px;
            height: 1.5px;
            background: var(--arcane-bright);
            transition: transform 0.35s ease, opacity 0.25s ease;
            transform-origin: center;
        }

        /* Burger → croix quand ouvert */
        .nav-burger.is-open span:nth-child(1) { transform: translateY(6.5px) rotate(45deg); }
        .nav-burger.is-open span:nth-child(2) { opacity: 0; transform: scaleX(0); }
        .nav-burger.is-open span:nth-child(3) { transform: translateY(-6.5px) rotate(-45deg); }

        /* ─── MENU MOBILE DRAWER ────────────────────────────────────── */
        .nav-mobile {
            display: none; /* caché par défaut, JS gère l'open */
            position: fixed;
            top: 70px;
            left: 0; right: 0;
            z-index: 99;
            background: linear-gradient(180deg, rgba(4,5,15,0.99) 0%, rgba(7,9,26,0.97) 100%);
            border-bottom: 1px solid rgba(123,130,255,0.15);
            backdrop-filter: blur(16px);
            padding: 1.5rem 1.5rem 2rem;
            transform: translateY(-8px);
            opacity: 0;
            transition: transform 0.3s ease, opacity 0.3s ease;
        }

        .nav-mobile.is-open {
            display: block;
            transform: translateY(0);
            opacity: 1;
        }

        /* Liens nav dans le drawer */
        .nav-mobile-links {
            list-style: none;
            border-bottom: 1px solid rgba(123,130,255,0.1);
            padding-bottom: 1.2rem;
            margin-bottom: 1.2rem;
        }

        .nav-mobile-links li { }

        .nav-mobile-links a {
            display: block;
            font-family: 'Cinzel', serif;
            font-size: 0.8rem;
            letter-spacing: 0.18em;
            color: var(--silver);
            text-decoration: none;
            text-transform: uppercase;
            padding: 0.7rem 0.5rem;
            border-bottom: 1px solid rgba(123,130,255,0.06);
            transition: color 0.3s, padding-left 0.2s;
        }

        .nav-mobile-links a:hover {
            color: var(--arcane-bright);
            padding-left: 1rem;
        }

        /* Section compte dans le drawer */
        .nav-mobile-account {
            display: flex;
            flex-direction: column;
            gap: 0.7rem;
        }

        .nav-mobile-greeting {
            font-family: 'Cinzel', serif;
            font-size: 0.7rem;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--silver);
            padding: 0.3rem 0.5rem;
        }
        .nav-mobile-greeting span { color: var(--gold-bright); }

        .nav-mobile-account .btn,
        .nav-mobile-account .btn-gold {
            width: 100%;
            justify-content: center;
            text-align: center;
        }

        .btn-logout-mobile {
            display: block;
            text-align: center;
            font-family: 'Cinzel', serif;
            font-size: 0.68rem;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--error);
            text-decoration: none;
            border: 1px solid rgba(255,95,95,0.25);
            padding: 0.6rem 1rem;
            transition: all 0.3s;
            clip-path: polygon(5px 0%, 100% 0%, calc(100% - 5px) 100%, 0% 100%);
        }
        .btn-logout-mobile:hover {
            background: rgba(255,95,95,0.08);
            border-color: rgba(255,95,95,0.5);
        }

        /* ─── ANIMATIONS ────────────────────────────────────────────── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn { to { opacity: 1; } }

        /* ─── RESPONSIVE ────────────────────────────────────────────── */
        @media (max-width: 1100px) {
            .nav-links { gap: 1.1rem; }
            .nav-links a { font-size: 0.62rem; letter-spacing: 0.08em; }
        }

        /* Tablette : masquer les liens, garder les actions */
        @media (max-width: 900px) {
            nav { padding: 0 1.2rem; }
            .nav-links { display: none; }
        }

        /* Mobile : tout dans le drawer */
        @media (max-width: 600px) {
            nav { padding: 0 1rem; height: 56px; }
            .nav-mobile { top: 56px; }
            .nav-logo-text { font-size: 0.8rem; letter-spacing: 0.05em; }
            .nav-logo-icon { width: 30px; height: 30px; }
            .nav-actions { display: none; }
            .nav-burger { display: flex; }
        }
    </style>
</head>
<body>

<canvas id="starfield"></canvas>

<nav>
    <a class="nav-logo" href="index.php">
        <svg class="nav-logo-icon" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="18" cy="18" r="16" stroke="#c8972a" stroke-width="1" stroke-opacity="0.5"/>
            <circle cx="18" cy="18" r="10" stroke="#7b82ff" stroke-width="1" stroke-opacity="0.4"/>
            <path d="M18 4 L20 14 L18 18 L16 14 Z" fill="#c8972a" opacity="0.7"/>
            <path d="M18 32 L20 22 L18 18 L16 22 Z" fill="#c8972a" opacity="0.5"/>
            <path d="M4 18 L14 16 L18 18 L14 20 Z" fill="#7b82ff" opacity="0.7"/>
            <path d="M32 18 L22 16 L18 18 L22 20 Z" fill="#7b82ff" opacity="0.5"/>
            <circle cx="18" cy="18" r="3" fill="#f0c060" opacity="0.9"/>
        </svg>
        <span class="nav-logo-text">Eons</span>
    </a>

    <!-- Liens desktop -->
    <ul class="nav-links">
        <li><a href="#">Royaumes</a></li>
        <li><a href="#">Classements</a></li>
        <li><a href="#">Boutique</a></li>
        <li><a href="#">Guides</a></li>
        <li><a href="#">Communauté</a></li>
    </ul>

    <!-- Actions desktop -->
    <div class="nav-actions">
        <?php if ($isLoggedIn): ?>
            <span class="nav-greeting">⚔ <span><?= $navUsername ?></span></span>
            <a href="dashboard.php" class="btn btn-outline">⚗ Mon compte</a>
            <a href="logout.php" class="btn-logout-nav">Déconnexion</a>
        <?php else: ?>
            <a href="auth.php" class="btn btn-gold">⚔ Connexion</a>
        <?php endif; ?>
    </div>

    <!-- Bouton burger (mobile uniquement) -->
    <button class="nav-burger" id="navBurger" aria-label="Menu" aria-expanded="false">
        <span></span>
        <span></span>
        <span></span>
    </button>
</nav>

<!-- Drawer mobile -->
<div class="nav-mobile" id="navMobile" role="navigation" aria-label="Menu mobile">
    <ul class="nav-mobile-links">
        <li><a href="#">Royaumes</a></li>
        <li><a href="#">Classements</a></li>
        <li><a href="#">Boutique</a></li>
        <li><a href="#">Guides</a></li>
        <li><a href="#">Communauté</a></li>
    </ul>

    <div class="nav-mobile-account">
        <?php if ($isLoggedIn): ?>
            <p class="nav-mobile-greeting">Bienvenue, <span><?= $navUsername ?></span></p>
            <a href="dashboard.php" class="btn btn-outline" style="justify-content:center;">⚗ Mon compte</a>
            <a href="logout.php" class="btn-logout-mobile">⚔ Déconnexion</a>
        <?php else: ?>
            <a href="auth.php" class="btn btn-gold" style="display:block;text-align:center;">⚔ Connexion</a>
        <?php endif; ?>
    </div>
</div>

<script>
// ─── BURGER MENU ───────────────────────────────────────────────────────────
(function() {
    const burger = document.getElementById('navBurger');
    const drawer = document.getElementById('navMobile');
    if (!burger || !drawer) return;

    burger.addEventListener('click', function() {
        const isOpen = burger.classList.toggle('is-open');
        burger.setAttribute('aria-expanded', isOpen);

        if (isOpen) {
            drawer.style.display = 'block';
            // Force reflow pour que la transition CSS s'applique
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

    // Fermer si on clique en dehors
    document.addEventListener('click', function(e) {
        if (burger.classList.contains('is-open') && !burger.contains(e.target) && !drawer.contains(e.target)) {
            burger.click();
        }
    });

    // Fermer si on passe sur desktop (resize)
    window.addEventListener('resize', function() {
        if (window.innerWidth > 600 && burger.classList.contains('is-open')) {
            burger.click();
        }
    });
})();

// ─── STARFIELD CANVAS ──────────────────────────────────────────────────────
(function() {
    const canvas = document.getElementById('starfield');
    const ctx = canvas.getContext('2d');
    let W, H, stars = [], nebulas = [];

    function resize() {
        W = canvas.width  = window.innerWidth;
        H = canvas.height = window.innerHeight;
    }

    function initStars() {
        stars = [];
        for (let i = 0; i < 220; i++) {
            stars.push({
                x: Math.random() * W, y: Math.random() * H,
                r: Math.random() * 1.2 + 0.2, a: Math.random(),
                speed: Math.random() * 0.3 + 0.05,
                twinkle: Math.random() * Math.PI * 2,
                color: Math.random() > 0.7 ? `rgba(123,130,255,`
                     : Math.random() > 0.5  ? `rgba(200,180,255,`
                     :                        `rgba(220,230,255,`
            });
        }
        for (let i = 0; i < 12; i++) {
            stars.push({
                x: Math.random() * W, y: Math.random() * H,
                r: Math.random() * 1.5 + 0.5, a: Math.random() * 0.7 + 0.3,
                speed: Math.random() * 0.2 + 0.03,
                twinkle: Math.random() * Math.PI * 2,
                color: `rgba(240,200,100,`
            });
        }
        nebulas = [];
        for (let i = 0; i < 5; i++) {
            nebulas.push({
                x: Math.random() * W, y: Math.random() * H * 0.8,
                r: Math.random() * 180 + 80,
                c1: Math.random() > 0.5 ? [74,50,180] : [30,33,100],
                a: Math.random() * 0.06 + 0.02
            });
        }
    }

    let t = 0;
    function draw() {
        ctx.clearRect(0, 0, W, H);
        nebulas.forEach(n => {
            const g = ctx.createRadialGradient(n.x, n.y, 0, n.x, n.y, n.r);
            g.addColorStop(0, `rgba(${n.c1[0]},${n.c1[1]},${n.c1[2]},${n.a})`);
            g.addColorStop(1, 'transparent');
            ctx.fillStyle = g;
            ctx.beginPath(); ctx.arc(n.x, n.y, n.r, 0, Math.PI * 2); ctx.fill();
        });
        stars.forEach(s => {
            const flicker = Math.sin(t * s.speed + s.twinkle) * 0.35 + 0.65;
            ctx.beginPath(); ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
            ctx.fillStyle = s.color + (s.a * flicker) + ')'; ctx.fill();
            if (s.r > 1.0) {
                const g = ctx.createRadialGradient(s.x, s.y, 0, s.x, s.y, s.r * 3.5);
                g.addColorStop(0, s.color + (s.a * 0.25 * flicker) + ')');
                g.addColorStop(1, 'transparent');
                ctx.fillStyle = g; ctx.beginPath();
                ctx.arc(s.x, s.y, s.r * 3.5, 0, Math.PI * 2); ctx.fill();
            }
        });
        t += 0.012;
        requestAnimationFrame(draw);
    }

    window.addEventListener('resize', () => { resize(); initStars(); });
    resize(); initStars(); draw();
})();
</script>
