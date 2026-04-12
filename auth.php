<?php
// ============================================================
//  auth.php — Eons CMS | Arcanic Theme Enhanced
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/srp6.php';

if (!empty($_SESSION['account_id'])) { header('Location: dashboard.php'); exit; }

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrfToken  = $_SESSION['csrf_token'];
$loginError = '';
$regErrors  = [];
$regSuccess = false;
$activeTab  = 'login';

// ── LOGIN ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'login') {
    $activeTab = 'login';
    $username  = trim($_POST['username'] ?? '');
    $password  = $_POST['password']      ?? '';
    $csrf      = $_POST['csrf_token']    ?? '';
    $remember  = !empty($_POST['remember']);

    if (!hash_equals($_SESSION['csrf_token'], $csrf)) $loginError = 'Token CSRF invalide. Rechargez la page.';

    if (!$loginError) {
        $_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? 0;
        $_SESSION['login_last']     = $_SESSION['login_last']     ?? 0;
        if ($_SESSION['login_attempts'] >= 5) {
            $wait = 60 - (time() - $_SESSION['login_last']);
            if ($wait > 0) $loginError = "Trop de tentatives. Réessayez dans {$wait}s.";
            else $_SESSION['login_attempts'] = 0;
        }
    }

    if (!$loginError && $username && $password) {
        try {
            $db   = getAuthDB();
            $stmt = $db->prepare("SELECT id,username,salt,verifier,email,locked,expansion,failed_logins FROM account WHERE UPPER(username)=UPPER(:u) LIMIT 1");
            $stmt->execute([':u' => $username]);
            $account = $stmt->fetch();
            $valid = false;
            if ($account) {
                if ((int)$account['locked'] === 1) $loginError = "Ce compte est verrouillé. Contactez l'administration.";
                else $valid = SRP6::verifyPassword($account['username'], $password, $account['salt'], $account['verifier']);
            }
            if (!$loginError && !$valid) {
                $_SESSION['login_attempts']++;
                $_SESSION['login_last'] = time();
                if ($account) { $upd = $db->prepare("UPDATE account SET failed_logins=failed_logins+1,last_attempt_ip=:ip WHERE id=:id"); $upd->execute([':ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', ':id' => $account['id']]); }
                $loginError = "Nom de compte ou mot de passe incorrect.";
            }
            if (!$loginError && $valid && $account) {
                $_SESSION['login_attempts'] = 0;
                session_regenerate_id(true);
                $_SESSION['account_id']    = $account['id'];
                $_SESSION['account_name']  = $account['username'];
                $_SESSION['account_email'] = $account['email'];
                $_SESSION['expansion']     = $account['expansion'];
                $_SESSION['logged_in']     = true;
                $_SESSION['login_time']    = time();
                $upd = $db->prepare("UPDATE account SET last_login=NOW(),last_ip=:ip,failed_logins=0 WHERE id=:id");
                $upd->execute([':ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', ':id' => $account['id']]);
                if ($remember) setcookie('au_remember', base64_encode($account['id'].':'.bin2hex(random_bytes(32))), ['expires'=>time()+86400*30,'path'=>'/','httponly'=>true,'samesite'=>'Strict']);
                header('Location: dashboard.php'); exit;
            }
        } catch (PDOException $e) { $loginError = "Erreur de connexion à la base de données."; error_log('[AU Login] '.$e->getMessage()); }
    } elseif (!$loginError) { $loginError = "Veuillez renseigner tous les champs."; }
}

