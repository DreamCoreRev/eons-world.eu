<?php
// index.php — Eons CMS
require_once __DIR__ . '/config.php';

// ── Joueurs en ligne (auc_chars.characters) ───────────────────
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
            z-index: 2;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 120px 2rem 4rem;
            overflow: hidden;
        }

        .hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 70% 50% at 50% 60%, rgba(30,33,96,0.55) 0%, transparent 70%),
                radial-gradient(ellipse 40% 30% at 50% 80%, rgba(98,54,212,0.2) 0%, transparent 60%),
                radial-gradient(ellipse 100% 40% at 50% 100%, rgba(4,5,15,0.9) 0%, transparent 50%);
            pointer-events: none;
        }

        .hero-bg-image {
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 50% 40%, rgba(11,13,36,0.4) 0%, transparent 60%),
                linear-gradient(180deg, var(--midnight) 0%, transparent 20%, transparent 70%, var(--midnight) 100%);
            z-index: 1;
        }

        .hero-moon {
            position: absolute;
            width: 380px;
            height: 380px;
            border-radius: 50%;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -55%);
            background: radial-gradient(circle at 40% 35%,
                rgba(180,190,255,0.07) 0%,
                rgba(74,79,212,0.04) 40%,
                transparent 70%);
            box-shadow:
                0 0 80px rgba(123,130,255,0.12),
                0 0 200px rgba(98,54,212,0.08),
                inset 0 0 60px rgba(180,190,255,0.04);
            border: 1px solid rgba(123,130,255,0.08);
            z-index: 1;
            animation: moonPulse 6s ease-in-out infinite;
        }

        @keyframes moonPulse {
            0%, 100% { box-shadow: 0 0 80px rgba(123,130,255,0.12), 0 0 200px rgba(98,54,212,0.08), inset 0 0 60px rgba(180,190,255,0.04); }
            50%       { box-shadow: 0 0 120px rgba(123,130,255,0.22), 0 0 280px rgba(98,54,212,0.14), inset 0 0 80px rgba(180,190,255,0.07); }
        }

        .hero-content { position: relative; z-index: 3; }

        .hero-eyebrow {
            font-family: 'Cinzel', serif;
            font-size: 0.7rem;
            letter-spacing: 0.4em;
            color: var(--gold);
            text-transform: uppercase;
            margin-bottom: 1.5rem;
            opacity: 0;
            animation: fadeUp 1s 0.3s forwards;
        }
        .hero-eyebrow span {
            display: inline-block;
            width: 32px;
            height: 1px;
            background: var(--gold);
            vertical-align: middle;
            margin: 0 1rem;
            opacity: 0.6;
        }

        .hero-title {
            font-family: 'Cinzel Decorative', serif;
            font-weight: 900;
            font-size: clamp(2.8rem, 7vw, 5.5rem);
            line-height: 1.05;
            letter-spacing: 0.04em;
            color: transparent;
            background: linear-gradient(180deg, #ffffff 0%, #d4deff 25%, #a8b4ff 55%, #7060c8 85%, #3a2880 100%);
            -webkit-background-clip: text;
            background-clip: text;
            filter: drop-shadow(0 0 30px rgba(123,130,255,0.35));
            margin-bottom: 0.3rem;
            opacity: 0;
            animation: fadeUp 1s 0.5s forwards;
        }

        .hero-subtitle-name {
            font-family: 'Cinzel Decorative', serif;
            font-weight: 400;
            font-size: clamp(1rem, 2.5vw, 1.6rem);
            letter-spacing: 0.35em;
            color: var(--gold-bright);
            text-shadow: 0 0 25px rgba(240,192,96,0.5);
            text-transform: uppercase;
            margin-bottom: 1.8rem;
            opacity: 0;
            animation: fadeUp 1s 0.7s forwards;
        }

        .hero-divider {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 1.8rem;
            opacity: 0;
            animation: fadeUp 1s 0.85s forwards;
        }
        .hero-divider-line {
            width: 80px;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(123,130,255,0.5));
        }
        .hero-divider-line:last-child { background: linear-gradient(90deg, rgba(123,130,255,0.5), transparent); }
        .hero-divider-gem {
            width: 8px; height: 8px;
            background: var(--arcane-bright);
            transform: rotate(45deg);
            box-shadow: 0 0 12px rgba(123,130,255,0.8);
        }

        .hero-desc {
            font-size: 1.15rem;
            font-weight: 300;
            line-height: 1.8;
            color: var(--silver);
            max-width: 560px;
            margin: 0 auto 2.5rem;
            font-style: italic;
            opacity: 0;
            animation: fadeUp 1s 1s forwards;
        }
        .hero-desc strong { color: var(--arcane-bright); font-style: normal; font-weight: 600; }

        .hero-cta {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            opacity: 0;
            animation: fadeUp 1s 1.2s forwards;
        }

        .hero-info-bar {
            position: relative;
            z-index: 3;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 0;
            margin-top: 3rem;
            width: 100%;
            max-width: 680px;
            background: rgba(7,9,26,0.6);
            border: 1px solid rgba(123,130,255,0.12);
            backdrop-filter: blur(10px);
            clip-path: polygon(10px 0%, 100% 0%, calc(100% - 10px) 100%, 0% 100%);
            opacity: 0;
            animation: fadeUp 1s 1.5s forwards;
        }
        .hero-info-item {
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            gap: 0.25rem; padding: 1rem 2rem; flex: 1 1 120px;
        }
        .hero-info-value {
            font-family: 'Cinzel', serif;
            font-size: 1.3rem; font-weight: 700;
            color: var(--gold-bright);
            text-shadow: 0 0 15px rgba(240,192,96,0.4);
            line-height: 1;
        }
        .hero-info-label {
            font-size: 0.62rem; letter-spacing: 0.2em;
            color: var(--silver); text-transform: uppercase; white-space: nowrap;
        }
        .hero-info-sep { width: 1px; height: 36px; background: rgba(123,130,255,0.18); flex-shrink: 0; }

        /* ─── FEATURES SECTION ──────────────────────────────────────── */
        .section { position: relative; z-index: 2; padding: 6rem 2rem; }
        .section-header { text-align: center; margin-bottom: 4rem; }
        .section-eyebrow {
            font-family: 'Cinzel', serif; font-size: 0.68rem;
            letter-spacing: 0.35em; color: var(--gold);
            text-transform: uppercase; margin-bottom: 1rem;
        }
        .section-title {
            font-family: 'Cinzel Decorative', serif;
            font-size: clamp(1.6rem, 3.5vw, 2.5rem); font-weight: 700;
            color: var(--white); text-shadow: 0 0 30px rgba(123,130,255,0.3); margin-bottom: 1rem;
        }
        .section-line {
            display: flex; align-items: center; justify-content: center; gap: 0.75rem; margin-top: 1.2rem;
        }
        .section-line::before, .section-line::after {
            content: ''; width: 50px; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(200,151,42,0.5));
        }
        .section-line::after { background: linear-gradient(90deg, rgba(200,151,42,0.5), transparent); }
        .section-line-diamond { width: 6px; height: 6px; background: var(--gold); transform: rotate(45deg); }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem; max-width: 1100px; margin: 0 auto;
        }
        .feature-card {
            background: linear-gradient(135deg, rgba(17,20,51,0.8) 0%, rgba(11,13,36,0.9) 100%);
            border: 1px solid rgba(123,130,255,0.12);
            padding: 2.2rem 1.8rem; position: relative; overflow: hidden;
            transition: transform 0.3s, border-color 0.3s, box-shadow 0.3s;
            clip-path: polygon(0 0, calc(100% - 16px) 0, 100% 16px, 100% 100%, 16px 100%, 0 calc(100% - 16px));
        }
        .feature-card::before {
            content: ''; position: absolute; top: 0; right: 0;
            width: 16px; height: 16px;
            background: rgba(123,130,255,0.2);
            clip-path: polygon(0 0, 100% 0, 100% 100%);
        }
        .feature-card:hover { transform: translateY(-4px); border-color: rgba(123,130,255,0.3); box-shadow: 0 8px 40px rgba(98,54,212,0.2); }
        .feature-icon { font-size: 2rem; margin-bottom: 1rem; display: block; filter: drop-shadow(0 0 8px rgba(123,130,255,0.5)); }
        .feature-title { font-family: 'Cinzel', serif; font-size: 1rem; font-weight: 700; color: var(--gold-bright); margin-bottom: 0.75rem; letter-spacing: 0.05em; }
        .feature-text { font-size: 0.95rem; color: var(--silver); line-height: 1.7; font-weight: 300; }

        /* ─── CTA BANNER ────────────────────────────────────────────── */
        .cta-banner {
            position: relative; z-index: 2;
            background: linear-gradient(135deg, rgba(30,33,96,0.6) 0%, rgba(98,54,212,0.3) 50%, rgba(17,20,51,0.8) 100%);
            border-top: 1px solid rgba(123,130,255,0.15);
            border-bottom: 1px solid rgba(123,130,255,0.15);
            padding: 5rem 2rem; text-align: center; overflow: hidden;
        }
        .cta-banner::before {
            content: ''; position: absolute; inset: 0;
            background: radial-gradient(ellipse 60% 80% at 50% 50%, rgba(123,130,255,0.06) 0%, transparent 70%);
        }
        .cta-title { font-family: 'Cinzel Decorative', serif; font-size: clamp(1.5rem, 3vw, 2.2rem); color: var(--white); margin-bottom: 1rem; position: relative; }
        .cta-text { font-size: 1.1rem; color: var(--silver); margin-bottom: 2rem; font-weight: 300; font-style: italic; position: relative; }
        .cta-buttons { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; position: relative; }

        /* ─── FOOTER ────────────────────────────────────────────────── */
        footer {
            position: relative; z-index: 2;
            background: rgba(4,5,15,0.98);
            border-top: 1px solid rgba(123,130,255,0.1);
            padding: 3rem 2rem 2rem; text-align: center;
        }
        .footer-logo { font-family: 'Cinzel Decorative', serif; font-size: 1.4rem; color: var(--gold-bright); text-shadow: 0 0 20px rgba(240,192,96,0.3); margin-bottom: 0.5rem; }
        .footer-tagline { font-size: 0.85rem; color: var(--silver); font-style: italic; margin-bottom: 2rem; }
        .footer-links { display: flex; gap: 2rem; justify-content: center; flex-wrap: wrap; margin-bottom: 2rem; }
        .footer-links a { font-family: 'Cinzel', serif; font-size: 0.65rem; letter-spacing: 0.18em; color: var(--silver); text-decoration: none; text-transform: uppercase; transition: color 0.3s; }
        .footer-links a:hover { color: var(--arcane-bright); }
        .footer-bottom { font-size: 0.75rem; color: rgba(168,180,208,0.4); letter-spacing: 0.05em; }
        .footer-bottom span { color: rgba(200,151,42,0.5); }

        @media (max-width: 768px) {
            .hero-info-bar { max-width: 100%; clip-path: none; }
            .hero-info-sep { display: none; }
            .hero-info-item { flex: 1 1 40%; border-bottom: 1px solid rgba(123,130,255,0.08); padding: 0.8rem 1rem; }
            .hero-info-item:last-child { border-bottom: none; }
            .hero-moon { width: 220px; height: 220px; }
        }
    </style>

