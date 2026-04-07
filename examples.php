<?php
/**
 * examples.php — Flutterwave integration usage examples
 *
 * This file is for REFERENCE ONLY. Do not deploy as a public endpoint.
 * Copy the relevant snippet into your actual controller / service.
 *
 * Place in: assets/libraries/webview/flutterwave/examples.php
 */

require_once __DIR__ . '/index.php';

// ═════════════════════════════════════════════════════════════════════════════
// 1. CHARGE A SAVED CARD (token-based, off-session)
// ═════════════════════════════════════════════════════════════════════════════
function example_chargeToken(): void {
    $flw = FlutterwavePayment::getInstance();

    $result = $flw->execute([
        'amount'         => 150.00,
        'vCurrency'      => 'GHS',
        'tCardToken'     => 'flw-token-xxxxxxxxxx',   // saved token from previous charge
        'tCustomerId'    => 'cust-00123',
        'description'    => 'Subscription renewal',
        'customer_email' => 'john@example.com',
        'customer_name'  => 'John Doe',
        'iMemberId'      => 42,
        'iOrderId'       => 1001,
    ]);

    if ($result['Action'] === '1') {
        echo "Payment successful. Transaction ID: " . $result['tPaymentTransactionId'];
    } else {
        echo "Payment failed: " . $result['message'];
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// 2. HOSTED CHECKOUT (redirect user to Flutterwave payment page)
// ═════════════════════════════════════════════════════════════════════════════
function example_hostedCheckout(): void {
    $flw = FlutterwavePayment::getInstance();

    $result = $flw->execute([
        'amount'         => 25.00,
        'vCurrency'      => 'USD',
        'return_url'     => 'https://yourdomain.com/payment/callback',
        'description'    => 'Order #5055',
        'customer_email' => 'jane@example.com',
        'customer_name'  => 'Jane Smith',
        'iOrderId'       => 5055,
        'eType'          => 'Standard',   // any value other than 'DeliverAll' returns the URL
    ]);

    if (isset($result['AUTHENTICATION_URL'])) {
        // Redirect user to Flutterwave's hosted checkout
        header('Location: ' . $result['AUTHENTICATION_URL']);
        exit;
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// 3. MOBILE MONEY (Ghana — MTN MoMo example)
// ═════════════════════════════════════════════════════════════════════════════
function example_mobileMoney(): void {
    $flw = FlutterwavePayment::getInstance();

    $result = $flw->mobileMoneyPayment([
        'amount'         => 50.00,
        'vCurrency'      => 'GHS',
        'network'        => 'MTN',
        'customer_phone' => '0241234567',
        'customer_email' => 'kwame@example.com',
        'customer_name'  => 'Kwame Mensah',
        'description'    => 'Ride payment',
        'return_url'     => 'https://yourdomain.com/payment/callback',
        'iOrderId'       => 2001,
    ]);

    if ($result['Action'] === '1') {
        echo "Mobile money request sent.";
    } else {
        echo "Failed: " . $result['message'];
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// 4. AUTH-ONLY THEN CAPTURE (two-step)
// ═════════════════════════════════════════════════════════════════════════════
function example_authAndCapture(): void {
    $flw = FlutterwavePayment::getInstance();

    // Step A: authorise (no immediate capture)
    $auth = $flw->execute([
        'amount'      => 200.00,
        'vCurrency'   => 'USD',
        'tCardToken'  => 'flw-token-xxxxxxxxxx',
        'isAuthorize' => true,
        'description' => 'Pre-auth for hotel booking',
        'iOrderId'    => 3001,
    ]);

    if ($auth['Action'] !== '1') {
        echo "Auth failed: " . $auth['message'];
        return;
    }

    $chargeId = $auth['tPaymentTransactionId'];

    // … later, when the service is delivered …

    // Step B: capture
    $capture = $flw->capturePayment([
        'iAuthorizePaymentId' => $chargeId,
        'amount'              => 200.00,   // omit for full capture
    ]);

    if ($capture['Action'] === '1') {
        echo "Captured successfully.";
    } else {
        echo "Capture failed: " . $capture['message'];
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// 5. VOID / CANCEL AN AUTHORISATION
// ═════════════════════════════════════════════════════════════════════════════
function example_voidAuth(): void {
    $flw = FlutterwavePayment::getInstance();

    $result = $flw->cancelAuthorizedPayment([
        'iAuthorizePaymentId' => 'flw-charge-id-here',
    ]);

    echo $result['Action'] === '1' ? 'Void successful.' : 'Void failed: ' . $result['message'];
}

// ═════════════════════════════════════════════════════════════════════════════
// 6. REFUND
// ═════════════════════════════════════════════════════════════════════════════
function example_refund(): void {
    $flw = FlutterwavePayment::getInstance();

    $result = $flw->refundPayment([
        'tPaymentTransactionId' => '12345678',   // FLW transaction id
        'amount'                => 75.00,         // partial refund; omit for full
        'comments'              => 'Customer returned item',
    ]);

    echo $result['Action'] === '1' ? 'Refund initiated.' : 'Refund failed: ' . $result['message'];
}

// ═════════════════════════════════════════════════════════════════════════════
// 7. VERIFY A TRANSACTION (e.g. after redirect callback)
// ═════════════════════════════════════════════════════════════════════════════
function example_verify(): void {
    // Flutterwave appends ?transaction_id=xxx&tx_ref=yyy to your return_url
    $transactionId = $_GET['transaction_id'] ?? '';

    if (empty($transactionId)) {
        echo 'No transaction ID in callback.';
        return;
    }

    $flw    = FlutterwavePayment::getInstance();
    $result = $flw->verifyTransaction($transactionId);

    if ($result['Action'] === '1') {
        echo "Payment verified. Amount: " . $result['amount'] . ' ' . $result['currency'];
        // TODO: update order in DB using $result['tx_ref']
    } else {
        echo "Verification failed: " . $result['message'];
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// 8. READ A CONFIG VALUE DIRECTLY
// ═════════════════════════════════════════════════════════════════════════════
function example_config(): void {
    $cfg = FlutterwaveConfig::getInstance();

    echo "Environment  : " . $cfg->environment . PHP_EOL;
    echo "Gateway      : " . ($cfg->isActive() ? 'Active' : 'Inactive') . PHP_EOL;
    echo "Default CCY  : " . $cfg->defaultCurrency . PHP_EOL;
    echo "Payment opts : " . $cfg->enabledPaymentOptions() . PHP_EOL;
    echo "Min amount   : " . $cfg->minTransactionAmount . PHP_EOL;
    echo "Max amount   : " . $cfg->maxTransactionAmount . PHP_EOL;
    echo "Retry count  : " . $cfg->retryAttempts . PHP_EOL;
    echo "Logging      : " . ($cfg->loggingEnabled ? 'Yes' : 'No') . PHP_EOL;
}