// ── REGISTER ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'register') {
    $activeTab = 'register';
    $username  = trim($_POST['username']  ?? '');
    $email     = trim($_POST['email']     ?? '');
    $password  = $_POST['password']       ?? '';
    $password2 = $_POST['password2']      ?? '';
    $csrf      = $_POST['csrf_token']     ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrf)) $regErrors[] = 'Token CSRF invalide.';
    if (!$regErrors) {
        if (strlen($username) < 3 || strlen($username) > 16) $regErrors[] = "Le nom doit contenir entre 3 et 16 caractères.";
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username))     $regErrors[] = "Lettres, chiffres et underscores uniquement.";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))       $regErrors[] = "E-mail invalide.";
        if (strlen($password) < 6)                            $regErrors[] = "Mot de passe : 6 caractères minimum.";
        if ($password !== $password2)                         $regErrors[] = "Les mots de passe ne correspondent pas.";
    }
    if (!$regErrors) {
        try {
            $db = getAuthDB();
            $s  = $db->prepare("SELECT id FROM account WHERE UPPER(username)=UPPER(:u) LIMIT 1"); $s->execute([':u'=>$username]); if ($s->fetch()) $regErrors[] = "Ce nom de compte est déjà utilisé.";
            $s  = $db->prepare("SELECT id FROM account WHERE email=:e LIMIT 1"); $s->execute([':e'=>strtolower($email)]); if ($s->fetch()) $regErrors[] = "Cette adresse e-mail est déjà utilisée.";
        } catch (PDOException $e) { $regErrors[] = "Erreur de base de données."; error_log('[AU Reg check] '.$e->getMessage()); }
    }
    if (!$regErrors) {
        try {
            $db=$getAuthDB(); $saltBytes=SRP6::generateSalt(); $srp=SRP6::calcVerifier($username,$password,$saltBytes);
            $stmt=$db->prepare("INSERT INTO account (username,salt,verifier,email,reg_mail,joindate,last_ip,last_attempt_ip,failed_logins,locked,lock_country,online,expansion,mutetime,mutereason,muteby,locale,os,recruiter,timezone_offset) VALUES (UPPER(:username),:salt,:verifier,:email,:reg_mail,NOW(),'127.0.0.1','127.0.0.1',0,0,'00',0,2,0,'','',0,'',0,0)");
            $stmt->execute([':username'=>strtoupper($username),':salt'=>$srp['salt'],':verifier'=>$srp['verifier'],':email'=>strtolower($email),':reg_mail'=>strtolower($email)]);
            $regSuccess=true; $_SESSION['csrf_token']=bin2hex(random_bytes(32)); $csrfToken=$_SESSION['csrf_token'];
        } catch (PDOException $e) { $regErrors[]="Erreur lors de la création du compte."; error_log('[AU Reg insert] '.$e->getMessage()); }
    }
}

$flashMessage = '';
if (!empty($_SESSION['flash'])) { $flashMessage = $_SESSION['flash']; unset($_SESSION['flash']); }

$pageTitle = 'Connexion — Eons';
require_once __DIR__ . '/header.php';
?>
<style>
body { overflow-x: hidden; }

/* ─── LAYOUT ───────────────────────────────────────────────── */
.auth-page {
    position: relative; z-index: 10;
    display: flex; align-items: flex-start; justify-content: center;
    min-height: calc(100vh - 62px);
    margin-top: 62px;
    padding: 2rem 1.5rem;
    overflow-y: auto;
}

.auth-page::before {
    content: '';
    position: absolute; inset: 0; pointer-events: none;
    background:
        radial-gradient(ellipse 50% 70% at 25% 50%, rgba(20,24,80,.28) 0%, transparent 60%),
        radial-gradient(ellipse 50% 70% at 75% 50%, rgba(90,48,212,.18) 0%, transparent 60%);
}

/* ─── WRAPPER 2 PANELS ─────────────────────────────────────── */
.auth-wrapper {
    position: relative;
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 0;
    width: 100%;
    max-width: 960px;
    background: rgba(6,8,26,0.82);
    backdrop-filter: blur(24px);
    /* Bordure + coins via box-shadow pour éviter le bug Chrome ::before/::after + backdrop-filter */
    box-shadow:
        /* Bordure principale */
        0 0 0 1px rgba(136,144,255,0.15),
        /* Coin haut-gauche doré */
        -1px -1px 0 0 var(--gold-bright),
        0 0 15px rgba(240,192,96,0.4),
        /* Coin bas-droit arcane */
        1px 1px 0 0 var(--arcane-bright),
        0 0 15px rgba(136,144,255,0.4);
}

