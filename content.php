<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Eons : Contenu</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
<style>
  :root {
    --gold: #c9a84c;
    --gold-light: #e8c97a;
    --gold-dim: #7a6130;
    --bg-page: #0f0e0d;
    --bg-card: #1a1916;
    --bg-sec: #161513;
    --border: rgba(201,168,76,0.12);
    --border-strong: rgba(201,168,76,0.22);
    --text: #e8e2d6;
    --text-muted: #8a8070;
  }

  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    background: var(--bg-page);
    color: var(--text);
    min-height: 100vh;
    position: relative;
  }

  body::before {
    content: '';
    position: fixed;
    inset: 0;
    background-image:
      radial-gradient(ellipse 80% 50% at 50% -10%, rgba(201,168,76,0.07) 0%, transparent 60%),
      radial-gradient(ellipse 40% 30% at 80% 80%, rgba(201,168,76,0.04) 0%, transparent 50%);
    pointer-events: none;
    z-index: 0;
  }

  body::after {
    content: '';
    position: fixed;
    inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
    background-size: 256px 256px;
    opacity: 0.5;
    pointer-events: none;
    z-index: 0;
  }

  .page {
    position: relative;
    z-index: 1;
    max-width: 760px;
    margin: 0 auto;
    padding: 3rem 2rem 4rem;
  }

  .header { text-align: center; margin-bottom: 2.5rem; }
  .server-name {
    font-size: 32px;
    font-weight: 600;
    color: var(--gold);
    letter-spacing: 5px;
    text-transform: uppercase;
    text-shadow: 0 0 40px rgba(201,168,76,0.25);
  }
  .subtitle { font-size: 12px; color: var(--text-muted); margin-top: 6px; letter-spacing: 2px; text-transform: uppercase; }
  .badge-version {
    display: inline-block;
    background: rgba(201,168,76,0.15);
    color: var(--gold);
    border: 0.5px solid var(--gold-dim);
    font-size: 11px;
    font-weight: 500;
    padding: 4px 14px;
    border-radius: 20px;
    margin-top: 12px;
    letter-spacing: 1px;
  }

  .divider {
    border: none;
    border-top: 0.5px solid rgba(201,168,76,0.2);
    margin: 2rem 0;
  }

  .section { margin-bottom: 2rem; }
  .section-title {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: var(--gold);
    border-left: 2px solid var(--gold);
    padding-left: 10px;
    margin-bottom: 1rem;
    opacity: 0.8;
  }

  .cat-block {
    background: var(--bg-card);
    border: 0.5px solid var(--border);
    border-radius: 12px;
    margin-bottom: 10px;
    overflow: hidden;
    transition: border-color 0.2s;
  }
  .cat-block:hover { border-color: var(--border-strong); }

  .cat-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px 16px;
    border-bottom: 0.5px solid var(--border);
    background: rgba(201,168,76,0.03);
  }
  .cat-icon { font-size: 16px; color: var(--gold); opacity: 0.85; }
  .cat-name { font-size: 13px; font-weight: 500; color: var(--text); }
  .cat-tag {
    margin-left: auto;
    font-size: 10px;
    background: rgba(201,168,76,0.08);
    color: var(--gold-dim);
    border: 0.5px solid rgba(201,168,76,0.2);
    padding: 2px 8px;
    border-radius: 20px;
    letter-spacing: 0.5px;
  }

  .items { padding: 10px 16px; display: flex; flex-direction: column; gap: 7px; }
  .item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 13px;
    color: var(--text-muted);
    line-height: 1.6;
  }
  .item::before {
    content: '';
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: var(--gold-dim);
    flex-shrink: 0;
    margin-top: 7px;
    opacity: 0.7;
  }
  .item strong { color: #d4c49a; font-weight: 500; }

  .new-badge {
    font-size: 9px;
    background: rgba(29,158,117,0.12);
    color: #4ecba0;
    border: 0.5px solid rgba(29,158,117,0.3);
    padding: 1px 6px;
    border-radius: 10px;
    margin-left: 6px;
    vertical-align: middle;
    letter-spacing: 0.5px;
  }

  .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
  @media (max-width: 500px) { .two-col { grid-template-columns: 1fr; } }

  .footer {
    text-align: center;
    font-size: 11px;
    color: rgba(138,128,112,0.5);
    letter-spacing: 1px;
    margin-top: 1rem;
  }
</style>
</head>
<body>
<div class="page">

  <div class="header">
    <div class="server-name">⚔ Eons ⚔</div>
    <div class="subtitle">📋 Notre Contenu</div>
    <div class="badge-version">🏔️ 3.3.5</div>
  </div>

  <hr class="divider">

  <!-- CLIENT -->
  <div class="section">
    <div class="section-title"><i class="ti ti-device-desktop"></i> Client</div>

    <div class="cat-block">
      <div class="cat-header">
        <i class="ti ti-layout-dashboard cat-icon"></i>
        <span class="cat-name">Interface Utilisateur : Dragonflight</span>
        <span class="cat-tag">UI</span>
      </div>
      <div class="items">
        <div class="item">Design similaire à l'extension Dragonflight pour une expérience visuelle moderne.</div>
        <div class="item">Barre des sorts redessinée dans le style Dragonflight.</div>
        <div class="item">Micro menu repensé dans le style Dragonflight.</div>
        <div class="item">MiniMap dans le style Dragonflight.</div>
      </div>
    </div>

    <div class="cat-block">
      <div class="cat-header">
        <i class="ti ti-shield cat-icon"></i>
        <span class="cat-name">Système Héritage (UI)</span>
        <span class="cat-tag">UI</span>
      </div>
      <div class="items">
        <div class="item">Affichage d'un panneau dédié pour stocker les équipements héritage qui vous suivront tout au long de votre aventure.</div>
        <div class="item">Les équipements héritage s'obtiennent grâce à la monnaie Éclat du Gardien.</div>
        <div class="item">Chaque équipement héritage coûte 50 Éclats du Gardien.</div>
        <div class="item">Les Éclats du Gardien sont obtenables en jeu ou via la boutique Eons.</div>
        <div class="item">Les équipements achetés sont envoyés directement dans le sac (pas de boîte aux lettres).</div>
      </div>
    </div>

    <div class="cat-block">
      <div class="cat-header">
        <i class="ti ti-shirt cat-icon"></i>
        <span class="cat-name">Système de Transmogrification (UI)</span>
        <span class="cat-tag">UI</span>
      </div>
      <div class="items">
        <div class="item">Affichage d'un panneau pour transformer l'apparence de vos équipements.</div>
        <div class="item">Remplacez le modèle visuel d'un équipement équipé par celui d'un équipement dans votre sac.</div>
        <div class="item">Coût de chaque transmogrification : 50 pièces d'or.</div>
        <div class="item">Réinitialisation de l'apparence par emplacement ou en totalité.</div>
      </div>
    </div>

    <div class="cat-block">
      <div class="cat-header">
        <i class="ti ti-arrow-up-circle cat-icon"></i>
        <span class="cat-name">Système Amélioration d'équipements : Item Upgrade (UI)</span>
        <span class="cat-tag">UI</span>
      </div>
      <div class="items">
        <div class="item">Affichage d'un panneau pour améliorer vos équipements actuels vers une version supérieure.</div>
        <div class="item">Statistiques actuelles et statistiques améliorées affichées côte à côte pour comparer avant confirmation.</div>
        <div class="item">Coût de chaque amélioration : 5 000 pièces d'or par pièce d'équipement.</div>
      </div>
    </div>

    <div class="cat-block">
      <div class="cat-header">
        <i class="ti ti-store cat-icon"></i>
        <span class="cat-name">Système de Boutique + Monnaie (UI)</span>
        <span class="cat-tag">UI</span>
      </div>
      <div class="items">
        <div class="item">Boutique intégrée permettant d'acheter des articles avec la monnaie du serveur Éclat du Gardien.</div>
        <div class="item">La monnaie peut être gagnée en jeu ou achetée directement sur la boutique Eons.</div>
      </div>
    </div>
  </div>

  <hr class="divider">

  <!-- PERSONNAGE -->
  <div class="section">
    <div class="section-title"><i class="ti ti-user"></i> Personnage</div>

    <div class="cat-block">
      <div class="cat-header">
        <i class="ti ti-star cat-icon"></i>
        <span class="cat-name">Système de Multiplicateur d'Expérience</span>
        <span class="cat-tag">Progression</span>
      </div>
      <div class="items">
        <div class="item">Multiplicateur d'XP personnalisé permettant d'accélérer la progression de niveau sur le serveur Eons.</div>
      </div>
    </div>

    <div class="cat-block">
      <div class="cat-header">
        <i class="ti ti-crown cat-icon"></i>
        <span class="cat-name">Système de Paragon</span>
        <span class="cat-tag">Progression</span>
      </div>
      <div class="items">
        <div class="item">Système de progression post-niveau maximum permettant de continuer à gagner des récompenses et de la puissance après avoir atteint le niveau plafonné.</div>
      </div>
    </div>

    <div class="cat-block">
      <div class="cat-header">
        <i class="ti ti-seeding cat-icon"></i>
        <span class="cat-name">Système de Talents</span>
        <span class="cat-tag">Classe</span>
      </div>
      <div class="items">
        <div class="item">Système de talents implémenté pour personnaliser votre style de jeu et spécialisation de classe.</div>
      </div>
    </div>

    <div class="cat-block">
      <div class="cat-header">
        <i class="ti ti-gift cat-icon"></i>
        <span class="cat-name">Système Contributeur (Bonus Safe)</span>
        <span class="cat-tag">Avantage</span>
      </div>
      <div class="items">
        <div class="item">Les contributeurs du serveur bénéficient de bonus exclusifs en récompense de leur soutien à Eons.</div>
      </div>
    </div>

    <div class="cat-block">
      <div class="cat-header">
        <i class="ti ti-book cat-icon"></i>
        <span class="cat-name">Grimoire des Métiers</span>
        <span class="cat-tag">Artisanat</span>
      </div>
      <div class="items">
        <div class="item">Quatre professions primaires et trois professions secondaires disponibles.</div>
      </div>
    </div>

    <div class="two-col">
      <div class="cat-block" style="margin-bottom:0">
        <div class="cat-header">
          <i class="ti ti-user-plus cat-icon"></i>
          <span class="cat-name">Classe : Moine <span class="new-badge">NEW</span></span>
        </div>
        <div class="items">
          <div class="item">La classe Moine est désormais jouable sur Eons.</div>
        </div>
      </div>
      <div class="cat-block" style="margin-bottom:0">
        <div class="cat-header">
          <i class="ti ti-users cat-icon"></i>
          <span class="cat-name">Race : Pandaren <span class="new-badge">NEW</span></span>
        </div>
        <div class="items">
          <div class="item">La race Pandaren est disponible avec ses raciaux complets.</div>
          <div class="item">Monture de race exclusive : Ban-Lu.</div>
        </div>
      </div>
    </div>
  </div>

  <hr class="divider">

  <!-- MONDE -->
  <div class="section">
    <div class="section-title"><i class="ti ti-map"></i> Monde</div>

    <div class="cat-block">
      <div class="cat-header">
        <i class="ti ti-tool cat-icon"></i>
        <span class="cat-name">Systèmes de Jeu</span>
        <span class="cat-tag">Core</span>
      </div>
      <div class="items">
        <div class="item">Système Héritage : progression d'équipements persistants tout au long de votre aventure.</div>
        <div class="item">Système de Transmogrification : personnalisation de l'apparence de vos équipements.</div>
        <div class="item">Système Amélioration d'équipements (Item Upgrade) : améliorez vos pièces d'équipement actuelles.</div>
        <div class="item">Système de Ramassage de Zone : collecte automatique ou améliorée des objets dans la zone.</div>
      </div>
    </div>

    <div class="cat-block">
      <div class="cat-header">
        <i class="ti ti-moon cat-icon"></i>
        <span class="cat-name">Système de Marché Noir</span>
        <span class="cat-tag">Économie</span>
      </div>
      <div class="items">
        <div class="item">Accès au Marché Noir : enchères secrètes sur des objets rares et exclusifs en jeu.</div>
      </div>
    </div>

    <div class="cat-block">
      <div class="cat-header">
        <i class="ti ti-map-pin cat-icon"></i>
        <span class="cat-name">Zones & Donjons ajoutés</span>
        <span class="cat-tag">Contenu</span>
      </div>
      <div class="items">
        <div class="item">L'Île Vagabonde : zone de départ Pandaren avec quêtes d'introduction de la race.</div>
        <div class="item">Les Ports Oubliés : zone ouverte destinée aux joueurs niveau 80.</div>
        <div class="item">Forêt Mystérieuse : donjon niveau 80.</div>
        <div class="item">Temple du Temps : donjon niveau 80.</div>
      </div>
    </div>

    <div class="two-col">
      <div class="cat-block" style="margin-bottom:0">
        <div class="cat-header">
          <i class="ti ti-flag cat-icon"></i>
          <span class="cat-name">Champs de Bataille <span class="new-badge">NEW</span></span>
        </div>
        <div class="items">
          <div class="item">Les Pics Jumeaux</div>
          <div class="item">La Bataille de Gilnéas</div>
          <div class="item">Temple de Kotmogu</div>
        </div>
      </div>
      <div class="cat-block" style="margin-bottom:0">
        <div class="cat-header">
          <i class="ti ti-swords cat-icon"></i>
          <span class="cat-name">Arènes <span class="new-badge">NEW</span></span>
        </div>
        <div class="items">
          <div class="item">Arène Tol'Viron</div>
          <div class="item">Le Croc du Tigre</div>
        </div>
      </div>
    </div>
  </div>

  <hr class="divider">
  <div class="footer">Eons World</div>

</div>
</body>
</html>
