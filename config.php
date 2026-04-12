<?php
// ============================================================
//  config.php — Eons CMS
//  Base de données auth TrinityCore 3.3.5a (MariaDB / XAMPP)
// ============================================================

// ── Connexion Auth DB ────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_PORT', 3308);
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'eons_auth');

define('SOAP_HOST',   '127.0.0.1');
define('SOAP_PORT',   7878);
define('SOAP_USER',   'Eonswsoap');
define('SOAP_PASS',   'Eonsworldsoap');
define('SOAP_SENDER', 'Boutique');

// ── Connexion Characters DB (optionnelle, pour stats) ────────
define('DB_CHARS_NAME', 'eons_chars');

// ── Sécurité ─────────────────────────────────────────────────
define('SITE_URL',  'http://localhost/');
define('SECRET_KEY', '8e420c7d45f4c08eb45a86c03cc79cd51390622899899fc670d2ba3fb05db5e5');

// ── Création PDO Auth ─────────────────────────────────────────
function getAuthDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=localhost;port=3308;dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ── Démarrage session sécurisé ────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,     // true en HTTPS
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}
