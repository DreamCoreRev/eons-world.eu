<?php
// ============================================================
//  dashboard.php — Eons CMS | Arcanic Theme Enhanced
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/srp6.php';

if (empty($_SESSION['logged_in']) || empty($_SESSION['account_id'])) { header('Location: auth.php'); exit; }

$accountId = (int)$_SESSION['account_id'];
$flashMsg  = '';
$flashType = 'info';
$errors    = [];

try {
    $db   = getAuthDB();
    $stmt = $db->prepare("SELECT id, username, email, joindate, last_login, last_ip, online, expansion, failed_logins, locked, mutetime, dp, vp FROM account WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $accountId]);
    $account = $stmt->fetch();
    if (!$account) { session_destroy(); header('Location: auth.php'); exit; }
} catch (PDOException $e) { $account = null; error_log('[AU Dashboard] DB error: ' . $e->getMessage()); }

$realmOnline  = false;
$realmPlayers = 0;
$realmName    = 'Eons';
try {
    $stmt = $db->query("SELECT name, flag FROM realmlist LIMIT 1");
    $realm = $stmt->fetch();
    if ($realm) { $realmName = $realm['name']; $realmOnline = !(((int)$realm['flag']) & 2); }
} catch (PDOException $e) {}

$characters = [];
try {
    $dsn   = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_CHARS_NAME . ';charset=utf8mb4';
    $chars = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $realmPlayers = (int)$chars->query("SELECT COUNT(*) FROM characters WHERE online = 1")->fetchColumn();
    $stmt = $chars->prepare("SELECT name, level, race, class, gender, zone, totaltime, money, online FROM characters WHERE account = :aid AND deleteDate IS NULL ORDER BY level DESC LIMIT 10");
$stmt->execute([':aid' => $accountId]);
$characters = $stmt->fetchAll();

// Récupérer les noms de zones depuis eons_auth
$zoneNames = [];
if (!empty($characters)) {
    $zoneIds = array_unique(array_filter(array_column($characters, 'zone')));
    if ($zoneIds) {
        $placeholders = implode(',', array_fill(0, count($zoneIds), '?'));
        $zStmt = $db->prepare("SELECT id, zone_name FROM zones WHERE id IN ($placeholders)");
        $zStmt->execute($zoneIds);
        foreach ($zStmt->fetchAll() as $z) {
            $zoneNames[$z['id']] = $z['zone_name'];
        }
    }
}
} catch (PDOException $e) { error_log('[AU Dashboard] Chars DB: ' . $e->getMessage()); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) { $errors[] = 'Token CSRF invalide.'; }
    else {
        if ($_POST['action'] === 'change_password') {
            $currentPw = $_POST['current_password'] ?? ''; $newPw = $_POST['new_password'] ?? ''; $confirmPw = $_POST['confirm_password'] ?? '';
            if (strlen($newPw) < 6) $errors[] = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';
            elseif ($newPw !== $confirmPw) $errors[] = 'Les mots de passe ne correspondent pas.';
            else {
                $stmt = $db->prepare("SELECT salt, verifier FROM account WHERE id = :id"); $stmt->execute([':id' => $accountId]); $row = $stmt->fetch();
                if ($row && SRP6::verifyPassword($account['username'], $currentPw, $row['salt'], $row['verifier'])) {
                    $salt = SRP6::generateSalt(); $srp = SRP6::calcVerifier($account['username'], $newPw, $salt);
                    $upd = $db->prepare("UPDATE account SET salt=:s, verifier=:v WHERE id=:id"); $upd->execute([':s'=>$srp['salt'],':v'=>$srp['verifier'],':id'=>$accountId]);
                    $flashMsg = '✦ Mot de passe modifié avec succès !'; $flashType = 'success';
                } else { $errors[] = 'Mot de passe actuel incorrect.'; }
            }
        }
        if ($_POST['action'] === 'change_email') {
            $newEmail = trim($_POST['new_email'] ?? ''); $pw = $_POST['email_password'] ?? '';
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Adresse e-mail invalide.';
            else {
                $stmt = $db->prepare("SELECT salt, verifier FROM account WHERE id = :id"); $stmt->execute([':id' => $accountId]); $row = $stmt->fetch();
                if ($row && SRP6::verifyPassword($account['username'], $pw, $row['salt'], $row['verifier'])) {
                    $upd = $db->prepare("UPDATE account SET email=:e WHERE id=:id"); $upd->execute([':e'=>strtolower($newEmail),':id'=>$accountId]);
                    $_SESSION['account_email'] = strtolower($newEmail); $account['email'] = strtolower($newEmail);
                    $flashMsg = '✦ Adresse e-mail mise à jour !'; $flashType = 'success';
                } else { $errors[] = 'Mot de passe incorrect.'; }
            }
        }
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf_token'];

// ── Helpers ───────────────────────────────────────────────────
$classNames = [1=>'Guerrier',2=>'Paladin',3=>'Chasseur',4=>'Voleur',5=>'Prêtre',6=>'Chevalier de la mort',7=>'Chaman',8=>'Mage',9=>'Démoniste',11=>'Druide'];
$classIcons = [1=>'⚔',2=>'🛡',3=>'🏹',4=>'🗡',5=>'✝',6=>'💀',7=>'⚡',8=>'🔥',9=>'👁',11=>'🌿'];
$classColors = [1=>'#c79c6e',2=>'#f58cba',3=>'#abd473',4=>'#fff569',5=>'#ffffff',6=>'#c41f3b',7=>'#0070de',8=>'#69ccf0',9=>'#9482c9',11=>'#ff7d0a'];
$raceNames = [1=>'Humain',2=>'Orc',3=>'Nain',4=>'Elfe de la nuit',5=>'Mort-vivant',6=>'Tauren',7=>'Gnome',8=>'Troll',10=>'Elfe du sang',11=>'Draeneï'];
$expansionNames = [0=>'Classic',1=>'TBC',2=>'WotLK'];

function formatTime(int $secs): string {
    $d = intdiv($secs, 86400); $h = intdiv($secs % 86400, 3600);
    return $d > 0 ? "{$d}j {$h}h" : ($h > 0 ? "{$h}h " . intdiv($secs % 3600, 60) . 'min' : intdiv($secs, 60) . 'min');
}
function formatMoney(int $copper): string {
    $g = intdiv($copper, 10000); $s = intdiv($copper % 10000, 100); $c = $copper % 100;
    $out = '';
    if ($g) $out .= "<span style='color:#f0c060'>{$g}or</span> ";
    if ($s) $out .= "<span style='color:#c0c0c0'>{$s}ar</span> ";
    if ($c || !$out) $out .= "<span style='color:#cd7f32'>{$c}cu</span>";
    return trim($out);
}

$pageTitle = 'Tableau de Bord — Eons';
require_once __DIR__ . '/header.php';
?>
<style>
/* ─── DASHBOARD LAYOUT ─────────────────────────────────────── */
.dashboard {
    position: relative; z-index: 10;
    padding: 5.5rem 2rem 4rem;
    max-width: 1300px;
    margin: 0 auto;
}

.dash-header {
    margin-bottom: 2.5rem;
    display: flex; align-items: flex-end; justify-content: space-between;
    flex-wrap: wrap; gap: 1rem;
}

.dash-greeting {
    font-family: 'Cinzel Decorative', serif;
    font-size: clamp(1.2rem, 3vw, 1.8rem);
    font-weight: 700;
    color: var(--white);
    text-shadow: 0 0 30px rgba(136,144,255,0.25);
}
.dash-greeting span { color: var(--gold-bright); text-shadow: 0 0 20px rgba(240,192,96,0.4); }

.dash-breadcrumb {
    font-family: 'Cinzel', serif; font-size: 0.6rem;
    letter-spacing: 0.18em; color: var(--silver);
    text-transform: uppercase; opacity: 0.6;
}
.dash-breadcrumb a { color: var(--arcane-bright); text-decoration: none; }

/* ─── ALERTS ────────────────────────────────────────────────── */
.dash-alert {
    padding: 1rem 1.4rem; margin-bottom: 1.8rem;
    border-left: 3px solid; font-size: 0.92rem;
    display: flex; align-items: center; gap: 0.8rem;
    background: rgba(9,12,34,0.7); backdrop-filter: blur(10px);
}
.dash-alert-success { border-color: var(--success); color: var(--success); }
.dash-alert-error   { border-color: var(--error);   color: #ff9999; }

/* ─── GRID ──────────────────────────────────────────────────── */
.dash-grid {
    display: grid;
    grid-template-columns: 280px 1fr;
    grid-template-rows: auto auto auto;
    gap: 1.5rem;
}

/* ─── PANEL ─────────────────────────────────────────────────── */
.panel {
    background: rgba(6,8,26,0.78);
    border: 1px solid rgba(136,144,255,0.12);
    position: relative; overflow: hidden;
    backdrop-filter: blur(16px);
    transition: border-color 0.3s;
}
.panel:hover { border-color: rgba(136,144,255,0.22); }

/* Animated corner accent */
.panel::before {
    content: '';
    position: absolute; top: 0; left: 0;
    width: 20px; height: 20px;
    border-top: 1px solid var(--gold-bright);
    border-left: 1px solid var(--gold-bright);
    opacity: 0.5;
}
.panel::after {
    content: '';
    position: absolute; bottom: 0; right: 0;
    width: 20px; height: 20px;
    border-bottom: 1px solid var(--arcane-bright);
    border-right: 1px solid var(--arcane-bright);
    opacity: 0.4;
}

.panel-arcane { border-color: rgba(136,144,255,0.2); }
.panel-arcane::before { border-color: var(--arcane-bright); opacity: 0.6; }

.panel-header {
    display: flex; align-items: center; gap: 0.8rem;
    padding: 1.2rem 1.5rem;
    border-bottom: 1px solid rgba(136,144,255,0.08);
    background: rgba(9,12,34,0.5);
}
.panel-icon { font-size: 1.1rem; filter: drop-shadow(0 0 8px rgba(136,144,255,0.5)); }
.panel-title {
    font-family: 'Cinzel', serif; font-size: 0.75rem;
    letter-spacing: 0.18em; text-transform: uppercase; color: var(--silver-bright);
    font-weight: 600;
}
.panel-title span { color: var(--gold-bright); }

.panel-body { padding: 1.5rem; }

/* ─── ACCOUNT PANEL ─────────────────────────────────────────── */
.account-avatar {
    width: 70px; height: 70px;
    background: linear-gradient(135deg, rgba(20,24,80,0.8), rgba(90,48,212,0.4));
    border: 1px solid rgba(136,144,255,0.3);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.8rem;
    margin: 0 auto 1rem;
    box-shadow: 0 0 30px rgba(136,144,255,0.15), inset 0 0 20px rgba(136,144,255,0.05);
    animation: orbPulse 5s ease-in-out infinite;
}
@keyframes orbPulse {
    0%,100% { box-shadow: 0 0 30px rgba(136,144,255,0.15); }
    50%      { box-shadow: 0 0 50px rgba(136,144,255,0.28); }
}

.account-name {
    font-family: 'Cinzel Decorative', serif; font-size: 1.1rem; font-weight: 700;
    color: var(--gold-bright); text-align: center; margin-bottom: 0.25rem;
    text-shadow: 0 0 20px rgba(240,192,96,0.35);
}
.account-email {
    font-size: 0.8rem; color: var(--silver); text-align: center; margin-bottom: 1rem;
    font-style: italic; opacity: 0.7;
}

.badges { display: flex; gap: 0.4rem; justify-content: center; flex-wrap: wrap; margin-bottom: 1.2rem; }
.badge {
    font-family: 'Cinzel', serif; font-size: 0.55rem; letter-spacing: 0.12em;
    padding: 0.25rem 0.6rem; text-transform: uppercase;
    border: 1px solid; clip-path: polygon(4px 0%,100% 0%,calc(100% - 4px) 100%,0% 100%);
}
.badge-expansion { border-color: rgba(240,192,96,0.4); color: var(--gold-bright); background: rgba(240,192,96,0.06); }
.badge-online    { border-color: rgba(95,255,176,0.4); color: var(--success); background: rgba(95,255,176,0.06); }
.badge-offline   { border-color: rgba(168,180,208,0.2); color: var(--silver); background: transparent; opacity: 0.6; }

.info-sep { height: 1px; background: linear-gradient(90deg, transparent, rgba(136,144,255,0.2), transparent); margin: 1.2rem 0; }

.info-list { list-style: none; display: flex; flex-direction: column; gap: 0.6rem; }
.info-list li { display: flex; justify-content: space-between; align-items: center; font-size: 0.82rem; }
.info-label { color: var(--silver); opacity: 0.65; }
.info-value { color: var(--silver-bright); font-weight: 400; }
.info-value.gold { color: var(--gold-bright); }

.panel-logout {
    display: block; text-align: center; margin-top: 1.5rem;
    font-family: 'Cinzel', serif; font-size: 0.58rem; letter-spacing: 0.15em;
    color: rgba(168,180,208,0.45); text-decoration: none; text-transform: uppercase;
    transition: color 0.3s;
}
.panel-logout:hover { color: var(--error); }

/* ─── CHARACTER CARDS ───────────────────────────────────────── */
.chars-list { display: flex; flex-direction: column; gap: 0.85rem; }

.char-card {
    display: flex; align-items: center; gap: 1rem;
    padding: 0.9rem 1rem;
    background: rgba(9,12,34,0.6);
    border: 1px solid rgba(136,144,255,0.08);
    transition: border-color 0.3s, background 0.3s, transform 0.2s;
    position: relative; overflow: hidden;
}
.char-card:hover {
    border-color: rgba(136,144,255,0.22);
    background: rgba(12,16,44,0.7);
    transform: translateX(3px);
}
.char-card::before {
    content: '';
    position: absolute; left: 0; top: 0; bottom: 0;
    width: 2px; background: var(--class-color, var(--arcane-bright));
    opacity: 0.7;
}

.char-icon {
    width: 42px; height: 42px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem; flex-shrink: 0;
    border: 1px solid; background: rgba(9,12,34,0.8);
}

.char-info { flex: 1; min-width: 0; }
.char-name { font-family: 'Cinzel', serif; font-size: 0.85rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.char-meta { font-size: 0.75rem; color: var(--silver); opacity: 0.7; margin-top: 0.15rem; display: flex; gap: 0.5rem; flex-wrap: wrap; }
.char-money { font-size: 0.72rem; margin-top: 0.2rem; }
.char-ingame {
    font-size: 0.55rem; font-family: 'Cinzel', serif; letter-spacing: 0.12em;
    color: var(--success); margin-left: 0.4rem; vertical-align: middle;
    animation: dotBlink 2s infinite;
}
@keyframes dotBlink { 0%,100%{opacity:1} 50%{opacity:.4} }

.char-right { text-align: center; flex-shrink: 0; }
.char-level { font-family: 'Cinzel Decorative', serif; font-size: 1.3rem; font-weight: 700; color: var(--white); line-height: 1; }
.char-level-lbl { font-size: 0.55rem; letter-spacing: 0.15em; color: var(--silver); opacity: 0.5; text-transform: uppercase; font-family: 'Cinzel', serif; }
.char-time { font-size: 0.65rem; color: var(--silver); opacity: 0.5; margin-top: 0.2rem; }

.empty-chars {
    text-align: center; padding: 2.5rem 1rem;
    font-family: 'Cinzel', serif; font-size: 0.7rem;
    letter-spacing: 0.15em; color: var(--silver); opacity: 0.5;
}
.empty-chars-icon { font-size: 2.5rem; margin-bottom: 1rem; display: block; filter: grayscale(1); }

/* ─── SERVER STATUS ─────────────────────────────────────────── */
.server-status {
    display: flex; align-items: center; gap: 0.8rem; margin-bottom: 1.2rem;
}
.status-indicator {
    width: 10px; height: 10px; border-radius: 50%;
    flex-shrink: 0;
}
.status-indicator.online {
    background: var(--success);
    box-shadow: 0 0 10px rgba(95,255,176,0.8);
    animation: dotBlink 2s infinite;
}
.status-indicator.offline {
    background: rgba(168,180,208,0.3);
}
.status-text { font-family: 'Cinzel', serif; font-size: 0.72rem; letter-spacing: 0.12em; }
.status-text.online { color: var(--success); }
.status-text.offline { color: var(--silver); opacity: 0.5; }
.status-realm { font-size: 0.8rem; color: var(--silver); margin-left: auto; opacity: 0.6; }

.server-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; }
.stat-box {
    background: rgba(9,12,34,0.6);
    border: 1px solid rgba(136,144,255,0.08);
    padding: 0.9rem; text-align: center;
}
.stat-num {
    font-family: 'Cinzel Decorative', serif; font-size: 1.4rem; font-weight: 700;
    color: var(--white); line-height: 1;
    text-shadow: 0 0 20px rgba(136,144,255,0.3);
}
.stat-desc { font-size: 0.6rem; color: var(--silver); opacity: 0.55; margin-top: 0.3rem; letter-spacing: 0.1em; font-family: 'Cinzel', serif; text-transform: uppercase; }

/* ─── ACCOUNT MANAGEMENT TABS ───────────────────────────────── */
.mgmt-tabs { display: flex; gap: 0; border-bottom: 1px solid rgba(136,144,255,0.1); margin-bottom: 1.8rem; }
.mgmt-tab {
    font-family: 'Cinzel', serif; font-size: 0.62rem; letter-spacing: 0.14em;
    text-transform: uppercase; padding: 0.7rem 1.2rem;
    background: none; border: none; cursor: pointer; color: var(--silver);
    border-bottom: 2px solid transparent; margin-bottom: -1px;
    transition: color 0.3s, border-color 0.3s;
}
.mgmt-tab:hover { color: var(--arcane-bright); }
.mgmt-tab.active { color: var(--gold-bright); border-bottom-color: var(--gold-bright); }

.mgmt-panel { display: none; max-width: 440px; }
.mgmt-panel.active { display: block; }

.form-group { margin-bottom: 1.2rem; }
.form-label {
    display: block; font-family: 'Cinzel', serif; font-size: 0.58rem;
    letter-spacing: 0.2em; text-transform: uppercase; color: var(--silver); margin-bottom: 0.4rem;
}
.input-wrap { position: relative; }
.input-icon { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); pointer-events: none; z-index: 2; }
.input-wrap input {
    width: 100%; background: rgba(9,12,34,0.8); border: 1px solid rgba(136,144,255,0.18);
    color: var(--white); font-family: 'Crimson Pro', serif; font-size: 0.95rem;
    padding: 0.7rem 0.9rem 0.7rem 2.4rem; outline: none;
    transition: border-color 0.3s, box-shadow 0.3s;
    clip-path: polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%);
}
.input-wrap input::placeholder { color: rgba(168,180,208,0.35); }
.input-wrap input:focus { border-color: rgba(136,144,255,0.5); box-shadow: 0 0 16px rgba(136,144,255,0.07); }
.input-wrap::after {
    content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(136,144,255,0.5), transparent);
    opacity: 0; transition: opacity 0.3s;
}
.input-wrap:focus-within::after { opacity: 1; }