/* Traits des coins via outline partiel avec clip-path sur pseudo-elements */
.auth-wrapper::before {
    content: '';
    position: absolute;
    top: 0; left: 0;
    width: 28px; height: 28px;
    border-top: 2px solid var(--gold-bright);
    border-left: 2px solid var(--gold-bright);
    filter: drop-shadow(0 0 6px rgba(240,192,96,0.7));
    pointer-events: none;
    z-index: 2;
}
.auth-wrapper::after {
    content: '';
    position: absolute;
    bottom: 0; right: 0;
    width: 28px; height: 28px;
    border-bottom: 2px solid var(--arcane-bright);
    border-right: 2px solid var(--arcane-bright);
    filter: drop-shadow(0 0 6px rgba(136,144,255,0.7));
    pointer-events: none;
    z-index: 2;
}

/* ─── PANEL ────────────────────────────────────────────────── */
.auth-panel {
    padding: 2.8rem 2.5rem;
    position: relative;
    overflow: hidden;
}
.auth-panel::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at top center, rgba(136,144,255,0.04) 0%, transparent 60%);
    pointer-events: none;
}

/* ─── SEPARATOR ────────────────────────────────────────────── */
.auth-sep {
    width: 1px;
    background: linear-gradient(180deg, transparent, rgba(136,144,255,0.2) 20%, rgba(240,192,96,0.15) 50%, rgba(136,144,255,0.2) 80%, transparent);
    margin: 2rem 0;
    position: relative;
    flex-shrink: 0;
}
.auth-sep::before {
    content: '✦';
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(6,8,26,0.9);
    color: rgba(136,144,255,0.5);
    font-size: 0.7rem;
    padding: 0.4rem 0;
    letter-spacing: 0;
}

/* ─── CARD HEADER ──────────────────────────────────────────── */
.card-icon {
    font-size: 2rem;
    display: block;
    margin-bottom: 0.8rem;
    filter: drop-shadow(0 0 12px rgba(136,144,255,0.6));
}
.card-title {
    font-family: 'Cinzel Decorative', serif;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--white);
    margin-bottom: 0.3rem;
}
.card-subtitle {
    font-size: 0.9rem;
    color: var(--silver);
    font-style: italic;
    margin-bottom: 1.5rem;
}

/* ─── DIVIDER ──────────────────────────────────────────────── */
.auth-divider {
    display: flex; align-items: center; gap: 0.8rem; margin-bottom: 1.8rem;
}
.auth-divider::before, .auth-divider::after {
    content: ''; flex: 1; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(240,192,96,0.35));
}
.auth-divider::after { transform: scaleX(-1); }
.divider-gem {
    width: 7px; height: 7px;
    background: var(--gold);
    transform: rotate(45deg);
    box-shadow: 0 0 10px rgba(240,192,96,0.7);
}
.divider-gem-arcane {
    background: var(--arcane-bright);
    box-shadow: 0 0 10px rgba(136,144,255,0.7);
}
.auth-divider-arcane::before, .auth-divider-arcane::after {
    background: linear-gradient(90deg, transparent, rgba(136,144,255,0.3));
}

/* ─── ALERT MESSAGES ───────────────────────────────────────── */
.alert {
    padding: 0.85rem 1.1rem;
    border-left: 3px solid;
    margin-bottom: 1.4rem;
    font-size: 0.88rem;
    line-height: 1.5;
}
.alert-error {
    background: rgba(255,95,95,0.08);
    border-color: rgba(255,95,95,0.6);
    color: #ff9999;
}
.alert-error ul { margin: 0; padding-left: 1.2rem; }
.alert-success {
    background: rgba(95,255,176,0.08);
    border-color: rgba(95,255,176,0.6);
    color: var(--success);
}
.alert-success a { color: var(--gold-bright); }

/* ─── FORM ─────────────────────────────────────────────────── */
.form-group { margin-bottom: 1.2rem; }
.form-group label {
    display: block;
    font-family: 'Cinzel', serif;
    font-size: 0.58rem;
    letter-spacing: 0.2em;
    color: var(--silver);
    text-transform: uppercase;
    margin-bottom: 0.45rem;
}
.input-wrap {
    position: relative;
}
.input-icon {
    position: absolute;
    left: 0.9rem; top: 50%;
    transform: translateY(-50%);
    font-size: 0.9rem;
    pointer-events: none;
    z-index: 2;
    filter: grayscale(0.3);
}
.input-wrap input {
    width: 100%;
    background: rgba(9,12,34,0.8);
    border: 1px solid rgba(136,144,255,0.18);
    color: var(--white);
    font-family: 'Crimson Pro', serif;
    font-size: 0.95rem;
    padding: 0.7rem 0.9rem 0.7rem 2.4rem;
    outline: none;
    transition: border-color 0.3s, box-shadow 0.3s;
    clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%);
}
.input-wrap input::placeholder { color: rgba(168,180,208,0.4); }
.input-wrap input:focus {
    border-color: rgba(136,144,255,0.55);
    box-shadow: 0 0 0 2px rgba(136,144,255,0.1), 0 0 20px rgba(136,144,255,0.08);
    background: rgba(12,16,44,0.9);
}
.input-wrap input:focus ~ .input-glow { opacity: 1; }

