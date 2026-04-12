<?php
// ============================================================
//  forgot_password.php — Eons CMS | Arcanic Theme
//  Réinitialisation de mot de passe par adresse e-mail
//  Flow : step 1 → vérif e-mail | step 2 → nouveau MDP
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/srp6.php';

if (!empty($_SESSION['account_id'])) { header('Location: dashboard.php'); exit; }

// Nettoyer la session reset si demandé
if (isset($_GET['clearsession']) || isset($_GET['reset'])) {
    unset($_SESSION['reset_account_id'], $_SESSION['reset_verified_at']);
    header('Location: forgot_password.php'); exit;
}

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrfToken = $_SESSION['csrf_token'];

$step    = 1;
$error   = '';
$account = null;

// ── STEP 1 : vérification de l'e-mail ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === '1') {
    $email = trim($_POST['email'] ?? '');
    $csrf  = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        $error = 'Token CSRF invalide. Rechargez la page.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse e-mail invalide.';
    } else {
        try {
            $db   = getAuthDB();
            $stmt = $db->prepare("SELECT id, username, email FROM account WHERE email = :e LIMIT 1");
            $stmt->execute([':e' => strtolower($email)]);
            $account = $stmt->fetch();

            if (!$account) {
                $error = 'Aucun compte trouvé avec cette adresse e-mail.';
            } else {
                $_SESSION['reset_account_id']  = $account['id'];
                $_SESSION['reset_verified_at'] = time();
                $step = 2;
            }
        } catch (PDOException $e) {
            error_log('[Eons ForgotPW step1] ' . $e->getMessage());
            $error = 'Erreur de base de données. Réessayez plus tard.';
        }
    }
}

// ── STEP 2 : mise à jour du mot de passe ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === '2') {
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';
    $csrf      = $_POST['csrf_token'] ?? '';
    $accountId  = $_SESSION['reset_account_id']  ?? null;
    $verifiedAt = $_SESSION['reset_verified_at'] ?? 0;

    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        $error = 'Token CSRF invalide. Rechargez la page.'; $step = 1;
    } elseif (!$accountId || (time() - $verifiedAt) > 900) {
        $error = 'Session expirée. Recommencez depuis le début.';
        unset($_SESSION['reset_account_id'], $_SESSION['reset_verified_at']); $step = 1;
    } elseif (strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.'; $step = 2;
    } elseif ($password !== $password2) {
        $error = 'Les deux mots de passe ne correspondent pas.'; $step = 2;
    } else {
        try {
            $db   = getAuthDB();
            $stmt = $db->prepare("SELECT id, username, email FROM account WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $accountId]);
            $account = $stmt->fetch();

            if (!$account) {
                $error = 'Compte introuvable.'; $step = 1;
                unset($_SESSION['reset_account_id'], $_SESSION['reset_verified_at']);
            } else {
                $saltBytes = SRP6::generateSalt();
                $srp       = SRP6::calcVerifier($account['username'], $password, $saltBytes);
                $db->prepare("UPDATE account SET salt = :salt, verifier = :verifier WHERE id = :id")
                   ->execute([':salt' => $srp['salt'], ':verifier' => $srp['verifier'], ':id' => $account['id']]);
                unset($_SESSION['reset_account_id'], $_SESSION['reset_verified_at']);
                error_log('[Eons ResetPW] Mot de passe mis à jour pour : ' . $account['username']);
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $csrfToken = $_SESSION['csrf_token'];
                $step = 3;
            }
        } catch (PDOException $e) {
            error_log('[Eons ForgotPW step2] ' . $e->getMessage());
            $error = 'Erreur lors de la mise à jour. Réessayez.'; $step = 2;
        }
    }
}