.btn-primary {
    font-family: 'Cinzel', serif; font-size: 0.65rem; letter-spacing: 0.18em;
    text-transform: uppercase; padding: 0.75rem 1.6rem; border: none; cursor: pointer;
    background: linear-gradient(135deg, #9a6418, #d4a030, #f0c060, #d4a030, #9a6418);
    color: #1a0e00; font-weight: 800;
    clip-path: polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%);
    transition: box-shadow 0.3s, transform 0.2s;
    box-shadow: 0 2px 20px rgba(200,151,42,0.35);
}
.btn-primary:hover { box-shadow: 0 4px 32px rgba(200,151,42,0.6); transform: translateY(-2px); }

.btn-arcane-form {
    font-family: 'Cinzel', serif; font-size: 0.65rem; letter-spacing: 0.18em;
    text-transform: uppercase; padding: 0.75rem 1.6rem; border: none; cursor: pointer;
    background: linear-gradient(135deg, var(--arcane), var(--void-purple));
    color: var(--white);
    clip-path: polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%);
    transition: box-shadow 0.3s, transform 0.2s;
    box-shadow: 0 2px 20px rgba(90,48,212,0.4);
}
.btn-arcane-form:hover { box-shadow: 0 4px 32px rgba(160,112,255,0.6); transform: translateY(-2px); }

