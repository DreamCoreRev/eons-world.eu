<?php
// ============================================================
//  api/online.php — Eons CMS
//  Retourne le nombre de joueurs connectés en JSON
//  Appelé toutes les 30s par index.php en AJAX
// ============================================================
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$count = 0;
try {
    $dsn   = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_CHARS_NAME . ';charset=utf8mb4';
    $chars = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $count = (int)$chars->query("SELECT COUNT(*) FROM characters WHERE online = 1")->fetchColumn();
} catch (PDOException $e) {
    error_log('[AU API] online.php error: ' . $e->getMessage());
}

echo json_encode(['count' => $count]);
