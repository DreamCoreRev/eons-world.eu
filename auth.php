<?php
// ============================================================
//  auth.php — Eons CMS
//  Connexion + Inscription sur une seule page, sans scroll
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/srp6.php';

if (!empty($_SESSION['account_id'])) {
    header('Location: dashboard.php');
    exit;
}

// ── Init CSRF ─────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$loginError   = '';
$regErrors    = [];
$regSuccess   = false;
$activeTab    = 'login'; // panneau affiché par défaut

// ── Traitement LOGIN ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form']) && $_POST['form'] === 'login') {
    $activeTab = 'login';
    $username  = trim($_POST['username'] ?? '');
    $password  = $_POST['password']      ?? '';
    $csrf      = $_POST['csrf_token']    ?? '';
    $remember  = !empty($_POST['remember']);

    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        $loginError = 'Token CSRF invalide. Rechargez la page.';
    }

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
                if ((int)$account['locked'] === 1) {
                    $loginError = "Ce compte est verrouillé. Contactez l'administration.";
                } else {
                    $valid = SRP6::verifyPassword($account['username'], $password, $account['salt'], $account['verifier']);
                }
            }

            if (!$loginError && !$valid) {
                $_SESSION['login_attempts']++;
                $_SESSION['login_last'] = time();
                if ($account) {
                    $upd = $db->prepare("UPDATE account SET failed_logins=failed_logins+1,last_attempt_ip=:ip WHERE id=:id");
                    $upd->execute([':ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', ':id' => $account['id']]);
                }
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
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('au_remember', base64_encode($account['id'].':'.$token), ['expires'=>time()+86400*30,'path'=>'/','httponly'=>true,'samesite'=>'Strict']);
                }
                header('Location: dashboard.php');
                exit;
            }
        } catch (PDOException $e) {
            $loginError = "Erreur de connexion à la base de données.";
            error_log('[AU Login] '.$e->getMessage());
        }
    } elseif (!$loginError) {
        $loginError = "Veuillez renseigner tous les champs.";
    }
}

// ── Traitement REGISTER ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form']) && $_POST['form'] === 'register') {
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
            $db   = getAuthDB();
            $stmt = $db->prepare("SELECT id FROM account WHERE UPPER(username)=UPPER(:u) LIMIT 1");
            $stmt->execute([':u' => $username]);
            if ($stmt->fetch()) $regErrors[] = "Ce nom de compte est déjà utilisé.";
            $stmt = $db->prepare("SELECT id FROM account WHERE email=:e LIMIT 1");
            $stmt->execute([':e' => strtolower($email)]);
            if ($stmt->fetch()) $regErrors[] = "Cette adresse e-mail est déjà utilisée.";
        } catch (PDOException $e) {
            $regErrors[] = "Erreur de base de données.";
            error_log('[AU Reg check] '.$e->getMessage());
        }
    }

    if (!$regErrors) {
        try {
            $db        = getAuthDB();
            $saltBytes = SRP6::generateSalt();
            $srp       = SRP6::calcVerifier($username, $password, $saltBytes);
            $stmt = $db->prepare("INSERT INTO account (username,salt,verifier,email,reg_mail,joindate,last_ip,last_attempt_ip,failed_logins,locked,lock_country,online,expansion,mutetime,mutereason,muteby,locale,os,recruiter,timezone_offset) VALUES (UPPER(:username),:salt,:verifier,:email,:reg_mail,NOW(),'127.0.0.1','127.0.0.1',0,0,'00',0,2,0,'','',0,'',0,0)");
            $stmt->execute([':username'=>strtoupper($username),':salt'=>$srp['salt'],':verifier'=>$srp['verifier'],':email'=>strtolower($email),':reg_mail'=>strtolower($email)]);
            $regSuccess = true;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $csrfToken = $_SESSION['csrf_token'];
        } catch (PDOException $e) {
            $regErrors[] = "Erreur lors de la création du compte.";
            error_log('[AU Reg insert] '.$e->getMessage());
        }
    }
}

$flashMessage = '';
if (!empty($_SESSION['flash'])) { $flashMessage = $_SESSION['flash']; unset($_SESSION['flash']); }

