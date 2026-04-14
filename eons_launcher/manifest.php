<?php
/**
 * Eons Launcher - Manifest avec Cache
 * C:/Servers/WebServers/eons-world.eu/eons_launcher/manifest.php
 */

// ⚠️ IMPORTANT : le scan MD5 de 19 Go prend plusieurs minutes
set_time_limit(600); // 10 minutes max
ini_set('memory_limit', '512M');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// =============================================
// CONFIGURATION
// =============================================

define('CLIENT_BASE_PATH', 'C:/Servers/WebServers/eons-world.eu/eons_client/');
define('CLIENT_BASE_URL',  'https://eons-world.eu/eons_client/');

define('CACHE_FILE',     __DIR__ . '/cache/manifest_cache.json');
define('CACHE_DURATION', 86400); // 24h — régénération rare vu le volume

define('REFRESH_TOKEN', '62b69a792f5e205dfaf537ba5e8fd1aea28f51e6bab74c5ec8761cf8f400a374');

// =============================================
// CACHE
// =============================================

function isCacheValid(): bool
{
    if (!file_exists(CACHE_FILE)) return false;
    return (time() - filemtime(CACHE_FILE)) < CACHE_DURATION;
}

function loadCache(): string
{
    return file_get_contents(CACHE_FILE);
}

function saveCache(string $json): void
{
    $dir = dirname(CACHE_FILE);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents(CACHE_FILE, $json);
}

// =============================================
// REFRESH FORCÉ
// =============================================

$forceRefresh = isset($_GET['refresh'])
             && isset($_GET['token'])
             && hash_equals(REFRESH_TOKEN, $_GET['token']);

if (!$forceRefresh && isCacheValid()) {
    header('X-Cache: HIT');
    echo loadCache();
    exit;
}

header('X-Cache: MISS');

// =============================================
// SCAN DU DOSSIER CLIENT
// =============================================

function scanClientDirectory(string $basePath, string $baseUrl): array
{
    $files    = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($basePath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) continue;

        $absolutePath = str_replace('\\', '/', $file->getPathname());
        $cleanBase    = str_replace('\\', '/', $basePath);
        $relativePath = ltrim(str_replace($cleanBase, '', $absolutePath), '/');
        $encodedUrl   = $baseUrl . implode('/', array_map('rawurlencode', explode('/', $relativePath)));

        $files[] = [
            'path'     => $relativePath,
            'url'      => $encodedUrl,
            'size'     => $file->getSize(),
            'md5'      => md5_file($absolutePath),
            'modified' => $file->getMTime(),
        ];
    }

    return $files;
}

// =============================================
// GÉNÉRATION
// =============================================

try {
    if (!is_dir(CLIENT_BASE_PATH)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Dossier client introuvable : ' . CLIENT_BASE_PATH]);
        exit;
    }

    $files     = scanClientDirectory(CLIENT_BASE_PATH, CLIENT_BASE_URL);
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
    saveCache($json);
    echo $json;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
