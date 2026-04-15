<?php
// ============================================================
//  client.php — Eons CMS | Arcanic Theme
//  Téléchargement du client de jeu — réservé aux membres
// ============================================================
require_once __DIR__ . '/config.php';

$isLoggedIn = !empty($_SESSION['logged_in']) && !empty($_SESSION['account_id']);

// ── Téléchargement ───────────────────────────────────────────
// Redirection vers l'URL publique du fichier (plus fiable qu'un readfile)
define('CLIENT_FILE_URL',  '/uploads/client/Eons.rar');
define('CLIENT_FILE_NAME', 'Eons.rar');
define('CLIENT_FILE_SIZE_DISPLAY', '~19.1 Go');

define('LAUNCHER_FILE_URL',  'https://eons-world.eu/uploads/launcher/_LauncherEonsWorld_.rar');
define('LAUNCHER_FILE_NAME', '_LauncherEonsWorld_.rar');

if (isset($_GET['dl']) && $_GET['dl'] === '1') {
    if (!$isLoggedIn) {
        header('Location: auth.php');
        exit;
    }
    // Redirect direct — le navigateur gère le téléchargement
    header('Location: ' . CLIENT_FILE_URL);
    exit;
}

if (isset($_GET['dl']) && $_GET['dl'] === 'launcher') {
    if (!$isLoggedIn) {
        header('Location: auth.php');
        exit;
    }
    header('Location: ' . LAUNCHER_FILE_URL);
    exit;
}

$downloadError = $_GET['error'] ?? '';
$pageTitle = 'Téléchargement — Eons';
require_once __DIR__ . '/header.php';
?>

<style>
/* ─── PAGE CLIENT ───────────────────────────────────────────── */
.client-page {
    position: relative; z-index: 10;
    padding: 5.5rem 2rem 5rem;
    max-width: 900px;
    margin: 0 auto;
}

/* ─── HERO ──────────────────────────────────────────────────── */
.client-hero {
    text-align: center;
    padding: 3.5rem 1rem 3rem;
    position: relative;
    margin-bottom: 2.5rem;
}
.client-hero::before {
    content: '';
    position: absolute; top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 500px; height: 250px;
    background: radial-gradient(ellipse, rgba(136,144,255,0.13) 0%, transparent 70%);
    pointer-events: none;
}
.client-eyebrow {
    font-family: 'Cinzel', serif;
    font-size: 0.65rem; letter-spacing: 0.45em;
    color: var(--arcane-bright); text-transform: uppercase;
    margin-bottom: 1rem;
}
.client-title {
    font-family: 'Cinzel Decorative', serif;
    font-size: clamp(1.8rem, 4vw, 2.8rem);
    font-weight: 900; color: var(--white);
    text-shadow: 0 0 60px rgba(136,144,255,0.2), 0 0 120px rgba(90,48,212,0.15);
    margin-bottom: 1rem;
}
.client-title span {
    background: linear-gradient(135deg, var(--arcane-bright) 0%, #c0c6ff 50%, var(--arcane-bright) 100%);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}
.client-subtitle {
    font-family: 'Crimson Pro', serif;
    font-size: 1.05rem; font-style: italic;
    color: var(--silver); max-width: 560px;
    margin: 0 auto 2rem; line-height: 1.7;
}
.client-divider {
    display: flex; align-items: center; justify-content: center; gap: 1rem;
    margin-bottom: 0.5rem;
}
.client-divider::before, .client-divider::after {
    content: ''; width: 80px; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(136,144,255,0.4));
}
.client-divider::after { transform: scaleX(-1); }
.client-divider-gem {
    width: 8px; height: 8px;
    background: var(--arcane-bright); transform: rotate(45deg);
    box-shadow: 0 0 14px rgba(136,144,255,0.7);
}

/* ─── ALERTE ────────────────────────────────────────────────── */
.client-alert {
    padding: 1rem 1.4rem; margin-bottom: 1.8rem;
    border-left: 3px solid;
    font-family: 'Crimson Pro', serif; font-size: 0.96rem;
    display: flex; align-items: center; gap: 0.8rem;
    background: rgba(9,12,34,0.75); backdrop-filter: blur(10px);
}
.client-alert-error   { border-color: var(--error);   color: var(--error); }
.client-alert-info    { border-color: var(--info);     color: var(--info); }

/* ─── BLOC PRINCIPAL ────────────────────────────────────────── */
.client-card {
    position: relative;
    background: rgba(9,12,34,0.85);
    border: 1px solid rgba(136,144,255,0.12);
    overflow: hidden;
    margin-bottom: 2rem;
}
.client-card::before {
    content: '';
    position: absolute; top: 0; right: 0;
    border-style: solid; border-width: 0 56px 56px 0;
    border-color: transparent rgba(136,144,255,0.1) transparent transparent;
    z-index: 2;
}
.client-card::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at 20% 0%, rgba(136,144,255,0.06) 0%, transparent 60%);
    pointer-events: none;
}

