<?php
// ============================================================
//  actualites.php — Eons CMS | Arcanic Theme
//  Affichage des actualités du serveur
// ============================================================
require_once __DIR__ . '/config.php';

// ── Paramètres ────────────────────────────────────────────────
$perPage    = 6;
$page       = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page - 1) * $perPage;
$filterCat  = $_GET['cat'] ?? '';

$categories = [
    ''            => 'Toutes',
    'annonce'     => 'Annonces',
    'patch'       => 'Patchs',
    'event'       => 'Événements',
    'maintenance' => 'Maintenance',
    'hotfix'      => 'Hotfix',
];

$catIcons = [
    'annonce'     => '📜',
    'patch'       => '⚙️',
    'event'       => '🌟',
    'maintenance' => '🔧',
    'hotfix'      => '⚡',
];

$catColors = [
    'annonce'     => '#8890ff',
    'patch'       => '#f0c060',
    'event'       => '#a070ff',
    'maintenance' => '#ff9966',
    'hotfix'      => '#5fffb0',
];

// ── Récupération news ─────────────────────────────────────────
$news       = [];
$total      = 0;
$pinnedNews = [];
$error      = null;

try {
    $pdo = getAuthDB();

    // WHERE clause
    $where = 'published = 1';
    $params = [];
    if ($filterCat && array_key_exists($filterCat, $categories) && $filterCat !== '') {
        $where .= ' AND category = :cat';
        $params[':cat'] = $filterCat;
    }

    // Épinglées (page 1, sans filtre actif)
    if ($page === 1 && $filterCat === '') {
        $stmtPin = $pdo->prepare("SELECT * FROM news WHERE published = 1 AND pinned = 1 ORDER BY created_at DESC LIMIT 3");
        $stmtPin->execute();
        $pinnedNews = $stmtPin->fetchAll();
    }

    // Comptage total
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM news WHERE $where" . ($page === 1 && $filterCat === '' ? ' AND pinned = 0' : ''));
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();

    // News paginées
    $notPinned = ($page === 1 && $filterCat === '') ? ' AND pinned = 0' : '';
    $stmtNews  = $pdo->prepare("SELECT * FROM news WHERE $where $notPinned ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
    $stmtNews->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $stmtNews->bindValue(':offset', $offset,  PDO::PARAM_INT);
    foreach ($params as $k => $v) $stmtNews->bindValue($k, $v);
    $stmtNews->execute();
    $news = $stmtNews->fetchAll();

} catch (PDOException $e) {
    error_log('[Eons Actualités] DB error: ' . $e->getMessage());
    $error = 'Impossible de charger les actualités. Veuillez réessayer plus tard.';
}

$totalPages = max(1, (int)ceil($total / $perPage));

// ── Formatage date ────────────────────────────────────────────
function formatDate(string $date): string {
    $ts = strtotime($date);
    $months = ['jan.','fév.','mar.','avr.','mai','juin','juil.','août','sep.','oct.','nov.','déc.'];
    return intval(date('d', $ts)) . ' ' . $months[intval(date('m', $ts)) - 1] . ' ' . date('Y', $ts);
}

$pageTitle = 'Actualités — Eons';
require_once __DIR__ . '/header.php';
?>
<style>
/* ── Fix débordement horizontal global ───────────────────────── */
html, body {
    overflow-x: clip;
    width: 100%;
    box-sizing: border-box;
}

/* ─── HERO ACTUALITÉS ────────────────────────────────────────── */
.news-hero {
    position: relative;
    z-index: 10;
    padding: 130px 1rem 50px;
    text-align: center;
    overflow-x: hidden;
}

.news-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(ellipse 80% 60% at 50% 0%, rgba(26,29,90,0.55) 0%, transparent 60%);
    pointer-events: none;
}

.news-hero-tag {
    display: inline-block;
    font-family: 'Cinzel', serif;
    font-size: 0.6rem;
    letter-spacing: 0.35em;
    text-transform: uppercase;
    color: var(--arcane-bright);
    border: 1px solid rgba(136,144,255,0.3);
    border-radius: 2px;
    padding: 0.3rem 1rem;
    margin-bottom: 1.2rem;
    position: relative;
    z-index: 1;
    animation: tagPulse 3s ease-in-out infinite;
}

