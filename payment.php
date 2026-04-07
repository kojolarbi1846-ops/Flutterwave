<?php
/**
 * payment.php — Flutterwave webview loader (COMPLETE / MERGED)
 * Reads config from DB via FlutterwaveConfig and renders the payment UI.
 *
 * Usage:
 *   require_once '.../flutterwave/payment.php';
 *   FlutterwaveWebview::render($orderData);
 *
 * Or direct URL load:
 *   https://yourdomain.com/pay?order_id=5821
 */

require_once __DIR__ . '/index.php'; // loads Config, Payment, Webhook, Logger

class FlutterwaveWebview {

    /**
     * Render the full payment page.
     *
     * @param array $order {
     *   amount        float    Required
     *   order_id      mixed    Required
     *   currency      string   Optional  Defaults to DB config (GHS)
     *   description   string   Optional
     *   merchant_name string   Optional
     *   customer_name string   Optional
     *   customer_email string  Optional
     *   customer_phone string  Optional
     *   return_url    string   Optional  3DS redirect target
     * }
     */
    public static function render(array $order): void {
        $cfg = FlutterwaveConfig::getInstance();

        // Guard: gateway must be active
        if (!$cfg->isActive()) {
            self::renderError('Payment gateway is currently unavailable. Please try again later.');
            return;
        }

        // Resolve order fields with DB-config fallbacks
        $amount        = number_format((float)($order['amount']        ?? 0), 2, '.', '');
        $currency      = strtoupper($order['currency']      ?? $cfg->defaultCurrency);
        $orderId       = $order['order_id']      ?? ('ord-' . time());
        $description   = htmlspecialchars($order['description']   ?? 'Payment');
        $merchantName  = htmlspecialchars($order['merchant_name'] ?? 'Merchant');
        $custName      = htmlspecialchars($order['customer_name'] ?? '');
        $custEmail     = htmlspecialchars($order['customer_email']?? '');
        $custPhone     = htmlspecialchars($order['customer_phone']?? '');
        $returnUrl     = htmlspecialchars($order['return_url']    ?? '');
        $txRef         = 'FLW-' . $orderId . '-' . time();

        // Payment options from DB config
        $cardEnabled    = $cfg->cardEnabled;
        $momoEnabled    = $cfg->mobileMoneyyEnabled;
        $bankEnabled    = $cfg->accountTransferEnabled;
        $environment    = $cfg->environment; // 'Test' | 'Live'
        $publicKey      = $cfg->publicKey;

        // Flutterwave inline JS SDK URL
        $sdkUrl = 'https://checkout.flutterwave.com/v3.js';

        self::renderPage(compact(
            'amount', 'currency', 'orderId', 'description', 'merchantName',
            'custName', 'custEmail', 'custPhone', 'returnUrl', 'txRef',
            'cardEnabled', 'momoEnabled', 'bankEnabled',
            'environment', 'publicKey', 'sdkUrl'
        ));
    }

    // ── Private ────────────────────────────────────────────────────────────

