<?php
// ============================================================
//  admin.php — Eons CMS | Panel Administrateur
//  Gestion du catalogue boutique (shop_catalog)
//
//  Accès réservé : SecurityLevel >= 3 dans la table account_access (eons_auth)
// ============================================================
require_once __DIR__ . '/config.php';

$isLoggedIn = !empty($_SESSION['logged_in']) && !empty($_SESSION['account_id']);
$accountId  = $isLoggedIn ? (int)$_SESSION['account_id'] : 0;

if (!$isLoggedIn) {
    header('Location: auth.php');
    exit;
}

try {
    $db   = getAuthDB();
    $stmt = $db->prepare(
        "SELECT a.username, aa.SecurityLevel
         FROM account a
         LEFT JOIN account_access aa ON aa.AccountID = a.id AND aa.RealmID = -1
         WHERE a.id = :id
         LIMIT 1"
    );
    $stmt->execute([':id' => $accountId]);
    $adminRow = $stmt->fetch();
} catch (PDOException $e) {
    die('Erreur de connexion à la base de données.');
}

if (!$adminRow || (int)($adminRow['SecurityLevel'] ?? 0) < 3) {
    header('Location: index.php');
    exit;
}

$adminName = htmlspecialchars($adminRow['username']);

// ── CSRF Token ────────────────────────────────────────────────
if (empty($_SESSION['csrf_admin'])) {
    $_SESSION['csrf_admin'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_admin'];

// ── Messages flash ────────────────────────────────────────────
$flash     = '';
$flashType = 'success';

// ── Helpers ───────────────────────────────────────────────────
function sanitizeId(string $v): string {
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower(trim($v)));
}

function collectItem(array $post): array {
    return [
        'id'           => sanitizeId($post['id'] ?? ''),
        'name'         => mb_substr(trim($post['name'] ?? ''), 0, 128),
        'description'  => mb_substr(trim($post['description'] ?? ''), 0, 1000),
        'icon'         => mb_substr(trim($post['icon'] ?? '🎁'), 0, 8),
        'price'        => max(0, (int)($post['price'] ?? 0)),
        'currency'     => in_array($post['currency'] ?? '', ['dp','vp']) ? $post['currency'] : 'dp',
        'category'     => in_array($post['category'] ?? '', ['montures','pets','equipement','services'])
                            ? $post['category'] : 'montures',
        'game_item_id' => max(0, (int)($post['game_item_id'] ?? 0)),
        'quantity'     => max(1, (int)($post['quantity'] ?? 1)),
        'soap_cmd'     => mb_substr(trim($post['soap_cmd'] ?? ''), 0, 255) ?: null,
        'badge'        => mb_substr(trim($post['badge'] ?? ''), 0, 32) ?: null,
        'badge_color'  => preg_match('/^#[0-9a-fA-F]{3,6}$/', $post['badge_color'] ?? '')
                            ? $post['badge_color'] : null,
        'ribbon'       => mb_substr(trim($post['ribbon'] ?? ''), 0, 32) ?: null,
        'active'       => isset($post['active']) ? 1 : 0,
        'sort_order'   => (int)($post['sort_order'] ?? 0),
    ];
}

// ============================================================
//  TRAITEMENT DES ACTIONS POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_admin'] ?? '', $_POST['csrf_token'] ?? '')) {
        $flash     = '⚠ Token CSRF invalide. Action annulée.';
        $flashType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            $db = getAuthDB();

            if ($action === 'add') {
                $item = collectItem($_POST);
                if (empty($item['id']) || empty($item['name'])) {
                    $flash = '⚠ ID et Nom sont obligatoires.';
                    $flashType = 'error';
                } else {
                    $stmt = $db->prepare(
                        "INSERT INTO shop_catalog
                           (id, name, description, icon, price, currency, category,
                            game_item_id, quantity, soap_cmd, badge, badge_color, ribbon, active, sort_order)
                         VALUES
                           (:id,:name,:description,:icon,:price,:currency,:category,
                            :game_item_id,:quantity,:soap_cmd,:badge,:badge_color,:ribbon,:active,:sort_order)"
                    );
                    $stmt->execute($item);
                    $flash = '✦ Article <strong>' . htmlspecialchars($item['name']) . '</strong> ajouté avec succès.';
                }
            } elseif ($action === 'edit') {
                $originalId = sanitizeId($_POST['original_id'] ?? '');
                $item       = collectItem($_POST);
                if (empty($originalId) || empty($item['name'])) {
                    $flash = '⚠ Données invalides.';
                    $flashType = 'error';
                } else {
                    $stmt = $db->prepare(
                        "UPDATE shop_catalog SET
                           id=:id, name=:name, description=:description, icon=:icon,
                           price=:price, currency=:currency, category=:category,
                           game_item_id=:game_item_id, quantity=:quantity, soap_cmd=:soap_cmd,
                           badge=:badge, badge_color=:badge_color, ribbon=:ribbon,
                           active=:active, sort_order=:sort_order
                         WHERE id=:original_id"
                    );
                    $params = $item;
                    $params['original_id'] = $originalId;
                    $stmt->execute($params);
                    $flash = '✦ Article <strong>' . htmlspecialchars($item['name']) . '</strong> mis à jour.';
                }
            } elseif ($action === 'delete') {
                $deleteId = sanitizeId($_POST['delete_id'] ?? '');
                if ($deleteId) {
                    $stmt = $db->prepare("DELETE FROM shop_catalog WHERE id = :id");
                    $stmt->execute([':id' => $deleteId]);
                    $flash = '🗑 Article supprimé.';
                    $flashType = 'info';
                }
            } elseif ($action === 'toggle') {
                $toggleId = sanitizeId($_POST['toggle_id'] ?? '');
                if ($toggleId) {
                    $stmt = $db->prepare("UPDATE shop_catalog SET active = 1 - active WHERE id = :id");
                    $stmt->execute([':id' => $toggleId]);
                    $_SESSION['flash_admin']      = '✦ Visibilité modifiée.';
                    $_SESSION['flash_admin_type'] = 'success';
                    header('Location: admin.php?section=catalogue');
                    exit;
                }
            }
        } catch (PDOException $e) {
            $flash     = '⚠ Erreur base de données : ' . htmlspecialchars($e->getMessage());
            $flashType = 'error';
        }
        // PRG : redirect après add/edit/delete pour éviter le double-submit
        if (empty($flash)) {
            // succès sans flash spécifique (ne devrait pas arriver)
        } elseif ($flashType !== 'error' && in_array($action, ['add','edit','delete'])) {
            $_SESSION['flash_admin']      = $flash;
            $_SESSION['flash_admin_type'] = $flashType ?: 'success';
            header('Location: admin.php?section=catalogue');
            exit;
        }
    }
}

// ── Récupération du flash depuis session (après PRG) ─────────
if (!empty($_SESSION['flash_admin'])) {
    $flash     = $_SESSION['flash_admin'];
    $flashType = $_SESSION['flash_admin_type'] ?? 'success';
    unset($_SESSION['flash_admin'], $_SESSION['flash_admin_type']);
}

// ── Chargement du catalogue ───────────────────────────────────
try {
    $db      = getAuthDB();
    $catalog = $db->query(
        "SELECT * FROM shop_catalog ORDER BY category, sort_order, name"
    )->fetchAll();
} catch (PDOException $e) {
    $catalog = [];
}

