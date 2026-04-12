<?php
// ============================================================
//  reset_password.php — Eons CMS | Arcanic Theme
//  Réinitialisation effective du mot de passe via token
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/srp6.php';

if (!empty($_SESSION['account_id'])) { header('Location: dashboard.php'); exit; }

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrfToken = $_SESSION['csrf_token'];

$token     = trim($_GET['token'] ?? '');
$error     = '';
$success   = false;
$tokenData = null;

// ── Vérification du token (GET et POST) ───────────────────────
if (!$token || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    $error = 'Lien de réinitialisation invalide ou malformé.';
} else {
    try {
        $db   = getAuthDB();
        $stmt = $db->prepare("
            SELECT prt.id, prt.account_id, prt.token, prt.expires_at, prt.used,
                   a.username, a.email
            FROM password_reset_tokens prt
            JOIN account a ON a.id = prt.account_id
            WHERE prt.token = :token
            LIMIT 1
        ");
        $stmt->execute([':token' => $token]);
        $tokenData = $stmt->fetch();

        if (!$tokenData) {
            $error = 'Ce lien de réinitialisation est invalide.';
        } elseif ((int)$tokenData['used'] === 1) {
            $error = 'Ce lien a déjà été utilisé. Faites une nouvelle demande si nécessaire.';
        } elseif (strtotime($tokenData['expires_at']) < time()) {
            $error = 'Ce lien a expiré (validité : 1 heure). <a href="forgot_password.php">Faire une nouvelle demande</a>.';
        }

    } catch (PDOException $e) {
        error_log('[Eons ResetPW check] ' . $e->getMessage());
        $error = 'Erreur de base de données.';
    }
}

// ── Traitement du nouveau mot de passe ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error && $tokenData) {
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';
    $csrf      = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        $error = 'Token CSRF invalide. Rechargez la page.';
    } elseif (strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } elseif ($password !== $password2) {
        $error = 'Les deux mots de passe ne correspondent pas.';
    } else {
        try {
            $db = getAuthDB();

            // Recalculer salt + verifier SRP6
            $saltBytes = SRP6::generateSalt();
            $srp       = SRP6::calcVerifier($tokenData['username'], $password, $saltBytes);

            // Mettre à jour le compte
            $db->prepare("UPDATE account SET salt = :salt, verifier = :verifier WHERE id = :id")
               ->execute([
                   ':salt'     => $srp['salt'],
                   ':verifier' => $srp['verifier'],
                   ':id'       => $tokenData['account_id'],
               ]);

            // Invalider le token
            $db->prepare("UPDATE password_reset_tokens SET used = 1 WHERE id = :id")
               ->execute([':id' => $tokenData['id']]);

            // Invalider tous les autres tokens de ce compte
            $db->prepare("DELETE FROM password_reset_tokens WHERE account_id = :aid AND id != :id")
               ->execute([':aid' => $tokenData['account_id'], ':id' => $tokenData['id']]);

            error_log('[Eons ResetPW] Mot de passe réinitialisé pour : ' . $tokenData['username']);

            $success = true;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $csrfToken = $_SESSION['csrf_token'];

        } catch (PDOException $e) {
            error_log('[Eons ResetPW update] ' . $e->getMessage());
            $error = 'Erreur lors de la mise à jour du mot de passe. Réessayez.';
        }
    }
}

$pageTitle = 'Réinitialisation du mot de passe — Eons';
require_once __DIR__ . '/header.php';
?>
<style>
body { overflow-x: hidden; }

/* ─── LAYOUT ────────────────────────────────────────────────── */
.rp-page {
    position: relative; z-index: 10;
    display: flex; align-items: center; justify-content: center;
    min-height: calc(100vh - 62px);
    margin-top: 62px;
    padding: 2rem 1.5rem;
}
.rp-page::before {
    content: '';
    position: absolute; inset: 0; pointer-events: none;
    background:
        radial-gradient(ellipse 60% 70% at 30% 40%, rgba(20,24,80,.28) 0%, transparent 60%),
        radial-gradient(ellipse 50% 60% at 70% 60%, rgba(90,48,212,.15) 0%, transparent 60%);
}

