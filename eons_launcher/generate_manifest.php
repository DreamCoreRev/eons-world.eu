<?php
/**
 * Eons Launcher - Générateur de Manifest (sans cache)
 * À placer dans : C:/xampp/htdocs/eons_launcher/generate_manifest.php
 *
 * Utilise ce script pour générer/tester le manifest manuellement.
 * Pour la prod, utilise manifest.php (avec cache).
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// =============================================
// CONFIGURATION
// =============================================

define('CLIENT_BASE_PATH', 'C:/Servers/WebServers/eons-world.eu/eons_client/');

// ⚠️ URL locale — les fichiers sont servis par XAMPP via l'alias /eons_client/
define('CLIENT_BASE_URL', 'https://eons-world.eu/eons_client/');

// =============================================
// SCAN
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

    $startTime = microtime(true);
    $files     = scanClientDirectory(CLIENT_BASE_PATH, CLIENT_BASE_URL);
    $elapsed   = round(microtime(true) - $startTime, 3);
    $totalSize = array_sum(array_column($files, 'size'));

    $manifest = [
        'success'         => true,
        'version'         => '3.3.5a',
        'generated_at'    => date('Y-m-d H:i:s'),
        'generation_time' => $elapsed . 's',
        'total_files'     => count($files),
        'total_size'      => $totalSize,
        'total_size_mb'   => round($totalSize / 1048576, 2),
        'base_url'        => CLIENT_BASE_URL,
        'files'           => $files,
    ];

    echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