// ── Section active (définie ICI, avant tout usage) ───────────
$section = $_GET['section'] ?? 'catalogue';

// ── Logs achats ───────────────────────────────────────────────
$shopLogs = [];
if ($section === 'logs') {
    try {
        $db = getAuthDB();
        $shopLogs = $db->query(
            "SELECT 'dp' AS currency, id, account_id, char_name, item_name, game_item_id, cost, soap_status, soap_error, created_at
             FROM dp_shop_log
             UNION ALL
             SELECT 'vp' AS currency, id, account_id, char_name, item_name, game_item_id, cost, soap_status, soap_error, created_at
             FROM vp_shop_log
             ORDER BY created_at DESC
             LIMIT 200"
        )->fetchAll();
    } catch (PDOException $e) { $shopLogs = []; }
}

// ── File d'attente ─────────────────────────────────────────────
$shopQueue = [];
if ($section === 'queue') {
    try {
        $db = getAuthDB();
        $shopQueue = $db->query(
            "SELECT 'dp' AS currency, id, account_id, char_name, item_name, game_item_id, quantity, status, attempts, created_at, delivered_at
             FROM dp_shop_queue
             UNION ALL
             SELECT 'vp' AS currency, id, account_id, char_name, item_name, game_item_id, quantity, status, attempts, created_at, delivered_at
             FROM vp_shop_queue
             ORDER BY created_at DESC
             LIMIT 200"
        )->fetchAll();
    } catch (PDOException $e) { $shopQueue = []; }
}

// ── Comptes ────────────────────────────────────────────────────
$accounts = [];
if ($section === 'accounts') {
    try {
        $db = getAuthDB();
        $accounts = $db->query(
            "SELECT a.id, a.username, a.email, a.dp, a.vp, a.joindate, a.last_login,
                    a.online, a.locked, a.failed_logins,
                    COALESCE(aa.SecurityLevel, 0) AS security_level
             FROM account a
             LEFT JOIN account_access aa ON aa.AccountID = a.id AND aa.RealmID = -1
             ORDER BY a.id DESC
             LIMIT 500"
        )->fetchAll();
    } catch (PDOException $e) { $accounts = []; }
}

// ── Item à éditer (pré-remplissage modal) ─────────────────────
$editItem = null;
if (!empty($_GET['edit'])) {
    $editId = sanitizeId($_GET['edit']);
    foreach ($catalog as $c) {
        if ($c['id'] === $editId) { $editItem = $c; break; }
    }
}

// ── Stats rapides ─────────────────────────────────────────────
$totalItems  = count($catalog);
$activeItems = count(array_filter($catalog, fn($c) => $c['active']));
$cats        = array_count_values(array_column($catalog, 'category'));

$pageTitle = 'Admin — Eons CMS';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700;900&family=Cinzel:wght@400;600;700&family=Crimson+Pro:ital,wght@0,300;0,400;0,600;1,300;1,400&display=swap" rel="stylesheet">
<style>
/* ─── RESET & VARS ───────────────────────────────────────────── */
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
:root {
    --midnight:      #02030c;
    --deep-void:     #06081a;
    --abyss:         #090c22;
    --arcane-dark:   #0f1230;
    --arcane:        #1a1d5a;
    --arcane-glow:   #4855d4;
    --arcane-bright: #8890ff;
    --void-purple:   #5a30d4;
    --void-bright:   #a070ff;
    --gold:          #c89028;
    --gold-bright:   #f0c060;
    --silver:        #a8b4d0;
    --silver-bright: #d4dff0;
    --white:         #eef2ff;
    --error:         #ff5f5f;
    --success:       #5fffb0;
    --info:          #7b82ff;
    --green:         #6edf8a;
    --sw:            240px;   /* sidebar width */
}
html { scroll-behavior:smooth; }
body {
    font-family:'Crimson Pro', Georgia, serif;
    background:var(--midnight);
    color:var(--silver-bright);
    overflow-x:hidden;
    min-height:100vh;
}
body::before {
    content:'';
    position:fixed; inset:0; z-index:0;
    background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.06'/%3E%3C/svg%3E");
    pointer-events:none; opacity:0.45; mix-blend-mode:overlay;
}
#starfield { position:fixed; inset:0; z-index:0; pointer-events:none; }

/* ─── LAYOUT ─────────────────────────────────────────────────── */
.shell {
    display:flex;
    min-height:100vh;
    position:relative;
    z-index:10;
}

/* ─── SIDEBAR ───────────────────────────────────────────────── */
.sidebar {
    width:var(--sw);
    min-width:var(--sw);
    position:fixed;
    top:0; left:0; bottom:0;
    background:rgba(6,8,26,0.97);
    border-right:1px solid rgba(136,144,255,0.12);
    backdrop-filter:blur(24px);
    display:flex;
    flex-direction:column;
    z-index:200;
    overflow-y:auto;
}
.sb-brand {
    padding:1.6rem 1.4rem 1.2rem;
    border-bottom:1px solid rgba(136,144,255,0.1);
}
.sb-brand-title {
    font-family:'Cinzel Decorative', serif;
    font-size:1.1rem;
    color:var(--gold-bright);
    text-shadow:0 0 24px rgba(240,192,96,0.45);
    letter-spacing:0.05em;
    line-height:1;
}
.sb-brand-sub {
    font-family:'Cinzel', serif;
    font-size:0.52rem;
    letter-spacing:0.32em;
    color:var(--arcane-bright);
    text-transform:uppercase;
    opacity:0.65;
    margin-top:0.35rem;
}
.sb-user {
    margin:0.8rem 1.2rem;
    padding:0.55rem 0.9rem;
    background:rgba(136,144,255,0.07);
    border:1px solid rgba(136,144,255,0.16);
    border-radius:7px;
    font-family:'Cinzel', serif;
    font-size:0.58rem;
    letter-spacing:0.1em;
    color:var(--gold-bright);
    display:flex;
    align-items:center;
    gap:0.5rem;
    overflow:hidden;
}
.sb-user::before { content:'⚔'; font-size:0.72rem; flex-shrink:0; }
.sb-user span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.sb-nav { flex:1; padding:0.5rem 0; }
.sb-section {
    font-family:'Cinzel', serif;
    font-size:0.48rem;
    letter-spacing:0.38em;
    color:rgba(168,180,208,0.35);
    text-transform:uppercase;
    padding:1rem 1.4rem 0.3rem;
}
.sb-link {
    display:flex;
    align-items:center;
    gap:0.7rem;
    padding:0.6rem 1.4rem;
    font-family:'Cinzel', serif;
    font-size:0.6rem;
    letter-spacing:0.1em;
    color:var(--silver);
    text-decoration:none;
    text-transform:uppercase;
    transition:all 0.18s;
    border-left:2px solid transparent;
    white-space:nowrap;
}
.sb-link:hover { color:var(--arcane-bright); background:rgba(136,144,255,0.05); border-left-color:rgba(136,144,255,0.4); }
.sb-link.active { color:var(--gold-bright); background:rgba(240,192,96,0.05); border-left-color:var(--gold-bright); }
.sb-link i { width:1.1rem; text-align:center; font-style:normal; font-size:0.88rem; flex-shrink:0; }
.sb-count {
    margin-left:auto;
    background:rgba(136,144,255,0.13);
    color:var(--arcane-bright);
    font-family:'Cinzel', serif;
    font-size:0.5rem;
    padding:0.1rem 0.4rem;
    border-radius:20px;
    border:1px solid rgba(136,144,255,0.2);
    flex-shrink:0;
}
.sb-footer {
    padding:0.9rem 1.2rem;
    border-top:1px solid rgba(136,144,255,0.08);
    display:flex;
    flex-direction:column;
    gap:0.4rem;
}
.sb-footer a {
    font-family:'Cinzel', serif;
    font-size:0.55rem;
    letter-spacing:0.1em;
    color:rgba(168,180,208,0.45);
    text-decoration:none;
    text-transform:uppercase;
    transition:color 0.18s;
    display:flex;
    align-items:center;
    gap:0.5rem;
    padding:0.22rem 0;
}
.sb-footer a:hover { color:var(--silver-bright); }
.sb-footer .logout { color:rgba(255,95,95,0.45); }
.sb-footer .logout:hover { color:var(--error); }