/* Header carte */
.client-card-header {
    display: flex; align-items: center; gap: 1.6rem;
    padding: 2rem 2rem 1.5rem;
    border-bottom: 1px solid rgba(136,144,255,0.07);
    position: relative; z-index: 3;
}
.client-icon {
    width: 72px; height: 72px; flex-shrink: 0;
    background: rgba(15,18,48,0.8);
    border: 1px solid rgba(136,144,255,0.18);
    display: flex; align-items: center; justify-content: center;
    font-size: 2.2rem;
    box-shadow: 0 0 24px rgba(136,144,255,0.12) inset;
}
.client-info { flex: 1; }
.client-game-name {
    font-family: 'Cinzel Decorative', serif;
    font-size: 1.3rem; font-weight: 700; color: var(--white);
    margin-bottom: 0.35rem;
    text-shadow: 0 0 20px rgba(136,144,255,0.3);
}
.client-meta {
    display: flex; flex-wrap: wrap; gap: 0.6rem; margin-top: 0.4rem;
}
.client-badge {
    display: inline-flex; align-items: center; gap: 0.3rem;
    padding: 0.18rem 0.65rem;
    font-family: 'Cinzel', serif; font-size: 0.5rem;
    letter-spacing: 0.1em; text-transform: uppercase;
}
.client-badge--version {
    background: rgba(136,144,255,0.1);
    border: 1px solid rgba(136,144,255,0.25);
    color: var(--arcane-bright);
}
.client-badge--size {
    background: rgba(240,192,96,0.08);
    border: 1px solid rgba(240,192,96,0.2);
    color: var(--gold-bright);
}
.client-badge--os {
    background: rgba(95,255,176,0.07);
    border: 1px solid rgba(95,255,176,0.18);
    color: var(--success);
}

/* Body carte */
.client-card-body {
    padding: 1.8rem 2rem;
    position: relative; z-index: 3;
}
.client-desc {
    font-family: 'Crimson Pro', serif; font-size: 0.96rem;
    font-style: italic; color: var(--silver); line-height: 1.8;
    margin-bottom: 1.6rem;
    border-left: 2px solid rgba(136,144,255,0.2);
    padding-left: 1rem;
}