.col-span-full { grid-column: 1 / -1; }

/* ─── CURRENCY PANEL ────────────────────────────────────────── */
.currency-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}
.currency-card {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 1.4rem 1rem;
    position: relative; overflow: hidden;
    text-align: center;
}
.currency-card-dp {
    background: linear-gradient(135deg, rgba(20,24,80,0.6), rgba(90,48,212,0.2));
    border: 1px solid rgba(136,144,255,0.22);
}
.currency-card-vp {
    background: linear-gradient(135deg, rgba(40,20,10,0.6), rgba(200,151,42,0.18));
    border: 1px solid rgba(240,192,96,0.22);
}
.currency-icon { font-size: 1.8rem; margin-bottom: 0.5rem; line-height: 1; }
.currency-amount {
    font-family: 'Cinzel Decorative', serif;
    font-size: 1.7rem; font-weight: 700; line-height: 1;
}
.currency-card-dp .currency-amount { color: var(--arcane-bright); text-shadow: 0 0 20px rgba(136,144,255,0.5); }
.currency-card-vp .currency-amount { color: var(--gold-bright);   text-shadow: 0 0 20px rgba(240,192,96,0.5); }
.currency-label {
    font-family: 'Cinzel', serif; font-size: 0.55rem;
    letter-spacing: 0.2em; text-transform: uppercase;
    color: var(--silver); opacity: 0.7; margin-top: 0.35rem;
}
.currency-desc {
    font-family: 'Crimson Pro', serif; font-size: 0.8rem;
    color: var(--silver); opacity: 0.55; margin-top: 0.3rem;
    font-style: italic;
}