/* ─── CARD ───────────────────────────────────────────────────── */
.rp-card {
    position: relative;
    width: 100%; max-width: 460px;
    background: rgba(6,8,26,0.85);
    backdrop-filter: blur(24px);
    padding: 3rem 2.8rem;
    box-shadow:
        0 0 0 1px rgba(136,144,255,0.15),
        0 0 60px rgba(136,144,255,0.07);
}
.rp-card::before {
    content: '';
    position: absolute; top: 0; left: 0;
    width: 28px; height: 28px;
    border-top: 2px solid var(--gold-bright);
    border-left: 2px solid var(--gold-bright);
    filter: drop-shadow(0 0 6px rgba(240,192,96,0.7));
    pointer-events: none;
}
.rp-card::after {
    content: '';
    position: absolute; bottom: 0; right: 0;
    width: 28px; height: 28px;
    border-bottom: 2px solid var(--arcane-bright);
    border-right: 2px solid var(--arcane-bright);
    filter: drop-shadow(0 0 6px rgba(136,144,255,0.7));
    pointer-events: none;
}

/* ─── HEADER ────────────────────────────────────────────────── */
.rp-icon { font-size: 2rem; display: block; margin-bottom: 0.8rem; filter: drop-shadow(0 0 12px rgba(136,144,255,0.6)); }
.rp-title {
    font-family: 'Cinzel Decorative', serif;
    font-size: 1.2rem; font-weight: 700;
    color: var(--white); margin-bottom: 0.3rem;
}
.rp-subtitle { font-size: 0.9rem; color: var(--silver); font-style: italic; margin-bottom: 1.5rem; }

/* ─── DIVIDER ───────────────────────────────────────────────── */
.rp-divider { display: flex; align-items: center; gap: 0.8rem; margin-bottom: 1.8rem; }
.rp-divider::before, .rp-divider::after {
    content: ''; flex: 1; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(136,144,255,0.35));
}
.rp-divider::after { transform: scaleX(-1); }
.rp-gem {
    width: 7px; height: 7px;
    background: var(--arcane-bright);
    transform: rotate(45deg);
    box-shadow: 0 0 10px rgba(136,144,255,0.7);
}

/* ─── ALERT ─────────────────────────────────────────────────── */
.alert {
    padding: 0.85rem 1.1rem;
    border-left: 3px solid;
    margin-bottom: 1.4rem;
    font-size: 0.88rem;
    line-height: 1.5;
}
.alert-error   { background: rgba(255,95,95,0.08); border-color: rgba(255,95,95,0.6); color: #ff9999; }
.alert-error a { color: var(--gold-bright); }
.alert-success { background: rgba(95,255,176,0.08); border-color: rgba(95,255,176,0.6); color: var(--success); }

/* ─── ACCOUNT BADGE ─────────────────────────────────────────── */
.rp-account-badge {
    display: flex; align-items: center; gap: 0.8rem;
    background: rgba(136,144,255,0.06);
    border: 1px solid rgba(136,144,255,0.15);
    border-radius: 4px;
    padding: 0.7rem 1rem;
    margin-bottom: 1.4rem;
}
.rp-account-icon { font-size: 1.2rem; }
.rp-account-info { flex: 1; }
.rp-account-name {
    font-family: 'Cinzel', serif;
    font-size: 0.78rem; letter-spacing: 0.1em;
    color: var(--white);
}
.rp-account-email { font-size: 0.75rem; color: var(--silver); opacity: 0.6; }

/* ─── FORM ───────────────────────────────────────────────────── */
.form-group { margin-bottom: 1.2rem; }
.form-group label {
    display: block;
    font-family: 'Cinzel', serif;
    font-size: 0.58rem; letter-spacing: 0.2em;
    color: var(--silver); text-transform: uppercase;
    margin-bottom: 0.45rem;
}
.input-wrap { position: relative; }
.input-icon {
    position: absolute; left: 0.9rem; top: 50%;
    transform: translateY(-50%);
    font-size: 0.9rem; pointer-events: none; z-index: 2;
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
    box-sizing: border-box;
}
.input-wrap input::placeholder { color: rgba(168,180,208,0.4); }
.input-wrap input:focus {
    border-color: rgba(136,144,255,0.55);
    box-shadow: 0 0 0 2px rgba(136,144,255,0.1), 0 0 20px rgba(136,144,255,0.08);
    background: rgba(12,16,44,0.9);
}
.input-wrap::after {
    content: ''; position: absolute;
    bottom: 0; left: 0; right: 0; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(136,144,255,0.6), transparent);
    opacity: 0; transition: opacity 0.3s;
}
.input-wrap:focus-within::after { opacity: 1; }