/* Bouton téléchargement */
.btn-download {
    display: inline-flex; align-items: center; gap: 0.7rem;
    font-family: 'Cinzel', serif; font-size: 0.68rem;
    font-weight: 700; letter-spacing: 0.16em; text-transform: uppercase;
    padding: 0.85rem 2.2rem; text-decoration: none;
    background: linear-gradient(135deg, #1a1d60 0%, #252880 40%, #8890ff 50%, #252880 60%, #1a1d60 100%);
    color: var(--white); border: none; cursor: pointer;
    clip-path: polygon(12px 0%, 100% 0%, calc(100% - 12px) 100%, 0% 100%);
    box-shadow: 0 2px 24px rgba(136,144,255,0.4);
    transition: all 0.3s; white-space: nowrap;
    position: relative; overflow: hidden;
    animation: dlPulse 3s ease-in-out infinite;
}
.btn-download::before {
    content: '';
    position: absolute; inset: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
    transform: translateX(-100%) skewX(-20deg); transition: transform 0.6s;
}
.btn-download:hover {
    box-shadow: 0 4px 40px rgba(136,144,255,0.65);
    transform: translateY(-2px);
    animation: none;
}
.btn-download:hover::before { transform: translateX(150%) skewX(-20deg); }
.btn-download svg { width: 18px; height: 18px; flex-shrink: 0; }

@keyframes dlPulse {
    0%, 100% { box-shadow: 0 2px 24px rgba(136,144,255,0.4); }
    50%       { box-shadow: 0 2px 40px rgba(136,144,255,0.7); }
}

/* Bouton connexion */
.btn-login-prompt {
    display: inline-flex; align-items: center; gap: 0.7rem;
    font-family: 'Cinzel', serif; font-size: 0.68rem;
    font-weight: 700; letter-spacing: 0.16em; text-transform: uppercase;
    padding: 0.85rem 2.2rem; text-decoration: none;
    background: transparent; color: var(--arcane-bright);
    border: 1px solid rgba(136,144,255,0.4);
    clip-path: polygon(12px 0%, 100% 0%, calc(100% - 12px) 100%, 0% 100%);
    transition: all 0.3s; white-space: nowrap;
}
.btn-login-prompt:hover {
    background: rgba(136,144,255,0.08);
    border-color: rgba(136,144,255,0.7);
    box-shadow: 0 0 24px rgba(136,144,255,0.25);
}

/* Zone bouton */
.client-cta {
    display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
}
.client-cta-note {
    font-family: 'Cinzel', serif; font-size: 0.52rem;
    letter-spacing: 0.08em; color: var(--silver); opacity: 0.45;
}
.client-cta-note a {
    color: var(--arcane-bright); text-decoration: none; opacity: 1;
}
.client-cta-note a:hover { text-decoration: underline; }

/* Verrou — message non connecté */
.client-locked-banner {
    display: flex; align-items: center; gap: 1.2rem;
    padding: 1.4rem 1.6rem;
    background: rgba(136,144,255,0.05);
    border: 1px solid rgba(136,144,255,0.15);
    margin-bottom: 1.6rem;
}
.client-locked-icon { font-size: 2rem; flex-shrink: 0; }
.client-locked-text { flex: 1; }
.client-locked-text strong {
    font-family: 'Cinzel', serif; font-size: 0.72rem;
    letter-spacing: 0.1em; color: var(--white); text-transform: uppercase;
    display: block; margin-bottom: 0.3rem;
}
.client-locked-text span {
    font-family: 'Crimson Pro', serif; font-size: 0.88rem;
    font-style: italic; color: var(--silver);
}

/* ─── INSTRUCTIONS ──────────────────────────────────────────── */
.client-steps {
    background: rgba(9,12,34,0.7);
    border: 1px solid rgba(136,144,255,0.08);
    padding: 1.8rem 2rem;
    margin-bottom: 2rem;
    position: relative; overflow: hidden;
}
.client-steps::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at right, rgba(136,144,255,0.04) 0%, transparent 60%);
    pointer-events: none;
}
.client-steps-title {
    font-family: 'Cinzel', serif; font-size: 0.62rem;
    letter-spacing: 0.25em; color: var(--arcane-bright);
    text-transform: uppercase; margin-bottom: 1.4rem;
    display: flex; align-items: center; gap: 0.6rem;
}
.client-steps-title::after {
    content: ''; flex: 1; height: 1px;
    background: linear-gradient(90deg, rgba(136,144,255,0.2), transparent);
}
.client-steps-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1.2rem;
    position: relative; z-index: 1;
}
.client-step {
    display: flex; align-items: flex-start; gap: 0.8rem;
}
.client-step-num {
    font-family: 'Cinzel Decorative', serif;
    font-size: 1.4rem; font-weight: 900;
    color: rgba(136,144,255,0.2); line-height: 1; flex-shrink: 0;
}
.client-step-text h4 {
    font-family: 'Cinzel', serif; font-size: 0.62rem;
    letter-spacing: 0.08em; color: var(--arcane-bright);
    margin-bottom: 0.28rem; text-transform: uppercase;
}
.client-step-text p {
    font-family: 'Crimson Pro', serif; font-size: 0.86rem;
    color: var(--silver); line-height: 1.6; font-style: italic;
}
.client-step-text code {
    font-family: monospace; font-size: 0.78rem; font-style: normal;
    background: rgba(136,144,255,0.08); border: 1px solid rgba(136,144,255,0.15);
    padding: 0.1rem 0.35rem; color: var(--arcane-bright);
}