@keyframes tagPulse {
    0%,100% { box-shadow: 0 0 8px rgba(136,144,255,0.2); }
    50%      { box-shadow: 0 0 18px rgba(136,144,255,0.5); }
}

.news-hero h1 {
    font-family: 'Cinzel Decorative', serif;
    font-size: clamp(1.8rem, 4vw, 3rem);
    color: var(--gold-bright);
    text-shadow: 0 0 30px rgba(240,192,96,0.5), 0 0 70px rgba(240,192,96,0.15);
    position: relative;
    z-index: 1;
    margin-bottom: 0.8rem;
}

.news-hero p {
    font-family: 'Crimson Pro', serif;
    font-size: 1.15rem;
    color: var(--silver);
    max-width: 520px;
    margin: 0 auto;
    position: relative;
    z-index: 1;
}

/* ─── SÉPARATEUR ARCANIQUE ───────────────────────────────────── */
.arcane-divider {
    width: 100%;
    max-width: 500px;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(136,144,255,0.5), rgba(240,192,96,0.4), rgba(136,144,255,0.5), transparent);
    margin: 1rem auto 1.5rem;
    position: relative;
    z-index: 1;
}

.arcane-divider::before, .arcane-divider::after {
    content: '◆';
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.55rem;
    color: var(--gold);
}
.arcane-divider::before { left: calc(50% - 6px); }
.arcane-divider::after  { left: calc(50% + 2px); }

/* ─── WRAPPER PRINCIPAL ──────────────────────────────────────── */
.news-wrapper {
    position: relative;
    z-index: 10;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem 6rem;
}

/* ─── FILTRES ────────────────────────────────────────────────── */
.news-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: center;
    align-items: center;
    position: relative;
    z-index: 1;
    padding: 0 2rem;
    margin: 0 auto;
    max-width: 800px;
    width: 100%;
    box-sizing: border-box;
}

.filter-btn {
    font-family: 'Cinzel', serif;
    font-size: 0.6rem;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    color: var(--silver);
    background: rgba(15,18,48,0.7);
    border: 1px solid rgba(136,144,255,0.2);
    border-radius: 3px;
    padding: 0.45rem 1rem;
    text-decoration: none;
    transition: all 0.3s;
    backdrop-filter: blur(8px);
}

.filter-btn:hover {
    color: var(--arcane-bright);
    border-color: rgba(136,144,255,0.5);
    background: rgba(72,85,212,0.15);
    box-shadow: 0 0 12px rgba(136,144,255,0.2);
}

.filter-btn.active {
    color: var(--gold-bright);
    border-color: rgba(240,192,96,0.45);
    background: rgba(200,144,40,0.12);
    box-shadow: 0 0 14px rgba(240,192,96,0.18);
}

/* ─── NEWS ÉPINGLÉE (hero card) ──────────────────────────────── */
.pinned-section {
    margin-bottom: 3rem;
}

.pinned-label {
    font-family: 'Cinzel', serif;
    font-size: 0.6rem;
    letter-spacing: 0.25em;
    text-transform: uppercase;
    color: var(--gold);
    margin-bottom: 1.2rem;
    display: flex;
    align-items: center;
    gap: 0.7rem;
}

.pinned-label::before, .pinned-label::after {
    content: '';
    flex: 1;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(200,144,40,0.35));
}
.pinned-label::after {
    background: linear-gradient(270deg, transparent, rgba(200,144,40,0.35));
}

.pinned-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

/* ─── CARD GÉNÉRIQUE ─────────────────────────────────────────── */
.news-card {
    position: relative;
    background: linear-gradient(145deg, rgba(15,18,48,0.85) 0%, rgba(6,8,26,0.9) 100%);
    border: 1px solid rgba(136,144,255,0.14);
    border-radius: 6px;
    overflow: hidden;
    transition: transform 0.35s ease, box-shadow 0.35s ease, border-color 0.35s ease;
    backdrop-filter: blur(12px);
    display: flex;
    flex-direction: column;
}

.news-card::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(136,144,255,0.04) 0%, transparent 60%);
    pointer-events: none;
    transition: opacity 0.35s;
    opacity: 0;
}

