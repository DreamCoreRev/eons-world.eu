<?php
// ============================================================
//  don_webhook.php — Eons CMS
//  Webhook Stripe — crédite les DP après paiement confirmé
//
//  ── SETUP ─────────────────────────────────────────────────
//  Dans le dashboard Stripe > Développeurs > Webhooks :
//    URL de l'endpoint : https://eons-world.eu/don_webhook.php
//    Événements à écouter : checkout.session.completed
//
//  Copier le "Signing secret" (whsec_...) dans config.php :
//    define('STRIPE_WEBHOOK_SECRET', 'whsec_XXXXXXXXXXXXXXXX');
//
//  ── SÉCURITÉ ──────────────────────────────────────────────
//  Ce fichier NE doit PAS inclure header.php.
//  Il répond uniquement 200 OK (succès) ou 400 (erreur).
//  Stripe retentera l'envoi en cas de non-200.
//  La signature Stripe est vérifiée — toute requête non
//  signée est rejetée immédiatement.
// ============================================================

// Pas de session, pas de HTML — endpoint brut
require_once __DIR__ . '/config.php';

if (!defined('STRIPE_SECRET_KEY'))    define('STRIPE_SECRET_KEY',    'sk_test_REMPLACER_PAR_VOTRE_CLE_SECRETE');
if (!defined('STRIPE_WEBHOOK_SECRET'))define('STRIPE_WEBHOOK_SECRET','whsec_REMPLACER_PAR_VOTRE_WEBHOOK_SECRET');

// ── Logger dédié webhook ──────────────────────────────────────
function webhookLog(string $msg): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    error_log('[Webhook] ' . $msg);
    // Optionnel : log fichier dédié
    // file_put_contents(__DIR__ . '/logs/webhook.log', $line, FILE_APPEND | LOCK_EX);
}

// ── Lecture du payload brut ───────────────────────────────────
$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (empty($payload)) {
    http_response_code(400);
    webhookLog('Payload vide');
    exit('Payload vide');
}

// ── Vérifier que le SDK Stripe est disponible ─────────────────
$stripeAutoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($stripeAutoload)) {
    http_response_code(500);
    webhookLog('SDK Stripe manquant — composer require stripe/stripe-php');
    exit('SDK manquant');
}
require_once $stripeAutoload;

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

// ── Vérification de la signature Stripe ──────────────────────
try {
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sigHeader,
        STRIPE_WEBHOOK_SECRET
    );
} catch (\UnexpectedValueException $e) {
    http_response_code(400);
    webhookLog('Payload invalide : ' . $e->getMessage());
    exit('Payload invalide');
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    webhookLog('Signature invalide : ' . $e->getMessage());
    exit('Signature invalide');
}

webhookLog('Événement reçu : ' . $event->type . ' | id=' . $event->id);

// ── Traitement des événements ─────────────────────────────────
switch ($event->type) {

    // ── Paiement confirmé ─────────────────────────────────────
    case 'checkout.session.completed':

        $session = $event->data->object;

        // Vérifier que le paiement est bien payé
        if ($session->payment_status !== 'paid') {
            webhookLog('Session ' . $session->id . ' non payée (status=' . $session->payment_status . ') — ignorée');
            http_response_code(200);
            exit('ok');
        }

        $stripeSessionId = $session->id;
        $metadata        = $session->metadata;
        $accountId       = (int)($metadata->account_id ?? 0);
        $tierId          = $metadata->tier_id ?? '';
        $dpAmount        = (int)($metadata->dp_amount ?? 0);

        if ($accountId <= 0 || $dpAmount <= 0) {
            webhookLog('Métadonnées invalides — account_id=' . $accountId . ' dp=' . $dpAmount);
            http_response_code(400);
            exit('Métadonnées invalides');
        }

        try {
            $db = getAuthDB();

            // ── Idempotence : vérifier si déjà traité ─────────
            $check = $db->prepare(
                "SELECT id, status FROM dp_donations
                 WHERE stripe_session = :sess LIMIT 1"
            );
            $check->execute([':sess' => $stripeSessionId]);
            $existing = $check->fetch();

            if ($existing && $existing['status'] === 'completed') {
                webhookLog('Session déjà traitée : ' . $stripeSessionId . ' — ignorée');
                http_response_code(200);
                exit('ok');
            }

            // ── Transaction : créditer DP + mettre à jour log ──
            $db->beginTransaction();

            // Créditer les DP sur le compte
            $db->prepare(
                "UPDATE account SET dp = dp + :dp WHERE id = :id"
            )->execute([':dp' => $dpAmount, ':id' => $accountId]);

            if ($existing) {
                // Mettre à jour l'entrée existante
                $db->prepare(
                    "UPDATE dp_donations
                     SET status = 'completed', completed_at = NOW()
                     WHERE stripe_session = :sess"
                )->execute([':sess' => $stripeSessionId]);
            } else {
                // Créer l'entrée (webhook arrivé avant la page don.php)
                $db->prepare(
                    "INSERT INTO dp_donations
                       (account_id, stripe_session, tier_id, amount_eur, dp_granted, status, completed_at)
                     VALUES (:aid, :sess, :tier, :eur, :dp, 'completed', NOW())"
                )->execute([
                    ':aid'  => $accountId,
                    ':sess' => $stripeSessionId,
                    ':tier' => $tierId,
                    ':eur'  => (int)round($session->amount_total / 100),
                    ':dp'   => $dpAmount,
                ]);
            }

            $db->commit();

            webhookLog(
                'DP crédités — account_id=' . $accountId .
                ' tier=' . $tierId .
                ' dp=+' . $dpAmount .
                ' session=' . $stripeSessionId
            );

        } catch (PDOException $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            webhookLog('Erreur DB : ' . $e->getMessage());
            http_response_code(500);
            exit('Erreur DB');
        }

        break;

    // ── Paiement expiré / annulé ──────────────────────────────
    case 'checkout.session.expired':

        $session = $event->data->object;
        try {
            $db = getAuthDB();
            $db->prepare(
                "UPDATE dp_donations SET status = 'failed'
                 WHERE stripe_session = :sess AND status = 'pending'"
            )->execute([':sess' => $session->id]);
        } catch (PDOException $e) {
            webhookLog('Erreur DB (expired) : ' . $e->getMessage());
        }
        webhookLog('Session expirée : ' . $session->id);
        break;

    // ── Autres événements — ignorés ───────────────────────────
    default:
        webhookLog('Événement ignoré : ' . $event->type);
        break;
}

http_response_code(200);
echo 'ok';