$pageTitle = 'Connexion — Eons';
require_once __DIR__ . '/header.php';
?>
<style>
/* ── LAYOUT SANS SCROLL ─────────────────────────────────────── */
body { overflow: hidden; }

main {
    position: relative; z-index: 2;
    display: flex; align-items: center; justify-content: center;
    height: calc(100vh - 60px);
    margin-top: 60px;
    padding: 1rem 1.5rem;
    overflow: hidden;
}

main::before {
    content:''; position:absolute; inset:0; pointer-events:none;
    background:
        radial-gradient(ellipse 55% 70% at 30% 50%, rgba(30,33,96,.3) 0%, transparent 65%),
        radial-gradient(ellipse 55% 70% at 70% 50%, rgba(98,54,212,.2) 0%, transparent 65%);
}

/* ── CONTENEUR 2 COLONNES ───────────────────────────────────── */
.auth-wrapper {
    display: grid;
    grid-template-columns: 1fr 1px 1fr;
    gap: 0;
    width: 100%;
    max-width: 900px;
    align-items: start;
}

/* Séparateur vertical */
.auth-sep {
    align-self: stretch;
    background: linear-gradient(180deg,
        transparent 0%,
        rgba(123,130,255,.2) 20%,
        rgba(200,151,42,.15) 50%,
        rgba(123,130,255,.2) 80%,
        transparent 100%);
    position: relative;
}
.auth-sep::before {
    content: '✦';
    position: absolute; top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    color: var(--gold);
    font-size: .75rem;
    background: var(--midnight);
    padding: .4rem 0;
    text-shadow: 0 0 10px rgba(200,151,42,.6);
}

/* ── PANNEAU ────────────────────────────────────────────────── */
.auth-panel {
    padding: 0 2.5rem;
}

/* ── CARD (sans clip-path sur le wrapper, juste visuel léger) ── */
.card-icon { text-align:center; font-size:1.8rem; margin-bottom:.5rem; filter:drop-shadow(0 0 8px rgba(240,192,96,.5)); }
.card-title { font-family:'Cinzel Decorative',serif; font-weight:700; font-size:1.15rem; text-align:center; color:var(--white); text-shadow:0 0 20px rgba(200,151,42,.25); margin-bottom:.25rem; }
.card-subtitle { font-size:.82rem; text-align:center; color:var(--silver); font-style:italic; margin-bottom:1.2rem; }

.divider { display:flex; align-items:center; gap:.6rem; margin-bottom:1.2rem; }
.divider::before,.divider::after { content:''; flex:1; height:1px; }
.divider-login::before,.divider-login::after  { background:linear-gradient(90deg,transparent,rgba(200,151,42,.3)); }
.divider-login::after  { background:linear-gradient(90deg,rgba(200,151,42,.3),transparent); }
.divider-register::before { background:linear-gradient(90deg,transparent,rgba(123,130,255,.3)); }
.divider-register::after  { background:linear-gradient(90deg,rgba(123,130,255,.3),transparent); }
.divider-gem-gold   { width:5px; height:5px; background:var(--gold); transform:rotate(45deg); box-shadow:0 0 8px rgba(200,151,42,.7); }
.divider-gem-arcane { width:5px; height:5px; background:var(--arcane-bright); transform:rotate(45deg); box-shadow:0 0 8px rgba(123,130,255,.8); }

/* ── FORM ELEMENTS ──────────────────────────────────────────── */
.form-group { margin-bottom:.85rem; }
label { display:block; font-family:'Cinzel',serif; font-size:.6rem; letter-spacing:.16em; text-transform:uppercase; color:var(--silver); margin-bottom:.35rem; }

.input-wrap { position:relative; }
.input-icon { position:absolute; left:.8rem; top:50%; transform:translateY(-50%); font-size:.85rem; pointer-events:none; opacity:.45; }