<!-- HERO -->
<section class="hero">
    <div class="hero-bg-image"></div>
    <div class="hero-moon"></div>

    <div class="hero-content">
        <p class="hero-eyebrow"><span></span>Serveur Privé World of Warcraft<span></span></p>
        <h1 class="hero-title">EONS</h1>

        <div class="hero-divider">
            <div class="hero-divider-line"></div>
            <div class="hero-divider-gem"></div>
            <div class="hero-divider-line" style="transform:scaleX(-1)"></div>
        </div>

        <p class="hero-desc">
            Plongez dans l'univers de <strong>Wrath of the Lich King</strong>.<br>
            Un serveur forgé dans l'ombre de la <strong>nuit arcanique</strong>,<br>
            prêt à vous accueillir pour un voyage sans fin.
        </p>

        <div class="hero-cta">
            <?php if ($isLoggedIn): ?>
                <a href="dashboard.php" class="btn btn-gold btn-lg">⚗ Mon tableau de bord</a>
            <?php else: ?>
                <a href="auth.php" class="btn btn-gold btn-lg">⚔ Se connecter</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="hero-info-bar">
        <div class="hero-info-item">
            <span class="hero-info-value">3.3.5a</span>
            <span class="hero-info-label">Version</span>
        </div>
        <div class="hero-info-sep"></div>
        <div class="hero-info-item">
            <span class="hero-info-value" id="online-count"><?= $onlinePlayers ?></span>
            <span class="hero-info-label">Joueurs en ligne</span>
        </div>
        <div class="hero-info-sep"></div>
        <div class="hero-info-item">
            <span class="hero-info-value">x1</span>
            <span class="hero-info-label">Taux d'XP</span>
        </div>
        <div class="hero-info-sep"></div>
        <div class="hero-info-item">
            <span class="hero-info-value">PvE</span>
            <span class="hero-info-label">Mode</span>
        </div>
    </div>