.news-card:hover {
    transform: translateY(-4px);
    border-color: rgba(136,144,255,0.35);
    box-shadow: 0 12px 40px rgba(72,85,212,0.2), 0 0 0 1px rgba(136,144,255,0.08);
}

.news-card:hover::before { opacity: 1; }

/* Image bannière */
.card-image {
    width: 100%;
    height: 180px;
    object-fit: cover;
    display: block;
    filter: brightness(0.75) saturate(0.9);
    transition: filter 0.35s;
}

.news-card:hover .card-image { filter: brightness(0.9) saturate(1.1); }

.card-image-placeholder {
    width: 100%;
    height: 160px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    background: linear-gradient(135deg, rgba(26,29,90,0.7), rgba(9,12,34,0.9));
    border-bottom: 1px solid rgba(136,144,255,0.1);
    flex-shrink: 0;
}

/* Badge catégorie */
.card-badge {
    position: absolute;
    top: 1rem;
    left: 1rem;
    font-family: 'Cinzel', serif;
    font-size: 0.52rem;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    padding: 0.25rem 0.65rem;
    border-radius: 2px;
    border: 1px solid;
    backdrop-filter: blur(8px);
    font-weight: 600;
}

/* Badge épinglée */
.pin-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    font-size: 0.7rem;
    background: rgba(200,144,40,0.18);
    border: 1px solid rgba(240,192,96,0.35);
    border-radius: 2px;
    padding: 0.2rem 0.5rem;
    color: var(--gold-bright);
    font-family: 'Cinzel', serif;
    font-size: 0.5rem;
    letter-spacing: 0.12em;
}

/* Corps carte */
.card-body {
    padding: 1.4rem 1.6rem 1.6rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.card-meta {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    margin-bottom: 0.75rem;
    font-family: 'Cinzel', serif;
    font-size: 0.56rem;
    letter-spacing: 0.1em;
    color: var(--silver);
    opacity: 0.7;
}

.card-meta-sep { opacity: 0.4; }

.card-title {
    font-family: 'Cinzel', serif;
    font-size: 1rem;
    font-weight: 700;
    color: var(--gold-pale);
    margin-bottom: 0.7rem;
    line-height: 1.4;
    text-shadow: 0 0 18px rgba(240,192,96,0.2);
}

.card-excerpt {
    font-family: 'Crimson Pro', serif;
    font-size: 0.95rem;
    color: var(--silver);
    line-height: 1.65;
    flex: 1;
    margin-bottom: 1.2rem;
}

.card-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-family: 'Cinzel', serif;
    font-size: 0.58rem;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--arcane-bright);
    text-decoration: none;
    transition: color 0.3s, gap 0.3s;
    margin-top: auto;
    width: fit-content;
}

.card-link:hover {
    color: var(--gold-bright);
    gap: 0.8rem;
}

.card-link svg { width: 14px; height: 14px; transition: transform 0.3s; }
.card-link:hover svg { transform: translateX(3px); }

/* ─── GRILLE PRINCIPALE ──────────────────────────────────────── */
.news-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.8rem;
    margin-bottom: 3rem;
}

/* ─── ÉTAT VIDE ──────────────────────────────────────────────── */
.news-empty {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--silver);
    opacity: 0.6;
}

.news-empty-icon { font-size: 3rem; margin-bottom: 1rem; }

.news-empty h3 {
    font-family: 'Cinzel', serif;
    font-size: 1rem;
    color: var(--arcane-bright);
    margin-bottom: 0.5rem;
}

/* ─── ERREUR ─────────────────────────────────────────────────── */
.news-error {
    background: rgba(255,95,95,0.08);
    border: 1px solid rgba(255,95,95,0.25);
    border-radius: 6px;
    padding: 1.5rem 2rem;
    text-align: center;
    color: var(--error);
    font-family: 'Cinzel', serif;
    font-size: 0.85rem;
    letter-spacing: 0.05em;
}

/* ─── PAGINATION ─────────────────────────────────────────────── */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
    margin-top: 2rem;
}