/* ─── MAIN ───────────────────────────────────────────────────── */
.main {
    flex:1;
    margin-left:var(--sw);
    padding:2.2rem 2.8rem 5rem;
    min-width:0;
    min-height:100vh;
}

/* ─── TOPBAR ─────────────────────────────────────────────────── */
.topbar {
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:1rem;
    margin-bottom:2rem;
    padding-bottom:1.4rem;
    border-bottom:1px solid rgba(136,144,255,0.1);
    flex-wrap:wrap;
}
.eyebrow {
    font-family:'Cinzel', serif;
    font-size:0.52rem;
    letter-spacing:0.38em;
    color:var(--gold);
    text-transform:uppercase;
    margin-bottom:0.3rem;
}
.page-title {
    font-family:'Cinzel Decorative', serif;
    font-size:1.5rem;
    color:var(--white);
    text-shadow:0 0 32px rgba(136,144,255,0.2);
    line-height:1.1;
}

/* ─── STATS ──────────────────────────────────────────────────── */
.stats {
    display:grid;
    grid-template-columns:repeat(6,1fr);
    gap:0.9rem;
    margin-bottom:2rem;
}
.stat {
    background:rgba(15,18,48,0.7);
    border:1px solid rgba(136,144,255,0.11);
    border-radius:10px;
    padding:1rem 1.1rem 0.9rem;
    position:relative;
    overflow:hidden;
    transition:border-color 0.2s, transform 0.2s;
}
.stat::before {
    content:'';
    position:absolute; top:0; left:0; right:0; height:2px;
    background:linear-gradient(90deg,transparent,var(--a,var(--arcane-bright)),transparent);
    opacity:0.7;
}
.stat:hover { border-color:rgba(136,144,255,0.22); transform:translateY(-2px); }
.stat-val {
    font-family:'Cinzel Decorative', serif;
    font-size:1.9rem;
    color:var(--a,var(--gold-bright));
    line-height:1;
    margin-bottom:0.3rem;
}
.stat-lbl {
    font-family:'Cinzel', serif;
    font-size:0.53rem;
    letter-spacing:0.2em;
    color:var(--silver);
    text-transform:uppercase;
    opacity:0.65;
}

/* ─── PANEL ──────────────────────────────────────────────────── */
.panel {
    background:rgba(9,12,34,0.88);
    border:1px solid rgba(136,144,255,0.11);
    border-radius:12px;
    overflow:hidden;
    margin-bottom:2rem;
}
.panel-head {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:1rem;
    padding:1rem 1.5rem;
    background:rgba(15,18,48,0.5);
    border-bottom:1px solid rgba(136,144,255,0.09);
    flex-wrap:wrap;
}
.panel-title {
    font-family:'Cinzel', serif;
    font-size:0.68rem;
    letter-spacing:0.2em;
    color:var(--gold-bright);
    text-transform:uppercase;
    display:flex;
    align-items:center;
    gap:0.6rem;
}
.panel-body { padding:1.4rem 1.5rem; }

/* ─── FILTRES ────────────────────────────────────────────────── */
.filters {
    display:flex;
    gap:0.55rem;
    flex-wrap:wrap;
}
.f-btn {
    padding:0.32rem 0.9rem;
    font-family:'Cinzel', serif;
    font-size:0.55rem;
    letter-spacing:0.12em;
    text-transform:uppercase;
    border:1px solid rgba(136,144,255,0.18);
    border-radius:20px;
    color:var(--silver);
    background:transparent;
    cursor:pointer;
    transition:all 0.18s;
    white-space:nowrap;
}
.f-btn:hover { background:rgba(136,144,255,0.09); border-color:var(--arcane-bright); color:var(--arcane-bright); }
.f-btn.active { background:rgba(240,192,96,0.07); border-color:var(--gold-bright); color:var(--gold-bright); }

/* ─── TABLE ──────────────────────────────────────────────────── */
.tbl-scroll { overflow-x:auto; }
.tbl {
    width:100%;
    border-collapse:collapse;
    font-size:0.87rem;
    white-space:nowrap;
}
.tbl th {
    font-family:'Cinzel', serif;
    font-size:0.53rem;
    letter-spacing:0.2em;
    color:var(--silver);
    text-transform:uppercase;
    padding:0.6rem 0.9rem;
    border-bottom:1px solid rgba(136,144,255,0.11);
    text-align:left;
    opacity:0.65;
    background:rgba(6,8,26,0.3);
}
.tbl td {
    padding:0.7rem 0.9rem;
    border-bottom:1px solid rgba(136,144,255,0.05);
    vertical-align:middle;
}
.tbl tr:last-child td { border-bottom:none; }
.tbl tbody tr:hover td { background:rgba(136,144,255,0.03); }
.t-icon { font-size:1.25rem; text-align:center; width:3rem; }
.t-name {
    font-family:'Cinzel', serif;
    font-size:0.7rem;
    letter-spacing:0.06em;
    color:var(--silver-bright);
}
.t-id {
    font-size:0.65rem;
    color:var(--arcane-bright);
    opacity:0.55;
    font-family:monospace;
    margin-top:0.12rem;
}
.t-price {
    font-family:'Cinzel', serif;
    font-size:0.74rem;
    font-weight:700;
}
.dp { color:var(--gold-bright); }
.vp { color:var(--green); }

/* ─── BADGES ─────────────────────────────────────────────────── */
.bdg {
    display:inline-block;
    padding:0.13rem 0.52rem;
    border-radius:20px;
    font-family:'Cinzel', serif;
    font-size:0.52rem;
    letter-spacing:0.1em;
    text-transform:uppercase;
    border:1px solid;
    white-space:nowrap;
}
.bdg-cat    { background:rgba(136,144,255,0.09); border-color:rgba(136,144,255,0.22); color:var(--arcane-bright); }
.bdg-on     { background:rgba(94,255,176,0.08);  border-color:rgba(94,255,176,0.28);  color:var(--success); }
.bdg-off    { background:rgba(255,95,95,0.08);   border-color:rgba(255,95,95,0.25);   color:var(--error); }