</section>

<!-- FEATURES -->
<section class="section">
    <div class="section-header">
        <p class="section-eyebrow">Pourquoi nous rejoindre</p>
        <h2 class="section-title">L'Héritage des Anciens</h2>
        <div class="section-line"><div class="section-line-diamond"></div></div>
    </div>
    <div class="features-grid">
        <div class="feature-card">
            <span class="feature-icon">🌑</span>
            <h3 class="feature-title">Midnight Authentique</h3>
            <p class="feature-text">Un serveur WotLK 3.3.5a sous TrinityCore, fidèle au lore de World of Warcraft avec des corrections continues et une stabilité éprouvée.</p>
        </div>
        <div class="feature-card">
            <span class="feature-icon">⚗</span>
            <h3 class="feature-title">Contenu Personnalisé</h3>
            <p class="feature-text">Events exclusifs, donjons retravaillés et quêtes uniques plongent chaque aventurier dans un monde Azeroth revisité et profond.</p>
        </div>
        <div class="feature-card">
            <span class="feature-icon">🏰</span>
            <h3 class="feature-title">Raids & Guildes</h3>
            <p class="feature-text">Tous les raids emblématiques de Wrath sont actifs. Formez vos guildes, organisez vos assauts et gravez votre nom dans les annales.</p>
        </div>
        <div class="feature-card">
            <span class="feature-icon">⚔</span>
            <h3 class="feature-title">PvP & Arènes</h3>
            <p class="feature-text">Battlegrounds, arènes et World PvP. Montez dans les classements et prouvez votre valeur sous la lune de Nordrassil.</p>
        </div>
        <div class="feature-card">
            <span class="feature-icon">🌠</span>
            <h3 class="feature-title">Communauté Active</h3>
            <p class="feature-text">Une communauté francophone passionnée, un Discord vivant et une équipe de GM dédiée à votre expérience de jeu.</p>
        </div>
        <div class="feature-card">
            <span class="feature-icon">🔮</span>
            <h3 class="feature-title">Boutique Équilibrée</h3>
            <p class="feature-text">Cosmétiques et services exclusifs sans impact sur le gameplay. L'aventure reste équitable pour tous les héros d'Azeroth.</p>
        </div>
    </div>