    private static function renderError(string $msg): void { ?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Payment unavailable</title>
<style>
body { font-family: sans-serif; display: flex; align-items: center; justify-content: center;
       min-height: 100vh; background: #faf8f5; margin: 0; }
.box { text-align: center; padding: 40px; max-width: 360px; }
.icon { font-size: 40px; margin-bottom: 16px; }
h2 { font-size: 18px; color: #0d0d0d; margin: 0 0 8px; }
p  { color: #8a8a8a; font-size: 14px; }
</style>
</head>
<body>
  <div class="box">
    <div class="icon">⚠️</div>
    <h2>Payment unavailable</h2>
    <p><?= htmlspecialchars($msg) ?></p>
  </div>
</body>
</html>
<?php exit; }

    private static function renderPage(array $d): void {
        extract($d);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Pay <?= $currency ?> <?= $amount ?> · <?= $merchantName ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

<style>
/* ── Reset & base ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --ink: #0d0d0d;
  --ink-2: #4a4a4a;
  --ink-3: #8a8a8a;
  --line: #e8e4de;
  --line-2: #f2efe9;
  --surface: #faf8f5;
  --white: #ffffff;
  --accent: #f5a623;
  --accent-deep: #e8920f;
  --accent-pale: #fef6e4;
  --green: #1a9e75;
  --green-pale: #e6f7f2;
  --red: #e5423a;
  --red-pale: #fdeeed;
  --blue: #2563eb;
  --blue-pale: #eff4ff;
  --r: 14px;
  --r-sm: 8px;
  --shadow: 0 2px 24px rgba(0,0,0,.07), 0 1px 4px rgba(0,0,0,.05);
  --shadow-lg: 0 8px 48px rgba(0,0,0,.12), 0 2px 8px rgba(0,0,0,.06);
  --font: 'DM Sans', sans-serif;
  --mono: 'DM Mono', monospace;
  --transition: cubic-bezier(0.4, 0, 0.2, 1);
}
html { font-size: 16px; }
body {
  font-family: var(--font);
  background: var(--surface);
  color: var(--ink);
  min-height: 100vh;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 0;
  overflow-x: hidden;
}

/* ── Layout ── */
.shell {
  width: 100%;
  max-width: 460px;
  min-height: 100vh;
  background: var(--white);
  position: relative;
  display: flex;
  flex-direction: column;
  box-shadow: var(--shadow-lg);
}

/* ── Header ── */
.header { padding: 20px 24px 0; position: relative; }
.header-top {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 20px;
}
.back-btn {
  width: 36px; height: 36px; border-radius: 50%;
  border: 1px solid var(--line); background: var(--white);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  transition: background .15s var(--transition), border-color .15s var(--transition);
}
.back-btn:hover { background: var(--surface); border-color: var(--ink-3); }
.back-btn svg { width: 16px; height: 16px; stroke: var(--ink-2); }
.secure-badge {
  display: flex; align-items: center; gap: 5px;
  font-size: 11px; font-weight: 500; color: var(--green);
  background: var(--green-pale); border-radius: 99px; padding: 4px 10px;
}
.secure-badge svg { width: 11px; height: 11px; fill: var(--green); }

/* ── Merchant block ── */
.merchant {
  display: flex; align-items: center; gap: 14px;
  padding: 16px 24px 20px;
  border-bottom: 1px solid var(--line-2);
}
.merchant-logo {
  width: 48px; height: 48px; border-radius: 12px;
  background: linear-gradient(135deg, var(--accent) 0%, var(--accent-deep) 100%);
  display: flex; align-items: center; justify-content: center;
  font-size: 20px; font-weight: 700; color: var(--white);
  flex-shrink: 0; letter-spacing: -.5px;
}
.merchant-info { flex: 1; }
.merchant-name { font-size: 15px; font-weight: 600; color: var(--ink); }
.merchant-desc { font-size: 12px; color: var(--ink-3); margin-top: 2px; }
.amount-display { text-align: right; }
.amount-label { font-size: 11px; color: var(--ink-3); text-transform: uppercase; letter-spacing: .06em; }
.amount-value { font-size: 22px; font-weight: 600; color: var(--ink); letter-spacing: -.5px; line-height: 1.2; }
.amount-currency { font-size: 13px; font-weight: 400; color: var(--ink-2); margin-right: 2px; }

/* ── Tab switcher ── */
.tabs {
  display: flex; padding: 16px 24px 0; gap: 4px;
  background: var(--white); position: sticky; top: 0; z-index: 10;
  border-bottom: 1px solid var(--line-2);
}
.tab {
  flex: 1; display: flex; flex-direction: column; align-items: center;
  gap: 4px; padding: 8px 6px 12px;
  border-bottom: 2px solid transparent; cursor: pointer;
  transition: all .2s var(--transition);
  font-size: 11px; font-weight: 500; color: var(--ink-3);
  text-align: center; user-select: none;
}
.tab.active { border-bottom-color: var(--accent); color: var(--ink); }
.tab-icon {
  width: 28px; height: 28px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  background: var(--line-2); transition: background .2s;
}
.tab.active .tab-icon { background: var(--accent-pale); }
.tab-icon svg { width: 14px; height: 14px; stroke: var(--ink-3); fill: none; }
.tab.active .tab-icon svg { stroke: var(--accent-deep); fill: none; }

/* ── Panels ── */
.panels { flex: 1; overflow: hidden; }
.panel { display: none; padding: 24px; }
.panel.active { display: block; animation: fadeSlide .25s var(--transition); }
@keyframes fadeSlide {
  from { opacity: 0; transform: translateY(6px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ── Form elements ── */
.field { margin-bottom: 16px; }
.field-label {
  font-size: 12px; font-weight: 500; color: var(--ink-2);
  margin-bottom: 6px;
  display: flex; align-items: center; justify-content: space-between;
}
.field-label-hint { font-weight: 400; color: var(--ink-3); }
.input-wrap { position: relative; }
.input {
  width: 100%; height: 48px;
  border: 1.5px solid var(--line); border-radius: var(--r-sm);
  font-family: var(--font); font-size: 15px; color: var(--ink);
  padding: 0 14px; background: var(--white); outline: none;
  transition: border-color .15s var(--transition), box-shadow .15s var(--transition);
  -webkit-appearance: none;
}
.input:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(245,166,35,.12);
}
.input.has-icon { padding-left: 42px; }
.input.has-icon-right { padding-right: 42px; }
.input-icon {
  position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
  width: 16px; height: 16px; stroke: var(--ink-3); fill: none; pointer-events: none;
}
.input-icon-right {
  position: absolute; right: 14px; top: 50%; transform: translateY(-50%); cursor: pointer;
}
.input-row { display: flex; gap: 10px; }
.input-row .field { flex: 1; }
.card-number-display { font-family: var(--mono); letter-spacing: .08em; font-size: 15px; }

/* ── Card preview ── */
.card-preview-wrap { margin-bottom: 20px; perspective: 900px; }
.card-preview {
  width: 100%; aspect-ratio: 1.586 / 1; border-radius: 16px;
  background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
  padding: 22px 24px;
  display: flex; flex-direction: column; justify-content: space-between;
  position: relative; overflow: hidden;
  transition: transform .6s var(--transition);
  transform-style: preserve-3d;
  box-shadow: 0 16px 40px rgba(0,0,0,.25);
}
.card-preview::before {
  content: ''; position: absolute; top: -60px; right: -60px;
  width: 220px; height: 220px; border-radius: 50%;
  background: rgba(255,255,255,.04);
}
.card-preview::after {
  content: ''; position: absolute; bottom: -80px; left: -40px;
  width: 240px; height: 240px; border-radius: 50%;
  background: rgba(245,166,35,.08);
}
.card-chip {
  width: 36px; height: 28px; border-radius: 6px;
  background: linear-gradient(135deg, #d4af37 0%, #f9d76e 50%, #d4af37 100%);
}
.card-network { position: absolute; top: 22px; right: 24px; display: flex; gap: 4px; align-items: center; }
.card-network-circle { width: 28px; height: 28px; border-radius: 50%; opacity: .9; }
.card-network-circle:first-child { background: #eb001b; margin-right: -12px; }
.card-network-circle:last-child  { background: #f79e1b; }
.card-visa-text { font-size: 20px; font-weight: 700; color: rgba(255,255,255,.9); font-style: italic; letter-spacing: -1px; }
.card-number-display-card {
  font-family: var(--mono); font-size: 17px; letter-spacing: .18em;
  color: rgba(255,255,255,.9); margin-top: auto;
}
.card-bottom { display: flex; align-items: flex-end; justify-content: space-between; }
.card-holder { font-size: 11px; }
.card-holder-label { color: rgba(255,255,255,.45); text-transform: uppercase; letter-spacing: .08em; font-size: 9px; }
.card-holder-name { color: rgba(255,255,255,.85); font-weight: 500; margin-top: 2px; text-transform: uppercase; letter-spacing: .06em; }
.card-expiry { text-align: right; font-size: 11px; }
.card-expiry-label { color: rgba(255,255,255,.45); text-transform: uppercase; letter-spacing: .08em; font-size: 9px; }
.card-expiry-value { color: rgba(255,255,255,.85); font-weight: 500; margin-top: 2px; font-family: var(--mono); }

/* ── Saved cards ── */
.saved-cards { margin-bottom: 16px; }
.saved-card-item {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 14px;
  border: 1.5px solid var(--line); border-radius: var(--r-sm);
  margin-bottom: 8px; cursor: pointer;
  transition: border-color .15s, background .15s; position: relative;
}
.saved-card-item:hover { border-color: var(--accent); background: var(--accent-pale); }
.saved-card-item.selected { border-color: var(--accent); background: var(--accent-pale); }
.saved-card-icon {
  width: 36px; height: 24px; border-radius: 4px;
  display: flex; align-items: center; justify-content: center;
  background: var(--surface); font-size: 9px; font-weight: 600; font-style: italic;
}
.saved-card-info { flex: 1; }
.saved-card-number { font-family: var(--mono); font-size: 13px; color: var(--ink); }
.saved-card-expiry { font-size: 11px; color: var(--ink-3); margin-top: 1px; }
.saved-card-radio {
  width: 18px; height: 18px; border-radius: 50%;
  border: 2px solid var(--line);
  display: flex; align-items: center; justify-content: center;
  transition: border-color .15s;
}
.saved-card-item.selected .saved-card-radio { border-color: var(--accent); }
.saved-card-radio::after {
  content: ''; width: 8px; height: 8px; border-radius: 50%;
  background: var(--accent); transform: scale(0); transition: transform .15s;
}
.saved-card-item.selected .saved-card-radio::after { transform: scale(1); }

/* ── Divider ── */
.divider { display: flex; align-items: center; gap: 10px; margin: 16px 0; }
.divider-line { flex: 1; height: 1px; background: var(--line-2); }
.divider-text { font-size: 11px; color: var(--ink-3); font-weight: 500; text-transform: uppercase; letter-spacing: .06em; }

/* ── Mobile money ── */
.network-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px; }
.network-item {
  border: 1.5px solid var(--line); border-radius: var(--r-sm);
  padding: 12px 8px; text-align: center; cursor: pointer;
  transition: all .15s var(--transition); position: relative;
}
.network-item:hover { border-color: var(--accent-deep); }
.network-item.selected { border-color: var(--accent); background: var(--accent-pale); }
.network-logo {
  width: 40px; height: 40px; border-radius: 50%;
  margin: 0 auto 6px;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: 11px;
}
.network-mtn  { background: #ffc000; color: #1a1a1a; }
.network-voda { background: #e60000; color: white; }
.network-at   { background: #0060af; color: white; }
.network-name { font-size: 11px; font-weight: 500; color: var(--ink-2); }
.network-check {
  position: absolute; top: -6px; right: -6px;
  width: 18px; height: 18px; border-radius: 50%;
  background: var(--accent); color: white;
  display: none; align-items: center; justify-content: center;
  font-size: 10px; font-weight: 700;
}
.network-item.selected .network-check { display: flex; }

/* ── Bank transfer ── */
.bank-info-card {
  background: var(--surface); border: 1px solid var(--line);
  border-radius: var(--r); padding: 18px; margin-bottom: 16px;
}
.bank-row {
  display: flex; justify-content: space-between; align-items: center;
  padding: 9px 0; border-bottom: 1px solid var(--line-2);
}
.bank-row:last-child { border-bottom: none; padding-bottom: 0; }
.bank-row-label { font-size: 12px; color: var(--ink-3); }
.bank-row-value { font-size: 13px; font-weight: 500; color: var(--ink); display: flex; align-items: center; gap: 6px; }
.copy-btn {
  padding: 3px 8px; border-radius: 4px;
  border: 1px solid var(--line); background: var(--white);
  font-size: 10px; font-weight: 500; color: var(--ink-2); cursor: pointer;
  transition: all .12s; font-family: var(--font);
}
.copy-btn:hover { border-color: var(--accent); color: var(--accent-deep); }
.copy-btn.copied { border-color: var(--green); color: var(--green); background: var(--green-pale); }
.bank-timer {
  display: flex; align-items: center; gap: 8px;
  background: var(--accent-pale); border: 1px solid rgba(245,166,35,.2);
  border-radius: var(--r-sm); padding: 10px 14px; margin-bottom: 16px;
}
.bank-timer-icon { font-size: 16px; }
.bank-timer-text { font-size: 12px; color: var(--accent-deep); }
.bank-timer-count { font-family: var(--mono); font-weight: 600; }

/* ── Pay button ── */
.pay-section { padding: 0 24px 32px; }
.pay-btn {
  width: 100%; height: 52px; border-radius: var(--r-sm); border: none;
  background: var(--accent); color: var(--white);
  font-family: var(--font); font-size: 15px; font-weight: 600;
  cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
  transition: background .15s, transform .12s, box-shadow .15s;
  position: relative; overflow: hidden; letter-spacing: .01em;
}
.pay-btn::before {
  content: ''; position: absolute; inset: 0;
  background: rgba(255,255,255,.12); opacity: 0; transition: opacity .15s;
}
.pay-btn:hover { background: var(--accent-deep); box-shadow: 0 4px 20px rgba(232,146,15,.35); }
.pay-btn:hover::before { opacity: 1; }
.pay-btn:active { transform: scale(.985); }
.pay-btn.loading { pointer-events: none; background: var(--accent-deep); }
.pay-btn.loading .btn-text { opacity: 0; }
.pay-btn .spinner {
  position: absolute; width: 20px; height: 20px;
  border: 2.5px solid rgba(255,255,255,.3); border-top-color: white;
  border-radius: 50%; animation: spin .6s linear infinite; display: none;
}
.pay-btn.loading .spinner { display: block; }
@keyframes spin { to { transform: rotate(360deg); } }
.pay-btn svg { width: 16px; height: 16px; stroke: white; fill: none; }
.pay-footnote {
  text-align: center; font-size: 11px; color: var(--ink-3); margin-top: 12px;
  display: flex; align-items: center; justify-content: center; gap: 4px;
}
.pay-footnote svg { width: 11px; height: 11px; stroke: var(--ink-3); fill: none; }

/* ── Success screen ── */
.success-screen {
  display: none; flex-direction: column; align-items: center;
  padding: 48px 24px 40px; text-align: center;
  animation: fadeSlide .35s var(--transition);
}
.success-screen.show { display: flex; }
.success-ring {
  width: 80px; height: 80px; border-radius: 50%;
  background: var(--green-pale);
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 24px; position: relative;
}
.success-ring::before {
  content: ''; position: absolute; inset: -6px; border-radius: 50%;
  border: 1.5px solid var(--green); opacity: .25;
  animation: ringPulse 2s ease-in-out infinite;
}
@keyframes ringPulse {
  0%, 100% { transform: scale(1); opacity: .25; }
  50% { transform: scale(1.06); opacity: .12; }
}
.success-ring svg { width: 36px; height: 36px; stroke: var(--green); fill: none; stroke-width: 2; }
.success-title { font-size: 22px; font-weight: 600; color: var(--ink); margin-bottom: 6px; letter-spacing: -.4px; }
.success-subtitle { font-size: 14px; color: var(--ink-2); margin-bottom: 28px; line-height: 1.6; }
.success-card {
  width: 100%; background: var(--surface); border: 1px solid var(--line);
  border-radius: var(--r); overflow: hidden; margin-bottom: 24px; text-align: left;
}
.success-row {
  display: flex; justify-content: space-between; align-items: center;
  padding: 12px 16px; border-bottom: 1px solid var(--line-2);
}
.success-row:last-child { border-bottom: none; }
.success-row-label { font-size: 12px; color: var(--ink-3); }
.success-row-value { font-size: 13px; font-weight: 500; color: var(--ink); }
.success-amount-row { background: var(--green-pale); padding: 16px; }
.success-amount-label { font-size: 12px; color: var(--green); font-weight: 500; }
.success-amount-value { font-size: 28px; font-weight: 700; color: var(--green); letter-spacing: -.8px; }
.success-btn {
  width: 100%; height: 48px; border-radius: var(--r-sm);
  border: 1.5px solid var(--line); background: var(--white);
  font-family: var(--font); font-size: 14px; font-weight: 500;
  color: var(--ink-2); cursor: pointer; transition: all .15s;
}
.success-btn:hover { border-color: var(--ink-3); color: var(--ink); }

/* ── Error toast ── */
.error-toast {
  position: fixed; bottom: 80px; left: 50%;
  transform: translateX(-50%) translateY(20px);
  background: var(--ink); color: white;
  font-size: 13px; font-weight: 500;
  padding: 10px 18px; border-radius: 99px; white-space: nowrap;
  opacity: 0; transition: all .25s var(--transition);
  z-index: 100; pointer-events: none;
}
.error-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

/* ── Field states ── */
.input.error { border-color: var(--red); box-shadow: 0 0 0 3px rgba(229,66,58,.1); }
.field-error { font-size: 11px; color: var(--red); margin-top: 4px; display: none; }
.field-error.show { display: block; }

/* ── Checkbox ── */
.checkbox-wrap { display: flex; align-items: center; gap: 10px; cursor: pointer; margin-top: 4px; }
.checkbox-box {
  width: 18px; height: 18px; border-radius: 4px;
  border: 1.5px solid var(--line); background: var(--white);
  display: flex; align-items: center; justify-content: center;
  transition: all .15s; flex-shrink: 0;
}
.checkbox-box.checked { background: var(--accent); border-color: var(--accent); }
.checkbox-box svg { width: 10px; height: 10px; stroke: white; fill: none; display: none; }
.checkbox-box.checked svg { display: block; }
.checkbox-label { font-size: 12px; color: var(--ink-2); }

/* ── Footer ── */
.flw-powered {
  display: flex; align-items: center; justify-content: center; gap: 5px;
  padding: 12px; font-size: 11px; color: var(--ink-3);
  border-top: 1px solid var(--line-2);
}
.flw-powered strong { color: var(--accent-deep); }
.amount-breakdown {
  background: var(--surface); border-radius: var(--r-sm);
  padding: 12px 14px; margin-bottom: 16px; font-size: 12px;
}
.amount-row {
  display: flex; justify-content: space-between; padding: 3px 0; color: var(--ink-2);
}
.amount-row.total {
  border-top: 1px solid var(--line); margin-top: 6px; padding-top: 8px;
  font-weight: 600; font-size: 13px; color: var(--ink);
}

/* ── Responsive ── */
@media (max-width: 460px) { .shell { box-shadow: none; } }
</style>
</head>
<body>

<?php /* ── PHP-injected config — available to all JS below ── */ ?>
<script>
const FLW_CONFIG = {
    publicKey:   <?= json_encode($publicKey) ?>,
    txRef:       <?= json_encode($txRef) ?>,
    amount:      <?= json_encode((float)$amount) ?>,
    currency:    <?= json_encode($currency) ?>,
    orderId:     <?= json_encode((string)$orderId) ?>,
    description: <?= json_encode($description) ?>,
    merchant:    <?= json_encode($merchantName) ?>,
    custName:    <?= json_encode($custName) ?>,
    custEmail:   <?= json_encode($custEmail) ?>,
    custPhone:   <?= json_encode($custPhone) ?>,
    returnUrl:   <?= json_encode($returnUrl) ?>,
    environment: <?= json_encode($environment) ?>,
    cardEnabled: <?= json_encode((bool)$cardEnabled) ?>,
    momoEnabled: <?= json_encode((bool)$momoEnabled) ?>,
    bankEnabled: <?= json_encode((bool)$bankEnabled) ?>,
    processUrl:  <?= json_encode(self::processUrl()) ?>,
};
</script>

<!-- ══════════════════════════════════════════
     MAIN PAYMENT SHELL
══════════════════════════════════════════ -->
<div class="shell" id="mainShell">

  <!-- Header -->
  <div class="header">
    <div class="header-top">
      <button class="back-btn" onclick="goBack()" title="Go back">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M19 12H5M12 19l-7-7 7-7"/>
        </svg>
      </button>
      <span class="secure-badge">
        <svg viewBox="0 0 16 16"><path d="M8 1L2 4v5c0 3 2.5 5.5 6 6.5C11.5 14.5 14 12 14 9V4L8 1z" fill="currentColor"/></svg>
        Secure checkout
      </span>
    </div>
  </div>

  <!-- Merchant info (populated by JS on DOMContentLoaded) -->
  <div class="merchant" id="merchantBlock">
    <div class="merchant-logo" id="merchantLogo"><?= strtoupper(substr($merchantName, 0, 2)) ?></div>
    <div class="merchant-info">
      <div class="merchant-name" id="merchantName"><?= $merchantName ?></div>
      <div class="merchant-desc" id="merchantDesc"><?= $description ?></div>
    </div>
    <div class="amount-display">
      <div class="amount-label">Total</div>
      <div class="amount-value">
        <span class="amount-currency" id="currencyDisplay"><?= $currency ?></span><span id="amountDisplay"><?= $amount ?></span>
      </div>
    </div>
  </div>

  <!-- Tabs (hidden per disabled payment method via JS) -->
  <div class="tabs" id="tabsEl">
    <div class="tab active" onclick="switchTab('card')" id="tab-card">
      <div class="tab-icon">
        <svg viewBox="0 0 24 24" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>
        </svg>
      </div>
      Card
    </div>
    <div class="tab" onclick="switchTab('momo')" id="tab-momo">
      <div class="tab-icon">
        <svg viewBox="0 0 24 24" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/>
        </svg>
      </div>
      Mobile Money
    </div>
    <div class="tab" onclick="switchTab('bank')" id="tab-bank">
      <div class="tab-icon">
        <svg viewBox="0 0 24 24" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11M20 10v11M8 14v4M12 14v4M16 14v4"/>
        </svg>
      </div>
      Bank Transfer
    </div>
  </div>

  <!-- Panels -->
  <div class="panels" id="panelsEl">

    <!-- ── Card Panel ── -->
    <div class="panel active" id="panel-card">

      <!-- Live card preview -->
      <div class="card-preview-wrap">
        <div class="card-preview" id="cardPreview">
          <div class="card-chip"></div>
          <div class="card-network" id="cardNetwork">
            <div class="card-network-circle"></div>
            <div class="card-network-circle"></div>
          </div>
          <div class="card-number-display-card" id="cardPreviewNumber">•••• &nbsp;•••• &nbsp;•••• &nbsp;••••</div>
          <div class="card-bottom">
            <div class="card-holder">
              <div class="card-holder-label">Cardholder</div>
              <div class="card-holder-name" id="cardPreviewName">YOUR NAME</div>
            </div>
            <div class="card-expiry">
              <div class="card-expiry-label">Expires</div>
              <div class="card-expiry-value" id="cardPreviewExpiry">MM / YY</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Saved cards -->
      <div class="saved-cards" id="savedCards">
        <div class="field-label">Saved cards</div>
        <div class="saved-card-item selected" onclick="selectSavedCard(this,'4242')" data-last4="4242">
          <div class="saved-card-icon" style="background:#1a1f71;color:white;font-style:italic;font-weight:700">VISA</div>
          <div class="saved-card-info">
            <div class="saved-card-number">•••• •••• •••• 4242</div>
            <div class="saved-card-expiry">Expires 08 / 26</div>
          </div>
          <div class="saved-card-radio"></div>
        </div>
        <div class="saved-card-item" onclick="selectSavedCard(this,'5100')" data-last4="5100">
          <div class="saved-card-icon" style="display:flex;gap:-4px">
            <div style="width:18px;height:18px;border-radius:50%;background:#eb001b;margin-right:-6px"></div>
            <div style="width:18px;height:18px;border-radius:50%;background:#f79e1b"></div>
          </div>
          <div class="saved-card-info">
            <div class="saved-card-number">•••• •••• •••• 5100</div>
            <div class="saved-card-expiry">Expires 03 / 27</div>
          </div>
          <div class="saved-card-radio"></div>
        </div>
      </div>

      <div class="divider">
        <div class="divider-line"></div>
        <div class="divider-text">New card</div>
        <div class="divider-line"></div>
      </div>

      <!-- Card form -->
      <div class="field">
        <div class="field-label">Card number</div>
        <div class="input-wrap">
          <svg class="input-icon" viewBox="0 0 24 24" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>
          </svg>
          <input class="input has-icon card-number-display" id="cardNumber" type="text"
            inputmode="numeric" placeholder="0000 0000 0000 0000"
            maxlength="19" autocomplete="cc-number"
            oninput="formatCardNumber(this)" onfocus="deselectSaved()">
        </div>
        <div class="field-error" id="cardNumberErr">Please enter a valid card number</div>
      </div>

      <div class="field">
        <div class="field-label">Cardholder name</div>
        <div class="input-wrap">
          <svg class="input-icon" viewBox="0 0 24 24" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
          </svg>
          <input class="input has-icon" id="cardName" type="text"
            placeholder="Name on card" autocomplete="cc-name"
            oninput="updateCardPreview()" onfocus="deselectSaved()">
        </div>
      </div>

      <div class="input-row">
        <div class="field">
          <div class="field-label">Expiry date</div>
          <input class="input card-number-display" id="cardExpiry" type="text"
            inputmode="numeric" placeholder="MM / YY"
            maxlength="7" autocomplete="cc-exp"
            oninput="formatExpiry(this)" onfocus="deselectSaved()">
          <div class="field-error" id="cardExpiryErr">Invalid expiry</div>
        </div>
        <div class="field">
          <div class="field-label">
            CVV
            <span class="field-label-hint">3-4 digits</span>
          </div>
          <div class="input-wrap">
            <input class="input has-icon-right card-number-display" id="cardCvv" type="password"
              inputmode="numeric" placeholder="•••"
              maxlength="4" autocomplete="cc-csc" onfocus="deselectSaved()">
            <div class="input-icon-right" onclick="toggleCvv()">
              <svg id="eyeIcon" viewBox="0 0 24 24" width="16" height="16"
                stroke="var(--ink-3)" fill="none" stroke-width="1.8"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
              </svg>
            </div>
          </div>
          <div class="field-error" id="cardCvvErr">Invalid CVV</div>
        </div>
      </div>

      <div class="checkbox-wrap" onclick="toggleSaveCard()">
        <div class="checkbox-box" id="saveCardBox">
          <svg viewBox="0 0 12 12" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2 6l3 3 5-5"/>
          </svg>
        </div>
        <span class="checkbox-label">Save card for future payments</span>
      </div>
    </div><!-- /panel-card -->

    <!-- ── Mobile Money Panel ── -->
    <div class="panel" id="panel-momo">
      <div class="field-label" style="margin-bottom:12px">Select your network</div>
      <div class="network-grid">
        <div class="network-item selected" onclick="selectNetwork(this,'MTN')" id="net-MTN">
          <div class="network-check">✓</div>
          <div class="network-logo network-mtn">MTN</div>
          <div class="network-name">MTN MoMo</div>
        </div>
        <div class="network-item" onclick="selectNetwork(this,'VODAFONE')" id="net-VODAFONE">
          <div class="network-check">✓</div>
          <div class="network-logo network-voda">VODA</div>
          <div class="network-name">Telecel</div>
        </div>
        <div class="network-item" onclick="selectNetwork(this,'TIGO')" id="net-TIGO">
          <div class="network-check">✓</div>
          <div class="network-logo network-at">AT</div>
          <div class="network-name">AirtelTigo</div>
        </div>
      </div>

      <div class="field">
        <div class="field-label">Mobile number</div>
        <div class="input-wrap">
          <svg class="input-icon" viewBox="0 0 24 24" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/>
          </svg>
          <input class="input has-icon" id="momoPhone" type="tel" inputmode="numeric"
            placeholder="024 000 0000" maxlength="13"
            oninput="formatPhone(this)">
        </div>
        <div class="field-error" id="momoPhoneErr">Enter a valid Ghana mobile number</div>
      </div>

      <div class="field">
        <div class="field-label">Your name <span class="field-label-hint">(optional)</span></div>
        <div class="input-wrap">
          <svg class="input-icon" viewBox="0 0 24 24" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
          </svg>
          <input class="input has-icon" id="momoName" type="text" placeholder="Kwame Mensah">
        </div>
      </div>

      <div class="amount-breakdown" id="momoBreakdown">
        <div class="amount-row"><span>Subtotal</span><span id="subtotalDisplay"><?= $currency ?> <?= $amount ?></span></div>
        <div class="amount-row"><span>Processing fee</span><span><?= $currency ?> 0.00</span></div>
        <div class="amount-row total"><span>Total due</span><span id="totalDisplay"><?= $currency ?> <?= $amount ?></span></div>
      </div>

      <div style="background:var(--blue-pale);border:1px solid rgba(37,99,235,.15);border-radius:var(--r-sm);padding:12px 14px;font-size:12px;color:var(--blue);line-height:1.5;">
        <strong>How it works:</strong> You'll receive a prompt on your phone to approve the payment. Ensure your MoMo wallet has sufficient funds.
      </div>
    </div><!-- /panel-momo -->

    <!-- ── Bank Transfer Panel ── -->
    <div class="panel" id="panel-bank">
      <div class="bank-timer" id="bankTimer">
        <div class="bank-timer-icon">⏱</div>
        <div class="bank-timer-text">
          Complete transfer within &nbsp;<span class="bank-timer-count" id="timerDisplay">29:47</span>
        </div>
      </div>

      <div class="field-label" style="margin-bottom:10px">Transfer to this account</div>

      <div class="bank-info-card">
        <div class="bank-row">
          <span class="bank-row-label">Bank name</span>
          <span class="bank-row-value">GCB Bank</span>
        </div>
        <div class="bank-row">
          <span class="bank-row-label">Account number</span>
          <span class="bank-row-value">
            <span id="bankAccNum">1234567890</span>
            <button class="copy-btn" onclick="copyText('1234567890', this)">Copy</button>
          </span>
        </div>
        <div class="bank-row">
          <span class="bank-row-label">Account name</span>
          <span class="bank-row-value">Flutterwave · <?= $merchantName ?></span>
        </div>
        <div class="bank-row">
          <span class="bank-row-label">Reference</span>
          <span class="bank-row-value">
            <span id="bankRef"><?= $txRef ?></span>
            <button class="copy-btn" onclick="copyText('<?= $txRef ?>', this)">Copy</button>
          </span>
        </div>
        <div class="bank-row">
          <span class="bank-row-label">Amount</span>
          <span class="bank-row-value" style="color:var(--green);font-weight:600"><?= $currency ?> <?= $amount ?></span>
        </div>
      </div>

      <div style="background:var(--red-pale);border:1px solid rgba(229,66,58,.15);border-radius:var(--r-sm);padding:12px 14px;font-size:12px;color:var(--red);line-height:1.5;">
        <strong>Important:</strong> Include the reference code exactly as shown. Transfers without the correct reference may not be credited automatically.
      </div>
    </div><!-- /panel-bank -->

  </div><!-- /panels -->

  <!-- Pay button -->
  <div class="pay-section" id="paySection">
    <button class="pay-btn" id="payBtn" onclick="initiatePayment()">
      <div class="spinner"></div>
      <div class="btn-text">
        <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
        Pay <?= $currency ?> <?= $amount ?>
      </div>
    </button>
    <div class="pay-footnote">
      <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
      </svg>
      256-bit SSL &nbsp;·&nbsp; PCI DSS Level 1 &nbsp;·&nbsp; Powered by
      <strong style="color:var(--accent-deep);font-weight:600">Flutterwave</strong>
    </div>
  </div>

  <div class="flw-powered" id="poweredBy">
    Protected by <strong>Flutterwave</strong> &nbsp;·&nbsp; Ghana 🇬🇭
  </div>

</div><!-- /mainShell -->

<!-- ══════════════════════════════════════════
     SUCCESS SHELL
══════════════════════════════════════════ -->
<div class="shell" id="successShell" style="display:none;">
  <div style="padding:20px 24px;border-bottom:1px solid var(--line-2)">
    <div style="display:flex;align-items:center;justify-content:space-between">
      <span style="font-size:13px;font-weight:500;color:var(--ink-2)">Payment receipt</span>
      <span style="background:var(--green-pale);color:var(--green);font-size:11px;font-weight:600;
                   padding:3px 10px;border-radius:99px;display:inline-block">Successful</span>
    </div>
  </div>
  <div class="success-screen show">
    <div class="success-ring">
      <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20 6L9 17l-5-5"/>
      </svg>
    </div>
    <div class="success-title">Payment received!</div>
    <div class="success-subtitle">Your payment has been confirmed.<br>A receipt has been sent to your email.</div>

    <div class="success-card">
      <div class="success-row success-amount-row">
        <div>
          <div class="success-amount-label">Amount paid</div>
          <div class="success-amount-value" id="successAmount"><?= $currency ?> <?= $amount ?></div>
        </div>
      </div>
      <div class="success-row">
        <span class="success-row-label">Merchant</span>
        <span class="success-row-value" id="successMerchant"><?= $merchantName ?></span>
      </div>
      <div class="success-row">
        <span class="success-row-label">Transaction ID</span>
        <span class="success-row-value" style="font-family:var(--mono);font-size:12px" id="successTxId">—</span>
      </div>
      <div class="success-row">
        <span class="success-row-label">Date &amp; time</span>
        <span class="success-row-value" id="successDate"></span>
      </div>
      <div class="success-row">
        <span class="success-row-label">Payment method</span>
        <span class="success-row-value" id="successMethod">—</span>
      </div>
      <div class="success-row">
        <span class="success-row-label">Status</span>
        <span class="success-row-value" style="color:var(--green)">✓ Completed</span>
      </div>
    </div>

    <button class="success-btn" onclick="window.close ? window.close() : location.reload()">
      Done
    </button>
  </div>
  <div class="flw-powered">Protected by <strong>Flutterwave</strong> &nbsp;·&nbsp; Ghana 🇬🇭</div>
</div>

<!-- Error toast -->
<div class="error-toast" id="errorToast"></div>

<!-- Flutterwave inline SDK (for hosted popup fallback) -->
<script src="<?= $sdkUrl ?>"></script>

<script>
// ── State — seeded from PHP/FLW_CONFIG ────────────────────────────────────
const state = {
    tab:               'card',
    selectedNetwork:   'MTN',
    selectedSavedCard: '4242',
    saveCard:          false,
    amount:            FLW_CONFIG.amount,
    currency:          FLW_CONFIG.currency,
    merchant:          FLW_CONFIG.merchant,
    txRef:             FLW_CONFIG.txRef,
    orderId:           FLW_CONFIG.orderId,
};

// ── Boot ─────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // Populate customer fields if server provided them
    if (FLW_CONFIG.custName) {
        const f = document.getElementById('cardName');
        if (f) { f.value = FLW_CONFIG.custName; updateCardPreview(); }
    }
    if (FLW_CONFIG.custPhone) {
        const f = document.getElementById('momoPhone');
        if (f) f.value = FLW_CONFIG.custPhone;
    }

    // Hide tabs for disabled payment methods
    if (!FLW_CONFIG.cardEnabled) document.getElementById('tab-card')?.remove();
    if (!FLW_CONFIG.momoEnabled) document.getElementById('tab-momo')?.remove();
    if (!FLW_CONFIG.bankEnabled) document.getElementById('tab-bank')?.remove();

    // Auto-activate first available tab
    const firstTab = document.querySelector('.tab');
    if (firstTab) {
        const tabName = firstTab.id.replace('tab-', '');
        switchTab(tabName);
    }
});

// ── Tab switching ─────────────────────────────────────────────────────────
function switchTab(name) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
    const tabEl   = document.getElementById('tab-' + name);
    const panelEl = document.getElementById('panel-' + name);
    if (tabEl)   tabEl.classList.add('active');
    if (panelEl) panelEl.classList.add('active');
    state.tab = name;
    const payBtnText = document.getElementById('payBtn').querySelector('.btn-text');
    if (name === 'bank') {
        payBtnText.lastChild.textContent = ' I have made the transfer';
    } else {
        payBtnText.lastChild.textContent = ' Pay ' + state.currency + ' ' + state.amount.toFixed(2);
    }
}

// ── Card number formatting ────────────────────────────────────────────────
function formatCardNumber(input) {
    let val = input.value.replace(/\D/g, '').substring(0, 16);
    input.value = val.replace(/(\d{4})(?=\d)/g, '$1 ');
    updateCardPreview();
    const preview = document.getElementById('cardPreview');
    const network = document.getElementById('cardNetwork');
    if (val.startsWith('4')) {
        preview.classList.add('visa');
        network.innerHTML = '<span class="card-visa-text">VISA</span>';
    } else {
        preview.classList.remove('visa');
        network.innerHTML = '<div class="card-network-circle"></div><div class="card-network-circle"></div>';
    }
}

function formatExpiry(input) {
    let val = input.value.replace(/\D/g, '');
    if (val.length >= 2) val = val.substring(0, 2) + ' / ' + val.substring(2, 4);
    input.value = val;
    updateCardPreview();
}

function formatPhone(input) {
    let val = input.value.replace(/\D/g, '').substring(0, 10);
    if (val.length > 7)      val = val.substring(0,3) + ' ' + val.substring(3,6) + ' ' + val.substring(6);
    else if (val.length > 3) val = val.substring(0,3) + ' ' + val.substring(3);
    input.value = val;
}

function updateCardPreview() {
    const num  = document.getElementById('cardNumber').value  || '•••• •••• •••• ••••';
    const name = (document.getElementById('cardName').value || 'YOUR NAME').toUpperCase();
    const exp  = document.getElementById('cardExpiry').value  || 'MM / YY';
    document.getElementById('cardPreviewNumber').textContent = num.length < 4 ? '•••• •••• •••• ••••' : num;
    document.getElementById('cardPreviewName').textContent   = name;
    document.getElementById('cardPreviewExpiry').textContent = exp;
}

// ── Saved card selection ──────────────────────────────────────────────────
function selectSavedCard(el, last4) {
    document.querySelectorAll('.saved-card-item').forEach(i => i.classList.remove('selected'));
    el.classList.add('selected');
    state.selectedSavedCard = last4;
    ['cardNumber','cardName','cardExpiry','cardCvv'].forEach(id => {
        document.getElementById(id).value = '';
    });
    updateCardPreview();
}

function deselectSaved() {
    document.querySelectorAll('.saved-card-item').forEach(i => i.classList.remove('selected'));
    state.selectedSavedCard = null;
}

// ── Network selection ─────────────────────────────────────────────────────
function selectNetwork(el, name) {
    document.querySelectorAll('.network-item').forEach(i => i.classList.remove('selected'));
    el.classList.add('selected');
    state.selectedNetwork = name;
}

// ── CVV toggle ────────────────────────────────────────────────────────────
function toggleCvv() {
    const input = document.getElementById('cardCvv');
    const icon  = document.getElementById('eyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24M1 1l22 22"/>';
    } else {
        input.type = 'password';
        icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    }
}

// ── Save card checkbox ────────────────────────────────────────────────────
function toggleSaveCard() {
    state.saveCard = !state.saveCard;
    document.getElementById('saveCardBox').classList.toggle('checked', state.saveCard);
}

// ── Copy to clipboard ─────────────────────────────────────────────────────
function copyText(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        btn.textContent = 'Copied!';
        btn.classList.add('copied');
        setTimeout(() => { btn.textContent = 'Copy'; btn.classList.remove('copied'); }, 2000);
    });
}

// ── Validation ────────────────────────────────────────────────────────────
function showErr(id, show) {
    const el = document.getElementById(id);
    el && el.classList.toggle('show', show);
}
function setInputErr(id, err) {
    const input = document.getElementById(id);
    input && input.classList.toggle('error', err);
}

function validateCard() {
    if (state.selectedSavedCard) return true;
    let ok = true;
    const num = document.getElementById('cardNumber').value.replace(/\s/g,'');
    const exp = document.getElementById('cardExpiry').value;
    const cvv = document.getElementById('cardCvv').value;
    if (num.length < 16) { showErr('cardNumberErr', true);  setInputErr('cardNumber', true);  ok = false; }
    else                 { showErr('cardNumberErr', false); setInputErr('cardNumber', false); }
    if (exp.length < 7)  { showErr('cardExpiryErr', true);  setInputErr('cardExpiry', true);  ok = false; }
    else                 { showErr('cardExpiryErr', false); setInputErr('cardExpiry', false); }
    if (cvv.length < 3)  { showErr('cardCvvErr', true);     setInputErr('cardCvv', true);     ok = false; }
    else                 { showErr('cardCvvErr', false);    setInputErr('cardCvv', false);    }
    return ok;
}

function validateMomo() {
    const phone = document.getElementById('momoPhone').value.replace(/\s/g,'');
    if (phone.length < 10) { showErr('momoPhoneErr', true);  setInputErr('momoPhone', true);  return false; }
    showErr('momoPhoneErr', false); setInputErr('momoPhone', false);
    return true;
}

// ── Build payload ─────────────────────────────────────────────────────────
function buildPayload() {
    const base = {
        tx_ref:       state.txRef,
        amount:       state.amount,
        currency:     state.currency,
        description:  FLW_CONFIG.description,
        payment_type: state.tab,
        order_id:     state.orderId,
    };
    if (state.tab === 'card') {
        if (state.selectedSavedCard) {
            base.tCardToken  = 'flw-saved-' + state.selectedSavedCard;
            base.tCustomerId = 'cust-demo';
        } else {
            base.card_number  = document.getElementById('cardNumber').value.replace(/\s/g,'');
            base.card_name    = document.getElementById('cardName').value;
            base.card_expiry  = document.getElementById('cardExpiry').value;
            base.save_card    = state.saveCard;
        }
    } else if (state.tab === 'momo') {
        base.network        = state.selectedNetwork;
        base.customer_phone = document.getElementById('momoPhone').value.replace(/\s/g,'');
        base.customer_name  = document.getElementById('momoName').value;
    }
    return base;
}

// ── Payment initiation (real fetch to processUrl) ─────────────────────────
function initiatePayment() {
    if (state.tab === 'card' && !validateCard()) return;
    if (state.tab === 'momo' && !validateMomo()) return;

    const btn = document.getElementById('payBtn');
    btn.classList.add('loading');

    const payload = buildPayload();

    fetch(FLW_CONFIG.processUrl, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(payload),
    })
    .then(r => r.json())
    .then(result => {
        btn.classList.remove('loading');
        if (result.Action === '1') {
            if (result.AUTHENTICATION_URL) {
                window.location.href = result.AUTHENTICATION_URL;
                return;
            }
            showSuccess({ ...payload, tx_id: result.tPaymentTransactionId });
            if (window.FlutterwaveWebView) {
                window.FlutterwaveWebView.postMessage(
                    JSON.stringify({ event: 'payment.success', ...result })
                );
            }
        } else {
            showToast(result.message || 'Payment failed. Please try again.');
        }
    })
    .catch(() => {
        btn.classList.remove('loading');
        showToast('Network error. Please check your connection.');
    });
}

// ── Show success screen ───────────────────────────────────────────────────
function showSuccess(payload) {
    document.getElementById('mainShell').style.display = 'none';
    const s = document.getElementById('successShell');
    s.style.display      = 'flex';
    s.style.flexDirection = 'column';

    document.getElementById('successAmount').textContent  = state.currency + ' ' + state.amount.toFixed(2);
    document.getElementById('successMerchant').textContent = state.merchant;
    document.getElementById('successTxId').textContent    =
        payload.tx_id ? 'FLW-TX-' + payload.tx_id : 'FLW-TX-' + Math.floor(10000 + Math.random() * 89999);
    document.getElementById('successDate').textContent = new Date().toLocaleString('en-GH', {
        dateStyle: 'medium', timeStyle: 'short'
    });
    let method = 'Bank Transfer';
    if (state.tab === 'card')
        method = state.selectedSavedCard ? 'Visa ···· ' + state.selectedSavedCard : 'Card';
    if (state.tab === 'momo')
        method = state.selectedNetwork + ' Mobile Money';
    document.getElementById('successMethod').textContent = method;
}

// ── OPTIONAL: Flutterwave hosted popup ────────────────────────────────────
function openFlwPopup() {
    FlutterwaveCheckout({
        public_key:      FLW_CONFIG.publicKey,
        tx_ref:          FLW_CONFIG.txRef,
        amount:          FLW_CONFIG.amount,
        currency:        FLW_CONFIG.currency,
        payment_options: 'card,mobilemoney,ussd',
        redirect_url:    FLW_CONFIG.returnUrl,
        customer: {
            email:       FLW_CONFIG.custEmail,
            name:        FLW_CONFIG.custName,
            phonenumber: FLW_CONFIG.custPhone,
        },
        customizations: {
            title:       FLW_CONFIG.merchant,
            description: FLW_CONFIG.description,
            logo:        '',
        },
        callback: function(data) {
            if (data.status === 'successful') showSuccess({ tx_id: data.transaction_id });
        },
        onclose: function() {
            if (window.FlutterwaveWebView) {
                window.FlutterwaveWebView.postMessage(
                    JSON.stringify({ event: 'payment.cancelled' })
                );
            }
        },
    });
}

// ── Back / cancel ─────────────────────────────────────────────────────────
function goBack() {
    if (window.history.length > 1) {
        window.history.back();
    } else if (window.FlutterwaveWebView) {
        window.FlutterwaveWebView.postMessage(JSON.stringify({ event: 'payment.cancelled' }));
    }
}

// ── Toast ─────────────────────────────────────────────────────────────────
function showToast(msg) {
    const t = document.getElementById('errorToast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

// ── Bank transfer countdown timer ─────────────────────────────────────────
let timerSeconds = 29 * 60 + 47;
function updateTimer() {
    const m  = Math.floor(timerSeconds / 60);
    const s  = timerSeconds % 60;
    const el = document.getElementById('timerDisplay');
    if (el) el.textContent = String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
    if (timerSeconds > 0) timerSeconds--;
}
setInterval(updateTimer, 1000);

// ── Card tilt hover effect ────────────────────────────────────────────────
const cardEl = document.getElementById('cardPreview');
if (cardEl) {
    cardEl.addEventListener('mousemove', e => {
        const rect = cardEl.getBoundingClientRect();
        const x = (e.clientX - rect.left) / rect.width  - .5;
        const y = (e.clientY - rect.top)  / rect.height - .5;
        cardEl.style.transform = `perspective(900px) rotateY(${x*10}deg) rotateX(${-y*8}deg) scale(1.01)`;
    });
    cardEl.addEventListener('mouseleave', () => {
        cardEl.style.transform = 'perspective(900px) rotateY(0) rotateX(0) scale(1)';
    });
}
</script>

</body>
</html>
<?php
    }

    /**
     * Returns the absolute URL to payment-process.php.
     * Adjust if your project uses a router or different base path.
     */
    private static function processUrl(): string {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . '/payment-process.php';
    }
}