/* ─── BOUTONS TABLE ──────────────────────────────────────────── */
.btn {
    display:inline-flex;
    align-items:center;
    gap:0.28rem;
    padding:0.3rem 0.65rem;
    font-family:'Cinzel', serif;
    font-size:0.52rem;
    letter-spacing:0.08em;
    text-transform:uppercase;
    border:1px solid;
    border-radius:5px;
    cursor:pointer;
    background:transparent;
    transition:all 0.18s;
    text-decoration:none;
    white-space:nowrap;
    line-height:1.4;
}
.btn-edit   { color:var(--arcane-bright); border-color:rgba(136,144,255,0.28); }
.btn-edit:hover { background:rgba(136,144,255,0.1); border-color:var(--arcane-bright); }
.btn-del    { color:var(--error);         border-color:rgba(255,95,95,0.28); }
.btn-del:hover  { background:rgba(255,95,95,0.08); border-color:var(--error); }
.btn-tgl    { color:var(--silver);        border-color:rgba(168,180,208,0.18); }
.btn-tgl:hover  { background:rgba(168,180,208,0.07); border-color:var(--silver); }
.acts { display:flex; gap:0.38rem; flex-wrap:wrap; }

/* ─── BOUTON PRINCIPAL ───────────────────────────────────────── */
.btn-add {
    display:inline-flex;
    align-items:center;
    gap:0.5rem;
    padding:0.6rem 1.3rem;
    font-family:'Cinzel', serif;
    font-size:0.62rem;
    letter-spacing:0.14em;
    text-transform:uppercase;
    background:linear-gradient(135deg,var(--arcane),var(--void-purple));
    color:var(--white);
    border:none;
    border-radius:7px;
    cursor:pointer;
    transition:all 0.2s;
    box-shadow:0 2px 20px rgba(90,48,212,0.4);
    white-space:nowrap;
}
.btn-add:hover { box-shadow:0 4px 30px rgba(160,112,255,0.55); transform:translateY(-1px); }

/* ─── BOUTONS MODAL ──────────────────────────────────────────── */
.btn-gold {
    display:inline-flex; align-items:center; gap:0.4rem;
    padding:0.6rem 1.3rem;
    font-family:'Cinzel', serif;
    font-size:0.62rem;
    letter-spacing:0.12em;
    text-transform:uppercase;
    background:linear-gradient(135deg,#9a6418,#d4a030 40%,#f0c060 50%,#d4a030 60%,#9a6418);
    color:#1a0e00;
    border:none; border-radius:6px;
    cursor:pointer; font-weight:700;
    transition:all 0.2s;
    box-shadow:0 2px 18px rgba(200,144,40,0.4);
}
.btn-gold:hover { box-shadow:0 4px 26px rgba(200,144,40,0.6); transform:translateY(-1px); }
.btn-cancel {
    display:inline-flex; align-items:center; gap:0.4rem;
    padding:0.6rem 1.1rem;
    font-family:'Cinzel', serif;
    font-size:0.6rem; letter-spacing:0.1em;
    text-transform:uppercase;
    background:transparent;
    color:var(--silver);
    border:1px solid rgba(168,180,208,0.2);
    border-radius:6px; cursor:pointer;
    transition:all 0.18s;
}
.btn-cancel:hover { border-color:var(--silver); color:var(--silver-bright); }

/* ─── FLASH ──────────────────────────────────────────────────── */
.flash {
    padding:0.8rem 1.2rem;
    border-radius:8px;
    margin-bottom:1.6rem;
    font-size:0.9rem;
    border:1px solid;
    display:flex; align-items:center; gap:0.6rem;
    animation:fslide 0.3s ease;
}
.flash-success { background:rgba(94,255,176,0.07);  border-color:rgba(94,255,176,0.24);  color:var(--success); }
.flash-error   { background:rgba(255,95,95,0.07);   border-color:rgba(255,95,95,0.24);   color:var(--error); }
.flash-info    { background:rgba(123,130,255,0.07); border-color:rgba(123,130,255,0.24); color:var(--info); }
@keyframes fslide { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:none} }

/* ─── MODAL ──────────────────────────────────────────────────── */
.overlay {
    display:none;
    position:fixed; inset:0; z-index:500;
    background:rgba(2,3,12,0.88);
    backdrop-filter:blur(8px);
    align-items:flex-start; justify-content:center;
    padding:2rem 1rem;
    overflow-y:auto;
}
.overlay.open { display:flex; animation:fslide 0.25s ease; }
.mbox {
    background:linear-gradient(160deg,var(--abyss),var(--arcane-dark));
    border:1px solid rgba(136,144,255,0.2);
    border-radius:14px;
    padding:2rem;
    width:100%; max-width:700px;
    position:relative;
    box-shadow:0 28px 80px rgba(0,0,0,0.75);
    margin:auto;
}
.mbox::before {
    content:'';
    position:absolute; top:0; left:15%; right:15%; height:1px;
    background:linear-gradient(90deg,transparent,rgba(240,192,96,0.5),transparent);
}
.mtitle {
    font-family:'Cinzel Decorative', serif;
    font-size:1.1rem;
    color:var(--gold-bright);
    text-shadow:0 0 20px rgba(240,192,96,0.3);
    margin-bottom:1.5rem;
}
.mclose {
    position:absolute; top:1rem; right:1rem;
    background:none; border:none;
    color:rgba(168,180,208,0.35);
    font-size:1.1rem; cursor:pointer;
    transition:all 0.18s;
    width:30px; height:30px;
    display:flex; align-items:center; justify-content:center;
    border-radius:50%;
}
.mclose:hover { color:var(--error); background:rgba(255,95,95,0.1); }

/* ─── FORMULAIRE ─────────────────────────────────────────────── */
.fg { display:grid; grid-template-columns:1fr 1fr; gap:1rem 1.3rem; }
.fg .full { grid-column:1/-1; }
.fgrp { display:flex; flex-direction:column; gap:0.38rem; }
.flbl {
    font-family:'Cinzel', serif;
    font-size:0.55rem; letter-spacing:0.18em;
    color:var(--silver); text-transform:uppercase; opacity:0.8;
}
.flbl .r { color:var(--error); margin-left:0.15rem; }
.fi, .fs, .fta {
    background:rgba(6,8,26,0.8);
    border:1px solid rgba(136,144,255,0.17);
    border-radius:6px;
    padding:0.58rem 0.8rem;
    color:var(--silver-bright);
    font-family:'Crimson Pro', serif;
    font-size:0.92rem;
    outline:none;
    transition:border-color 0.2s, box-shadow 0.2s;
    width:100%;
}
.fi:focus, .fs:focus, .fta:focus {
    border-color:rgba(136,144,255,0.5);
    box-shadow:0 0 0 3px rgba(136,144,255,0.07);
}
.fs { cursor:pointer; }
.fta { resize:vertical; min-height:78px; }
.fhint { font-size:0.72rem; color:var(--silver); opacity:0.45; font-style:italic; }
.fcheck { display:flex; align-items:center; gap:0.6rem; cursor:pointer; }
.fcheck input[type="checkbox"] { width:16px; height:16px; accent-color:var(--arcane-glow); cursor:pointer; }
.fcheck-lbl {
    font-family:'Cinzel', serif;
    font-size:0.58rem; letter-spacing:0.1em;
    color:var(--silver); text-transform:uppercase;
}
.factions {
    display:flex; gap:0.8rem; justify-content:flex-end;
    margin-top:1.5rem; padding-top:1.2rem;
    border-top:1px solid rgba(136,144,255,0.1);
}
.crow { display:flex; gap:0.5rem; align-items:center; }
.crow .fi { flex:1; }