input[type="text"], input[type="email"], input[type="password"] {
    width:100%; background:rgba(7,9,26,.8);
    border:1px solid rgba(123,130,255,.18); color:var(--white);
    font-family:'Crimson Pro',serif; font-size:.95rem;
    padding:.55rem .9rem .55rem 2.2rem; outline:none;
    transition:border-color .3s, box-shadow .3s;
    clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%);
}
input:focus { border-color:rgba(123,130,255,.5); box-shadow:0 0 12px rgba(123,130,255,.12); }
input::placeholder { color:rgba(168,180,208,.3); }

/* Login inputs teinte dorée */
.panel-login input[type="text"],
.panel-login input[type="password"] {
    border-color:rgba(200,151,42,.18);
}
.panel-login input:focus {
    border-color:rgba(200,151,42,.5);
    box-shadow:0 0 12px rgba(200,151,42,.1);
}

/* ── REMEMBER / FORGOT ──────────────────────────────────────── */
.row-remember { display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem; }
.check-wrap { display:flex; align-items:center; gap:.45rem; }
.check-wrap input[type="checkbox"] { width:12px; height:12px; accent-color:var(--gold); clip-path:none; padding:0; }
.check-wrap label { font-family:'Crimson Pro',serif; font-size:.8rem; letter-spacing:0; text-transform:none; color:var(--silver); cursor:pointer; margin:0; }
.forgot-link { font-size:.75rem; color:var(--gold); text-decoration:none; transition:color .2s; white-space:nowrap; }
.forgot-link:hover { color:var(--arcane-bright); }

/* ── ALERTS ─────────────────────────────────────────────────── */
.alert { padding:.7rem .9rem; margin-bottom:1rem; font-size:.82rem; line-height:1.5; clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%); }
.alert-error   { background:rgba(255,95,95,.08); border:1px solid rgba(255,95,95,.3); color:var(--error); }
.alert-success { background:rgba(95,255,176,.07); border:1px solid rgba(95,255,176,.3); color:var(--success); }
.alert-info    { background:rgba(123,130,255,.07); border:1px solid rgba(123,130,255,.25); color:var(--info); }
.alert ul { padding-left:1.1rem; }
.alert li { margin-bottom:.2rem; }
.alert a  { color:var(--gold-bright); }
.alert small { display:block; margin-top:.25rem; opacity:.7; }

/* ── BOUTONS SUBMIT ─────────────────────────────────────────── */
.btn-submit-gold {
    width:100%; padding:.7rem;
    font-family:'Cinzel',serif; font-size:.7rem; font-weight:700; letter-spacing:.18em; text-transform:uppercase;
    background:linear-gradient(135deg, #b07820 0%, #e8b840 50%, #b07820 100%);
    color:#1a1000; border:none; cursor:pointer;
    clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%);
    box-shadow:0 3px 20px rgba(200,151,42,.3);
    transition:transform .2s, box-shadow .3s; position:relative; overflow:hidden;
}
.btn-submit-gold::before { content:''; position:absolute; inset:0; background:rgba(255,255,255,.12); transform:translateX(-100%) skewX(-15deg); transition:transform .4s ease; }
.btn-submit-gold:hover::before { transform:translateX(120%) skewX(-15deg); }
.btn-submit-gold:hover { transform:translateY(-2px); box-shadow:0 5px 26px rgba(200,151,42,.5); }

.btn-submit-arcane {
    width:100%; padding:.7rem;
    font-family:'Cinzel',serif; font-size:.7rem; font-weight:700; letter-spacing:.18em; text-transform:uppercase;
    background:linear-gradient(135deg, var(--arcane) 0%, var(--void-purple) 100%);
    color:var(--white); border:none; cursor:pointer;
    clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%);
    box-shadow:0 3px 20px rgba(98,54,212,.35);
    transition:transform .2s, box-shadow .3s; position:relative; overflow:hidden;
}
.btn-submit-arcane::before { content:''; position:absolute; inset:0; background:rgba(255,255,255,.07); transform:translateX(-100%) skewX(-15deg); transition:transform .4s ease; }
.btn-submit-arcane:hover::before { transform:translateX(120%) skewX(-15deg); }
.btn-submit-arcane:hover { transform:translateY(-2px); box-shadow:0 5px 28px rgba(155,111,255,.45); }