/* ─── SPECS TECHNIQUES ──────────────────────────────────────── */
.client-specs {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.client-spec-item {
    background: rgba(9,12,34,0.7);
    border: 1px solid rgba(136,144,255,0.07);
    padding: 1rem 1.2rem;
    display: flex; align-items: center; gap: 0.8rem;
}
.client-spec-icon { font-size: 1.3rem; flex-shrink: 0; opacity: 0.7; }
.client-spec-label {
    font-family: 'Cinzel', serif; font-size: 0.5rem;
    letter-spacing: 0.1em; color: var(--silver); opacity: 0.5;
    text-transform: uppercase; margin-bottom: 0.15rem;
}
.client-spec-value {
    font-family: 'Cinzel', serif; font-size: 0.68rem;
    letter-spacing: 0.06em; color: var(--white);
}

/* ─── CARTE LAUNCHER ────────────────────────────────────────── */
.launcher-card {
    position: relative;
    background: rgba(9,12,34,0.75);
    border: 1px solid rgba(240,192,96,0.12);
    overflow: hidden;
    margin-bottom: 2rem;
}
.launcher-card::before {
    content: '';
    position: absolute; top: 0; right: 0;
    border-style: solid; border-width: 0 56px 56px 0;
    border-color: transparent rgba(240,192,96,0.1) transparent transparent;
    z-index: 2;
}
.launcher-card::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at 20% 0%, rgba(240,192,96,0.05) 0%, transparent 60%);
    pointer-events: none;
}
.launcher-badge--tag {
    background: rgba(240,192,96,0.08);
    border: 1px solid rgba(240,192,96,0.22);
    color: var(--gold-bright);
}
.launcher-badge--os {
    background: rgba(95,255,176,0.07);
    border: 1px solid rgba(95,255,176,0.18);
    color: var(--success);
}

