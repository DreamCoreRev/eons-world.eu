<?php
// ============================================================
//  dashboard.php — Eons CMS
//  Tableau de bord : compte, personnages, serveur, gestion
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/srp6.php';

if (empty($_SESSION['logged_in']) || empty($_SESSION['account_id'])) {
    header('Location: auth.php');
    exit;
}

$accountId   = (int)$_SESSION['account_id'];
$flashMsg    = '';
$flashType   = 'info';
$errors      = [];

// ── Récupère les infos complètes du compte ────────────────────
try {
    $db   = getAuthDB();
    $stmt = $db->prepare("
        SELECT id, username, email, joindate, last_login, last_ip,
               online, expansion, failed_logins, locked, mutetime
        FROM account WHERE id = :id LIMIT 1
    ");
    $stmt->execute([':id' => $accountId]);
    $account = $stmt->fetch();
    if (!$account) { session_destroy(); header('Location: auth.php'); exit; }
} catch (PDOException $e) {
    $account = null;
    error_log('[AU Dashboard] DB error: ' . $e->getMessage());
}

// ── Statut serveur (realm) ────────────────────────────────────
// flag & 2 = REALM_FLAG_OFFLINE dans TrinityCore.
// Joueurs connectés : comptés via online=1 dans auc_chars.characters (source fiable).
$realmOnline  = false;
$realmPlayers = 0;
$realmName    = 'Eons';
try {
    $stmt = $db->query("SELECT name, flag FROM realmlist LIMIT 1");
    $realm = $stmt->fetch();
    if ($realm) {
        $realmName   = $realm['name'];
        $realmOnline = !(((int)$realm['flag']) & 2);
    }
} catch (PDOException $e) { /* realmlist inaccessible */ }

// ── Personnages (auc_chars) ───────────────────────────────────
$characters = [];
try {
    $dsn   = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_CHARS_NAME . ';charset=utf8mb4';
    $chars = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Nombre réel de joueurs connectés sur le serveur
    $realmPlayers = (int)$chars->query("SELECT COUNT(*) FROM characters WHERE online = 1")->fetchColumn();

    // Personnages du compte (avec colonne online pour afficher le statut en jeu)
    $stmt = $chars->prepare("
        SELECT name, level, race, class, gender, zone, totaltime, money, online
        FROM characters WHERE account = :aid AND deleteDate IS NULL ORDER BY level DESC LIMIT 10
    ");
    $stmt->execute([':aid' => $accountId]);
    $characters = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[AU Dashboard] Chars DB error: ' . $e->getMessage());
}

// ── Traitement formulaire changement de mot de passe ─────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF invalide.';
    } else {
        // ── Changer le mot de passe ───────────────────────────
        if ($_POST['action'] === 'change_password') {
            $currentPw  = $_POST['current_password'] ?? '';
            $newPw      = $_POST['new_password']     ?? '';
            $confirmPw  = $_POST['confirm_password'] ?? '';

            if (strlen($newPw) < 6) {
                $errors[] = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';
            } elseif ($newPw !== $confirmPw) {
                $errors[] = 'Les mots de passe ne correspondent pas.';
            } else {
                // Vérifier l'ancien mot de passe
                $stmt = $db->prepare("SELECT salt, verifier FROM account WHERE id = :id");
                $stmt->execute([':id' => $accountId]);
                $row = $stmt->fetch();
                if ($row && SRP6::verifyPassword($account['username'], $currentPw, $row['salt'], $row['verifier'])) {
                    $salt = SRP6::generateSalt();
                    $srp  = SRP6::calcVerifier($account['username'], $newPw, $salt);
                    $upd  = $db->prepare("UPDATE account SET salt=:s, verifier=:v WHERE id=:id");
                    $upd->execute([':s' => $srp['salt'], ':v' => $srp['verifier'], ':id' => $accountId]);
                    $flashMsg  = '✦ Mot de passe modifié avec succès !';
                    $flashType = 'success';
                } else {
                    $errors[] = 'Mot de passe actuel incorrect.';
                }
            }
        }

        // ── Changer l'email ───────────────────────────────────
        if ($_POST['action'] === 'change_email') {
            $newEmail = trim($_POST['new_email'] ?? '');
            $pw       = $_POST['email_password'] ?? '';

            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Adresse e-mail invalide.';
            } else {
                $stmt = $db->prepare("SELECT salt, verifier FROM account WHERE id = :id");
                $stmt->execute([':id' => $accountId]);
                $row = $stmt->fetch();
                if ($row && SRP6::verifyPassword($account['username'], $pw, $row['salt'], $row['verifier'])) {
                    $upd = $db->prepare("UPDATE account SET email=:e WHERE id=:id");
                    $upd->execute([':e' => strtolower($newEmail), ':id' => $accountId]);
                    $_SESSION['account_email'] = strtolower($newEmail);
                    $account['email'] = strtolower($newEmail);
                    $flashMsg  = '✦ Adresse e-mail mise à jour !';
                    $flashType = 'success';
                } else {
                    $errors[] = 'Mot de passe incorrect.';
                }
            }
        }
    }
    // Régénérer le token CSRF
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// ── Helpers ───────────────────────────────────────────────────
$classNames = [
    1=>'Guerrier', 2=>'Paladin', 3=>'Chasseur', 4=>'Voleur',
    5=>'Prêtre', 6=>'Chevalier de la mort', 7=>'Chaman',
    8=>'Mage', 9=>'Démoniste', 11=>'Druide', 14=>'Moine'
];
$classColors = [
    1=>'#C69B3A', 2=>'#F48CBA', 3=>'#AAD372', 4=>'#FFF468',
    5=>'#FFFFFF', 6=>'#C41E3A', 7=>'#0070DD', 8=>'#3FC7EB',
    9=>'#8788EE', 11=>'#FF7C0A', 14=>'#FFF468'
];
$classIcons = [
    1=>'⚔', 2=>'🛡', 3=>'🏹', 4=>'🗡',
    5=>'✝', 6=>'💀', 7=>'⚡', 8=>'🔥',
    9=>'👁', 11=>'🌿', 14=>'🗡'
];
$raceNames = [
    1=>'Humain', 2=>'Orc', 3=>'Nain', 4=>'Elfe de la nuit',
    5=>'Mort-vivant', 6=>'Tauren', 7=>'Gnome', 8=>'Troll',
    10=>'Elfe du sang', 11=>'Draeneï'
];
$expansionNames = [0=>'Vanilla', 1=>'The Burning Crusade', 2=>'Wrath of the Lich King'];