/* ─── MODAL DANGER ───────────────────────────────────────────── */
.dtitle { font-family:'Cinzel',serif; font-size:0.92rem; color:var(--error); letter-spacing:0.1em; margin-bottom:0.8rem; }
.dtext  { color:var(--silver); font-size:0.92rem; margin-bottom:1.5rem; line-height:1.6; }

/* ─── EMPTY ──────────────────────────────────────────────────── */
.empty { text-align:center; padding:3rem; color:var(--silver); opacity:0.4; font-style:italic; }

/* ─── STATUS BADGES ──────────────────────────────────────────── */
.bdg-ok        { background:rgba(94,255,176,0.08);  border-color:rgba(94,255,176,0.28);  color:var(--success); }
.bdg-failed    { background:rgba(255,95,95,0.08);   border-color:rgba(255,95,95,0.25);   color:var(--error); }
.bdg-pending   { background:rgba(123,130,255,0.08); border-color:rgba(123,130,255,0.28); color:var(--info); }
.bdg-delivered { background:rgba(94,255,176,0.08);  border-color:rgba(94,255,176,0.28);  color:var(--success); }
.bdg-error     { background:rgba(255,95,95,0.08);   border-color:rgba(255,95,95,0.25);   color:var(--error); }
.bdg-service   { background:rgba(255,192,64,0.08);  border-color:rgba(255,192,64,0.25);  color:#ffc040; }
.bdg-na        { background:rgba(168,180,208,0.08); border-color:rgba(168,180,208,0.2);  color:var(--silver); }
.bdg-online    { background:rgba(94,255,176,0.08);  border-color:rgba(94,255,176,0.28);  color:var(--success); }
.bdg-offline   { background:rgba(168,180,208,0.06); border-color:rgba(168,180,208,0.15); color:var(--silver); opacity:0.6; }
.bdg-locked    { background:rgba(255,95,95,0.08);   border-color:rgba(255,95,95,0.25);   color:var(--error); }
.bdg-gm        { background:rgba(240,192,96,0.09);  border-color:rgba(240,192,96,0.3);   color:var(--gold-bright); }

/* ─── ACCOUNTS TABLE ─────────────────────────────────────────── */
.dp-val { font-family:'Cinzel',serif; font-size:0.78rem; font-weight:700; color:var(--gold-bright); }
.vp-val { font-family:'Cinzel',serif; font-size:0.78rem; font-weight:700; color:var(--green); }
.t-email { font-size:0.78rem; color:var(--arcane-bright); opacity:0.75; font-family:monospace; }
.t-date  { font-size:0.75rem; color:var(--silver); opacity:0.55; }

/* ─── RESPONSIVE ─────────────────────────────────────────────── */
@media(max-width:1200px) { .stats { grid-template-columns:repeat(3,1fr); } }
@media(max-width:900px) {
    :root { --sw:0px; }
    .sidebar { transform:translateX(-100%); width:240px; }
    .main { margin-left:0; padding:1.4rem 1.2rem 4rem; }
}
@media(max-width:700px)  { .stats { grid-template-columns:repeat(2,1fr); } }
@media(max-width:640px) {
    .fg { grid-template-columns:1fr; }
    .fg .full { grid-column:1; }
    .page-title { font-size:1.1rem; }
    .topbar { flex-direction:column; align-items:flex-start; }
}
</style>
</head>
<body>
<canvas id="starfield"></canvas>

<div class="shell">

<!-- ═══ SIDEBAR ══════════════════════════════════════════════ -->
<aside class="sidebar">
    <div class="sb-brand">
        <div class="sb-brand-title">Eons</div>
        <div class="sb-brand-sub">Panel Admin</div>
    </div>

    <div class="sb-user"><span><?= $adminName ?></span></div>

    <nav class="sb-nav">
        <div class="sb-section">Boutique</div>
        <a href="admin.php" class="sb-link <?= ($section==='catalogue')?'active':'' ?>">
            <i>🛒</i> Catalogue
            <span class="sb-count"><?= $totalItems ?></span>
        </a>
        <a href="admin.php?section=logs" class="sb-link <?= ($section==='logs')?'active':'' ?>">
            <i>📜</i> Logs achats
        </a>
        <a href="admin.php?section=queue" class="sb-link <?= ($section==='queue')?'active':'' ?>">
            <i>⏳</i> File d'attente
        </a>
        <div class="sb-section">Joueurs</div>
        <a href="admin.php?section=accounts" class="sb-link <?= ($section==='accounts')?'active':'' ?>">
            <i>👤</i> Comptes
        </a>
    </nav>

    <div class="sb-footer">
        <a href="index.php">← Retour au site</a>
        <a href="boutique.php">🛒 Voir la boutique</a>
        <a href="logout.php" class="logout">✕ Déconnexion</a>
    </div>
</aside>

<!-- ═══ MAIN ═════════════════════════════════════════════════ -->
<main class="main">

    <div class="topbar">
        <div>
            <div class="eyebrow">Administration</div>
            <?php if ($section === 'catalogue'): ?>
            <div class="page-title">Catalogue Boutique</div>
            <?php elseif ($section === 'logs'): ?>
            <div class="page-title">Logs Achats</div>
            <?php elseif ($section === 'queue'): ?>
            <div class="page-title">File d'attente</div>
            <?php elseif ($section === 'accounts'): ?>
            <div class="page-title">Gestion des Comptes</div>
            <?php endif; ?>
        </div>
        <?php if ($section === 'catalogue'): ?>
        <button class="btn-add" onclick="openAddModal()">✦ Nouvel article</button>
        <?php endif; ?>
    </div>

    <?php if ($flash): ?>
    <div class="flash flash-<?= $flashType ?>"><?= $flash ?></div>
    <?php endif; ?>

    <?php if ($section === 'catalogue'): ?>
    <!-- Stats -->
    <div class="stats">
        <div class="stat" style="--a:var(--gold-bright)">
            <div class="stat-val"><?= $totalItems ?></div>
            <div class="stat-lbl">Total articles</div>
        </div>
        <div class="stat" style="--a:var(--success)">
            <div class="stat-val"><?= $activeItems ?></div>
            <div class="stat-lbl">Visibles</div>
        </div>
        <div class="stat" style="--a:var(--arcane-bright)">
            <div class="stat-val"><?= $cats['montures'] ?? 0 ?></div>
            <div class="stat-lbl">Montures</div>
        </div>
        <div class="stat" style="--a:var(--void-bright)">
            <div class="stat-val"><?= $cats['pets'] ?? 0 ?></div>
            <div class="stat-lbl">Familiers</div>
        </div>
        <div class="stat" style="--a:var(--gold)">
            <div class="stat-val"><?= $cats['equipement'] ?? 0 ?></div>
            <div class="stat-lbl">Équipements</div>
        </div>
        <div class="stat" style="--a:var(--info)">
            <div class="stat-val"><?= $cats['services'] ?? 0 ?></div>
            <div class="stat-lbl">Services</div>
        </div>
    </div>

    <!-- Panel catalogue -->
    <div class="panel">
        <div class="panel-head">
            <div class="panel-title">⚔ Articles du catalogue</div>
            <div class="filters">
                <button class="f-btn active" onclick="filterTbl('all',this)">Tous</button>
                <button class="f-btn" onclick="filterTbl('montures',this)">🐉 Montures</button>
                <button class="f-btn" onclick="filterTbl('pets',this)">🔥 Familiers</button>
                <button class="f-btn" onclick="filterTbl('equipement',this)">⚔️ Équipements</button>
                <button class="f-btn" onclick="filterTbl('services',this)">⚡ Services</button>
            </div>
        </div>
        <div class="panel-body">
            <div class="tbl-scroll">
            <table class="tbl" id="catalogTable">
                <thead>
                    <tr>
                        <th style="width:3rem;"></th>
                        <th>Article</th>
                        <th>Catégorie</th>
                        <th>Prix</th>
                        <th>Item ID</th>
                        <th>Ordre</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($catalog)): ?>
                <tr><td colspan="8" class="empty">Aucun article dans le catalogue.</td></tr>
                <?php else: foreach ($catalog as $item): ?>
                <tr data-category="<?= htmlspecialchars($item['category']) ?>">
                    <td class="t-icon"><?= htmlspecialchars($item['icon']) ?></td>
                    <td>
                        <div class="t-name"><?= htmlspecialchars($item['name']) ?></div>
                        <div class="t-id"><?= htmlspecialchars($item['id']) ?></div>
                    </td>
                    <td><span class="bdg bdg-cat"><?= htmlspecialchars($item['category']) ?></span></td>
                    <td>
                        <span class="t-price <?= $item['currency']==='vp'?'vp':'dp' ?>">
                            <?= number_format((int)$item['price'],0,',',' ') ?>&nbsp;<?= strtoupper($item['currency']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($item['game_item_id']>0): ?>
                            <a href="https://www.wowhead.com/fr/item=<?= (int)$item['game_item_id'] ?>"
                               target="_blank" rel="noopener"
                               style="color:var(--arcane-bright);font-size:0.78rem;text-decoration:none;">
                                #<?= (int)$item['game_item_id'] ?> 🔍
                            </a>
                        <?php else: ?>
                            <span style="color:var(--silver);opacity:0.35;font-size:0.78rem;">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:0.78rem;color:var(--silver);opacity:0.6;"><?= (int)$item['sort_order'] ?></td>
                    <td>
                        <span class="bdg <?= $item['active']?'bdg-on':'bdg-off' ?>">
                            <?= $item['active']?'Actif':'Masqué' ?>
                        </span>
                    </td>
                    <td>
                        <div class="acts">
                            <button class="btn btn-edit"
                                onclick='openEditModal(<?= htmlspecialchars(json_encode($item),ENT_QUOTES) ?>)'>
                                ✎ Éditer
                            </button>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="toggle_id" value="<?= htmlspecialchars($item['id']) ?>">
                                <button type="submit" class="btn btn-tgl">
                                    <?= $item['active']?'⊘ Masquer':'✦ Activer' ?>
                                </button>
                            </form>
                            <button class="btn btn-del"
                                onclick="confirmDel('<?= htmlspecialchars(addslashes($item['id'])) ?>','<?= htmlspecialchars(addslashes($item['name'])) ?>')">
                                🗑 Suppr.
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <?php elseif ($section === 'logs'): ?>
    <!-- ═══ LOGS ACHATS ════════════════════════════════════════ -->
    <div class="panel">
        <div class="panel-head">
            <div class="panel-title">📜 Historique des achats (200 derniers)</div>
        </div>
        <div class="panel-body">
            <div class="tbl-scroll">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Compte</th>
                        <th>Personnage</th>
                        <th>Article</th>
                        <th>Item ID</th>
                        <th>Coût</th>
                        <th>Devise</th>
                        <th>SOAP</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($shopLogs)): ?>
                <tr><td colspan="9" class="empty">Aucun achat enregistré.</td></tr>
                <?php else: foreach ($shopLogs as $log): ?>
                <tr>
                    <td style="font-size:0.75rem;opacity:0.4;"><?= (int)$log['id'] ?></td>
                    <td style="font-size:0.78rem;font-family:monospace;color:var(--arcane-bright);">#<?= (int)$log['account_id'] ?></td>
                    <td>
                        <div class="t-name" style="font-size:0.75rem;"><?= htmlspecialchars($log['char_name']) ?></div>
                    </td>
                    <td>
                        <div class="t-name" style="font-size:0.75rem;"><?= htmlspecialchars($log['item_name']) ?></div>
                    </td>
                    <td>
                        <?php if ($log['game_item_id'] > 0): ?>
                            <a href="https://www.wowhead.com/fr/item=<?= (int)$log['game_item_id'] ?>"
                               target="_blank" rel="noopener"
                               style="color:var(--arcane-bright);font-size:0.75rem;text-decoration:none;">
                                #<?= (int)$log['game_item_id'] ?> 🔍
                            </a>
                        <?php else: ?>
                            <span style="opacity:0.3;font-size:0.75rem;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="<?= $log['currency']==='vp'?'vp-val':'dp-val' ?>">
                            <?= number_format((int)$log['cost'],0,',',' ') ?>
                        </span>
                    </td>
                    <td><span class="bdg <?= $log['currency']==='vp'?'bdg-on':'bdg-cat' ?>"><?= strtoupper(htmlspecialchars($log['currency'])) ?></span></td>
                    <td>
                        <?php $ss = $log['soap_status']; ?>
                        <span class="bdg bdg-<?= $ss ?>" title="<?= htmlspecialchars($log['soap_error'] ?? '') ?>">
                            <?= htmlspecialchars($ss) ?>
                        </span>
                    </td>
                    <td class="t-date"><?= htmlspecialchars($log['created_at']) ?></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <?php elseif ($section === 'queue'): ?>
    <!-- ═══ FILE D'ATTENTE ════════════════════════════════════ -->
    <div class="panel">
        <div class="panel-head">
            <div class="panel-title">⏳ File d'attente de livraison (200 dernières)</div>
        </div>
        <div class="panel-body">
            <div class="tbl-scroll">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Compte</th>
                        <th>Personnage</th>
                        <th>Article</th>
                        <th>Qté</th>
                        <th>Devise</th>
                        <th>Statut</th>
                        <th>Tentatives</th>
                        <th>Créé le</th>
                        <th>Livré le</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($shopQueue)): ?>
                <tr><td colspan="10" class="empty">File d'attente vide.</td></tr>
                <?php else: foreach ($shopQueue as $q): ?>
                <tr>
                    <td style="font-size:0.75rem;opacity:0.4;"><?= (int)$q['id'] ?></td>
                    <td style="font-size:0.78rem;font-family:monospace;color:var(--arcane-bright);">#<?= (int)$q['account_id'] ?></td>
                    <td><div class="t-name" style="font-size:0.75rem;"><?= htmlspecialchars($q['char_name']) ?></div></td>
                    <td><div class="t-name" style="font-size:0.75rem;"><?= htmlspecialchars($q['item_name']) ?></div></td>
                    <td style="font-size:0.78rem;text-align:center;"><?= (int)$q['quantity'] ?></td>
                    <td><span class="bdg <?= $q['currency']==='vp'?'bdg-on':'bdg-cat' ?>"><?= strtoupper(htmlspecialchars($q['currency'])) ?></span></td>
                    <td><span class="bdg bdg-<?= $q['status'] ?>"><?= htmlspecialchars($q['status']) ?></span></td>
                    <td style="font-size:0.78rem;text-align:center;color:var(--silver);opacity:0.65;"><?= (int)$q['attempts'] ?></td>
                    <td class="t-date"><?= htmlspecialchars($q['created_at']) ?></td>
                    <td class="t-date"><?= $q['delivered_at'] ? htmlspecialchars($q['delivered_at']) : '<span style="opacity:0.3;">—</span>' ?></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <?php elseif ($section === 'accounts'): ?>
    <!-- ═══ COMPTES ═══════════════════════════════════════════ -->
    <div class="stats" style="grid-template-columns:repeat(4,1fr);">
        <div class="stat" style="--a:var(--gold-bright)">
            <div class="stat-val"><?= count($accounts) ?></div>
            <div class="stat-lbl">Comptes chargés</div>
        </div>
        <div class="stat" style="--a:var(--success)">
            <div class="stat-val"><?= count(array_filter($accounts,fn($a)=>$a['online'])) ?></div>
            <div class="stat-lbl">En ligne</div>
        </div>
        <div class="stat" style="--a:var(--error)">
            <div class="stat-val"><?= count(array_filter($accounts,fn($a)=>$a['locked'])) ?></div>
            <div class="stat-lbl">Bannis</div>
        </div>
        <div class="stat" style="--a:var(--gold)">
            <div class="stat-val"><?= count(array_filter($accounts,fn($a)=>$a['security_level']>=3)) ?></div>
            <div class="stat-lbl">GMs</div>
        </div>
    </div>
    <div class="panel">
        <div class="panel-head">
            <div class="panel-title">👤 Comptes joueurs</div>
        </div>
        <div class="panel-body">
            <div class="tbl-scroll">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nom d'utilisateur</th>
                        <th>Email</th>
                        <th>DP</th>
                        <th>VP</th>
                        <th>Niveau</th>
                        <th>Statut</th>
                        <th>Inscription</th>
                        <th>Dernière connexion</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($accounts)): ?>
                <tr><td colspan="9" class="empty">Aucun compte trouvé.</td></tr>
                <?php else: foreach ($accounts as $acc): ?>
                <tr>
                    <td style="font-size:0.75rem;opacity:0.4;"><?= (int)$acc['id'] ?></td>
                    <td>
                        <div class="t-name" style="font-size:0.78rem;"><?= htmlspecialchars($acc['username']) ?></div>
                    </td>
                    <td><div class="t-email"><?= htmlspecialchars($acc['email']) ?></div></td>
                    <td><span class="dp-val"><?= number_format((int)$acc['dp'],0,',',' ') ?></span></td>
                    <td><span class="vp-val"><?= number_format((int)$acc['vp'],0,',',' ') ?></span></td>
                    <td>
                        <?php $sl = (int)$acc['security_level']; ?>
                        <?php if ($sl >= 3): ?>
                            <span class="bdg bdg-gm">GM <?= $sl ?></span>
                        <?php else: ?>
                            <span style="font-size:0.75rem;color:var(--silver);opacity:0.45;"><?= $sl ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($acc['locked']): ?>
                            <span class="bdg bdg-locked">Banni</span>
                        <?php elseif ($acc['online']): ?>
                            <span class="bdg bdg-online">En ligne</span>
                        <?php else: ?>
                            <span class="bdg bdg-offline">Hors ligne</span>
                        <?php endif; ?>
                    </td>
                    <td class="t-date"><?= htmlspecialchars(substr($acc['joindate'],0,10)) ?></td>
                    <td class="t-date"><?= $acc['last_login'] ? htmlspecialchars(substr($acc['last_login'],0,16)) : '<span style="opacity:0.3;">—</span>' ?></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <?php endif; ?>