/* Bouton launcher — variante dorée */
.btn-download-launcher {
    display: inline-flex; align-items: center; gap: 0.7rem;
    font-family: 'Cinzel', serif; font-size: 0.68rem;
    font-weight: 700; letter-spacing: 0.16em; text-transform: uppercase;
    padding: 0.85rem 2.2rem; text-decoration: none;
    background: linear-gradient(135deg, #3a2800 0%, #5a3e00 40%, #f0c060 50%, #5a3e00 60%, #3a2800 100%);
    color: var(--white); border: none; cursor: pointer;
    clip-path: polygon(12px 0%, 100% 0%, calc(100% - 12px) 100%, 0% 100%);
    box-shadow: 0 2px 24px rgba(240,192,96,0.35);
    transition: all 0.3s; white-space: nowrap;
    position: relative; overflow: hidden;
    animation: launcherPulse 3s ease-in-out infinite;
}
.btn-download-launcher::before {
    content: '';
    position: absolute; inset: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
    transform: translateX(-100%) skewX(-20deg); transition: transform 0.6s;
}
.btn-download-launcher:hover {
    box-shadow: 0 4px 40px rgba(240,192,96,0.6);
    transform: translateY(-2px);
    animation: none;
}
.btn-download-launcher:hover::before { transform: translateX(150%) skewX(-20deg); }
.btn-download-launcher svg { width: 18px; height: 18px; flex-shrink: 0; }
@keyframes launcherPulse {
    0%, 100% { box-shadow: 0 2px 24px rgba(240,192,96,0.35); }
    50%       { box-shadow: 0 2px 40px rgba(240,192,96,0.6); }
}

/* ─── RESPONSIVE ────────────────────────────────────────────── */
@media (max-width: 640px) {
    .client-page { padding: 5rem 1rem 4rem; }
    .client-card-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
    .client-card-header, .client-card-body { padding: 1.4rem; }
    .client-steps { padding: 1.4rem; }
    .client-title { font-size: 1.6rem; }
    .client-cta { flex-direction: column; align-items: flex-start; }
}
@media (max-width: 359px) {
    .client-title { font-size: 1.3rem; }
}
</style>

<main>
<div class="client-page">

    <!-- ── HERO ─────────────────────────────────────────────── -->
    <div class="client-hero reveal">
        <p class="client-eyebrow">✦ Rejoignez l'aventure ✦</p>
        <h1 class="client-title">Télécharger le <span>Client</span></h1>
        <p class="client-subtitle">Installez le client officiel d'Eons et plongez dans un univers de World of Warcraft 3.3.5a forgé par la communauté.</p>
        <div class="client-divider"><div class="client-divider-gem"></div></div>
    </div>

    <!-- ── ALERTES ──────────────────────────────────────────── -->
    <?php if ($downloadError === 'notfound'): ?>
    <div class="client-alert client-alert-error">
        <span>✖ Fichier introuvable sur le serveur. Contactez un administrateur.</span>
    </div>
    <?php endif; ?>

    <!-- ── CARTE PRINCIPALE ─────────────────────────────────── -->
    <div class="client-card reveal">

        <!-- Header -->
        <div class="client-card-header">
            <div class="client-icon">⚔️</div>
            <div class="client-info">
                <div class="client-game-name">Eons — World of Warcraft</div>
                <div class="client-meta">
                    <span class="client-badge client-badge--version">🧩 Version 3.3.5a</span>
                    <span class="client-badge client-badge--size">📦 <?= CLIENT_FILE_SIZE_DISPLAY ?></span>
                    <span class="client-badge client-badge--os">🖥️ Windows</span>
                </div>
            </div>
        </div>

        <!-- Body -->
        <div class="client-card-body">
            <p class="client-desc">
                Le client Eons est une version préconfigurée de WoW 3.3.5a pointant directement sur nos royaumes.
                Aucune modification manuelle de <code>realmlist.wtf</code> n'est nécessaire — lancez et jouez.
            </p>

            <?php if (!$isLoggedIn): ?>
            <!-- Non connecté — verrou -->
            <div class="client-locked-banner">
                <div class="client-locked-icon">🔒</div>
                <div class="client-locked-text">
                    <strong>Accès réservé aux membres</strong>
                    <span>Connectez-vous ou créez un compte gratuit pour accéder au téléchargement.</span>
                </div>
            </div>
            <div class="client-cta">
                <a href="auth.php" class="btn-login-prompt">🔑 Se connecter</a>
                <span class="client-cta-note">
                    Pas encore inscrit ?
                    <a href="auth.php#register">Créer un compte gratuitement</a>
                </span>
            </div>

            <?php else: ?>
            <!-- Connecté — bouton téléchargement -->
            <div class="client-cta">
                <a href="client.php?dl=1" class="btn-download">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    Télécharger le Client
                </a>
                <span class="client-cta-note">
                    Connecté en tant que <strong style="color:var(--arcane-bright);font-style:normal;">
                        <?= htmlspecialchars($_SESSION['account_name'] ?? 'Aventurier') ?>
                    </strong>
                </span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── CARTE LAUNCHER ──────────────────────────────────────── -->
    <div class="client-card launcher-card reveal">

        <!-- Header -->
        <div class="client-card-header">
            <div class="client-icon">🚀</div>
            <div class="client-info">
                <div class="client-game-name">Eons — Launcher</div>
                <div class="client-meta">
                    <span class="client-badge launcher-badge--tag">⚡ Mise à jour auto</span>
                    <span class="client-badge launcher-badge--os">🖥️ Windows</span>
                </div>
            </div>
        </div>

        <!-- Body -->
        <div class="client-card-body">
            <p class="client-desc">
                Le Launcher Eons détecte et applique automatiquement les mises à jour du client.
                Il suffit de le lancer avant chaque session — plus besoin de tout retélécharger manuellement.
            </p>

            <?php if (!$isLoggedIn): ?>
            <div class="client-locked-banner">
                <div class="client-locked-icon">🔒</div>
                <div class="client-locked-text">
                    <strong>Accès réservé aux membres</strong>
                    <span>Connectez-vous pour accéder au téléchargement du Launcher.</span>
                </div>
            </div>
            <div class="client-cta">
                <a href="auth.php" class="btn-login-prompt">🔑 Se connecter</a>
            </div>

            <?php else: ?>
            <div class="client-cta">
                <a href="client.php?dl=launcher" class="btn-download-launcher">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    Télécharger le Launcher
                </a>
                <span class="client-cta-note">
                    Connecté en tant que <strong style="color:var(--gold-bright);font-style:normal;">
                        <?= htmlspecialchars($_SESSION['account_name'] ?? 'Aventurier') ?>
                    </strong>
                </span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── SPECS ─────────────────────────────────────────────── -->
    <div class="client-specs reveal">
        <div class="client-spec-item">
            <div class="client-spec-icon">🎮</div>
            <div>
                <div class="client-spec-label">Jeu</div>
                <div class="client-spec-value">World of Warcraft</div>
            </div>
        </div>
        <div class="client-spec-item">
            <div class="client-spec-icon">🔢</div>
            <div>
                <div class="client-spec-label">Version</div>
                <div class="client-spec-value">3.3.5a (12340)</div>
            </div>
        </div>
        <div class="client-spec-item">
            <div class="client-spec-icon">📦</div>
            <div>
                <div class="client-spec-label">Format</div>
                <div class="client-spec-value">Archive .RAR</div>
            </div>
        </div>
        <div class="client-spec-item">
            <div class="client-spec-icon">🖥️</div>
            <div>
                <div class="client-spec-label">Système</div>
                <div class="client-spec-value">Windows 7 / 10 / 11</div>
            </div>
        </div>
        <div class="client-spec-item">
            <div class="client-spec-icon">🔧</div>
            <div>
                <div class="client-spec-label">Logiciel requis</div>
                <div class="client-spec-value">WinRAR / 7-Zip</div>
            </div>
        </div>
        <div class="client-spec-item">
            <div class="client-spec-icon">⚙️</div>
            <div>
                <div class="client-spec-label">Configuration</div>
                <div class="client-spec-value">Préconfigurée</div>
            </div>
        </div>
    </div>

    <!-- ── INSTRUCTIONS D'INSTALLATION ─────────────────────── -->
    <div class="client-steps reveal">
        <div class="client-steps-title">✦ Installation</div>
        <div class="client-steps-grid">
            <div class="client-step">
                <span class="client-step-num">01</span>
                <div class="client-step-text">
                    <h4>Télécharger</h4>
                    <p>Téléchargez l'archive <code>Eons.rar</code> via le bouton ci-dessus.</p>
                </div>
            </div>
            <div class="client-step">
                <span class="client-step-num">02</span>
                <div class="client-step-text">
                    <h4>Extraire</h4>
                    <p>Décompressez l'archive avec <code>WinRAR</code> ou <code>7-Zip</code> dans le dossier de votre choix.</p>
                </div>
            </div>
            <div class="client-step">
                <span class="client-step-num">03</span>
                <div class="client-step-text">
                    <h4>Lancer</h4>
                    <p>Exécutez <code>Wow.exe</code> dans le dossier extrait. Le realmlist est déjà configuré.</p>
                </div>
            </div>
            <div class="client-step">
                <span class="client-step-num">04</span>
                <div class="client-step-text">
                    <h4>Jouer</h4>
                    <p>Connectez-vous avec vos identifiants Eons et rejoignez l'aventure !</p>
                </div>
            </div>
        </div>
    </div>

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
