#!/usr/bin/env php
<?php
// ============================================================
//  cron_shop_deliver.php — Eons CMS
//  Livraison des items en file d'attente (dp_shop_queue)
//
//  Ce script est destiné à être exécuté automatiquement
//  toutes les 5 minutes via une tâche cron ou manuellement.
//
//  ── CRON (Linux/WSL) ──────────────────────────────────────
//  */5 * * * * /usr/bin/php /chemin/vers/cron_shop_deliver.php >> /var/log/eons_shop.log 2>&1
//
//  ── WINDOWS (XAMPP) ───────────────────────────────────────
//  Utiliser le Planificateur de tâches Windows :
//    Programme : C:\xampp\php\php.exe
//    Arguments : C:\xampp\htdocs\cron_shop_deliver.php
//    Fréquence : toutes les 5 minutes
//
//  ── APPEL MANUEL ──────────────────────────────────────────
//  php cron_shop_deliver.php
//  ou en HTTP (protégé) : https://votre-site/cron_shop_deliver.php?key=VOTRE_CLE
// ============================================================

// ── Sécurité HTTP optionnelle (clé secrète) ──────────────────
define('CRON_SECRET', 'changeme_cron_secret_key');

if (php_sapi_name() !== 'cli') {
    $key = $_GET['key'] ?? '';
    if (!hash_equals(CRON_SECRET, $key)) {
        http_response_code(403);
        die('Forbidden');
    }
}

// ── Includes ────────────────────────────────────────────────
$dir = __DIR__;
require_once $dir . '/config.php';

// ── Constantes SOAP (doivent être dans config.php) ───────────
if (!defined('SOAP_HOST'))   define('SOAP_HOST',   '127.0.0.1');
if (!defined('SOAP_PORT'))   define('SOAP_PORT',   7878);
if (!defined('SOAP_USER'))   define('SOAP_USER',   'soap_admin');
if (!defined('SOAP_PASS'))   define('SOAP_PASS',   'changeme');
if (!defined('SOAP_SENDER')) define('SOAP_SENDER', 'Boutique');

// ── Inclure TCSoap ────────────────────────────────────────────
// On recopie la classe ici pour que le cron soit autonome

class TCSoap
{
    private SoapClient $client;

    public function __construct()
    {
        if (!extension_loaded('soap')) {
            throw new \Exception('Extension PHP soap non chargée.');
        }
        $wsdl = sprintf('http://%s:%d/RPC2', SOAP_HOST, SOAP_PORT);
        $this->client = new SoapClient(null, [
            'location'           => $wsdl,
            'uri'                => 'urn:TC',
            'style'              => SOAP_RPC,
            'use'                => SOAP_ENCODED,
            'login'              => SOAP_USER,
            'password'           => SOAP_PASS,
            'exceptions'         => true,
            'connection_timeout' => 5,
            'trace'              => false,
        ]);
    }

    public function execute(string $command): string
    {
        $result = $this->client->__soapCall('executeCommand', [new SoapParam($command, 'command')]);
        return (string)($result ?? '');
    }

    public function sendItem(string $charName, int $gameItemId, int $qty, string $itemName): string
    {
        $subject = addslashes('Boutique Eons — ' . $itemName);
        $body    = addslashes('Félicitations ! Votre achat différé a été livré. Merci de votre fidélité, héros.');
        $itemArg = $gameItemId . ($qty > 1 ? ':' . $qty : '');
        $cmd     = sprintf('.send items %s "%s" "%s" %s', trim($charName), $subject, $body, $itemArg);
        return $this->execute($cmd);
    }
}

// ── Traitement de la file ─────────────────────────────────────
$log  = fn(string $msg) => print('[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL);
$maxAttempts = 5;

try {
    $db = getAuthDB();

    // Récupérer les entrées en attente (max 20 par run)
    $stmt = $db->prepare(
        "SELECT * FROM dp_shop_queue
         WHERE status = 'pending' AND attempts < :max
         ORDER BY created_at ASC
         LIMIT 20"
    );
    $stmt->execute([':max' => $maxAttempts]);
    $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($pending)) {
        $log('Aucun item en attente.');
        exit(0);
    }

    $log(count($pending) . ' item(s) en attente de livraison.');

    $soap = null;
    try {
        $soap = new TCSoap();
        $log('Connexion SOAP établie.');
    } catch (\Exception $e) {
        $log('SOAP indisponible : ' . $e->getMessage() . ' — abandon.');
        exit(1);
    }

    foreach ($pending as $row) {
        $id       = (int)$row['id'];
        $char     = $row['char_name'];
        $itemName = $row['item_name'];
        $gameId   = (int)$row['game_item_id'];
        $qty      = (int)$row['quantity'];

        $log("Livraison #$id → $char : $itemName (gameId=$gameId x$qty)");

        // Incrémenter les tentatives
        $db->prepare("UPDATE dp_shop_queue SET attempts = attempts + 1 WHERE id = :id")
           ->execute([':id' => $id]);

        try {
            $soap->sendItem($char, $gameId, $qty, $itemName);

            // Marquer comme livré
            $db->prepare(
                "UPDATE dp_shop_queue
                 SET status = 'delivered', delivered_at = NOW()
                 WHERE id = :id"
            )->execute([':id' => $id]);

            // Mettre à jour le log principal si possible
            $db->prepare(
                "UPDATE dp_shop_log SET soap_status = 'ok', soap_error = NULL
                 WHERE account_id = :aid AND item_id = :iid AND soap_status = 'failed'
                 ORDER BY created_at DESC LIMIT 1"
            )->execute([':aid' => $row['account_id'], ':iid' => $row['item_id']]);

            $log("  ✓ Livré avec succès.");

        } catch (\SoapFault $e) {
            $db->prepare(
                "UPDATE dp_shop_queue SET status = IF(attempts >= :max, 'error', 'pending') WHERE id = :id"
            )->execute([':max' => $maxAttempts, ':id' => $id]);

            $log("  ✗ Échec SOAP : " . $e->getMessage());
        }
    }

} catch (\PDOException $e) {
    $log('Erreur DB : ' . $e->getMessage());
    exit(1);
}

$log('Traitement terminé.');
exit(0);