</main>
</div><!-- /shell -->


<!-- ═══ MODAL AJOUTER / ÉDITER ═══════════════════════════════ -->
<div class="overlay" id="itemModal" onclick="closeModalOvl(event)">
<div class="mbox">
    <button class="mclose" onclick="closeItemModal()">✕</button>
    <div class="mtitle" id="modalTitle">✦ Nouvel article</div>

    <form method="POST" id="itemForm">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="action" id="formAction" value="add">
        <input type="hidden" name="original_id" id="originalId" value="">

        <div class="fg">
            <div class="fgrp">
                <label class="flbl" for="f_id">Identifiant <span class="r">*</span></label>
                <input class="fi" type="text" id="f_id" name="id"
                       placeholder="ability_mount_spectral" required
                       pattern="[a-z0-9_\-]+" title="Minuscules, chiffres, _ et - uniquement">
                <div class="fhint">Unique, minuscules. Ex&nbsp;: ability_mount_spectraltiger_dp</div>
            </div>
            <div class="fgrp">
                <label class="flbl" for="f_name">Nom <span class="r">*</span></label>
                <input class="fi" type="text" id="f_name" name="name"
                       placeholder="Rênes de tigre spectral" required maxlength="128">
            </div>
            <div class="fgrp full">
                <label class="flbl" for="f_desc">Description <span class="r">*</span></label>
                <textarea class="fta" id="f_desc" name="description"
                          placeholder="Invoque et renvoie..." required></textarea>
            </div>
            <div class="fgrp">
                <label class="flbl" for="f_icon">Icône (emoji)</label>
                <input class="fi" type="text" id="f_icon" name="icon" value="🎁" maxlength="8">
            </div>
            <div class="fgrp">
                <label class="flbl" for="f_category">Catégorie <span class="r">*</span></label>
                <select class="fs" id="f_category" name="category" required>
                    <option value="montures">🐉 Montures</option>
                    <option value="pets">🔥 Familiers</option>
                    <option value="equipement">⚔️ Équipements</option>
                    <option value="services">⚡ Services</option>
                </select>
            </div>
            <div class="fgrp">
                <label class="flbl" for="f_price">Prix <span class="r">*</span></label>
                <input class="fi" type="number" id="f_price" name="price" min="0" placeholder="800" required>
            </div>
            <div class="fgrp">
                <label class="flbl" for="f_currency">Devise</label>
                <select class="fs" id="f_currency" name="currency">
                    <option value="dp">DP — Donor Points</option>
                    <option value="vp">VP — Vote Points</option>
                </select>
            </div>
            <div class="fgrp">
                <label class="flbl" for="f_game_item_id">Game Item ID</label>
                <input class="fi" type="number" id="f_game_item_id" name="game_item_id" min="0" value="0" placeholder="33224">
                <div class="fhint">ID Wowhead/TrinityCore. 0&nbsp;= service sans item.</div>
            </div>
            <div class="fgrp">
                <label class="flbl" for="f_quantity">Quantité</label>
                <input class="fi" type="number" id="f_quantity" name="quantity" min="1" value="1">
            </div>
            <div class="fgrp full">
                <label class="flbl" for="f_soap_cmd">Commande SOAP GM (services)</label>
                <input class="fi" type="text" id="f_soap_cmd" name="soap_cmd" placeholder=".character rename %char%">
                <div class="fhint">%char% = nom du personnage. Vide = envoi d'item normal.</div>
            </div>
            <div class="fgrp">
                <label class="flbl" for="f_badge">Badge</label>
                <input class="fi" type="text" id="f_badge" name="badge" placeholder="Légendaire" maxlength="32">
            </div>
            <div class="fgrp">
                <label class="flbl" for="f_badge_color">Couleur badge</label>
                <div class="crow">
                    <input class="fi" type="text" id="f_badge_color" name="badge_color"
                           placeholder="#f0c060" maxlength="7"
                           oninput="document.getElementById('clrpick').value=this.value">
                    <input type="color" id="clrpick" value="#f0c060"
                           style="width:40px;height:40px;border:none;background:none;cursor:pointer;padding:0;border-radius:5px;flex-shrink:0;"
                           oninput="document.getElementById('f_badge_color').value=this.value">
                </div>
            </div>
            <div class="fgrp">
                <label class="flbl" for="f_ribbon">Ruban</label>
                <input class="fi" type="text" id="f_ribbon" name="ribbon" placeholder="Populaire" maxlength="32">
            </div>
            <div class="fgrp">
                <label class="flbl" for="f_sort_order">Ordre d'affichage</label>
                <input class="fi" type="number" id="f_sort_order" name="sort_order" value="0">
                <div class="fhint">Tri ASC par catégorie. Ex&nbsp;: 10, 20, 30…</div>
            </div>
            <div class="fgrp full" style="flex-direction:row;align-items:center;justify-content:flex-end;">
                <label class="fcheck">
                    <input type="checkbox" name="active" id="f_active" value="1" checked>
                    <span class="fcheck-lbl">Visible en boutique</span>
                </label>
            </div>
        </div>

        <div class="factions">
            <button type="button" class="btn-cancel" onclick="closeItemModal()">Annuler</button>
            <button type="submit" class="btn-gold" id="fsubmit">✦ Enregistrer</button>
        </div>
    </form>