.page-btn {
    font-family: 'Cinzel', serif;
    font-size: 0.62rem;
    letter-spacing: 0.1em;
    color: var(--silver);
    background: rgba(15,18,48,0.7);
    border: 1px solid rgba(136,144,255,0.2);
    border-radius: 3px;
    padding: 0.5rem 0.9rem;
    text-decoration: none;
    transition: all 0.3s;
    min-width: 38px;
    text-align: center;
}

.page-btn:hover {
    color: var(--arcane-bright);
    border-color: rgba(136,144,255,0.45);
    background: rgba(72,85,212,0.15);
}

.page-btn.current {
    color: var(--gold-bright);
    border-color: rgba(240,192,96,0.45);
    background: rgba(200,144,40,0.12);
    pointer-events: none;
}

.page-btn.disabled {
    opacity: 0.3;
    pointer-events: none;
}

/* ─── MODAL ARTICLE ──────────────────────────────────────────── */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 5000;
    background: rgba(2,3,12,0.92);
    backdrop-filter: blur(10px);
    padding: 2rem 1rem;
    overflow-y: auto;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.modal-overlay.open { display: flex; align-items: flex-start; justify-content: center; }

.modal-box {
    position: relative;
    background: linear-gradient(155deg, rgba(15,18,48,0.97) 0%, rgba(6,8,26,0.98) 100%);
    border: 1px solid rgba(136,144,255,0.22);
    border-radius: 8px;
    width: 100%;
    max-width: 760px;
    padding: 2.5rem 2.5rem 3rem;
    box-shadow: 0 0 80px rgba(72,85,212,0.18), 0 40px 80px rgba(0,0,0,0.6);
    animation: slideUp 0.35s cubic-bezier(0.16,1,0.3,1);
    margin-top: 60px;
}

@keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.modal-close {
    position: absolute;
    top: 1.2rem;
    right: 1.4rem;
    font-family: 'Cinzel', serif;
    font-size: 0.6rem;
    letter-spacing: 0.15em;
    color: var(--silver);
    background: none;
    border: 1px solid rgba(136,144,255,0.2);
    border-radius: 3px;
    padding: 0.35rem 0.8rem;
    cursor: pointer;
    transition: all 0.3s;
    text-transform: uppercase;
}

.modal-close:hover {
    color: var(--error);
    border-color: rgba(255,95,95,0.4);
}

.modal-badge {
    display: inline-block;
    font-family: 'Cinzel', serif;
    font-size: 0.52rem;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    padding: 0.25rem 0.75rem;
    border-radius: 2px;
    border: 1px solid;
    margin-bottom: 1rem;
}

.modal-title {
    font-family: 'Cinzel Decorative', serif;
    font-size: clamp(1.1rem, 3vw, 1.6rem);
    color: var(--gold-bright);
    text-shadow: 0 0 20px rgba(240,192,96,0.35);
    margin-bottom: 0.5rem;
    line-height: 1.3;
}

.modal-meta {
    font-family: 'Cinzel', serif;
    font-size: 0.58rem;
    letter-spacing: 0.1em;
    color: var(--silver);
    opacity: 0.6;
    margin-bottom: 1.5rem;
    display: flex;
    gap: 0.8rem;
    flex-wrap: wrap;
}

.modal-divider {
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(136,144,255,0.3), transparent);
    margin-bottom: 1.8rem;
}

.modal-content {
    font-family: 'Crimson Pro', serif;
    font-size: 1.05rem;
    line-height: 1.85;
    color: var(--silver-bright);
}

.modal-content h3 {
    font-family: 'Cinzel', serif;
    font-size: 0.85rem;
    color: var(--gold-pale);
    letter-spacing: 0.1em;
    margin: 1.5rem 0 0.7rem;
    text-transform: uppercase;
}

.modal-content p  { margin-bottom: 0.9rem; }
.modal-content ul { padding-left: 1.4rem; margin-bottom: 0.9rem; }
.modal-content li { margin-bottom: 0.3rem; }

.modal-content strong { color: var(--gold-pale); }

