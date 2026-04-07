<?php
/**
 * FlutterwaveLogger.php
 * Lightweight file logger controlled by DB config flags.
 *
 * Place in: assets/libraries/webview/flutterwave/FlutterwaveLogger.php
 */

require_once __DIR__ . '/FlutterwaveConfig.php';

class FlutterwaveLogger {

    private FlutterwaveConfig $cfg;

    /** Absolute path to the log file */
    private string $logFile;

    public function __construct(FlutterwaveConfig $cfg) {
        $this->cfg     = $cfg;
        // Store logs one level above the library folder so they are not web-accessible
        $this->logFile = dirname(__DIR__, 3) . '/logs/flutterwave.log';
    }

    // ── Public API ────────────────────────────────────────────────────────────

    public function info(string $action, array $context = []): void {
        if (!$this->cfg->loggingEnabled) return;
        $this->write('INFO', $action, $context);
    }

    public function error(string $action, array $context = []): void {
        // Errors are always logged regardless of FLUTTERWAVE_LOGGING_ENABLED
        $this->write('ERROR', $action, $context);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function write(string $level, string $action, array $context): void {
        // Strip sensitive fields unless explicitly allowed by DB flag
        if (!$this->cfg->logSensitiveData) {
            $context = $this->stripSensitive($context);
        }

        $entry = sprintf(
            "[%s] [%s] [%s] [env:%s] %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $action,
            $this->cfg->environment,
            json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        @file_put_contents($this->logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    /** Remove sensitive keys from context before writing */
    private function stripSensitive(array $context): array {
        $sensitive = ['card_number', 'cvv', 'pin', 'otp', 'authorization_code', 'tCardToken', 'secretKey'];
        foreach ($sensitive as $key) {
            if (array_key_exists($key, $context)) {
                $context[$key] = '***REDACTED***';
            }
        }
        return $context;
    }
}