</div>
</div>


<!-- ═══ MODAL SUPPRESSION ════════════════════════════════════ -->
<div class="overlay" id="delModal" onclick="closeDelOvl(event)">
<div class="mbox" style="max-width:440px;">
    <button class="mclose" onclick="closeDelModal()">✕</button>
    <div class="dtitle">⚠ Confirmer la suppression</div>
    <p class="dtext">
        Vous êtes sur le point de supprimer l'article<br>
        <strong id="delName" style="color:var(--white);"></strong>.<br><br>
        Cette action est <strong style="color:var(--error);">irréversible</strong>.
    </p>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="delete_id" id="delId">
        <div class="factions">
            <button type="button" class="btn-cancel" onclick="closeDelModal()">Annuler</button>
            <button type="submit" class="btn btn-del" style="padding:0.5rem 1.1rem;font-size:0.58rem;">
                🗑 Supprimer définitivement
            </button>
        </div>
    </form>
</div>
</div>


<script>
// ─── FILTRE ───────────────────────────────────────────────────
function filterTbl(cat, btn) {
    document.querySelectorAll('.f-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('#catalogTable tbody tr').forEach(r => {
        r.style.display = (cat==='all' || r.dataset.category===cat) ? '' : 'none';
    });
}

// ─── MODAL ITEM ───────────────────────────────────────────────
function openAddModal() {
    document.getElementById('modalTitle').textContent = '✦ Nouvel article';
    document.getElementById('formAction').value = 'add';
    document.getElementById('originalId').value = '';
    document.getElementById('itemForm').reset();
    document.getElementById('f_active').checked = true;
    document.getElementById('fsubmit').textContent = '✦ Ajouter';
    openOverlay('itemModal');
}
function openEditModal(item) {
    document.getElementById('modalTitle').textContent = '✎ Modifier l\'article';
    document.getElementById('formAction').value     = 'edit';
    document.getElementById('originalId').value     = item.id;
    document.getElementById('f_id').value           = item.id;
    document.getElementById('f_name').value         = item.name;
    document.getElementById('f_desc').value         = item.description;
    document.getElementById('f_icon').value         = item.icon;
    document.getElementById('f_price').value        = item.price;
    document.getElementById('f_currency').value     = item.currency;
    document.getElementById('f_category').value     = item.category;
    document.getElementById('f_game_item_id').value = item.game_item_id;
    document.getElementById('f_quantity').value     = item.quantity;
    document.getElementById('f_soap_cmd').value     = item.soap_cmd ?? '';
    document.getElementById('f_badge').value        = item.badge ?? '';
    document.getElementById('f_badge_color').value  = item.badge_color ?? '';
    document.getElementById('clrpick').value        = item.badge_color ?? '#f0c060';
    document.getElementById('f_ribbon').value       = item.ribbon ?? '';
    document.getElementById('f_sort_order').value   = item.sort_order;
    document.getElementById('f_active').checked     = item.active == 1;
    document.getElementById('fsubmit').textContent  = '✦ Mettre à jour';
    openOverlay('itemModal');
}
function closeItemModal() { closeOverlay('itemModal'); }
function closeModalOvl(e) { if(e.target.id==='itemModal') closeItemModal(); }