/* ─── ERROR LIST ────────────────────────────────────────────── */
.errors-box {
    background: rgba(255,95,95,0.07); border-left: 3px solid rgba(255,95,95,0.6);
    padding: 0.9rem 1.2rem; margin-bottom: 1.5rem; color: #ff9999; font-size: 0.88rem;
}
.errors-box ul { margin: 0; padding-left: 1.2rem; }

@media (max-width: 1024px) { .dash-grid { grid-template-columns: 1fr; } .char-card { transform: none !important; } }
@media (max-width: 600px) { .dashboard { padding: 4.5rem 1rem 3rem; } .panel-body { padding: 1.2rem; } }
</style>

<?php if ($flashMsg): ?>
<div class="flash-banner"><?= htmlspecialchars($flashMsg) ?></div>
<?php endif; ?>

<main class="dashboard">
    <div class="dash-header reveal">
        <div>
            <p class="dash-breadcrumb"><a href="index.php">Eons</a> &nbsp;›&nbsp; Tableau de Bord</p>
            <h1 class="dash-greeting">Bienvenue, <span><?= htmlspecialchars($account['username'] ?? '') ?></span></h1>
        </div>
        <a href="logout.php" class="btn-logout-nav" style="font-family:'Cinzel',serif;font-size:.6rem;letter-spacing:.14em;color:rgba(168,180,208,.45);text-decoration:none;text-transform:uppercase;transition:color .3s;">⚔ Déconnexion</a>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="errors-box"><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <div class="dash-grid">

        <!-- ACCOUNT PANEL -->
        <div class="panel reveal" style="grid-row: span 2;">
            <div class="panel-header">
                <span class="panel-icon">👤</span>
                <span class="panel-title">Mon <span>Compte</span></span>
            </div>
            <div class="panel-body" style="text-align:center;">
                <div class="account-avatar">⚔</div>
                <p class="account-name"><?= htmlspecialchars($account['username'] ?? '') ?></p>
                <p class="account-email"><?= htmlspecialchars($account['email'] ?? '') ?></p>

                <div class="badges">
                    <span class="badge badge-expansion">✦ <?= $expansionNames[(int)($account['expansion'] ?? 2)] ?? 'WotLK' ?></span>
                    <?php if ((int)($account['online'] ?? 0) === 1): ?>
                    <span class="badge badge-online">● En ligne</span>
                    <?php else: ?>
                    <span class="badge badge-offline">● Hors ligne</span>
                    <?php endif; ?>
                </div>

                <div class="info-sep"></div>

                <ul class="info-list" style="text-align:left;">
                    <li><span class="info-label">Inscription</span><span class="info-value"><?= $account['joindate'] ? date('d/m/Y', strtotime($account['joindate'])) : '—' ?></span></li>
                    <li><span class="info-label">Dernière connexion</span><span class="info-value"><?= $account['last_login'] ? date('d/m/Y H:i', strtotime($account['last_login'])) : '—' ?></span></li>
                    <li><span class="info-label">Dernière IP</span><span class="info-value"><?= htmlspecialchars($account['last_ip'] ?? '—') ?></span></li>
                    <li><span class="info-label">Personnages</span><span class="info-value gold"><?= count($characters) ?></span></li>
                    <?php if ((int)($account['mutetime'] ?? 0) > time()): ?>
                    <li><span class="info-label">Muet jusqu'au</span><span class="info-value" style="color:var(--error)"><?= date('d/m/Y', $account['mutetime']) ?></span></li>
                    <?php endif; ?>
                </ul>

                <a href="logout.php" class="panel-logout">⚔ Déconnexion</a>
            </div>
        </div>

        <!-- CHARACTERS PANEL -->
        <div class="panel panel-arcane reveal" style="grid-row: span 2;">
            <div class="panel-header">
                <span class="panel-icon">🧙</span>
                <span class="panel-title">Mes <span>Héros</span></span>
            </div>
            <div class="panel-body">
                <?php if (empty($characters)): ?>
                <div class="empty-chars">
                    <span class="empty-chars-icon">⚔</span>
                    <p>Aucun héros créé</p>
                    <p style="margin-top:.5rem;font-style:italic;opacity:.7;font-family:'Crimson Pro',serif;font-size:.88rem;">Connectez-vous au jeu pour créer votre premier personnage.</p>
                </div>
                <?php else: ?>
                <div class="chars-list">
                    <?php foreach ($characters as $char):
                        $classId = (int)$char['class'];
                        $color   = $classColors[$classId] ?? '#a8b4d0';
                        $icon    = $classIcons[$classId]  ?? '⚔';
                        $cls     = $classNames[$classId]  ?? 'Inconnu';
                        $race    = $raceNames[(int)$char['race']] ?? 'Inconnu';
                    ?>
                    <div class="char-card" style="--class-color:<?= $color ?>">
                        <div class="char-icon" style="background:<?= $color ?>14;border-color:<?= $color ?>40;color:<?= $color ?>"><?= $icon ?></div>
                        <div class="char-info">
                            <div class="char-name" style="color:<?= $color ?>">
                                <?= htmlspecialchars($char['name']) ?>
                                <?php if (!empty($char['online'])): ?><span class="char-ingame">● EN JEU</span><?php endif; ?>
                            </div>
                            <div class="char-meta">
                                <span><?= $race ?></span>
                                <span style="color:<?= $color ?>"><?= $cls ?></span>
                                <?php if (!empty($char['zone'])): ?>
								<span>· <?= htmlspecialchars($zoneNames[$char['zone']] ?? 'Zone ' . (int)$char['zone']) ?></span>
							<?php endif; ?>
                            </div>
                            <?php if (!empty($char['money'])): ?><div class="char-money"><?= formatMoney((int)$char['money']) ?></div><?php endif; ?>
                        </div>
                        <div class="char-right">
                            <div class="char-level"><?= (int)$char['level'] ?></div>
                            <div class="char-level-lbl">Niv.</div>
                            <?php if (!empty($char['totaltime'])): ?><div class="char-time"><?= formatTime((int)$char['totaltime']) ?></div><?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- SERVER STATUS -->
        <div class="panel reveal">
            <div class="panel-header">
                <span class="panel-icon">🌍</span>
                <span class="panel-title">Statut du <span>Serveur</span></span>
            </div>
            <div class="panel-body">
                <div class="server-status">
                    <div class="status-indicator <?= $realmOnline ? 'online' : 'offline' ?>"></div>
                    <span class="status-text <?= $realmOnline ? 'online' : 'offline' ?>"><?= $realmOnline ? 'En ligne' : 'Hors ligne' ?></span>
                    <span class="status-realm"><?= htmlspecialchars($realmName) ?></span>
                </div>
                <div class="server-stats">
                    <div class="stat-box">
                        <div class="stat-num"><?= $realmOnline ? $realmPlayers : '—' ?></div>
                        <div class="stat-desc">Joueurs connectés</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-num">3.3.5</div>
                        <div class="stat-desc">Version client</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CURRENCY PANEL -->
        <div class="panel reveal">
            <div class="panel-header">
                <span class="panel-icon">💎</span>
                <span class="panel-title">Mon <span>Solde</span></span>
            </div>
            <div class="panel-body">
                <div class="currency-grid">
                    <div class="currency-card currency-card-dp">
                        <div class="currency-icon">💠</div>
                        <div class="currency-amount"><?= number_format((int)($account['dp'] ?? 0)) ?></div>
                        <div class="currency-label">Donation Points</div>
                        <div class="currency-desc">Points de donation</div>
                    </div>
                    <div class="currency-card currency-card-vp">
                        <div class="currency-icon">⭐</div>
                        <div class="currency-amount"><?= number_format((int)($account['vp'] ?? 0)) ?></div>
                        <div class="currency-label">Vote Points</div>
                        <div class="currency-desc">Points de vote</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ACCOUNT MANAGEMENT -->
        <div class="panel col-span-full reveal">
            <div class="panel-header">
                <span class="panel-icon">🔮</span>
                <span class="panel-title">Gestion du <span>Compte</span></span>
            </div>
            <div class="panel-body">
                <div class="mgmt-tabs">
                    <button class="mgmt-tab active" onclick="switchTab('password', this)">🔑 Mot de passe</button>
                    <button class="mgmt-tab" onclick="switchTab('email', this)">✉ Adresse e-mail</button>
                </div>

                <!-- Password tab -->
                <div id="tab-password" class="mgmt-panel active">
                    <form method="POST" action="dashboard.php" autocomplete="off" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="change_password">
                        <div class="form-group"><label class="form-label">Mot de passe actuel</label><div class="input-wrap"><span class="input-icon">🔒</span><input type="password" name="current_password" placeholder="Votre mot de passe actuel" required></div></div>
                        <div class="form-group"><label class="form-label">Nouveau mot de passe</label><div class="input-wrap"><span class="input-icon">🔮</span><input type="password" name="new_password" placeholder="Minimum 6 caractères" minlength="6" required></div></div>
                        <div class="form-group"><label class="form-label">Confirmer le mot de passe</label><div class="input-wrap"><span class="input-icon">🔮</span><input type="password" name="confirm_password" placeholder="Répétez le nouveau mot de passe" required></div></div>
                        <button type="submit" class="btn-primary">⚔ &nbsp; Changer le mot de passe</button>
                    </form>
                </div>

                <!-- Email tab -->
                <div id="tab-email" class="mgmt-panel">
                    <form method="POST" action="dashboard.php" autocomplete="off" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="change_email">
                        <div class="form-group"><label class="form-label">Email actuel</label><p style="font-size:.88rem;color:var(--silver);font-style:italic;padding:.3rem 0;"><?= htmlspecialchars($account['email'] ?? '—') ?></p></div>
                        <div class="form-group"><label class="form-label">Nouvel e-mail</label><div class="input-wrap"><span class="input-icon">✉</span><input type="email" name="new_email" placeholder="nouveau@email.com" required></div></div>
                        <div class="form-group"><label class="form-label">Confirmez votre mot de passe</label><div class="input-wrap"><span class="input-icon">🔒</span><input type="password" name="email_password" placeholder="Votre mot de passe actuel" required></div></div>
                        <button type="submit" class="btn-arcane-form">✦ &nbsp; Mettre à jour l'e-mail</button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
function switchTab(name, btn) {
    document.querySelectorAll('.mgmt-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.mgmt-tab').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}
</script>
</body>
</html>