/* Input glow line at bottom */
.input-wrap::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(136,144,255,0.6), transparent);
    opacity: 0;
    transition: opacity 0.3s;
}
.input-wrap:focus-within::after { opacity: 1; }

/* ─── PASSWORD STRENGTH ────────────────────────────────────── */
.pw-strength-bar {
    height: 2px; background: rgba(136,144,255,0.1);
    margin-top: 0.4rem; position: relative; overflow: hidden;
}
.pw-strength-fill {
    height: 100%; width: 0%;
    transition: width 0.4s, background 0.4s;
    background: var(--arcane-bright);
}
.pw-strength-label {
    font-size: 0.7rem; color: var(--silver); margin-top: 0.3rem;
    font-family: 'Cinzel', serif; letter-spacing: 0.1em;
    min-height: 1rem;
}

/* ─── REMEMBER / TERMS ─────────────────────────────────────── */
.row-options {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 1.5rem; gap: 0.5rem; flex-wrap: wrap;
}
.check-wrap {
    display: flex; align-items: center; gap: 0.5rem;
}
.check-wrap input[type="checkbox"] { accent-color: var(--arcane-bright); }
.check-wrap label, .terms label {
    font-size: 0.78rem; color: var(--silver); cursor: pointer;
    letter-spacing: 0.02em;
}
.check-wrap label a, .terms label a { color: var(--arcane-bright); text-decoration: none; }
.terms { display: flex; align-items: flex-start; gap: 0.5rem; margin-bottom: 1.4rem; }
.forgot-link {
    font-family: 'Cinzel', serif; font-size: 0.58rem;
    letter-spacing: 0.1em; color: var(--silver); text-decoration: none;
    text-transform: uppercase; transition: color 0.3s;
    white-space: nowrap;
}
.forgot-link:hover { color: var(--arcane-bright); }

/* ─── SUBMIT BUTTONS ───────────────────────────────────────── */
.btn-submit {
    width: 100%; padding: 0.9rem;
    font-family: 'Cinzel', serif;
    font-size: 0.68rem; letter-spacing: 0.2em; font-weight: 700;
    text-transform: uppercase; border: none; cursor: pointer;
    position: relative; overflow: hidden;
    transition: transform 0.2s, box-shadow 0.3s;
}
.btn-submit::before {
    content: '';
    position: absolute; inset: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.12), transparent);
    transform: translateX(-100%) skewX(-20deg);
    transition: transform 0.5s;
}
.btn-submit:hover::before { transform: translateX(150%) skewX(-20deg); }
.btn-submit:hover { transform: translateY(-2px); }

