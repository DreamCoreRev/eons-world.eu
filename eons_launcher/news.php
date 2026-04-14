<?php
/**
 * Eons Launcher - API Actualités
 * À placer dans : C:/xampp/htdocs/eons_launcher/news.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// =============================================
// ACTUALITÉS (modifie ici tes news)
// =============================================

$news = [
    [
        'id'      => 1,
        'title'   => 'Bienvenue sur Eons World !',
        'content' => 'Le serveur Eons est de retour avec de nouvelles fonctionnalités. Rejoignez-nous pour une aventure épique en Azeroth.',
        'date'    => '2026-04-13',
        'type'    => 'info',
        'image'   => null,
    ],
    [
        'id'      => 2,
        'title'   => 'Mise à jour du client v3.3.5a',
        'content' => 'Nouveau patch disponible ! Des corrections de bugs et des améliorations de performance ont été apportées.',
        'date'    => '2026-04-11',
        'type'    => 'update',
        'image'   => null,
    ],
    [
        'id'      => 3,
        'title'   => 'Événement de Printemps',
        'content' => 'Profitez de bonus d\'expérience x2 ce weekend ! Connectez-vous entre le 12 et le 14 avril pour en profiter.',
        'date'    => '2026-04-10',
        'type'    => 'event',
        'image'   => null,
    ],
];

// =============================================
// VERSION & URLS
// =============================================

$versionInfo = [
    'required_version' => '3.3.5a',
    'launcher_version' => '1.0.0',

    // ⚠️ Le manifest est servi par XAMPP en local
    'manifest_url'     => 'https://eons-world.eu/eons_launcher/manifest.php',

    'website_url'      => 'https://eons-world.eu/',
    'register_url'     => 'https://eons-world.eu/auth.php',
    'server_status'    => getServerStatus(),
    'online_players'   => getOnlinePlayers(),
];

// =============================================
// STATUT SERVEUR — connexion MySQL réelle
// =============================================

function getServerStatus(): string
{
    try {
        $pdo = new PDO(
            'mysql:host=localhost:3308;dbname=eons_chars;charset=utf8',
            'root',
            'root',
            [PDO::ATTR_TIMEOUT => 2, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        // Vérifie si la DB répond
        $pdo->query('SELECT 1');
        return 'online';
    } catch (Exception $e) {
        return 'offline';
    }
}

function getOnlinePlayers(): int
{
    try {
        $pdo = new PDO(
            'mysql:host=localhost:3308;dbname=eons_chars;charset=utf8',
            'root',
            'root',
            [PDO::ATTR_TIMEOUT => 2, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $stmt = $pdo->query("SELECT * FROM characters WHERE online = 1");
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

// =============================================
// RÉPONSE
// =============================================

echo json_encode([
    'success'      => true,
    'version_info' => $versionInfo,
    'news'         => $news,
    'generated_at' => date('Y-m-d H:i:s'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