/* ─── RESPONSIVE ─────────────────────────────────────────────── */
@media (max-width: 640px) {
    .news-grid    { grid-template-columns: 1fr; }
    .pinned-grid  { grid-template-columns: 1fr; }
    .modal-box    { padding: 1.8rem 1.4rem 2.2rem; }
    .news-filters {
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: visible;
        justify-content: flex-start;
        padding: 0.25rem 1rem;
        gap: 0.4rem;
        scrollbar-width: none;
        -webkit-overflow-scrolling: touch;
    }
    .news-filters::after {
        content: '';
        flex: 0 0 0.5rem;
    }
    .news-filters::-webkit-scrollbar { display: none; }
    .filter-btn {
        flex: 0 0 auto;
        font-size: 0.52rem;
        padding: 0.38rem 0.75rem;
        letter-spacing: 0.08em;
        white-space: nowrap;
    }
}
</style>

<?php
// ── Données des news pour le modal (JSON) ─────────────────────
$allNewsForModal = array_map(fn($n) => [
    'id'        => $n['id'],
    'title'     => $n['title'],
    'category'  => $n['category'],
    'author'    => $n['author'],
    'date'      => formatDate($n['created_at']),
    'content'   => $n['content'],
], array_merge($pinnedNews, $news));
?>

<!-- ─── HERO ──────────────────────────────────────────────────── -->
<section class="news-hero reveal">
    <div class="news-hero-tag">Eons · Chroniques du serveur</div>
    <h1>Actualités</h1>
    <p>Restez informé des dernières nouvelles, mises à jour et événements du serveur.</p>
    <div class="arcane-divider"></div>

    <!-- Filtres catégories -->
    <nav class="news-filters" aria-label="Filtres par catégorie">
        <?php foreach ($categories as $slug => $label): ?>
            <?php
                $url = $slug === ''
                    ? 'actualites.php'
                    : 'actualites.php?cat=' . urlencode($slug);
                $active = ($filterCat === $slug) ? 'active' : '';
                $icon = $catIcons[$slug] ?? '';
            ?>
            <a href="<?= $url ?>" class="filter-btn <?= $active ?>">
                <?= $icon ? $icon . ' ' : '' ?><?= htmlspecialchars($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>
</section>

<!-- ─── CONTENU ──────────────────────────────────────────────── -->
<div class="news-wrapper">

    <?php if ($error): ?>
        <div class="news-error">⚠ <?= htmlspecialchars($error) ?></div>

    <?php else: ?>

        <!-- News épinglées -->
        <?php if (!empty($pinnedNews)): ?>
            <section class="pinned-section reveal">
                <div class="pinned-label">À la une</div>
                <div class="pinned-grid">
                    <?php foreach ($pinnedNews as $n): ?>
                        <?php renderCard($n, $catColors, $catIcons, true); ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- Grille principale -->
        <?php if (empty($news) && empty($pinnedNews)): ?>
            <div class="news-empty reveal">
                <div class="news-empty-icon">📜</div>
                <h3>Aucune actualité</h3>
                <p>Aucune actualité ne correspond à ce filtre pour l'instant.</p>
            </div>
        <?php elseif (!empty($news)): ?>
            <div class="news-grid">
                <?php foreach ($news as $n): ?>
                    <?php renderCard($n, $catColors, $catIcons, false); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="Pagination">
                <?php
                $baseUrl = 'actualites.php?' . ($filterCat ? 'cat=' . urlencode($filterCat) . '&' : '');
                ?>
                <a href="<?= $baseUrl ?>page=<?= $page - 1 ?>"
                   class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"
                   aria-label="Page précédente">←</a>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i === 1 || $i === $totalPages || abs($i - $page) <= 2): ?>
                        <a href="<?= $baseUrl ?>page=<?= $i ?>"
                           class="page-btn <?= $i === $page ? 'current' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php elseif (abs($i - $page) === 3): ?>
                        <span class="page-btn disabled">…</span>
                    <?php endif; ?>
                <?php endfor; ?>

                <a href="<?= $baseUrl ?>page=<?= $page + 1 ?>"
                   class="page-btn <?= $page >= $totalPages ? 'disabled' : '' ?>"
                   aria-label="Page suivante">→</a>
            </nav>
        <?php endif; ?>

    <?php endif; ?>
</div>

