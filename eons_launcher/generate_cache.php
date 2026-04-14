<?php
/**
 * Eons Launcher - Génération du cache manifest EN LIGNE DE COMMANDE
 *
 * Lancer depuis CMD ou PowerShell :
 *   php C:\Servers\WebServers\eons-world.eu\eons_launcher\generate_cache.php
 *
 * Aucun timeout, affiche la progression en temps réel.
 * À utiliser après chaque mise à jour du client.
 */

// Pas de limite de temps en CLI
set_time_limit(0);
ini_set('memory_limit', '1G');

define('CLIENT_BASE_PATH', 'C:/Servers/WebServers/eons-world.eu/eons_client/');
define('CLIENT_BASE_URL',  'https://eons-world.eu/eons_client/');
define('CACHE_FILE',       __DIR__ . '/cache/manifest_cache.json');

// =============================================
// AFFICHAGE PROGRESSION
// =============================================

function log_msg(string $msg): void
{
    echo '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
}

// =============================================
// SCAN
// =============================================

log_msg('Démarrage du scan de : ' . CLIENT_BASE_PATH);

if (!is_dir(CLIENT_BASE_PATH)) {
    log_msg('ERREUR : Dossier introuvable : ' . CLIENT_BASE_PATH);
    exit(1);
}

$files    = [];
$count    = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(CLIENT_BASE_PATH, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    if (!$file->isFile()) continue;

    $absolutePath = str_replace('\\', '/', $file->getPathname());
    $cleanBase    = str_replace('\\', '/', CLIENT_BASE_PATH);
    $relativePath = ltrim(str_replace($cleanBase, '', $absolutePath), '/');
    $encodedUrl   = CLIENT_BASE_URL . implode('/', array_map('rawurlencode', explode('/', $relativePath)));

    $files[] = [
        'path'     => $relativePath,
        'url'      => $encodedUrl,
        'size'     => $file->getSize(),
        'md5'      => md5_file($absolutePath),
        'modified' => $file->getMTime(),
    ];

    $count++;
    if ($count % 50 === 0) {
        log_msg("  ... $count fichiers traités (dernier : $relativePath)");
    }
}

// =============================================
// SAUVEGARDE DU CACHE
// =============================================

$totalSize = array_sum(array_column($files, 'size'));

$manifest = [
    'success'       => true,
    'version'       => '3.3.5a',
    'generated_at'  => date('Y-m-d H:i:s'),
    'total_files'   => count($files),
    'total_size'    => $totalSize,
    'total_size_mb' => round($totalSize / 1048576, 2),
    'base_url'      => CLIENT_BASE_URL,
    'files'         => $files,
];

$json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$dir = dirname(CACHE_FILE);
if (!is_dir($dir)) mkdir($dir, 0755, true);
file_put_contents(CACHE_FILE, $json);

log_msg('✓ Cache généré avec succès !');
log_msg('  Fichiers  : ' . count($files));
log_msg('  Taille    : ' . round($totalSize / 1048576, 0) . ' Mo');
log_msg('  Cache     : ' . CACHE_FILE);
log_msg('  Le launcher peut maintenant télécharger via manifest.php');