</section>

<!-- CTA BANNER -->
<div class="cta-banner">
    <?php if ($isLoggedIn): ?>
        <h2 class="cta-title">Bienvenue de retour, <?= htmlspecialchars($_SESSION['account_name'] ?? 'Héros') ?> !</h2>
        <p class="cta-text">Votre aventure continue. Gérez vos héros, suivez vos progrès et retournez au combat.</p>
        <div class="cta-buttons">
            <a href="dashboard.php" class="btn btn-gold btn-lg">⚗ Mon tableau de bord</a>
        </div>
    <?php else: ?>
        <h2 class="cta-title">Votre destin vous attend</h2>
        <p class="cta-text">Rejoignez des centaines d'aventuriers qui ont déjà répondu à l'appel de la nuit arcanique.</p>
        <div class="cta-buttons">
            <a href="auth.php" class="btn btn-gold btn-lg">⚔ Se connecter</a>
        </div>
    <?php endif; ?>
</div>

<!-- FOOTER -->
<footer>
    <p class="footer-logo">Eons</p>
    <p class="footer-tagline">World of Warcraft 3.3.5a — Powered by TrinityCore</p>
    <div class="footer-links">
        <a href="#">Accueil</a>
        <a href="#">Règlement</a>
        <a href="#">Classements</a>
        <a href="#">Support</a>
        <a href="#">Discord</a>
        <a href="#">À propos</a>
    </div>
    <p class="footer-bottom">
        &copy; <?php echo date('Y'); ?> Eons — Projet non officiel, sans affiliation avec <span>Blizzard Entertainment</span>.
    </p>
</footer>

<script>
(function() {
    const el = document.getElementById('online-count');
    function refresh() {
        fetch('api/online.php')
            .then(r => r.json())
            .then(d => { if (typeof d.count === 'number') el.textContent = d.count; })
            .catch(() => {});
    }
    setInterval(refresh, 30000);
})();
</script>
</body>
</html>
