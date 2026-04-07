<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>

<?php
/**
 * index.php — Flutterwave library entry point / autoloader
 *
 * Place in: assets/libraries/webview/flutterwave/index.php
 *
 * Include this single file anywhere you need Flutterwave functionality:
 *
 *   require_once $tconfig['tpanel_path'] . 'assets/libraries/webview/flutterwave/index.php';
 *
 *   $flw    = FlutterwavePayment::getInstance();
 *   $result = $flw->execute($paymentData);
 */

// ── Prevent double-loading ────────────────────────────────────────────────────
if (defined('FLUTTERWAVE_LOADED')) return;
define('FLUTTERWAVE_LOADED', true);

// ── Require all library classes ───────────────────────────────────────────────
$_flw_base = __DIR__;

require_once $_flw_base . '/payment.php';
require_once $_flw_base . '/FlutterwaveConfig.php';
require_once $_flw_base . '/FlutterwaveLogger.php';
require_once $_flw_base . '/FlutterwavePayment.php';
require_once $_flw_base . '/FlutterwaveWebhook.php';

unset($_flw_base);