/* ── FORCE METER ────────────────────────────────────────────── */
.password-strength { margin-top:.3rem; height:2px; background:rgba(123,130,255,.1); overflow:hidden; }
.password-strength-bar { height:100%; width:0%; transition:width .3s, background .3s; background:var(--error); }
.strength-label { font-size:.6rem; letter-spacing:.08em; color:var(--silver); margin-top:.2rem; min-height:.9em; }

/* ── TERMS ──────────────────────────────────────────────────── */
.terms { display:flex; align-items:flex-start; gap:.5rem; margin-bottom:.9rem; }
.terms input[type="checkbox"] { width:13px; height:13px; flex-shrink:0; margin-top:.2rem; accent-color:var(--arcane-glow); clip-path:none; padding:0; }
.terms label { font-family:'Crimson Pro',serif; font-size:.8rem; letter-spacing:0; text-transform:none; color:var(--silver); cursor:pointer; }
.terms label a { color:var(--gold); text-decoration:none; }

/* ── FORM FOOTER ────────────────────────────────────────────── */
.form-footer { text-align:center; margin-top:1rem; font-size:.82rem; color:var(--silver); }
.form-footer a { color:var(--gold-bright); text-decoration:none; }

/* ── ONGLETS MOBILE ─────────────────────────────────────────── */
.mobile-tabs { display:none; }

/* ── RESPONSIVE ─────────────────────────────────────────────── */
@media (max-width: 820px) {
    body { overflow: auto; }

    main {
        height: auto;
        min-height: calc(100vh - 56px);
        margin-top: 56px;
        padding: 1.5rem 1rem 2rem;
        align-items: flex-start;
        overflow: visible;
    }

    .auth-wrapper {
        grid-template-columns: 1fr;
        grid-template-rows: auto auto;
        max-width: 440px;
    }

    .auth-sep { display: none; }

    /* Onglets — en dehors des panneaux, couvrent toute la largeur */
    .mobile-tabs {
        display: flex;
        grid-column: 1 / -1;
        margin-bottom: 1.5rem;
        border-bottom: 1px solid rgba(123,130,255,.15);
    }
    .tab-btn {
        flex: 1; padding: .65rem .5rem;
        font-family: 'Cinzel', serif; font-size: .65rem;
        letter-spacing: .15em; text-transform: uppercase;
        background: transparent; border: none; cursor: pointer;
        color: var(--silver); transition: color .2s;
        position: relative;
    }
    .tab-btn::after {
        content: ''; position: absolute; bottom: -1px; left: 0; right: 0;
        height: 2px; background: transparent; transition: background .2s;
    }
    .tab-btn.active { color: var(--white); }
    .tab-btn.active-login::after  { background: var(--gold); }
    .tab-btn.active-register::after { background: var(--arcane-bright); }

    .auth-panel { padding: 0; }
    .panel-register { display: none; }
    .panel-register.tab-active { display: block; }
    .panel-login.tab-hidden { display: none; }
}
</style>