/* ─── PASSWORD STRENGTH ─────────────────────────────────────── */
.pw-strength-bar { height: 2px; background: rgba(136,144,255,0.1); margin-top: 0.4rem; overflow: hidden; }
.pw-strength-fill { height: 100%; width: 0%; transition: width 0.4s, background 0.4s; background: var(--arcane-bright); }
.pw-strength-label { font-size: 0.7rem; color: var(--silver); margin-top: 0.3rem; font-family: 'Cinzel', serif; letter-spacing: 0.1em; min-height: 1rem; }

/* ─── SUBMIT ─────────────────────────────────────────────────── */
.btn-submit {
    width: 100%; padding: 0.9rem;
    font-family: 'Cinzel', serif;
    font-size: 0.68rem; letter-spacing: 0.2em; font-weight: 800;
    text-transform: uppercase; border: none; cursor: pointer;
    position: relative; overflow: hidden;
    transition: transform 0.2s, box-shadow 0.3s;
    background: linear-gradient(135deg, #1a1d5a 0%, #5a30d4 100%);
    color: var(--white);
    clip-path: polygon(10px 0%, 100% 0%, calc(100% - 10px) 100%, 0% 100%);
    box-shadow: 0 2px 24px rgba(90,48,212,0.5);
}
.btn-submit::before {
    content: ''; position: absolute; inset: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.12), transparent);
    transform: translateX(-100%) skewX(-20deg); transition: transform 0.5s;
}
.btn-submit:hover::before { transform: translateX(150%) skewX(-20deg); }
.btn-submit:hover { transform: translateY(-2px); box-shadow: 0 4px 36px rgba(160,112,255,0.6); }

/* ─── BACK LINK ──────────────────────────────────────────────── */
.rp-back {
    display: block; text-align: center; margin-top: 1.6rem;
    font-family: 'Cinzel', serif; font-size: 0.58rem;
    letter-spacing: 0.18em; text-transform: uppercase;
    color: var(--silver); text-decoration: none; transition: color 0.3s;
}
.rp-back:hover { color: var(--arcane-bright); }

/* ─── SUCCESS ────────────────────────────────────────────────── */
.rp-success-icon {
    font-size: 3rem; display: block; margin-bottom: 1rem;
    filter: drop-shadow(0 0 20px rgba(95,255,176,0.5));
    animation: successPulse 2s ease-in-out infinite;
}
@keyframes successPulse {
    0%, 100% { filter: drop-shadow(0 0 20px rgba(95,255,176,0.5)); }
    50%       { filter: drop-shadow(0 0 35px rgba(95,255,176,0.85)); }
}
.rp-success-title { font-family: 'Cinzel Decorative', serif; font-size: 1.15rem; color: var(--success); margin-bottom: 0.8rem; }
.rp-success-text  { font-size: 0.92rem; color: var(--silver); line-height: 1.7; margin-bottom: 1.8rem; }

