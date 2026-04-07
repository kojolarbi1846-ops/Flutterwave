<?php
/**
 * FlutterwaveConfig.php
 * Loads all Flutterwave settings from `configurations_payment` table.
 *
 * Place in: assets/libraries/webview/flutterwave/FlutterwaveConfig.php
 */

class FlutterwaveConfig {

    /** @var FlutterwaveConfig|null */
    private static ?FlutterwaveConfig $instance = null;

    /** @var array<string,string> Raw DB values keyed by vName */
    private array $settings = [];

    /** @var string 'Test' or 'Live' */
    public string $environment;

    // ── API Credentials ──────────────────────────────────────────────────────
    public string $publicKey;
    public string $secretKey;
    public string $encryptionKey;

    // ── Webhook ───────────────────────────────────────────────────────────────
    public string $webhookUrl;
    public string $webhookSecret;

    // ── Payment Methods ───────────────────────────────────────────────────────
    public bool $cardEnabled;
    public bool $accountTransferEnabled;
    public bool $mobileMoneyyEnabled;
    public bool $ussdEnabled;
    public bool $africaStaplesEnabled;

    // ── Currency ──────────────────────────────────────────────────────────────
    public string $defaultCurrency;
    public array  $supportedCurrencies;
    public bool   $currencyConversionEnabled;

    // ── Transaction Limits ────────────────────────────────────────────────────
    public float $minTransactionAmount;
    public float $maxTransactionAmount;

    // ── Transfer Limits ───────────────────────────────────────────────────────
    public float $transferMinBalance;
    public float $maxTransferAmount;
    public float $maxTransferDaily;
    public int   $transferOtpExpiry;

    // ── Refunds ───────────────────────────────────────────────────────────────
    public bool $autoRefundEnabled;
    public int  $refundDays;
    public bool $refundEmailsEnabled;

    // ── Fraud Prevention ──────────────────────────────────────────────────────
    public bool   $fraudCheckEnabled;
    public string $fraudLevel;

    // ── Advanced / Operational ────────────────────────────────────────────────
    public int    $apiTimeout;
    public int    $retryAttempts;
    public string $paymentFlow;
    public bool   $loggingEnabled;
    public bool   $logSensitiveData;

    // ── Status ────────────────────────────────────────────────────────────────
    public string $status;          // 'Active' | 'Inactive'
    public bool   $isConfigured;
    public string $configVersion;

    // ── Auto-Settlement ───────────────────────────────────────────────────────
    public bool   $autoSettlementEnabled;
    public string $settlementFrequency;

    // ── Misc ──────────────────────────────────────────────────────────────────
    public string $paymentMode;     // e.g. 'Card,Account,Mobile Money,Wallet'
    public string $paymentMethod;   // e.g. 'Flutterwave'
    public bool   $restrictToWallet;
    public string $extraMoneyMethod;

    // ─────────────────────────────────────────────────────────────────────────

    private function __construct() {
        $this->load();
    }

    /**
     * Singleton accessor
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Force-reload settings from DB (useful after admin saves new values)
     */
    public static function refresh(): self {
        self::$instance = null;
        return self::getInstance();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Fetch all payment configurations from the DB and map them to properties.
     *
     * Expects the global $db object (or PDO / MySQLi) to be available.
     * Adjust the query execution to match your project's DB abstraction layer.
     */
    private function load(): void {
        $this->settings = $this->fetchFromDatabase();
        $this->mapProperties();
    }

    /**
     * Pull every row from configurations_payment where eStatus = 'Active'.
     * Returns [ 'vName' => 'vValue', ... ]
     *
     * ⚠️  Adapt the DB call below to match your project's connection helper.
     *     Common patterns already shown as examples.
     */
    private function fetchFromDatabase(): array {
        global $db; // Adjust to your project's DB variable / helper

        $map = [];

        try {
            /* ── Option A: PDO ──────────────────────────────────────────── */
            if ($db instanceof PDO) {
                $stmt = $db->prepare(
                    "SELECT `vName`, `vValue`
                     FROM   `configurations_payment`
                     WHERE  `eStatus` = 'Active'"
                );
                $stmt->execute();
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $map[$row['vName']] = $row['vValue'];
                }
            }

            /* ── Option B: MySQLi object ────────────────────────────────── */
            elseif ($db instanceof mysqli) {
                $result = $db->query(
                    "SELECT `vName`, `vValue`
                     FROM   `configurations_payment`
                     WHERE  `eStatus` = 'Active'"
                );
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $map[$row['vName']] = $row['vValue'];
                    }
                    $result->free();
                }
            }