// Reprise de session step 2 si déjà vérifié
if ($step === 1 && !empty($_SESSION['reset_account_id']) && !empty($_SESSION['reset_verified_at'])) {
    if ((time() - $_SESSION['reset_verified_at']) <= 900) {
        try {
            $db   = getAuthDB();
            $stmt = $db->prepare("SELECT id, username, email FROM account WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $_SESSION['reset_account_id']]);
            $a = $stmt->fetch();
            if ($a) { $account = $a; $step = 2; }
        } catch (PDOException $e) { /* silent */ }
    } else { unset($_SESSION['reset_account_id'], $_SESSION['reset_verified_at']); }
}

$pageTitle = 'Mot de passe oublié — Eons';
require_once __DIR__ . '/header.php';
?>
<style>
body { overflow-x: hidden; }
.fp-page {
    position: relative; z-index: 10;
    display: flex; align-items: center; justify-content: center;
    min-height: calc(100vh - 62px); margin-top: 62px; padding: 2rem 1.5rem;
}
.fp-page::before {
    content: ''; position: absolute; inset: 0; pointer-events: none;
    background: radial-gradient(ellipse 60% 70% at 30% 40%, rgba(20,24,80,.28) 0%, transparent 60%),
                radial-gradient(ellipse 50% 60% at 70% 60%, rgba(90,48,212,.15) 0%, transparent 60%);
}
.fp-card {
    position: relative; width: 100%; max-width: 460px;
    background: rgba(6,8,26,0.85); backdrop-filter: blur(24px);
    padding: 3rem 2.8rem;
    box-shadow: 0 0 0 1px rgba(136,144,255,0.15), 0 0 60px rgba(136,144,255,0.07);
}
.fp-card::before {
    content: ''; position: absolute; top: 0; left: 0; width: 28px; height: 28px;
    border-top: 2px solid var(--gold-bright); border-left: 2px solid var(--gold-bright);
    filter: drop-shadow(0 0 6px rgba(240,192,96,0.7)); pointer-events: none;
}
.fp-card::after {
    content: ''; position: absolute; bottom: 0; right: 0; width: 28px; height: 28px;
    border-bottom: 2px solid var(--arcane-bright); border-right: 2px solid var(--arcane-bright);
    filter: drop-shadow(0 0 6px rgba(136,144,255,0.7)); pointer-events: none;
}
/* Steps */
.fp-steps { display: flex; align-items: center; justify-content: center; gap: 0; margin-bottom: 2rem; }
.fp-step-dot {
    width: 28px; height: 28px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-family: 'Cinzel', serif; font-size: 0.65rem; font-weight: 700;
    border: 1px solid rgba(136,144,255,0.2); color: rgba(168,180,208,0.4);
    background: rgba(9,12,34,0.6); position: relative; z-index: 1;
}
.fp-step-dot.active { border-color: var(--gold-bright); color: var(--gold-bright); background: rgba(200,144,40,0.1); box-shadow: 0 0 14px rgba(240,192,96,0.3); }
.fp-step-dot.done   { border-color: var(--success); color: var(--success); background: rgba(95,255,176,0.08); }
.fp-step-line { flex: 1; max-width: 60px; height: 1px; background: rgba(136,144,255,0.15); }
.fp-step-line.done { background: rgba(95,255,176,0.35); }
/* Header */
.fp-icon { font-size: 2rem; display: block; margin-bottom: 0.8rem; }
.fp-title { font-family: 'Cinzel Decorative', serif; font-size: 1.15rem; font-weight: 700; color: var(--white); margin-bottom: 0.3rem; }
.fp-subtitle { font-size: 0.88rem; color: var(--silver); font-style: italic; margin-bottom: 1.5rem; }
/* Divider */
.fp-divider { display: flex; align-items: center; gap: 0.8rem; margin-bottom: 1.8rem; }
.fp-divider::before, .fp-divider::after { content: ''; flex: 1; height: 1px; background: linear-gradient(90deg, transparent, rgba(240,192,96,0.35)); }
.fp-divider-arcane::before, .fp-divider-arcane::after { background: linear-gradient(90deg, transparent, rgba(136,144,255,0.35)); }
.fp-divider::after { transform: scaleX(-1); }
.fp-gem { width: 7px; height: 7px; transform: rotate(45deg); background: var(--gold); box-shadow: 0 0 10px rgba(240,192,96,0.7); }
.fp-gem-arcane { background: var(--arcane-bright); box-shadow: 0 0 10px rgba(136,144,255,0.7); }
/* Alert */
.alert { padding: 0.85rem 1.1rem; border-left: 3px solid; margin-bottom: 1.4rem; font-size: 0.88rem; line-height: 1.5; }
.alert-error { background: rgba(255,95,95,0.08); border-color: rgba(255,95,95,0.6); color: #ff9999; }
/* Account badge */
.fp-account-badge {
    display: flex; align-items: center; gap: 0.8rem;
    background: rgba(136,144,255,0.06); border: 1px solid rgba(136,144,255,0.15);
    border-radius: 4px; padding: 0.7rem 1rem; margin-bottom: 1.4rem;
}
.fp-account-name { font-family: 'Cinzel', serif; font-size: 0.78rem; letter-spacing: 0.1em; color: var(--white); }
.fp-account-email { font-size: 0.75rem; color: var(--silver); opacity: 0.6; }
/* Form */
.form-group { margin-bottom: 1.2rem; }
.form-group label { display: block; font-family: 'Cinzel', serif; font-size: 0.58rem; letter-spacing: 0.2em; color: var(--silver); text-transform: uppercase; margin-bottom: 0.45rem; }
.input-wrap { position: relative; }
.input-icon { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); font-size: 0.9rem; pointer-events: none; z-index: 2; }
.input-wrap input { width: 100%; background: rgba(9,12,34,0.8); border: 1px solid rgba(136,144,255,0.18); color: var(--white); font-family: 'Crimson Pro', serif; font-size: 0.95rem; padding: 0.7rem 0.9rem 0.7rem 2.4rem; outline: none; transition: border-color 0.3s, box-shadow 0.3s; clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%); box-sizing: border-box; }
.input-wrap input::placeholder { color: rgba(168,180,208,0.4); }
.input-wrap input:focus { border-color: rgba(136,144,255,0.55); box-shadow: 0 0 0 2px rgba(136,144,255,0.1), 0 0 20px rgba(136,144,255,0.08); background: rgba(12,16,44,0.9); }
.input-wrap::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, rgba(136,144,255,0.6), transparent); opacity: 0; transition: opacity 0.3s; }
.input-wrap:focus-within::after { opacity: 1; }
.fp-hint { font-size: 0.8rem; color: var(--silver); opacity: 0.6; margin-top: 0.4rem; line-height: 1.5; }
/* Password strength */
.pw-strength-bar { height: 2px; background: rgba(136,144,255,0.1); margin-top: 0.4rem; overflow: hidden; }
.pw-strength-fill { height: 100%; width: 0%; transition: width 0.4s, background 0.4s; }
.pw-strength-label { font-size: 0.7rem; color: var(--silver); margin-top: 0.3rem; font-family: 'Cinzel', serif; letter-spacing: 0.1em; min-height: 1rem; }
/* Buttons */
.btn-submit { width: 100%; padding: 0.9rem; font-family: 'Cinzel', serif; font-size: 0.68rem; letter-spacing: 0.2em; font-weight: 800; text-transform: uppercase; border: none; cursor: pointer; position: relative; overflow: hidden; transition: transform 0.2s, box-shadow 0.3s; clip-path: polygon(10px 0%, 100% 0%, calc(100% - 10px) 100%, 0% 100%); }
.btn-submit::before { content: ''; position: absolute; inset: 0; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.12), transparent); transform: translateX(-100%) skewX(-20deg); transition: transform 0.5s; }
.btn-submit:hover::before { transform: translateX(150%) skewX(-20deg); }
.btn-submit:hover { transform: translateY(-2px); }
.btn-gold { background: linear-gradient(135deg, #9a6418 0%, #d4a030 35%, #f0c060 50%, #d4a030 65%, #9a6418 100%); color: #1a0e00; box-shadow: 0 2px 24px rgba(200,151,42,0.4); }
.btn-gold:hover { box-shadow: 0 4px 36px rgba(200,151,42,0.65); }
.btn-arcane { background: linear-gradient(135deg, #1a1d5a 0%, #5a30d4 100%); color: var(--white); box-shadow: 0 2px 24px rgba(90,48,212,0.5); }
.btn-arcane:hover { box-shadow: 0 4px 36px rgba(160,112,255,0.6); }
/* Back */
.fp-back { display: block; text-align: center; margin-top: 1.4rem; font-family: 'Cinzel', serif; font-size: 0.58rem; letter-spacing: 0.18em; text-transform: uppercase; color: var(--silver); text-decoration: none; transition: color 0.3s; }
.fp-back:hover { color: var(--arcane-bright); }
/* Success */
.fp-success-icon { font-size: 3rem; display: block; margin-bottom: 1rem; filter: drop-shadow(0 0 20px rgba(95,255,176,0.5)); animation: successPulse 2s ease-in-out infinite; }
@keyframes successPulse { 0%,100%{filter:drop-shadow(0 0 20px rgba(95,255,176,0.5))} 50%{filter:drop-shadow(0 0 35px rgba(95,255,176,0.85))} }
.fp-success-title { font-family: 'Cinzel Decorative', serif; font-size: 1.15rem; color: var(--success); margin-bottom: 0.8rem; }
.fp-success-text { font-size: 0.92rem; color: var(--silver); line-height: 1.7; margin-bottom: 1.8rem; }
.fp-success-text strong { color: var(--white); }
@media (max-width: 520px) { .fp-card { padding: 2.2rem 1.6rem; } .fp-step-line { max-width: 40px; } }
</style>

<main class="fp-page">
<div class="fp-card">

<?php if ($step === 3): ?>
<!-- ════ ÉTAPE 3 : Succès ════════════════════════════════════ -->
<div style="text-align:center;">
    <span class="fp-success-icon">✦</span>
    <h1 class="fp-success-title">Mot de passe mis à jour !</h1>
    <p class="fp-success-text">Votre nouveau mot de passe a été enregistré avec succès.<br><strong>Vous pouvez maintenant vous connecter.</strong></p>
    <a href="auth.php" class="btn-submit btn-gold" style="display:block;text-decoration:none;text-align:center;">⚔ &nbsp; Se connecter</a>
</div>

<?php elseif ($step === 2): ?>
<!-- ════ ÉTAPE 2 : Nouveau mot de passe ══════════════════════ -->
<div class="fp-steps">
    <div class="fp-step-dot done">✓</div>
    <div class="fp-step-line done"></div>
    <div class="fp-step-dot active">2</div>
    <div class="fp-step-line"></div>
    <div class="fp-step-dot">3</div>
</div>

<span class="fp-icon" style="filter:drop-shadow(0 0 12px rgba(136,144,255,0.6))">🔮</span>
<h1 class="fp-title">Nouveau mot de passe</h1>
<p class="fp-subtitle">Choisissez votre nouveau mot de passe</p>
<div class="fp-divider fp-divider-arcane"><div class="fp-gem fp-gem-arcane"></div></div>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($account): ?>
<div class="fp-account-badge">
    <span style="font-size:1.2rem">⚔</span>
    <div>
        <div class="fp-account-name"><?= htmlspecialchars($account['username']) ?></div>
        <div class="fp-account-email"><?= htmlspecialchars($account['email']) ?></div>
    </div>
</div>
<?php endif; ?>

<form method="POST" action="forgot_password.php" autocomplete="off" novalidate>
    <input type="hidden" name="step" value="2">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <div class="form-group">
        <label for="fp_password">Nouveau mot de passe</label>
        <div class="input-wrap">
            <span class="input-icon">🔮</span>
            <input type="password" id="fp_password" name="password" placeholder="Minimum 6 caractères" minlength="6" autocomplete="new-password" required>
        </div>
        <div class="pw-strength-bar"><div class="pw-strength-fill" id="strength-fill"></div></div>
        <p class="pw-strength-label" id="strength-label"></p>
    </div>
    <div class="form-group">
        <label for="fp_password2">Confirmer le mot de passe</label>
        <div class="input-wrap">
            <span class="input-icon">🔮</span>
            <input type="password" id="fp_password2" name="password2" placeholder="Répétez le mot de passe" autocomplete="new-password" required>
        </div>
    </div>
    <button type="submit" class="btn-submit btn-arcane">✦ &nbsp; Enregistrer le mot de passe</button>
</form>
<a href="forgot_password.php?clearsession=1" class="fp-back">← Recommencer</a>

<?php else: ?>
<!-- ════ ÉTAPE 1 : Saisie e-mail ═════════════════════════════ -->
<div class="fp-steps">
    <div class="fp-step-dot active">1</div>
    <div class="fp-step-line"></div>
    <div class="fp-step-dot">2</div>
    <div class="fp-step-line"></div>
    <div class="fp-step-dot">3</div>
</div>

<span class="fp-icon" style="filter:drop-shadow(0 0 12px rgba(240,192,96,0.5))">🔑</span>
<h1 class="fp-title">Mot de passe oublié</h1>
<p class="fp-subtitle">Récupérer l'accès à votre royaume</p>
<div class="fp-divider"><div class="fp-gem"></div></div>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="POST" action="forgot_password.php" novalidate>
    <input type="hidden" name="step" value="1">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <div class="form-group">
        <label for="fp_email">Adresse e-mail du compte</label>
        <div class="input-wrap">
            <span class="input-icon">✉</span>
            <input type="email" id="fp_email" name="email" placeholder="votre@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="email" required>
        </div>
        <p class="fp-hint">Entrez l'adresse e-mail associée à votre compte Eons.</p>
    </div>
    <button type="submit" class="btn-submit btn-gold">🔑 &nbsp; Vérifier mon adresse</button>
</form>
<a href="auth.php" class="fp-back">← Retour à la connexion</a>
<?php endif; ?>

</div>
</main>

<script>
(function(){
    const pw  = document.getElementById('fp_password');
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