function formatMoney(int $copper): string {
    $g = intdiv($copper, 10000);
    $s = intdiv($copper % 10000, 100);
    $c = $copper % 100;
    $out = '';
    if ($g > 0) $out .= "<span class='gold-coin'>{$g}Po</span> ";
    if ($s > 0) $out .= "<span class='silver-coin'>{$s}Pa</span> ";
    $out .= "<span class='copper-coin'>{$c}Pc</span>";
    return $out;
}

function formatTime(int $seconds): string {
    $d = intdiv($seconds, 86400);
    $h = intdiv($seconds % 86400, 3600);
    $m = intdiv($seconds % 3600, 60);
    if ($d > 0) return "{$d}j {$h}h";
    if ($h > 0) return "{$h}h {$m}m";
    return "{$m}m";
}

$pageTitle = 'Tableau de bord — Eons';
require_once __DIR__ . '/header.php';
?>

    <style>
        /* ─── Variables supplémentaires ─── */
        :root {
            --panel-bg:     rgba(11,13,36,.85);
            --panel-border: rgba(200,151,42,.15);
            --panel-border-arcane: rgba(123,130,255,.15);
        }

        body::after {
            content:''; position:fixed; inset:0; z-index:1; pointer-events:none;
            background:
                radial-gradient(ellipse 60% 50% at 20% 20%, rgba(30,33,96,.25) 0%, transparent 70%),
                radial-gradient(ellipse 40% 40% at 80% 80%, rgba(98,54,212,.12) 0%, transparent 70%),
                radial-gradient(ellipse 30% 30% at 50% 100%, rgba(200,151,42,.07) 0%, transparent 60%);
        }

        main { position:relative; z-index:2; flex:1; padding:5rem 2rem 2.5rem; max-width:1280px; margin:0 auto; width:100%; }

        .page-header { margin-bottom:2.5rem; }
        .page-title {
            font-family:'Cinzel Decorative',serif; font-size:1.6rem; font-weight:700;
            color:var(--white); text-shadow:0 0 30px rgba(200,151,42,.2);
            display:flex; align-items:center; gap:.75rem;
        }
        .page-title .gem { width:8px; height:8px; background:var(--gold); transform:rotate(45deg); box-shadow:0 0 12px rgba(200,151,42,.8); flex-shrink:0; }
        .page-subtitle { font-family:'Cinzel',serif; font-size:.68rem; letter-spacing:.2em; text-transform:uppercase; color:var(--silver); margin-top:.5rem; }

        .alert { padding:.9rem 1.2rem; margin-bottom:1.5rem; font-size:.95rem; line-height:1.5; clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%); }
        .alert-error   { background:rgba(255,95,95,.08);  border:1px solid rgba(255,95,95,.3);  color:var(--error); }
        .alert-success { background:rgba(95,255,176,.07); border:1px solid rgba(95,255,176,.3); color:var(--success); }
        .alert-info    { background:rgba(123,130,255,.07);border:1px solid rgba(123,130,255,.25);color:var(--info); }

        .dashboard-grid {
            display:grid;
            grid-template-columns: 340px 1fr;
            grid-template-rows: auto auto auto;
            gap:1.5rem;
        }
        @media(max-width:900px) { .dashboard-grid { grid-template-columns:1fr; } }

        .panel {
            background:var(--panel-bg);
            border:1px solid var(--panel-border);
            backdrop-filter:blur(10px);
            clip-path:polygon(0 0, calc(100% - 16px) 0, 100% 16px, 100% 100%, 16px 100%, 0 calc(100% - 16px));
            position:relative;
            animation:fadeUp .6s ease forwards;
            animation-fill-mode:both;
        }
        .panel::before { content:''; position:absolute; top:0; right:0; width:16px; height:16px; background:rgba(200,151,42,.2); clip-path:polygon(0 0,100% 0,100% 100%); }
        .panel::after  { content:''; position:absolute; bottom:0; left:0; width:16px; height:16px; background:rgba(123,130,255,.1); clip-path:polygon(0 0,0 100%,100% 100%); }
        .panel.arcane-border { border-color:var(--panel-border-arcane); }
        .panel:nth-child(1){animation-delay:.05s} .panel:nth-child(2){animation-delay:.1s}
        .panel:nth-child(3){animation-delay:.15s} .panel:nth-child(4){animation-delay:.2s}
        .panel:nth-child(5){animation-delay:.25s}

        .panel-header { padding:1.2rem 1.5rem .9rem; border-bottom:1px solid rgba(200,151,42,.1); display:flex; align-items:center; gap:.7rem; }
        .panel-icon { font-size:1.1rem; opacity:.9; }
        .panel-title { font-family:'Cinzel',serif; font-size:.72rem; letter-spacing:.2em; text-transform:uppercase; color:var(--silver); }
        .panel-title span { color:var(--gold-bright); }
        .panel-body { padding:1.4rem 1.5rem; }

        .account-avatar { width:64px; height:64px; border-radius:50%; background:linear-gradient(135deg, var(--arcane) 0%, var(--void-purple) 100%); border:2px solid rgba(200,151,42,.3); display:flex; align-items:center; justify-content:center; font-size:1.6rem; margin:0 auto 1.2rem; box-shadow:0 0 20px rgba(123,130,255,.2); }
        .account-name { font-family:'Cinzel Decorative',serif; font-size:1.1rem; color:var(--white); text-align:center; margin-bottom:.25rem; }
        .account-tag { text-align:center; font-size:.82rem; color:var(--silver); font-style:italic; margin-bottom:1.4rem; }
        .account-badge { display:inline-flex; align-items:center; gap:.3rem; font-family:'Cinzel',serif; font-size:.58rem; letter-spacing:.15em; text-transform:uppercase; padding:.25rem .7rem; margin:0 auto .3rem; border:1px solid; }
        .badge-expansion { color:var(--arcane-bright); border-color:rgba(123,130,255,.3); background:rgba(123,130,255,.06); }
        .badge-online    { color:var(--success); border-color:rgba(95,255,176,.3); background:rgba(95,255,176,.05); }
        .badge-offline   { color:var(--silver); border-color:rgba(168,180,208,.2); background:rgba(168,180,208,.04); }
        .badges { display:flex; flex-wrap:wrap; gap:.4rem; justify-content:center; margin-bottom:1.4rem; }

        .info-list { list-style:none; }
        .info-list li { display:flex; justify-content:space-between; align-items:center; padding:.6rem 0; border-bottom:1px solid rgba(123,130,255,.07); font-size:.9rem; gap:1rem; }
        .info-list li:last-child { border-bottom:none; }
        .info-label { font-family:'Cinzel',serif; font-size:.62rem; letter-spacing:.12em; text-transform:uppercase; color:var(--silver); flex-shrink:0; }
        .info-value { color:var(--white); text-align:right; font-size:.88rem; }
        .info-value.gold { color:var(--gold-bright); }

        .server-status { display:flex; align-items:center; gap:1rem; margin-bottom:1.2rem; padding:1rem 1.2rem; background:rgba(7,9,26,.5); border:1px solid rgba(123,130,255,.1); }
        .status-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; animation:pulse 2s ease-in-out infinite; }
        .status-dot.online  { background:var(--success); box-shadow:0 0 10px rgba(95,255,176,.5); }
        .status-dot.offline { background:var(--error);   box-shadow:0 0 10px rgba(255,95,95,.4); animation:none; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
        .status-label { font-family:'Cinzel',serif; font-size:.7rem; letter-spacing:.15em; text-transform:uppercase; }
        .status-label.online  { color:var(--success); }
        .status-label.offline { color:var(--error); }
        .status-realm { color:var(--silver); font-size:.8rem; margin-left:auto; }
        .server-stats { display:grid; grid-template-columns:1fr 1fr; gap:.8rem; }
        .stat-box { background:rgba(7,9,26,.5); border:1px solid rgba(200,151,42,.1); padding:.9rem 1rem; text-align:center; }
        .stat-number { font-family:'Cinzel Decorative',serif; font-size:1.6rem; color:var(--gold-bright); line-height:1; margin-bottom:.25rem; }
        .stat-desc { font-family:'Cinzel',serif; font-size:.58rem; letter-spacing:.15em; text-transform:uppercase; color:var(--silver); }

        .chars-grid { display:flex; flex-direction:column; gap:.6rem; }
        .char-card { display:grid; grid-template-columns:40px 1fr auto; align-items:center; gap:.9rem; padding:.8rem 1rem; background:rgba(7,9,26,.5); border:1px solid rgba(123,130,255,.08); transition:border-color .3s, background .3s; cursor:default; }
        .char-card:hover { border-color:rgba(123,130,255,.25); background:rgba(30,33,96,.15); }
        .char-icon { width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.1rem; border:1px solid; flex-shrink:0; }
        .char-info .char-name { font-family:'Cinzel',serif; font-size:.82rem; color:var(--white); margin-bottom:.15rem; }
        .char-info .char-meta { font-size:.78rem; color:var(--silver); }
        .char-info .char-meta span { margin-right:.5rem; }
        .char-right { text-align:right; flex-shrink:0; }
        .char-level { font-family:'Cinzel Decorative',serif; font-size:1.1rem; color:var(--gold-bright); line-height:1; }
        .char-level-label { font-size:.6rem; letter-spacing:.1em; text-transform:uppercase; color:var(--silver); }
        .char-time { font-size:.72rem; color:var(--silver); margin-top:.15rem; }
        .empty-chars { text-align:center; padding:2.5rem 1rem; color:var(--silver); font-style:italic; }
        .empty-chars .empty-icon { font-size:2.2rem; margin-bottom:.8rem; opacity:.4; }
        .empty-chars p { font-family:'Cinzel',serif; font-size:.68rem; letter-spacing:.15em; text-transform:uppercase; }

        .tabs { display:flex; gap:0; margin-bottom:1.4rem; border-bottom:1px solid rgba(123,130,255,.1); }
        .tab-btn { font-family:'Cinzel',serif; font-size:.62rem; letter-spacing:.15em; text-transform:uppercase; padding:.65rem 1.1rem; background:none; border:none; cursor:pointer; color:var(--silver); border-bottom:2px solid transparent; margin-bottom:-1px; transition:color .3s, border-color .3s; }
        .tab-btn:hover { color:var(--arcane-bright); }
        .tab-btn.active { color:var(--gold-bright); border-color:var(--gold); }
        .tab-panel { display:none; }
        .tab-panel.active { display:block; }

        .form-group { margin-bottom:1.1rem; }
        .form-label { display:block; font-family:'Cinzel',serif; font-size:.62rem; letter-spacing:.18em; text-transform:uppercase; color:var(--silver); margin-bottom:.4rem; }
        .input-wrap { position:relative; }
        .input-icon { position:absolute; left:.9rem; top:50%; transform:translateY(-50%); font-size:.85rem; pointer-events:none; opacity:.45; }
        input[type="password"], input[type="email"] {
            width:100%; background:rgba(7,9,26,.8); border:1px solid rgba(200,151,42,.15);
            color:var(--white); font-family:'Crimson Pro',serif; font-size:1rem;
            padding:.65rem 1rem .65rem 2.3rem; outline:none;
            transition:border-color .3s, box-shadow .3s;
            clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%);
        }
        input:focus { border-color:rgba(200,151,42,.45); box-shadow:0 0 12px rgba(200,151,42,.1); }
        input::placeholder { color:rgba(168,180,208,.3); }

        .btn-primary { width:100%; padding:.8rem; font-family:'Cinzel',serif; font-size:.72rem; font-weight:700; letter-spacing:.18em; text-transform:uppercase; background:linear-gradient(135deg, #b07820 0%, #e8b840 50%, #b07820 100%); color:#1a1000; border:none; cursor:pointer; clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%); box-shadow:0 4px 20px rgba(200,151,42,.25); transition:transform .2s, box-shadow .3s; position:relative; overflow:hidden; }
        .btn-primary::before { content:''; position:absolute; inset:0; background:rgba(255,255,255,.12); transform:translateX(-100%) skewX(-15deg); transition:transform .4s; }
        .btn-primary:hover::before { transform:translateX(120%) skewX(-15deg); }
        .btn-primary:hover { transform:translateY(-2px); box-shadow:0 6px 28px rgba(200,151,42,.45); }

        .btn-arcane-form { width:100%; padding:.8rem; font-family:'Cinzel',serif; font-size:.72rem; font-weight:700; letter-spacing:.18em; text-transform:uppercase; background:linear-gradient(135deg, var(--arcane) 0%, var(--void-purple) 100%); color:var(--white); border:none; cursor:pointer; clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%); box-shadow:0 4px 20px rgba(98,54,212,.3); transition:transform .2s, box-shadow .3s; position:relative; overflow:hidden; }
        .btn-arcane-form::before { content:''; position:absolute; inset:0; background:rgba(255,255,255,.07); transform:translateX(-100%) skewX(-15deg); transition:transform .4s; }
        .btn-arcane-form:hover::before { transform:translateX(120%) skewX(-15deg); }
        .btn-arcane-form:hover { transform:translateY(-2px); box-shadow:0 6px 28px rgba(155,111,255,.45); }

        .gold-coin   { color:#f0c060; font-weight:600; }
        .silver-coin { color:#c0cce0; }
        .copper-coin { color:#c87030; }

        .section-divider { display:flex; align-items:center; gap:.6rem; margin:1.2rem 0; }
        .section-divider::before,.section-divider::after { content:''; flex:1; height:1px; background:linear-gradient(90deg,transparent,rgba(200,151,42,.2)); }
        .section-divider::after { background:linear-gradient(90deg,rgba(200,151,42,.2),transparent); }
        .section-divider-gem { width:5px; height:5px; background:var(--gold); transform:rotate(45deg); box-shadow:0 0 6px rgba(200,151,42,.6); }

        .col-span-full { grid-column:1 / -1; }
        @media(max-width:640px) { main { padding:5rem 1rem 1.5rem; } .server-stats { grid-template-columns:1fr; } }
    </style>

<main>
    <div class="page-header">
        <h1 class="page-title">
            <span class="gem"></span>
            Tableau de Bord
        </h1>
        <p class="page-subtitle">Gérez votre compte et vos héros d'Eons</p>
    </div>

    <?php if ($flashMsg): ?>
    <div class="alert alert-<?= $flashType ?>"><?= htmlspecialchars($flashMsg) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as $e): ?><?= htmlspecialchars($e) ?><br><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="dashboard-grid">

        <!-- Panel Compte -->
        <div class="panel">
            <div class="panel-header">
                <span class="panel-icon">⚔</span>
                <span class="panel-title">Mon <span>Compte</span></span>
            </div>
            <div class="panel-body">
                <div class="account-avatar">⚔</div>
                <div class="account-name"><?= htmlspecialchars($account['username'] ?? '') ?></div>
                <div class="account-tag"><?= htmlspecialchars($account['email'] ?? '') ?></div>
                <div class="badges">
                    <span class="account-badge badge-expansion">✦ <?= $expansionNames[(int)($account['expansion'] ?? 2)] ?? 'WotLK' ?></span>
                    <?php if ((int)($account['online'] ?? 0) === 1): ?>
                    <span class="account-badge badge-online">● En ligne</span>
                    <?php else: ?>
                    <span class="account-badge badge-offline">● Hors ligne</span>
                    <?php endif; ?>
                </div>
                <div class="section-divider"><div class="section-divider-gem"></div></div>
                <ul class="info-list">
                    <li><span class="info-label">Inscription</span><span class="info-value"><?= $account['joindate'] ? date('d/m/Y', strtotime($account['joindate'])) : '—' ?></span></li>
                    <li><span class="info-label">Dernière connexion</span><span class="info-value"><?= $account['last_login'] ? date('d/m/Y H:i', strtotime($account['last_login'])) : '—' ?></span></li>
                    <li><span class="info-label">Dernière IP</span><span class="info-value"><?= htmlspecialchars($account['last_ip'] ?? '—') ?></span></li>
                    <li><span class="info-label">Personnages</span><span class="info-value gold"><?= count($characters) ?></span></li>
                    <?php if ((int)($account['mutetime'] ?? 0) > time()): ?>
                    <li><span class="info-label">Réduit au silence</span><span class="info-value" style="color:var(--error)">Jusqu'au <?= date('d/m/Y', $account['mutetime']) ?></span></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Panel Personnages -->
        <div class="panel arcane-border" style="grid-row: 1 / 3;">
            <div class="panel-header">
                <span class="panel-icon">🧙</span>
                <span class="panel-title">Mes <span>Héros</span></span>
            </div>
            <div class="panel-body">
                <?php if (empty($characters)): ?>
                <div class="empty-chars">
                    <div class="empty-icon">⚔</div>
                    <p>Aucun héros créé</p>
                    <p style="font-family:'Crimson Pro',serif;font-size:.88rem;margin-top:.5rem;font-style:italic;color:var(--silver);">Connectez-vous au jeu pour créer votre premier personnage.</p>
                </div>
                <?php else: ?>
                <div class="chars-grid">
                    <?php foreach ($characters as $char):
                        $classId = (int)$char['class'];
                        $color   = $classColors[$classId]  ?? '#a8b4d0';
                        $icon    = $classIcons[$classId]   ?? '⚔';
                        $class   = $classNames[$classId]   ?? 'Inconnu';
                        $race    = $raceNames[(int)$char['race']] ?? 'Inconnu';
                    ?>
                    <div class="char-card">
                        <div class="char-icon" style="background:<?= $color ?>18;border-color:<?= $color ?>40;color:<?= $color ?>"><?= $icon ?></div>
                        <div class="char-info">
                            <div class="char-name" style="color:<?= $color ?>"><?= htmlspecialchars($char['name']) ?>
                                <?php if (!empty($char['online'])): ?><span style="font-size:.6rem;font-family:'Cinzel',serif;letter-spacing:.1em;color:var(--success);margin-left:.4rem;vertical-align:middle;">● EN JEU</span><?php endif; ?>
                            </div>
                            <div class="char-meta">
                                <span><?= $race ?></span>
                                <span style="color:<?= $color ?>"><?= $class ?></span>
                                <?php if (!empty($char['zone'])): ?><span>· Zone <?= (int)$char['zone'] ?></span><?php endif; ?>
                            </div>
                            <?php if (!empty($char['money'])): ?><div style="font-size:.75rem;margin-top:.2rem"><?= formatMoney((int)$char['money']) ?></div><?php endif; ?>
                        </div>
                        <div class="char-right">
                            <div class="char-level"><?= (int)$char['level'] ?></div>
                            <div class="char-level-label">Niv.</div>
                            <?php if (!empty($char['totaltime'])): ?><div class="char-time"><?= formatTime((int)$char['totaltime']) ?></div><?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Panel Serveur -->
        <div class="panel">
            <div class="panel-header">
                <span class="panel-icon">🌍</span>
                <span class="panel-title">Statut du <span>Serveur</span></span>
            </div>
            <div class="panel-body">
                <div class="server-status">
                    <div class="status-dot <?= $realmOnline ? 'online' : 'offline' ?>"></div>
                    <span class="status-label <?= $realmOnline ? 'online' : 'offline' ?>"><?= $realmOnline ? 'En ligne' : 'Hors ligne' ?></span>
                    <span class="status-realm"><?= htmlspecialchars($realmName) ?></span>
                </div>
                <div class="server-stats">
                    <div class="stat-box">
                        <div class="stat-number"><?= $realmOnline ? $realmPlayers : '—' ?></div>
                        <div class="stat-desc">Joueurs connectés</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number">3.3.5</div>
                        <div class="stat-desc">Version du client</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel Gestion -->
        <div class="panel col-span-full">
            <div class="panel-header">
                <span class="panel-icon">🔮</span>
                <span class="panel-title">Gestion du <span>Compte</span></span>
            </div>
            <div class="panel-body">
                <div class="tabs">
                    <button class="tab-btn active" onclick="switchTab('password', this)">🔑 Mot de passe</button>
                    <button class="tab-btn" onclick="switchTab('email', this)">✉ Adresse e-mail</button>
                </div>
                <div id="tab-password" class="tab-panel active" style="max-width:460px">
                    <form method="POST" action="dashboard.php" autocomplete="off" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="change_password">
                        <div class="form-group"><label class="form-label" for="current_password">Mot de passe actuel</label><div class="input-wrap"><span class="input-icon">🔒</span><input type="password" id="current_password" name="current_password" placeholder="Votre mot de passe actuel" required></div></div>
                        <div class="form-group"><label class="form-label" for="new_password">Nouveau mot de passe</label><div class="input-wrap"><span class="input-icon">🔮</span><input type="password" id="new_password" name="new_password" placeholder="Minimum 6 caractères" minlength="6" required></div></div>
                        <div class="form-group"><label class="form-label" for="confirm_password">Confirmer le mot de passe</label><div class="input-wrap"><span class="input-icon">🔮</span><input type="password" id="confirm_password" name="confirm_password" placeholder="Répétez le nouveau mot de passe" required></div></div>
                        <button type="submit" class="btn-primary">⚔ &nbsp; Changer le mot de passe</button>
                    </form>
                </div>
                <div id="tab-email" class="tab-panel" style="max-width:460px">
                    <form method="POST" action="dashboard.php" autocomplete="off" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="change_email">
                        <div class="form-group"><label class="form-label">Email actuel</label><div style="font-size:.9rem;color:var(--silver);padding:.5rem 0;font-style:italic;"><?= htmlspecialchars($account['email'] ?? '—') ?></div></div>
                        <div class="form-group"><label class="form-label" for="new_email">Nouvel e-mail</label><div class="input-wrap"><span class="input-icon">✉</span><input type="email" id="new_email" name="new_email" placeholder="nouveau@email.com" required></div></div>
                        <div class="form-group"><label class="form-label" for="email_password">Confirmez votre mot de passe</label><div class="input-wrap"><span class="input-icon">🔒</span><input type="password" id="email_password" name="email_password" placeholder="Votre mot de passe actuel" required></div></div>
                        <button type="submit" class="btn-arcane-form">✦ &nbsp; Mettre à jour l'e-mail</button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</main>
<script>
function switchTab(name, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}
</script>
</body>
</html>