            /* ── Option C: custom project helper (e.g. db_fetch_all) ────── */
            // elseif (function_exists('db_fetch_all')) {
            //     $rows = db_fetch_all("SELECT `vName`,`vValue` FROM `configurations_payment` WHERE `eStatus`='Active'");
            //     foreach ($rows as $row) { $map[$row['vName']] = $row['vValue']; }
            // }

            else {
                error_log('[FlutterwaveConfig] No recognised DB connection.');
            }

        } catch (Throwable $e) {
            error_log('[FlutterwaveConfig] DB error: ' . $e->getMessage());
        }

        return $map;
    }

    /** Map raw DB strings onto typed class properties */
    private function mapProperties(): void {
        $s = $this->settings;

        // ── Environment ───────────────────────────────────────────────────────
        $this->environment = $s['SYSTEM_PAYMENT_ENVIRONMENT'] ?? 'Test';

        $isLive = ($this->environment === 'Live');

        // ── API Keys (switch by environment automatically) ────────────────────
        $this->publicKey    = $isLive
            ? ($s['FLUTTERWAVE_PUBLIC_KEY_LIVE']     ?? '')
            : ($s['FLUTTERWAVE_PUBLIC_KEY_SANDBOX']  ?? '');

        $this->secretKey    = $isLive
            ? ($s['FLUTTERWAVE_SECRET_KEY_LIVE']     ?? '')
            : ($s['FLUTTERWAVE_SECRET_KEY_SANDBOX']  ?? '');

        $this->encryptionKey = $isLive
            ? ($s['FLUTTERWAVE_ENCRYPTION_KEY_LIVE']    ?? '')
            : ($s['FLUTTERWAVE_ENCRYPTION_KEY_SANDBOX'] ?? '');

        // ── Webhook ───────────────────────────────────────────────────────────
        $this->webhookUrl    = $s['FLUTTERWAVE_WEBHOOK_URL']    ?? '';
        $this->webhookSecret = $s['FLUTTERWAVE_WEBHOOK_SECRET'] ?? '';

        // ── Payment Methods ───────────────────────────────────────────────────
        $this->cardEnabled            = $this->toBool($s['FLUTTERWAVE_CARD_ENABLED']              ?? 'Yes');
        $this->accountTransferEnabled = $this->toBool($s['FLUTTERWAVE_ACCOUNT_TRANSFER_ENABLED']  ?? 'Yes');
        $this->mobileMoneyyEnabled    = $this->toBool($s['FLUTTERWAVE_MOBILE_MONEY_ENABLED']      ?? 'Yes');
        $this->ussdEnabled            = $this->toBool($s['FLUTTERWAVE_USSD_ENABLED']              ?? 'No');
        $this->africaStaplesEnabled   = $this->toBool($s['FLUTTERWAVE_AFRICA_STAPLES_ENABLED']    ?? 'No');

        // ── Currency ──────────────────────────────────────────────────────────
        $this->defaultCurrency           = $s['FLUTTERWAVE_DEFAULT_CURRENCY'] ?? 'USD';
        $this->supportedCurrencies       = array_map('trim', explode(',', $s['FLUTTERWAVE_CURRENCIES'] ?? 'USD'));
        $this->currencyConversionEnabled = $this->toBool($s['FLUTTERWAVE_CURRENCY_CONVERSION_ENABLED'] ?? 'No');

        // ── Transaction Limits ────────────────────────────────────────────────
        $this->minTransactionAmount = (float)($s['FLUTTERWAVE_MIN_AMOUNT'] ?? 1.00);
        $this->maxTransactionAmount = (float)($s['FLUTTERWAVE_MAX_AMOUNT'] ?? 999999.00);

        // ── Transfer Limits ───────────────────────────────────────────────────
        $this->transferMinBalance = (float)($s['FLUTTERWAVE_TRANSFER_MIN_BALANCE'] ?? 15.00);
        $this->maxTransferAmount  = (float)($s['FLUTTERWAVE_MAX_TRANSFER_AMOUNT']  ?? 10000.00);
        $this->maxTransferDaily   = (float)($s['FLUTTERWAVE_MAX_TRANSFER_DAILY']   ?? 50000.00);
        $this->transferOtpExpiry  = (int)  ($s['FLUTTERWAVE_TRANSFER_OTP_EXPIRY']  ?? 5);

        // ── Refunds ───────────────────────────────────────────────────────────
        $this->autoRefundEnabled  = $this->toBool($s['FLUTTERWAVE_AUTO_REFUND_ENABLED']    ?? 'Yes');
        $this->refundDays         = (int)          ($s['FLUTTERWAVE_REFUND_DAYS']          ?? 3);
        $this->refundEmailsEnabled= $this->toBool($s['FLUTTERWAVE_REFUND_EMAILS_ENABLED']  ?? 'Yes');

        // ── Fraud ─────────────────────────────────────────────────────────────
        $this->fraudCheckEnabled = $this->toBool($s['FLUTTERWAVE_FRAUD_CHECK_ENABLED'] ?? 'Yes');
        $this->fraudLevel        = $s['FLUTTERWAVE_FRAUD_LEVEL'] ?? 'Medium';

        // ── Advanced ──────────────────────────────────────────────────────────
        $this->apiTimeout      = (int)($s['FLUTTERWAVE_API_TIMEOUT']    ?? 30);
        $this->retryAttempts   = (int)($s['FLUTTERWAVE_RETRY_ATTEMPTS'] ?? 3);
        $this->paymentFlow     = $s['SYSTEM_PAYMENT_FLOW'] ?? 'Standard';
        $this->loggingEnabled  = $this->toBool($s['FLUTTERWAVE_LOGGING_ENABLED']    ?? 'Yes');
        $this->logSensitiveData= $this->toBool($s['FLUTTERWAVE_LOG_SENSITIVE_DATA'] ?? 'No');

        // ── Status ────────────────────────────────────────────────────────────
        $this->status        = $s['FLUTTERWAVE_STATUS']      ?? 'Active';
        $this->isConfigured  = $this->toBool($s['FLUTTERWAVE_CONFIGURED'] ?? 'No');
        $this->configVersion = $s['FLUTTERWAVE_CONFIG_VERSION'] ?? '1.0';

        // ── Settlement ────────────────────────────────────────────────────────
        $this->autoSettlementEnabled = $this->toBool($s['FLUTTERWAVE_AUTO_SETTLEMENT_ENABLED'] ?? 'No');
        $this->settlementFrequency   = $s['FLUTTERWAVE_SETTLEMENT_FREQUENCY'] ?? 'Daily';

        // ── Misc ──────────────────────────────────────────────────────────────
        $this->paymentMode      = $s['APP_PAYMENT_MODE']              ?? 'Card';
        $this->paymentMethod    = $s['APP_PAYMENT_METHOD']            ?? 'Flutterwave';
        $this->restrictToWallet = $this->toBool($s['PAYMENT_MODE_RESTRICT_TO_WALLET'] ?? 'No');
        $this->extraMoneyMethod = $s['EXTRA_MONEY_CASH_OR_OUTSTANDING'] ?? 'Cash';
    }

    // ── Public utilities ──────────────────────────────────────────────────────

    /**
     * Get a raw DB setting by key.
     * Useful for ad-hoc lookups that do not have a dedicated property.
     */
    public function get(string $key, string $default = ''): string {
        return $this->settings[$key] ?? $default;
    }

    /** True when the gateway is enabled and keys are present */
    public function isActive(): bool {
        return $this->status === 'Active'
            && !empty($this->secretKey)
            && !empty($this->publicKey);
    }

    /** Return the active environment label */
    public function envLabel(): string {
        return $this->environment; // 'Test' | 'Live'
    }

    /** Human-readable list of enabled payment options for the FLW inline widget */
    public function enabledPaymentOptions(): string {
        $options = [];
        if ($this->cardEnabled)            $options[] = 'card';
        if ($this->accountTransferEnabled) $options[] = 'account';
        if ($this->mobileMoneyyEnabled)    $options[] = 'mobilemoney';
        if ($this->ussdEnabled)            $options[] = 'ussd';
        return implode(',', $options) ?: 'card';
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function toBool(string $value): bool {
        return strtolower($value) === 'yes';
    }
}