.btn-submit-gold {
    background: linear-gradient(135deg, #9a6418 0%, #d4a030 35%, #f0c060 50%, #d4a030 65%, #9a6418 100%);
    color: #1a0e00; font-weight: 800;
    clip-path: polygon(10px 0%, 100% 0%, calc(100% - 10px) 100%, 0% 100%);
    box-shadow: 0 2px 24px rgba(200,151,42,0.4);
}
.btn-submit-gold:hover { box-shadow: 0 4px 36px rgba(200,151,42,0.65); }

.btn-submit-arcane {
    background: linear-gradient(135deg, #1a1d5a 0%, #5a30d4 100%);
    color: var(--white);
    clip-path: polygon(10px 0%, 100% 0%, calc(100% - 10px) 100%, 0% 100%);
    box-shadow: 0 2px 24px rgba(90,48,212,0.5);
}
.btn-submit-arcane:hover { box-shadow: 0 4px 36px rgba(160,112,255,0.6); }

/* ─── MOBILE TABS ──────────────────────────────────────────── */
.auth-tabs {
    display: none;
    background: rgba(6,8,26,0.95);
    border-bottom: 1px solid rgba(136,144,255,0.12);
}
.auth-tab-btn {
    flex: 1; padding: 0.9rem;
    font-family: 'Cinzel', serif; font-size: 0.62rem;
    letter-spacing: 0.14em; text-transform: uppercase;
    background: none; border: none; cursor: pointer;
    color: var(--silver); transition: color 0.3s;
    position: relative;
}
.auth-tab-btn::after {
    content: '';
    position: absolute; bottom: 0; left: 20%; right: 20%; height: 1px;
    background: transparent; transition: background 0.3s;
}
.auth-tab-btn.active-gold   { color: var(--gold-bright); }
.auth-tab-btn.active-gold::after { background: var(--gold-bright); box-shadow: 0 0 8px rgba(240,192,96,0.6); }
.auth-tab-btn.active-arcane { color: var(--arcane-bright); }
.auth-tab-btn.active-arcane::after { background: var(--arcane-bright); box-shadow: 0 0 8px rgba(136,144,255,0.6); }

@media (max-width: 820px) {
    .auth-wrapper { grid-template-columns: 1fr; max-width: 440px; }
    .auth-sep { display: none; }
    .auth-tabs { display: flex; }
    .panel-login, .panel-register { display: none; }
    .panel-login.tab-active, .panel-register.tab-active { display: block; }
}
</style>

<?php if ($flashMessage): ?>
<div class="flash-banner"><?= htmlspecialchars($flashMessage) ?></div>
<?php endif; ?>

<main class="auth-page">
    <!-- Mobile tabs -->
    <div style="display:none" id="mobileTabsWrapper"><!-- injected by JS --></div>

    <div class="auth-wrapper">
        <!-- Mobile tabs bar -->
        <div class="auth-tabs" id="authTabs" style="grid-column:1/-1">
            <button class="auth-tab-btn active-gold" id="tabLogin"    onclick="showTab('login')">⚔ Connexion</button>
            <button class="auth-tab-btn"             id="tabRegister" onclick="showTab('register')">✦ Inscription</button>
        </div>

        <!-- LOGIN PANEL -->
        <div class="auth-panel panel-login tab-active" id="panelLogin">
            <span class="card-icon">⚔</span>
            <h2 class="card-title">Connexion</h2>
            <p class="card-subtitle">Entrez dans le royaume</p>

            <div class="auth-divider"><div class="divider-gem"></div></div>

            <?php if ($loginError): ?>
            <div class="alert alert-error"><?= htmlspecialchars($loginError) ?></div>
            <?php endif; ?>

            <form method="POST" action="auth.php" autocomplete="off" novalidate>
                <input type="hidden" name="form" value="login">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="form-group">
                    <label for="login_username">Nom de compte</label>
                    <div class="input-wrap">
                        <span class="input-icon">⚔</span>
                        <input type="text" id="login_username" name="username"
                               placeholder="Votre nom de compte"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               autocomplete="username" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="login_password">Mot de passe</label>
                    <div class="input-wrap">
                        <span class="input-icon">🔮</span>
                        <input type="password" id="login_password" name="password"
                               placeholder="Votre mot de passe"
                               autocomplete="current-password" required>
                    </div>
                </div>

                <div class="row-options">
                    <div class="check-wrap">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Se souvenir de moi</label>
                    </div>
                    <a href="forgot_password.php" class="forgot-link">Mot de passe oublié ?</a>
                </div>

                <button type="submit" class="btn-submit btn-submit-gold">⚔ &nbsp; Entrer dans le Royaume</button>
            </form>
        </div>

        <!-- SEPARATOR -->
        <div class="auth-sep"></div>

        <!-- REGISTER PANEL -->
        <div class="auth-panel panel-register" id="panelRegister">
            <span class="card-icon">⚗</span>
            <h2 class="card-title">Créer un compte</h2>
            <p class="card-subtitle">Rejoindre la nuit arcanique</p>

            <div class="auth-divider auth-divider-arcane"><div class="divider-gem divider-gem-arcane"></div></div>

            <?php if ($regSuccess): ?>
            <div class="alert alert-success">
                ✦ Compte créé avec succès ! Vous pouvez maintenant <a href="auth.php">vous connecter</a>.
            </div>
            <?php endif; ?>

            <?php if (!empty($regErrors)): ?>
            <div class="alert alert-error">
                <ul><?php foreach ($regErrors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
            </div>
            <?php endif; ?>

            <?php if (!$regSuccess): ?>
            <form method="POST" action="auth.php" autocomplete="off" novalidate>
                <input type="hidden" name="form" value="register">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="form-group">
                    <label for="reg_username">Nom de compte</label>
                    <div class="input-wrap">
                        <span class="input-icon">⚔</span>
                        <input type="text" id="reg_username" name="username"
                               placeholder="ex: Eons" maxlength="16"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="reg_email">Adresse e-mail</label>
                    <div class="input-wrap">
                        <span class="input-icon">✉</span>
                        <input type="email" id="reg_email" name="email"
                               placeholder="votre@email.com"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="reg_password">Mot de passe</label>
                    <div class="input-wrap">
                        <span class="input-icon">🔮</span>
                        <input type="password" id="reg_password" name="password"
                               placeholder="Minimum 6 caractères" minlength="6" required>
                    </div>
                    <div class="pw-strength-bar"><div class="pw-strength-fill" id="strength-fill"></div></div>
                    <p class="pw-strength-label" id="strength-label"></p>
                </div>

                <div class="form-group">
                    <label for="reg_password2">Confirmer le mot de passe</label>
                    <div class="input-wrap">
                        <span class="input-icon">🔮</span>
                        <input type="password" id="reg_password2" name="password2"
                               placeholder="Répétez le mot de passe" required>
                    </div>
                </div>

                <div class="terms">
                    <input type="checkbox" id="terms" name="terms" required>
                    <label for="terms">J'accepte les <a href="#">conditions d'utilisation</a> et la <a href="#">politique de confidentialité</a>.</label>
                </div>

                <button type="submit" class="btn-submit btn-submit-arcane">✦ &nbsp; Rejoindre Eons</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
function showTab(tab) {
    const login    = document.getElementById('panelLogin');
    const register = document.getElementById('panelRegister');
    const tabL     = document.getElementById('tabLogin');
    const tabR     = document.getElementById('tabRegister');
    if (tab === 'login') {
        login.classList.add('tab-active');
        register.classList.remove('tab-active');
        tabL.classList.add('active-gold'); tabL.classList.remove('active-arcane');
        tabR.classList.remove('active-arcane', 'active-gold');
    } else {
        register.classList.add('tab-active');
        login.classList.remove('tab-active');
        tabR.classList.add('active-arcane'); tabR.classList.remove('active-gold');
        tabL.classList.remove('active-gold', 'active-arcane');
    }
}
<?php if ($activeTab === 'register'): ?>
if (window.innerWidth <= 820) showTab('register');
<?php endif; ?>

// Password strength
(function(){
    const pw  = document.getElementById('reg_password');
    const bar = document.getElementById('strength-fill');
    const lbl = document.getElementById('strength-label');
    if (!pw) return;
    const levels = [
        {min:0,  w:'0%',   c:'#ff5f5f', t:''},
        {min:1,  w:'20%',  c:'#ff5f5f', t:'Très faible'},
        {min:4,  w:'42%',  c:'#ff8800', t:'Faible'},
        {min:8,  w:'62%',  c:'#f0c060', t:'Correct'},
        {min:12, w:'82%',  c:'#8890ff', t:'Bon'},
        {min:16, w:'100%', c:'#5fffb0', t:'Fort'},
    ];
    pw.addEventListener('input', () => {
        const v = pw.value;
        let score = 0;
        if (v.length >= 6) score += v.length;
        if (/[A-Z]/.test(v)) score += 3;
        if (/[0-9]/.test(v)) score += 3;
        if (/[^a-zA-Z0-9]/.test(v)) score += 5;
        let lvl = levels[0];
        for (const l of levels) if (score >= l.min) lvl = l;
        bar.style.width = lvl.w;
        bar.style.background = lvl.c;
        lbl.textContent = lvl.t;
        lbl.style.color = lvl.c;
    });
})();
</script>
</body>
</html>