<main>
    <div class="auth-wrapper">

        <!-- Onglets mobile (en dehors des panneaux) -->
        <div class="mobile-tabs">
            <button class="tab-btn active active-login" id="tabLogin" onclick="showTab('login')">⚔ Connexion</button>
            <button class="tab-btn" id="tabRegister" onclick="showTab('register')">⚗ Inscription</button>
        </div>

        <!-- ══ PANNEAU LOGIN ════════════════════════════════════ -->
        <div class="auth-panel panel-login" id="panelLogin">

            <div class="card-icon">⚔</div>
            <h2 class="card-title">Connexion</h2>
            <p class="card-subtitle">L'aventure vous attend, héros</p>
            <div class="divider divider-login"><div class="divider-gem-gold"></div></div>

            <?php if ($flashMessage): ?>
            <div class="alert alert-info"><?= htmlspecialchars($flashMessage) ?></div>
            <?php endif; ?>

            <?php if ($loginError): ?>
            <div class="alert alert-error">
                ⚠ <?= htmlspecialchars($loginError) ?>
                <?php $rem = max(0, 5 - ($_SESSION['login_attempts'] ?? 0)); if ($rem < 5 && $rem > 0): ?>
                <small><?= $rem ?> tentative(s) restante(s) avant verrouillage.</small>
                <?php endif; ?>
            </div>
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

                <div class="row-remember">
                    <div class="check-wrap">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Se souvenir de moi</label>
                    </div>
                    <a href="forgot_password.php" class="forgot-link">Mot de passe oublié ?</a>
                </div>

                <button type="submit" class="btn-submit-gold">⚔ &nbsp; Entrer dans le Royaume</button>
            </form>
        </div>

        <!-- ── Séparateur vertical ─────────────────────────── -->
        <div class="auth-sep"></div>

        <!-- ══ PANNEAU REGISTER ═════════════════════════════════ -->
        <div class="auth-panel panel-register" id="panelRegister">

            <div class="card-icon">⚗</div>
            <h2 class="card-title">Créer un compte</h2>
            <p class="card-subtitle">Rejoignez la nuit arcanique</p>
            <div class="divider divider-register"><div class="divider-gem-arcane"></div></div>

            <?php if ($regSuccess): ?>
            <div class="alert alert-success">
                ✦ Compte créé ! Vous pouvez maintenant <a href="auth.php">vous connecter</a>.
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
                    <div class="password-strength"><div class="password-strength-bar" id="strength-bar"></div></div>
                    <p class="strength-label" id="strength-label"></p>
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

                <button type="submit" class="btn-submit-arcane">✦ &nbsp; Rejoindre Eons</button>
            </form>
            <?php endif; ?>
        </div>

    </div><!-- /.auth-wrapper -->
</main>

<script>
// ── Onglets mobile ─────────────────────────────────────────────
function showTab(tab) {
    const login    = document.getElementById('panelLogin');
    const register = document.getElementById('panelRegister');
    const tabL     = document.getElementById('tabLogin');
    const tabR     = document.getElementById('tabRegister');

    if (tab === 'login') {
        login.classList.remove('tab-hidden');
        register.classList.remove('tab-active');
        tabL.classList.add('active', 'active-login');
        tabR.classList.remove('active', 'active-register');
    } else {
        login.classList.add('tab-hidden');
        register.classList.add('tab-active');
        tabR.classList.add('active', 'active-register');
        tabL.classList.remove('active', 'active-login');
    }
}

<?php if ($activeTab === 'register' && (window.innerWidth <= 820)): ?>
// Ouvrir sur l'onglet register si erreur/succès register
if (window.innerWidth <= 820) showTab('register');
<?php endif; ?>

// ── Force mot de passe register ────────────────────────────────
(function(){
    const pw  = document.getElementById('reg_password');
    const bar = document.getElementById('strength-bar');
    const lbl = document.getElementById('strength-label');
    const levels = [
        {min:0,  w:'0%',   col:'#ff5f5f', txt:''},
        {min:1,  w:'25%',  col:'#ff5f5f', txt:'Très faible'},
        {min:4,  w:'50%',  col:'#ffaa00', txt:'Faible'},
        {min:6,  w:'70%',  col:'#f0c060', txt:'Correct'},
        {min:10, w:'85%',  col:'#7b82ff', txt:'Bon'},
        {min:14, w:'100%', col:'#5fffb0', txt:'Fort'},
    ];
    if (!pw) return;
    pw.addEventListener('input', () => {
        const v = pw.value;
        let score = 0;
        if (v.length >= 6)           score += v.length;
        if (/[A-Z]/.test(v))         score += 3;
        if (/[0-9]/.test(v))         score += 3;
        if (/[^a-zA-Z0-9]/.test(v))  score += 4;
        let lvl = levels[0];
        for (const l of levels) if (score >= l.min) lvl = l;
        bar.style.width = lvl.w;
        bar.style.background = lvl.col;
        lbl.textContent = lvl.txt;
        lbl.style.color = lvl.col;
    });
})();

// Ouvrir automatiquement l'onglet register sur mobile si erreur/succès
<?php if ($activeTab === 'register'): ?>
if (window.innerWidth <= 820) showTab('register');
<?php endif; ?>
</script>
</body>
</html>
