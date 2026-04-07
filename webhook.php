<?php
/**
 * webhook.php — Flutterwave webhook receiver endpoint
 *
 * Register this URL in your Flutterwave dashboard:
 *   Settings → Webhooks → https://yourdomain.com/path/to/this/webhook.php
 *
 * Place in: your project's public webhook directory (NOT inside the library folder)
 *
 * ⚠️  This file should be publicly accessible over HTTPS.
 *     Protect it only via Flutterwave's verif-hash signature, which this file
 *     validates automatically using the secret stored in the DB.
 */

// ── Bootstrap your project (adjust path as needed) ───────────────────────────
// require_once __DIR__ . '/../../bootstrap.php';        // sets up $db, etc.
// require_once __DIR__ . '/../../config/database.php';

// ── Load Flutterwave library ──────────────────────────────────────────────────
require_once __DIR__ . '/assets/libraries/webview/flutterwave/index.php';

// ── Handle the webhook ────────────────────────────────────────────────────────
header('Content-Type: application/json');

$webhook = new FlutterwaveWebhook();
$event   = $webhook->handle();             // validates signature; exits on failure

$tx      = $webhook->extractTransactionData($event);
$eventType = $tx['event'];

switch ($eventType) {

    // ── Card / account charge completed ──────────────────────────────────────
    case 'charge.completed':
        if ($tx['status'] === 'successful') {
            // TODO: Verify transaction server-side before updating order
            $flw    = FlutterwavePayment::getInstance();
            $verify = $flw->verifyTransaction((string)$tx['tPaymentTransactionId']);

            if ($verify['Action'] === '1') {
                // TODO: Mark order as paid in your DB
                // updateOrderStatus($tx['tx_ref'], 'paid', $tx['tPaymentTransactionId']);
            }
        }
        break;

    // ── Transfer / payout completed ───────────────────────────────────────────
    case 'transfer.completed':
        // TODO: Update provider wallet / settlement record
        break;

    // ── Refund processed ──────────────────────────────────────────────────────
    case 'refund.completed':
        // TODO: Update your refund record
        break;

    default:
        // Unknown event — log and ignore
        error_log('[FlutterwaveWebhook] Unhandled event: ' . $eventType);
        break;
}

http_response_code(200);
echo json_encode(['status' => 'ok']);
