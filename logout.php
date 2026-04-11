<?php
// ============================================================
//  logout.php — Eons CMS
//  Déconnexion : destruction session + cookie remember me
// ============================================================
require_once __DIR__ . '/config.php';

// Mettre online=0 si l'utilisateur était connecté
if (!empty($_SESSION['account_id'])) {
    try {
        $db = getAuthDB();
        $stmt = $db->prepare("UPDATE account SET online = 0 WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['account_id']]);
    } catch (PDOException $e) {
        error_log('[AU Logout] DB error: ' . $e->getMessage());
    }
}

// Supprimer le cookie remember me
if (isset($_COOKIE['au_remember'])) {
    setcookie('au_remember', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

// Vider et détruire la session
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}
session_destroy();

// Redémarrer une nouvelle session pour le flash
session_start();
$_SESSION['flash'] = '✦ Vous avez été déconnecté avec succès. À bientôt, héros !';

header('Location: index.php');
exit;