<!-- ─── MODAL ARTICLE ─────────────────────────────────────────── -->
<div class="modal-overlay" id="newsModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()" aria-label="Fermer">✕ Fermer</button>
        <div id="modalBadge" class="modal-badge"></div>
        <h2 class="modal-title" id="modalTitle"></h2>
        <div class="modal-meta" id="modalMeta"></div>
        <div class="modal-divider"></div>
        <div class="modal-content" id="modalContent"></div>
    </div>
</div>

<script>
// ─── Données news ─────────────────────────────────────────────
const NEWS_DATA = <?= json_encode($allNewsForModal, JSON_HEX_TAG | JSON_HEX_AMP) ?>;

const CAT_COLORS = <?= json_encode($catColors) ?>;
const CAT_LABELS = {
    annonce:     'Annonce',
    patch:       'Patch',
    event:       'Événement',
    maintenance: 'Maintenance',
    hotfix:      'Hotfix',
};

// ─── Modal ────────────────────────────────────────────────────
function openNews(id) {
    const n = NEWS_DATA.find(x => x.id == id);
    if (!n) return;

    const color = CAT_COLORS[n.category] || '#8890ff';
    const label = CAT_LABELS[n.category] || n.category;

    const badge = document.getElementById('modalBadge');
    badge.textContent = label;
    badge.style.color       = color;
    badge.style.borderColor = color + '55';
    badge.style.background  = color + '15';

    document.getElementById('modalTitle').textContent   = n.title;
    document.getElementById('modalMeta').innerHTML      =
        `<span>✍ ${escHtml(n.author)}</span><span>◆</span><span>📅 ${escHtml(n.date)}</span>`;
    document.getElementById('modalContent').innerHTML   = n.content; // HTML contrôlé côté serveur

    const modal = document.getElementById('newsModal');
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('newsModal').classList.remove('open');
    document.body.style.overflow = '';
}

// Fermer en cliquant hors du box
document.getElementById('newsModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// Fermer avec Escape
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php
// ── Fonction rendu carte (appelée dans le template) ───────────
function renderCard(array $n, array $catColors, array $catIcons, bool $pinned): void {
    $color   = $catColors[$n['category']] ?? '#8890ff';
    $icon    = $catIcons[$n['category']] ?? '📜';
    $catLabels = [
        'annonce'     => 'Annonce',
        'patch'       => 'Patch',
        'event'       => 'Événement',
        'maintenance' => 'Maintenance',
        'hotfix'      => 'Hotfix',
    ];
    $catLabel = $catLabels[$n['category']] ?? ucfirst($n['category']);
    $date     = formatDate($n['created_at']);
    $excerpt  = htmlspecialchars($n['excerpt']);
    $title    = htmlspecialchars($n['title']);
    $author   = htmlspecialchars($n['author']);
    $id       = (int)$n['id'];
    $imgUrl   = !empty($n['image_url']) ? htmlspecialchars($n['image_url']) : '';
    ?>
    <article class="news-card reveal" onclick="openNews(<?= $id ?>)" style="cursor:pointer;" tabindex="0"
             onkeydown="if(event.key==='Enter')openNews(<?= $id ?>)"
             aria-label="Lire l'article : <?= $title ?>">

        <?php if ($imgUrl): ?>
            <img src="<?= $imgUrl ?>" alt="" class="card-image" loading="lazy">
        <?php else: ?>
            <div class="card-image-placeholder"><?= $icon ?></div>
        <?php endif; ?>

        <span class="card-badge"
              style="color:<?= $color ?>;border-color:<?= $color ?>55;background:<?= $color ?>18;">
            <?= htmlspecialchars($catLabel) ?>
        </span>

        <?php if ($pinned): ?>
            <span class="pin-badge">📌 Épinglé</span>
        <?php endif; ?>

        <div class="card-body">
            <div class="card-meta">
                <span><?= $date ?></span>
                <span class="card-meta-sep">·</span>
                <span><?= $author ?></span>
            </div>
            <h2 class="card-title"><?= $title ?></h2>
            <p class="card-excerpt"><?= $excerpt ?></p>
            <span class="card-link">
                Lire la suite
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </span>
        </div>
    </article>
    <?php
}
?>