/* ─── ERROR STATE (token invalide) ──────────────────────────── */
.rp-invalid-icon {
    font-size: 3rem; display: block; margin-bottom: 1rem;
    filter: drop-shadow(0 0 20px rgba(255,95,95,0.5));
}
.rp-invalid-title { font-family: 'Cinzel Decorative', serif; font-size: 1.1rem; color: #ff9999; margin-bottom: 0.8rem; }
.rp-invalid-text  { font-size: 0.88rem; color: var(--silver); line-height: 1.7; margin-bottom: 1.6rem; }

@media (max-width: 520px) {
    .rp-card { padding: 2.2rem 1.6rem; }
}
</style>

<main class="rp-page">
    <div class="rp-card">

        <?php if ($success): ?>
        <!-- ── Succès ──────────────────────────────────────── -->
        <div style="text-align:center;">
            <span class="rp-success-icon">✦</span>
            <h1 class="rp-success-title">Mot de passe mis à jour !</h1>
            <p class="rp-success-text">
                Votre nouveau mot de passe a été enregistré avec succès.<br>
                Vous pouvez maintenant vous connecter.
            </p>
            <a href="auth.php" class="btn-submit" style="display:block;text-align:center;text-decoration:none;">⚔ &nbsp; Se connecter</a>
        </div>

        <?php elseif ($error && !$tokenData): ?>
        <!-- ── Token invalide / expiré ─────────────────────── -->
        <div style="text-align:center;">
            <span class="rp-invalid-icon">⚠</span>
            <h1 class="rp-invalid-title">Lien invalide</h1>
            <p class="rp-invalid-text"><?= $error ?></p>
            <a href="forgot_password.php" class="btn-submit" style="display:block;text-align:center;text-decoration:none;">🔑 &nbsp; Nouvelle demande</a>
            <a href="auth.php" class="rp-back">← Retour à la connexion</a>
        </div>

        <?php else: ?>
        <!-- ── Formulaire de reset ─────────────────────────── -->
        <span class="rp-icon">🔮</span>
        <h1 class="rp-title">Nouveau mot de passe</h1>
        <p class="rp-subtitle">Choisissez votre nouveau mot de passe</p>

        <div class="rp-divider"><div class="rp-gem"></div></div>

        <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Badge compte -->
        <?php if ($tokenData): ?>
        <div class="rp-account-badge">
            <span class="rp-account-icon">⚔</span>
            <div class="rp-account-info">
                <div class="rp-account-name"><?= htmlspecialchars($tokenData['username']) ?></div>
                <div class="rp-account-email"><?= htmlspecialchars($tokenData['email']) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST" action="reset_password.php?token=<?= urlencode($token) ?>" autocomplete="off" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="form-group">
                <label for="rp_password">Nouveau mot de passe</label>
                <div class="input-wrap">
                    <span class="input-icon">🔮</span>
                    <input type="password" id="rp_password" name="password"
                           placeholder="Minimum 6 caractères"
                           minlength="6" autocomplete="new-password" required>
                </div>
                <div class="pw-strength-bar"><div class="pw-strength-fill" id="strength-fill"></div></div>
                <p class="pw-strength-label" id="strength-label"></p>
            </div>

            <div class="form-group">
                <label for="rp_password2">Confirmer le mot de passe</label>
                <div class="input-wrap">
                    <span class="input-icon">🔮</span>
                    <input type="password" id="rp_password2" name="password2"
                           placeholder="Répétez le mot de passe"
                           autocomplete="new-password" required>
                </div>
            </div>

            <button type="submit" class="btn-submit">✦ &nbsp; Enregistrer le mot de passe</button>
        </form>

        <a href="auth.php" class="rp-back">← Retour à la connexion</a>
        <?php endif; ?>

    </div>
</main>

<script>
// Password strength
(function(){
    const pw  = document.getElementById('rp_password');
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