// ─── MODAL SUPPRESSION ────────────────────────────────────────
function confirmDel(id, name) {
    document.getElementById('delId').value = id;
    document.getElementById('delName').textContent = name;
    openOverlay('delModal');
}
function closeDelModal() { closeOverlay('delModal'); }
function closeDelOvl(e) { if(e.target.id==='delModal') closeDelModal(); }

// ─── HELPERS OVERLAY ──────────────────────────────────────────
function openOverlay(id) {
    document.getElementById(id).classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeOverlay(id) {
    document.getElementById(id).classList.remove('open');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => {
    if (e.key==='Escape') { closeItemModal(); closeDelModal(); }
});

// ─── STARFIELD ────────────────────────────────────────────────
(function(){
    const c=document.getElementById('starfield'), ctx=c.getContext('2d');
    let W,H,stars=[];
    function resize(){ W=c.width=innerWidth; H=c.height=innerHeight; }
    function init(){
        stars=[];
        for(let i=0;i<180;i++) stars.push({
            x:Math.random()*W, y:Math.random()*H,
            r:Math.random()*1.2+0.1, a:Math.random()*0.6+0.15,
            spd:Math.random()*0.2+0.03, ph:Math.random()*Math.PI*2,
            col:Math.random()>.7?'rgba(240,192,96,':'rgba(136,144,255,'
        });
    }
    let t=0;
    function draw(){
        ctx.clearRect(0,0,W,H);
        stars.forEach(s=>{
            const f=Math.sin(t*s.spd+s.ph)*0.3+0.7;
            ctx.beginPath(); ctx.arc(s.x,s.y,s.r,0,Math.PI*2);
            ctx.fillStyle=s.col+(s.a*f)+')'; ctx.fill();
        });
        t+=0.01; requestAnimationFrame(draw);
    }
    window.addEventListener('resize',()=>{resize();init();});
    resize(); init(); draw();
})();

// ─── AUTO-EDIT ────────────────────────────────────────────────
<?php if ($editItem): ?>
openEditModal(<?= json_encode($editItem) ?>);
<?php endif; ?>

// ─── FLASH AUTO-HIDE ──────────────────────────────────────────
(function(){
    const f=document.querySelector('.flash');
    if(f) setTimeout(()=>{
        f.style.transition='opacity 0.6s';
        f.style.opacity='0';
        setTimeout(()=>f.remove(),650);
    },4000);
})();
</script>
</body>
</html>
