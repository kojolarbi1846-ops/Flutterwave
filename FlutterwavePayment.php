<?php
/**
 * FlutterwavePayment.php
 * Handles charge, capture, void, and refund operations.
 * All API keys are loaded from the database via FlutterwaveConfig.
 *
 * Place in: assets/libraries/webview/flutterwave/FlutterwavePayment.php
 */

require_once __DIR__ . '/FlutterwaveConfig.php';
require_once __DIR__ . '/FlutterwaveLogger.php';

class FlutterwavePayment {

    private FlutterwaveConfig $cfg;
    private FlutterwaveLogger $log;

    // ── Construction ──────────────────────────────────────────────────────────

    public function __construct() {
        $this->cfg = FlutterwaveConfig::getInstance();
        $this->log = new FlutterwaveLogger($this->cfg);
    }

    public static function getInstance(): self {
        return new self();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Execute a payment charge.
     *
     * $paymentData keys:
     *   amount           float    Required
     *   vCurrency        string   Required  (ISO 4217)
     *   tCustomerId      string   Optional  Flutterwave customer id
     *   tCardToken       string   Optional  Saved card token / auth code
     *   description      string   Optional
     *   return_url       string   Optional  3DS redirect
     *   isAuthorize      bool     Optional  Auth-only (no immediate capture)
     *   iMemberId        mixed    Optional  Metadata
     *   UserType         string   Optional  Metadata
     *   iOrderId         mixed    Optional  Metadata
     *   eType            string   Optional  'DeliverAll' bypasses redirect return
     *   customer_email   string   Optional  Customer email
     *   customer_name    string   Optional  Customer name
     *   customer_phone   string   Optional  Customer phone
     *   network          string   Optional  Mobile-money network code
     */
    public function execute(array $paymentData): array {
        // ── Guard: gateway must be active ─────────────────────────────────────
        if (!$this->cfg->isActive()) {
            return $this->fail('Flutterwave payment gateway is not active or not fully configured.');
        }

        // ── Guard: validate amount ────────────────────────────────────────────
        $amount   = round((float)($paymentData['amount'] ?? 0), 2);
        $currency = strtoupper(trim($paymentData['vCurrency'] ?? $this->cfg->defaultCurrency));

        $limitErr = $this->validateAmount($amount);
        if ($limitErr) return $this->fail($limitErr);

        // ── Build payload ─────────────────────────────────────────────────────
        $cardToken  = $paymentData['tCardToken']   ?? null;
        $custId     = $paymentData['tCustomerId']  ?? null;
        $returnUrl  = $paymentData['return_url']   ?? '';
        $description= $paymentData['description']  ?? 'Payment';

        $meta = [];
        if (!empty($paymentData['iMemberId'])) $meta['iMemberId'] = $paymentData['iMemberId'];
        if (!empty($paymentData['UserType']))  $meta['UserType']  = $paymentData['UserType'];
        if (!empty($paymentData['iOrderId']))  $meta['iOrderId']  = $paymentData['iOrderId'];

        $txRef = 'tx-' . time() . '-' . mt_rand(1000, 9999);

        $payload = [
            'tx_ref'       => $txRef,
            'amount'       => number_format($amount, 2, '.', ''),
            'currency'     => $currency,
            'redirect_url' => $returnUrl ?: null,
            'narration'    => $description,
            'meta'         => $meta,
            'customer'     => $this->buildCustomer($paymentData, $custId),
        ];

        // ── Token charge vs. hosted checkout ─────────────────────────────────
        if (!empty($cardToken)) {
            $payload['authorization'] = ['authorization_code' => $cardToken];
            $payload['payment_type']  = 'card';
        } else {
            $payload['payment_options'] = $this->cfg->enabledPaymentOptions();
            if (!empty($paymentData['network'])) {
                $payload['network'] = $paymentData['network'];
            }
        }

        // ── Auth-only mode ────────────────────────────────────────────────────
        if (!empty($paymentData['isAuthorize'])) {
            $payload['capture'] = false;
        }

        // ── Send ──────────────────────────────────────────────────────────────
        $endpoint = 'https://api.flutterwave.com/v3/charges?type=card';
        $this->log->info('execute', ['tx_ref' => $txRef, 'amount' => $amount, 'currency' => $currency]);

        $resp = $this->request('POST', $endpoint, $payload);
        return $this->handleChargeResponse($resp, $cardToken, $paymentData);
    }

    /**
     * Capture a previously authorised charge.
     *
     * $paymentData keys:
     *   iAuthorizePaymentId  string   Required  Flutterwave charge id / flw_ref
     *   amount               float    Optional  Partial capture amount
     */
    public function capturePayment(array $paymentData): array {
        if (!$this->cfg->isActive()) {
            return $this->fail('Flutterwave payment gateway is not active.');
        }

        $chargeId = $paymentData['iAuthorizePaymentId'] ?? '';
        if (empty($chargeId)) return $this->fail('iAuthorizePaymentId is required.');

        $body = [];
        if (!empty($paymentData['amount'])) {
            $body['amount'] = number_format(round((float)$paymentData['amount'], 2), 2, '.', '');
        }

        $url  = "https://api.flutterwave.com/v3/charges/{$chargeId}/capture";
        $resp = $this->request('POST', $url, $body);

        $this->log->info('capture', ['chargeId' => $chargeId]);
        return $this->handleSimpleResponse($resp, $chargeId);
    }

    /**
     * Void / cancel an authorised charge (not yet captured).
     *
     * $paymentData keys:
     *   iAuthorizePaymentId  string   Required
     */
    public function cancelAuthorizedPayment(array $paymentData): array {
        if (!$this->cfg->isActive()) {
            return $this->fail('Flutterwave payment gateway is not active.');
        }

        $chargeId = $paymentData['iAuthorizePaymentId'] ?? '';
        if (empty($chargeId)) return $this->fail('iAuthorizePaymentId is required.');

        $url  = "https://api.flutterwave.com/v3/charges/{$chargeId}/void";
        $resp = $this->request('POST', $url, []);

        $this->log->info('void', ['chargeId' => $chargeId]);
        return $this->handleSimpleResponse($resp, $chargeId);
    }

    /**
     * Refund a completed transaction.
     *
     * $paymentData keys:
     *   tPaymentTransactionId  string  Required  FLW transaction id
     *   amount                 float   Optional  Partial refund amount; omit for full refund
     *   comments               string  Optional
     */
    public function refundPayment(array $paymentData): array {
        if (!$this->cfg->isActive()) {
            return $this->fail('Flutterwave payment gateway is not active.');
        }

        if (!$this->cfg->autoRefundEnabled) {
            return $this->fail('Auto-refund is disabled in payment configuration.');
        }

        $txId = $paymentData['tPaymentTransactionId'] ?? '';
        if (empty($txId)) return $this->fail('tPaymentTransactionId is required.');

        $body = ['id' => $txId];
        if (!empty($paymentData['amount'])) {
            $body['amount'] = number_format(round((float)$paymentData['amount'], 2), 2, '.', '');
        }
        if (!empty($paymentData['comments'])) {
            $body['comments'] = $paymentData['comments'];
        }

        $url  = "https://api.flutterwave.com/v3/transactions/{$txId}/refund";
        $resp = $this->request('POST', $url, $body);

        $this->log->info('refund', ['txId' => $txId]);
        return $this->handleSimpleResponse($resp, $txId);
    }

    /**
     * Verify a transaction by its Flutterwave id.
     * Useful for confirming webhook events or redirect returns.
     */
    public function verifyTransaction(string $transactionId): array {
        if (empty($transactionId)) return $this->fail('transactionId is required.');

        $url  = "https://api.flutterwave.com/v3/transactions/{$transactionId}/verify";
        $resp = $this->request('GET', $url);

        if ($resp['http_code'] >= 200 && $resp['http_code'] < 300
            && ($resp['body']['status'] ?? '') === 'success') {
            $data   = $resp['body']['data'] ?? [];
            $status = $data['status'] ?? 'unknown';

            if (in_array($status, ['successful', 'completed'], true)) {
                return [
                    'Action'                  => '1',
                    'tPaymentTransactionId'   => $data['id'] ?? $transactionId,
                    'amount'                  => $data['amount'] ?? null,
                    'currency'                => $data['currency'] ?? null,
                    'status'                  => $status,
                    'flw_ref'                 => $data['flw_ref'] ?? null,
                    'tx_ref'                  => $data['tx_ref'] ?? null,
                    'message'                 => 'success',
                    'USER_APP_PAYMENT_METHOD' => 'Flutterwave',
                ];
            }

            return $this->fail($data['processor_response'] ?? 'Verification failed', $status);
        }

        return $this->fail($resp['body']['message'] ?? 'Verification API error');
    }

    /**
     * Initiate a mobile-money payment.
     *
     * $paymentData keys – same as execute() plus:
     *   network        string   Required  e.g. 'MTN', 'VODAFONE', 'TIGO'
     *   mobile_number  string   Required
     */
    public function mobileMoneyPayment(array $paymentData): array {
        if (!$this->cfg->mobileMoneyyEnabled) {
            return $this->fail('Mobile money payment is disabled in configuration.');
        }

        // Route through execute(); the network key is forwarded in the payload.
        return $this->execute($paymentData);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    /** Build the FLW customer object from available payment data */
    private function buildCustomer(array $pd, ?string $custId): array {
        $customer = [];
        if (!empty($custId))                    $customer['id']           = $custId;
        if (!empty($pd['customer_email']))      $customer['email']        = $pd['customer_email'];
        if (!empty($pd['customer_name']))       $customer['name']         = $pd['customer_name'];
        if (!empty($pd['customer_phone']))      $customer['phonenumber']  = $pd['customer_phone'];
        return $customer;
    }

    /** Validate amount against DB limits */
    private function validateAmount(float $amount): ?string {
        if ($amount <= 0) return 'Payment amount must be greater than zero.';
        if ($amount < $this->cfg->minTransactionAmount) {
            return "Amount is below the minimum allowed ({$this->cfg->minTransactionAmount}).";
        }
        if ($amount > $this->cfg->maxTransactionAmount) {
            return "Amount exceeds the maximum allowed ({$this->cfg->maxTransactionAmount}).";
        }
        return null;
    }

    /** Parse a charge API response into a standardised return array */
    private function handleChargeResponse(array $resp, ?string $cardToken, array $pd): array {
        $http = $resp['http_code'];
        $body = $resp['body'];

        if ($http >= 200 && $http < 300 && ($body['status'] ?? '') === 'success') {
            $data   = $body['data'] ?? [];
            $status = $data['status'] ?? null;

            // ── 3DS redirect required ─────────────────────────────────────────
            if (!empty($data['auth_url'])) {
                if (isset($pd['eType']) && $pd['eType'] !== 'DeliverAll') {
                    return [
                        'Action'                  => '1',
                        'AUTHENTICATION_REQUIRED' => 'Yes',
                        'AUTHENTICATION_URL'      => $data['auth_url'],
                    ];
                }
                header('Location: ' . $data['auth_url']);
                exit;
            }

            // ── Charge succeeded ──────────────────────────────────────────────
            if (in_array($status, ['successful', 'authorized', 'pending'], true)) {
                $result = [
                    'Action'                  => '1',
                    'tPaymentTransactionId'   => $data['id'] ?? ($data['flw_ref'] ?? null),
                    'tCardToken'              => $cardToken,
                    'message'                 => 'success',
                    'USER_APP_PAYMENT_METHOD' => 'Flutterwave',
                    'tx_ref'                  => $data['tx_ref'] ?? null,
                    'flw_ref'                 => $data['flw_ref'] ?? null,
                    'environment'             => $this->cfg->environment,
                ];

                // Card details
                $cardInfo   = $data['card']          ?? ($data['authorization'] ?? []);
                if (!empty($cardInfo)) {
                    $result['vCardBrand']  = $cardInfo['brand']      ?? ($cardInfo['card_type'] ?? null);
                    $result['last4digits'] = $cardInfo['last_4digits']?? ($cardInfo['last4']     ?? null);
                    $result['expiry']      = ($cardInfo['expiry'] ?? null);
                }

                $this->log->info('charge_success', ['id' => $result['tPaymentTransactionId']]);
                return $result;
            }

            // ── Charge failed ─────────────────────────────────────────────────
            $msg = $data['processor_response'] ?? ($body['message'] ?? 'Payment failed');
            $this->log->error('charge_failed', ['status' => $status, 'msg' => $msg]);
            return $this->fail($msg, $status ?? 'failed');
        }

        $errMsg = $body['message'] ?? 'API request failed';
        $this->log->error('api_error', ['http' => $http, 'msg' => $errMsg]);
        return $this->fail($errMsg);
    }

    /** Generic success/fail handler for capture, void, refund */
    private function handleSimpleResponse(array $resp, string $fallbackId): array {
        $http = $resp['http_code'];
        $body = $resp['body'];

        if ($http >= 200 && $http < 300 && ($body['status'] ?? '') === 'success') {
            $data = $body['data'] ?? [];
            return [
                'Action'                  => '1',
                'tPaymentTransactionId'   => $data['id'] ?? ($data['flw_ref'] ?? $fallbackId),
                'message'                 => 'success',
                'USER_APP_PAYMENT_METHOD' => 'Flutterwave',
            ];
        }

        return $this->fail($body['message'] ?? 'Operation failed');
    }

    /**
     * cURL wrapper with retry logic pulled from DB settings.
     *
     * @param string $method  'GET' | 'POST'
     * @param string $url
     * @param array  $body    Request body (ignored for GET)
     * @return array{ http_code: int, body: array, error: string }
     */
    private function request(string $method, string $url, array $body = []): array {
        $attempts  = max(1, $this->cfg->retryAttempts);
        $timeout   = max(5,  $this->cfg->apiTimeout);
        $lastResult= ['http_code' => 0, 'body' => [], 'error' => ''];

        for ($i = 0; $i < $attempts; $i++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->cfg->secretKey,
                ],
            ]);

            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(
                    array_filter($body, fn($v) => $v !== null && $v !== '')
                ));
            }

            $raw      = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($raw === false) {
                $lastResult = ['http_code' => 0, 'body' => [], 'error' => $curlErr];
                usleep(500000); // 0.5 s before retry
                continue;
            }

            $decoded    = json_decode($raw, true) ?? [];
            $lastResult = ['http_code' => $httpCode, 'body' => $decoded, 'error' => ''];

            // Only retry on network / server errors (5xx)
            if ($httpCode < 500) break;

            usleep(500000);
        }

        return $lastResult;
    }

    /** Build a standardised failure response */
    private function fail(string $message, string $status = 'failed'): array {
        return [
            'Action'  => '0',
            'status'  => $status,
            'message' => $message,
        ];
    }
}
