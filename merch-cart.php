<?php
/**
 * RedWater Entertainment - Merch Cart
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$checkoutMaxQuantity = merchCheckoutMaxQuantity();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = postString('action');

    if (isset($_POST['remove_index'])) {
        removeMerchCartItem(intValue($_POST['remove_index'], -1));
        redirectWithMessage('/merch-cart.php', 'success', 'Item removed from your cart.');
    }

    if ($action === 'add') {
        $itemId = trim(postString('item_id'));
        $item = getMerchItemById($itemId);
        if ($item === null) {
            redirectWithMessage('/merch.php', 'error', 'That merch item is no longer available.');
        }

        $variant = trim(postString('variant'));
        $fulfillment = merchNormalizeFulfillmentMode(postString('fulfillment', 'shipping'));
        $quantity = max(1, min($checkoutMaxQuantity, postInt('quantity', 1)));

        if ($item['variants'] && !in_array($variant, $item['variants'], true)) {
            redirectWithMessage(merchItemUrl($item), 'error', 'Select a valid variant before adding this item to your cart.');
        }
        if (!$item['variants']) {
            $variant = '';
        }
        if (($fulfillment === 'shipping' && !$item['shipping_enabled']) || ($fulfillment === 'pickup' && !$item['pickup_enabled'])) {
            redirectWithMessage(merchItemUrl($item), 'error', 'That fulfillment option is not available for this item.');
        }

        addMerchCartItem($item['id'], $variant, $fulfillment, $quantity);
        redirectWithMessage('/merch-cart.php', 'success', 'Item added to your cart.');
    }

    if ($action === 'update_quantities') {
        $quantities = $_POST['quantity'] ?? [];
        updateMerchCartQuantities(is_array($quantities) ? $quantities : []);
        redirectWithMessage('/merch-cart.php', 'success', 'Cart updated.');
    }

    if ($action === 'clear_cart') {
        clearMerchCart();
        redirectWithMessage('/merch-cart.php', 'success', 'Cart cleared.');
    }

    if ($action === 'checkout') {
        $storeSettings = getMerchStoreSettings();
        if ($storeSettings['paypal_email'] === '') {
            redirectWithMessage('/merch-cart.php', 'error', 'Checkout is not configured yet.');
        }

        $cartState = buildMerchCartState(getMerchCart(), getMerchItems(true));
        if ($cartState['checkoutItems'] === []) {
            redirectWithMessage('/merch-cart.php', 'error', 'Remove unavailable items before checking out.');
        }

        $attemptId = merchGenerateCheckoutAttemptId();
        $payload = buildMerchPaypalCheckoutPayload($cartState['checkoutItems'], $storeSettings, $attemptId);
        $logResult = logMerchPaypalCheckoutAttempt($attemptId, $payload, $storeSettings);
        rememberMerchCheckoutAttempt($attemptId, $storeSettings, $payload, $logResult);
        renderMerchPaypalRedirectPage($payload, $storeSettings, $attemptId, $logResult);
        exit;
    }
}

/**
 * @param list<array{
 *   entry_index: int,
 *   quantity: int,
 *   variant: string,
 *   fulfillment: string,
 *   item: array{
 *     id: string,
 *     slug: string,
 *     name: string,
 *     seo_title: string,
 *     seo_description: string,
 *     description: string,
 *     price: string,
 *     category: string,
 *     tags: string,
 *     variants: list<string>,
 *     image_path: string,
 *     shipping_enabled: bool,
 *     shipping_cost: string,
 *     shipping_notes: string,
 *     pickup_enabled: bool,
 *     pickup_notes: string,
 *     sort_order: int,
 *     is_active: bool
 *   },
 *   shipping_cost: string
 * }> $checkoutItems
 * @param array{
 *   paypal_email: string,
 *   paypal_currency: string,
 *   paypal_use_sandbox: bool,
 *   shipping_notice: string,
 *   pickup_notice: string
 * } $storeSettings
 */
function renderMerchCartCheckoutForm(array $checkoutItems, array $storeSettings): void {
    ?>
    <form method="post" action="/merch-cart.php" class="merch-cart-checkout-form">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="checkout">
      <button type="submit" class="btn btn-primary w-full">Checkout with PayPal</button>
      <p class="merch-checkout-note">This cart uses PayPal Standard, so only the store email is required for checkout. Each checkout attempt is logged on the server, then a review page shows the target PayPal account and endpoint before you continue.</p>
    </form>
    <?php
}

/**
 * @param array{
 *   entry_index: int,
 *   quantity: int,
 *   variant: string,
 *   fulfillment: string,
 *   item: array{
 *     id: string,
 *     slug: string,
 *     name: string,
 *     seo_title: string,
 *     seo_description: string,
 *     description: string,
 *     price: string,
 *     category: string,
 *     tags: string,
 *     variants: list<string>,
 *     image_path: string,
 *     shipping_enabled: bool,
 *     shipping_cost: string,
 *     shipping_notes: string,
 *     pickup_enabled: bool,
 *     pickup_notes: string,
 *     sort_order: int,
 *     is_active: bool
 *   },
 *   shipping_cost: string
 * } $entry
 */
function merchPaypalItemName(array $entry): string {
    $fulfillmentLabel = merchFormatFulfillmentLabel($entry['fulfillment']);
    if ($entry['variant'] === '') {
        return $entry['item']['name'] . ' - ' . $fulfillmentLabel;
    }
    return $entry['item']['name'] . ' (' . $entry['variant'] . ' / ' . $fulfillmentLabel . ')';
}

function merchAmountToMinorUnits(string $amount): int {
    $normalized = merchNormalizeAmount($amount);
    $sign = str_starts_with($normalized, '-') ? -1 : 1;
    if ($sign < 0) {
        $normalized = substr($normalized, 1);
    }
    $parts = explode('.', $normalized, 2);
    $wholeUnits = $parts[0];
    $fractionalUnits = $parts[1] ?? '';
    if ($fractionalUnits === '') {
        $fractionalMinorUnits = 0;
    } elseif (strlen($fractionalUnits) === 1) {
        $fractionalMinorUnits = (int) $fractionalUnits * 10;
    } else {
        $fractionalMinorUnits = (int) substr($fractionalUnits, 0, 2);
    }
    return $sign * (((int) $wholeUnits * 100) + $fractionalMinorUnits);
}

function merchMinorUnitsToAmountString(int $minorUnits): string {
    $sign = $minorUnits < 0 ? '-' : '';
    $absoluteMinorUnits = abs($minorUnits);
    return sprintf('%s%d.%02d', $sign, intdiv($absoluteMinorUnits, 100), $absoluteMinorUnits % 100);
}

function merchCartDisplayAmount(int $minorUnits, string $currency): string {
    return merchFormatAmount(merchMinorUnitsToAmountString($minorUnits), $currency);
}

function merchGenerateCheckoutAttemptId(): string {
    try {
        return 'merch_checkout_' . bin2hex(random_bytes(6));
    } catch (Exception $e) {
        error_log('[Merch PayPal Checkout] random_bytes failed while generating a checkout attempt ID. Falling back to uniqid().');
        return str_replace('.', '', uniqid('merch_checkout_', true));
    }
}

/**
 * @param array<string, string> $payload
 * @return array<string, string>
 */
function merchPaypalDiagnosticPayload(array $payload): array {
    $allowedFieldPrefixes = merchPaypalDiagnosticFieldPrefixes();
    $diagnosticPayload = [];
    foreach ($payload as $name => $value) {
        $isAllowedField = in_array($name, ['cmd', 'upload', 'business', 'currency_code', 'return', 'cancel_return', 'custom'], true);
        foreach ($allowedFieldPrefixes as $prefix) {
            if (str_starts_with($name, $prefix)) {
                $isAllowedField = true;
                break;
            }
        }
        if ($isAllowedField) {
            $diagnosticPayload[$name] = $value;
        }
    }
    return $diagnosticPayload;
}

/**
 * @return list<string>
 */
function merchPaypalDiagnosticFieldPrefixes(): array {
    return ['item_name_', 'item_number_', 'amount_', 'quantity_', 'shipping_', 'on0_', 'os0_', 'on1_', 'os1_'];
}

/**
 * @param array<string, string> $payload
 */
function merchPaypalDiagnosticPayloadPreview(array $payload): string {
    return (string) json_encode(merchPaypalDiagnosticPayload($payload), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

function merchPaypalSiteLogPath(): string {
    return __DIR__ . '/uploads/temp/redwater_merch_paypal.log';
}

function merchPaypalDisplayLogDestination(string $logPath): string {
    $normalizedLogPath = str_replace('\\', '/', $logPath);
    $normalizedSiteLogPath = str_replace('\\', '/', merchPaypalSiteLogPath());
    if ($normalizedLogPath === $normalizedSiteLogPath) {
        return '/uploads/temp/redwater_merch_paypal.log';
    }

    $configuredLogPath = defined('MERCH_PAYPAL_LOG_PATH') ? trim((string) MERCH_PAYPAL_LOG_PATH) : '';
    if ($configuredLogPath !== '' && $normalizedLogPath === str_replace('\\', '/', $configuredLogPath)) {
        return 'Custom MERCH_PAYPAL_LOG_PATH';
    }

    $defaultLogPath = trim((string) ini_get('error_log'));
    if ($defaultLogPath !== '' && $normalizedLogPath === str_replace('\\', '/', $defaultLogPath)) {
        return 'PHP error log';
    }

    return 'System temp log file';
}

function merchSiteUrl(): string {
    if (defined('SITE_URL') && trim((string) SITE_URL) !== '') {
        return rtrim((string) SITE_URL, '/');
    }

    $scheme = 'http';
    $forwardedProtoParts = explode(',', serverString('HTTP_X_FORWARDED_PROTO'));
    $forwardedProto = strtolower(trim($forwardedProtoParts[0]));
    if ($forwardedProto === 'https') {
        $scheme = 'https';
    }
    $https = strtolower(serverString('HTTPS'));
    if ($https !== '' && $https !== 'off') {
        $scheme = 'https';
    }

    return $scheme . '://' . serverString('HTTP_HOST', 'localhost');
}

/**
 * @param list<array{
 *   entry_index: int,
 *   quantity: int,
 *   variant: string,
 *   fulfillment: string,
 *   item: array{
 *     id: string,
 *     slug: string,
 *     name: string,
 *     seo_title: string,
 *     seo_description: string,
 *     description: string,
 *     price: string,
 *     category: string,
 *     tags: string,
 *     variants: list<string>,
 *     image_path: string,
 *     shipping_enabled: bool,
 *     shipping_cost: string,
 *     shipping_notes: string,
 *     pickup_enabled: bool,
 *     pickup_notes: string,
 *     sort_order: int,
 *     is_active: bool
 *   },
 *   shipping_cost: string
 * }> $checkoutItems
 * @param array{
 *   paypal_email: string,
 *   paypal_currency: string,
 *   paypal_use_sandbox: bool,
 *   shipping_notice: string,
 *   pickup_notice: string
 * } $storeSettings
 * @return array<string, string>
 */
function buildMerchPaypalCheckoutPayload(array $checkoutItems, array $storeSettings, string $attemptId): array {
    $payload = [
        'cmd' => '_cart',
        'upload' => '1',
        'business' => $storeSettings['paypal_email'],
        'currency_code' => $storeSettings['paypal_currency'],
        'charset' => 'utf-8',
        'return' => merchSiteUrl() . '/merch-cart.php?paypal=returned&attempt=' . urlencode($attemptId),
        'cancel_return' => merchSiteUrl() . '/merch-cart.php?paypal=cancelled&attempt=' . urlencode($attemptId),
        'custom' => $attemptId,
    ];

    foreach ($checkoutItems as $index => $entry) {
        $paypalIndex = $index + 1;
        $payload['item_name_' . $paypalIndex] = merchPaypalItemName($entry);
        $payload['item_number_' . $paypalIndex] = $entry['item']['id'];
        $payload['amount_' . $paypalIndex] = merchNormalizeAmount($entry['item']['price']);
        $payload['quantity_' . $paypalIndex] = (string) $entry['quantity'];
        if (merchAmountToMinorUnits($entry['shipping_cost']) > 0) {
            $payload['shipping_' . $paypalIndex] = merchNormalizeAmount($entry['shipping_cost']);
        }
        if ($entry['variant'] !== '') {
            $payload['on0_' . $paypalIndex] = 'Variant';
            $payload['os0_' . $paypalIndex] = $entry['variant'];
            $payload['on1_' . $paypalIndex] = 'Fulfillment';
            $payload['os1_' . $paypalIndex] = merchFormatFulfillmentLabel($entry['fulfillment']);
        } else {
            $payload['on0_' . $paypalIndex] = 'Fulfillment';
            $payload['os0_' . $paypalIndex] = merchFormatFulfillmentLabel($entry['fulfillment']);
        }
    }

    return $payload;
}

/**
 * @param array<string, string> $payload
 * @param array{
 *   paypal_email: string,
 *   paypal_currency: string,
 *   paypal_use_sandbox: bool,
 *   shipping_notice: string,
 *   pickup_notice: string
 * } $storeSettings
 * @return array{
 *   log_path: string,
 *   log_label: string,
 *   status_message: string,
 *   write_succeeded: bool
 * }
 */
function logMerchPaypalCheckoutAttempt(string $attemptId, array $payload, array $storeSettings): array {
    $entry = [
        'timestamp' => gmdate('c'),
        'attempt_id' => $attemptId,
        'environment' => merchPaypalEnvironmentLabel($storeSettings),
        'target_url' => merchPaypalCheckoutUrl($storeSettings),
        'receiver_email' => $storeSettings['paypal_email'],
        'payload' => $payload,
    ];
    $json = json_encode($entry, JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        $json = (string) json_encode(['attempt_id' => $attemptId], JSON_UNESCAPED_SLASHES);
    }
    $line = '[Merch PayPal Checkout] ' . $json . PHP_EOL;
    $defaultLogPath = trim((string) ini_get('error_log'));
    $siteLogPath = merchPaypalSiteLogPath();
    $siteLogDirectory = dirname($siteLogPath);
    if (!is_dir($siteLogDirectory)) {
        @mkdir($siteLogDirectory, 0700, true);
        if (!is_dir($siteLogDirectory)) {
            error_log('[Merch PayPal Checkout] Could not create the site-local log directory at ' . $siteLogDirectory . '.');
        }
    }
    if (defined('MERCH_PAYPAL_LOG_PATH') && trim((string) MERCH_PAYPAL_LOG_PATH) !== '') {
        $logPath = (string) MERCH_PAYPAL_LOG_PATH;
    } elseif (is_dir($siteLogDirectory) && is_writable($siteLogDirectory)) {
        $logPath = $siteLogPath;
    } elseif ($defaultLogPath !== '') {
        $logPath = $defaultLogPath;
    } else {
        $logPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'redwater_merch_paypal.log';
    }
    if (error_log($line, 3, $logPath) !== false) {
        return [
            'log_path' => $logPath,
            'log_label' => merchPaypalDisplayLogDestination($logPath),
            'status_message' => 'Saved before redirect.',
            'write_succeeded' => true,
        ];
    }

    $statusMessage = 'Primary log write failed.';
    $effectiveLogPath = $logPath;
    if ($defaultLogPath !== '') {
        $lastError = error_get_last();
        $lastErrorData = is_array($lastError) ? $lastError : [];
        $reason = '';
        if (array_key_exists('message', $lastErrorData)) {
            $sanitizedReason = trim(str_replace(["\r", "\n"], ' ', stringValue($lastErrorData['message'])));
            if ($sanitizedReason !== '') {
                $reason = ' Reason: ' . substr($sanitizedReason, 0, 300);
            }
        }
        error_log('[Merch PayPal Checkout] Primary log write failed for attempt ' . $attemptId . '. Falling back to the default PHP error log.' . $reason);
        if (error_log($line) === false) {
            trigger_error('[Merch PayPal Checkout] Fallback log write also failed for attempt ' . $attemptId . '.', E_USER_WARNING);
            return [
                'log_path' => $effectiveLogPath,
                'log_label' => merchPaypalDisplayLogDestination($effectiveLogPath),
                'status_message' => $statusMessage . ' Fallback PHP error_log write also failed.',
                'write_succeeded' => false,
            ];
        }
        $effectiveLogPath = $defaultLogPath;
        $statusMessage = 'Primary log write failed, but the fallback PHP error log write succeeded.';
        return [
            'log_path' => $effectiveLogPath,
            'log_label' => merchPaypalDisplayLogDestination($effectiveLogPath),
            'status_message' => $statusMessage,
            'write_succeeded' => true,
        ];
    }
    trigger_error('[Merch PayPal Checkout] Primary log write failed for attempt ' . $attemptId . ' and no fallback PHP error log is configured.', E_USER_WARNING);
    return [
        'log_path' => $effectiveLogPath,
        'log_label' => merchPaypalDisplayLogDestination($effectiveLogPath),
        'status_message' => $statusMessage . ' No fallback PHP error log is configured.',
        'write_succeeded' => false,
    ];
}

/**
 * @param array{
 *   paypal_email: string,
 *   paypal_currency: string,
 *   paypal_use_sandbox: bool,
 *   shipping_notice: string,
 *   pickup_notice: string
 * } $storeSettings
 * @param array<string, string> $payload
 * @param array{
 *   log_path: string,
 *   log_label: string,
 *   status_message: string,
 *   write_succeeded: bool
 * } $logResult
 */
function rememberMerchCheckoutAttempt(string $attemptId, array $storeSettings, array $payload, array $logResult): void {
    $payloadPreview = merchPaypalDiagnosticPayloadPreview($payload);
    $_SESSION['merch_last_checkout_attempt'] = [
        'attempt_id' => $attemptId,
        'environment' => merchPaypalEnvironmentLabel($storeSettings),
        'target_url' => merchPaypalCheckoutUrl($storeSettings),
        'receiver_email' => $storeSettings['paypal_email'],
        'logged_at' => gmdate('c'),
        'log_path' => $logResult['log_path'],
        'log_label' => $logResult['log_label'],
        'log_status' => $logResult['status_message'],
        'payload_preview' => $payloadPreview,
    ];
}

/**
 * @return array{
 *   attempt_id: string,
 *   environment: string,
 *   target_url: string,
 *   receiver_email: string,
 *   logged_at: string,
 *   log_path: string,
 *   log_label: string,
 *   log_status: string,
 *   payload_preview: string
 * }|null
 */
function getMerchLastCheckoutAttempt(): ?array {
    $attempt = $_SESSION['merch_last_checkout_attempt'] ?? null;
    if (!is_array($attempt)) {
        return null;
    }

    $attemptId = trim(stringValue($attempt['attempt_id'] ?? ''));
    if ($attemptId === '') {
        return null;
    }

    return [
        'attempt_id' => $attemptId,
        'environment' => trim(stringValue($attempt['environment'] ?? '')),
        'target_url' => trim(stringValue($attempt['target_url'] ?? '')),
        'receiver_email' => trim(stringValue($attempt['receiver_email'] ?? '')),
        'logged_at' => trim(stringValue($attempt['logged_at'] ?? '')),
        'log_path' => trim(stringValue($attempt['log_path'] ?? '')),
        'log_label' => trim(stringValue($attempt['log_label'] ?? '')),
        'log_status' => trim(stringValue($attempt['log_status'] ?? '')),
        'payload_preview' => trim(stringValue($attempt['payload_preview'] ?? '')),
    ];
}

/**
 * @param array<string, string> $payload
 * @param array{
 *   paypal_email: string,
 *   paypal_currency: string,
 *   paypal_use_sandbox: bool,
 *   shipping_notice: string,
 *   pickup_notice: string
 * } $storeSettings
 * @param array{
 *   log_path: string,
 *   log_label: string,
 *   status_message: string,
 *   write_succeeded: bool
 * } $logResult
 */
function renderMerchPaypalRedirectPage(array $payload, array $storeSettings, string $attemptId, array $logResult): void {
    $pageTitle = 'Review PayPal Checkout';
    $seoDescription = 'Review PayPal checkout details before continuing.';
    $payloadPreview = merchPaypalDiagnosticPayloadPreview($payload);
    include __DIR__ . '/includes/header.php';
    ?>
    <main class="page-wrapper">
      <section class="section-sm">
        <div class="container" style="max-width:760px;">
          <div class="card">
            <div class="card-body">
              <h1 style="font-size:1.6rem;">Review <?= e(merchPaypalEnvironmentLabel($storeSettings)) ?> Checkout</h1>
              <div class="alert-inline alert-info" style="margin:1rem 0;">
                Attempt ID: <strong><?= e($attemptId) ?></strong><br>
                The outgoing checkout payload was logged on the server before you continue to PayPal.
              </div>
              <p class="text-muted">If PayPal still shows the generic sandbox payment error, use this attempt ID to find the matching <code>[Merch PayPal Checkout]</code> entry in the server log. By default this first tries <code>/uploads/temp/redwater_merch_paypal.log</code>, unless <code>MERCH_PAYPAL_LOG_PATH</code> is set, then falls back to PHP&rsquo;s error log or the system temp directory if needed.</p>
              <div class="alert-inline" style="margin:1rem 0;display:grid;gap:0.65rem;">
                <div><strong>PayPal environment:</strong> <?= e(merchPaypalEnvironmentLabel($storeSettings)) ?></div>
                <div><strong>Receiver email:</strong> <code><?= e($storeSettings['paypal_email']) ?></code></div>
                <div><strong>Checkout endpoint:</strong> <code><?= e(merchPaypalCheckoutUrl($storeSettings)) ?></code></div>
                <div><strong>Log destination:</strong> <code><?= e($logResult['log_label']) ?></code></div>
                <div><strong>Log status:</strong> <?= e($logResult['status_message']) ?></div>
              </div>
              <details style="margin:1rem 0;">
                <summary style="cursor:pointer;font-weight:600;">Show PayPal diagnostic fields</summary>
                <pre style="margin-top:0.75rem;padding:1rem;border-radius:0.75rem;background:rgba(15,23,42,0.7);overflow:auto;white-space:pre-wrap;word-break:break-word;"><?= e($payloadPreview) ?></pre>
              </details>
              <form method="post" action="<?= e(merchPaypalCheckoutUrl($storeSettings)) ?>" id="paypal-redirect-form" class="merch-cart-checkout-form">
                <?php foreach ($payload as $name => $value): ?>
                  <input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>">
                <?php endforeach; ?>
                <button type="submit" class="btn btn-primary">Continue to PayPal</button>
              </form>
            </div>
          </div>
        </div>
      </section>
    </main>
    <?php
    include __DIR__ . '/includes/footer.php';
}

/**
 * @param list<array{item_id: string, variant: string, fulfillment: string, quantity: int}> $cartEntries
 * @param list<array{
 *   id: string,
 *   slug: string,
 *   name: string,
 *   seo_title: string,
 *   seo_description: string,
 *   description: string,
 *   price: string,
 *   category: string,
 *   tags: string,
 *   variants: list<string>,
 *   image_path: string,
 *   shipping_enabled: bool,
 *   shipping_cost: string,
 *   shipping_notes: string,
 *   pickup_enabled: bool,
 *   pickup_notes: string,
 *   sort_order: int,
 *   is_active: bool
 * }> $catalogItems
 * @return array{
 *   cartLines: list<array{
 *     entry_index: int,
 *     item: array{
 *       id: string,
 *       slug: string,
 *       name: string,
 *       seo_title: string,
 *       seo_description: string,
 *       description: string,
 *       price: string,
 *       category: string,
 *       tags: string,
 *       variants: list<string>,
 *       image_path: string,
 *       shipping_enabled: bool,
 *       shipping_cost: string,
 *       shipping_notes: string,
 *       pickup_enabled: bool,
 *       pickup_notes: string,
 *       sort_order: int,
 *       is_active: bool
 *     }|null,
 *     quantity: int,
 *     variant: string,
 *     fulfillment: string,
 *     shipping_cost: string,
 *     line_subtotal: int,
 *     line_total: int,
 *     reason: string
 *   }>,
 *   checkoutItems: list<array{
 *     entry_index: int,
 *     quantity: int,
 *     variant: string,
 *     fulfillment: string,
 *     item: array{
 *       id: string,
 *       slug: string,
 *       name: string,
 *       seo_title: string,
 *       seo_description: string,
 *       description: string,
 *       price: string,
 *       category: string,
 *       tags: string,
 *       variants: list<string>,
 *       image_path: string,
 *       shipping_enabled: bool,
 *       shipping_cost: string,
 *       shipping_notes: string,
 *       pickup_enabled: bool,
 *       pickup_notes: string,
 *       sort_order: int,
 *       is_active: bool
 *     },
 *     shipping_cost: string
 *   }>,
 *   subtotal: int,
 *   shippingTotal: int
 * }
 */
function buildMerchCartState(array $cartEntries, array $catalogItems): array {
    $cartLines = [];
    $checkoutItems = [];
    $subtotal = 0;
    $shippingTotal = 0;

    foreach ($cartEntries as $index => $entry) {
        $item = findMerchItemById($catalogItems, $entry['item_id']);
        $reason = '';
        if ($item === null) {
            $reason = 'This item is no longer available.';
        } elseif ($entry['variant'] !== '' && !in_array($entry['variant'], $item['variants'], true)) {
            $reason = 'The selected variant is no longer available.';
        } elseif ($entry['fulfillment'] === 'shipping' && !$item['shipping_enabled']) {
            $reason = 'Shipping is no longer available for this item.';
        } elseif ($entry['fulfillment'] === 'pickup' && !$item['pickup_enabled']) {
            $reason = 'Pickup is no longer available for this item.';
        }

        if ($item === null) {
            $cartLines[] = [
                'entry_index' => $index,
                'item' => null,
                'quantity' => $entry['quantity'],
                'variant' => $entry['variant'],
                'fulfillment' => $entry['fulfillment'],
                'shipping_cost' => '0.00',
                'line_subtotal' => 0,
                'line_total' => 0,
                'reason' => $reason,
            ];
            continue;
        }

        $shippingCost = $entry['fulfillment'] === 'shipping' ? merchNormalizeAmount($item['shipping_cost']) : '0.00';
        $lineSubtotal = merchAmountToMinorUnits($item['price']) * $entry['quantity'];
        $lineTotal = $lineSubtotal + merchAmountToMinorUnits($shippingCost);
        $cartLines[] = [
            'entry_index' => $index,
            'item' => $item,
            'quantity' => $entry['quantity'],
            'variant' => $entry['variant'],
            'fulfillment' => $entry['fulfillment'],
            'shipping_cost' => $shippingCost,
            'line_subtotal' => $lineSubtotal,
            'line_total' => $lineTotal,
            'reason' => $reason,
        ];

        if ($reason !== '') {
            continue;
        }

        $subtotal += $lineSubtotal;
        $shippingTotal += merchAmountToMinorUnits($shippingCost);
        $checkoutItems[] = [
            'entry_index' => $index,
            'quantity' => $entry['quantity'],
            'variant' => $entry['variant'],
            'fulfillment' => $entry['fulfillment'],
            'item' => $item,
            'shipping_cost' => $shippingCost,
        ];
    }

    return [
        'cartLines' => $cartLines,
        'checkoutItems' => $checkoutItems,
        'subtotal' => $subtotal,
        'shippingTotal' => $shippingTotal,
    ];
}

$storeSettings = getMerchStoreSettings();
$cartEntries = getMerchCart();
$catalogItems = getMerchItems(true);
$cartItemCount = getMerchCartItemCount();
$pageTitle = 'Merch Cart';
$seoDescription = 'Review your RedWater Entertainment merch cart and continue to PayPal checkout.';

$cartState = buildMerchCartState($cartEntries, $catalogItems);
$cartLines = $cartState['cartLines'];
$checkoutItems = $cartState['checkoutItems'];
$subtotal = $cartState['subtotal'];
$shippingTotal = $cartState['shippingTotal'];
$paypalStatus = getString('paypal');
$paypalAttemptId = trim(getString('attempt'));
$lastPaypalAttempt = getMerchLastCheckoutAttempt();

include __DIR__ . '/includes/header.php';
?>

<main class="page-wrapper">
  <div class="page-header">
    <div class="container">
      <div class="merch-page-header">
        <div>
          <h1>Your <span style="color:var(--blue)">Merch Cart</span></h1>
          <p>Review your items, update quantities, and continue to PayPal checkout when you are ready.</p>
        </div>
        <a href="/merch.php" class="btn btn-outline">Continue Shopping</a>
      </div>
    </div>
  </div>

  <section class="section-sm">
    <div class="container">
      <?php if (!$cartLines): ?>
        <div class="merch-placeholder">
          <div class="icon">🛍️</div>
          <h2>Your Cart Is Empty</h2>
          <p>Add merch items to your cart before heading to checkout.</p>
          <a href="/merch.php" class="btn btn-primary">Browse Merch</a>
        </div>
      <?php else: ?>
        <form method="post" action="/merch-cart.php" class="card mb-3">
          <div class="card-body">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_quantities">
            <div class="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Item</th>
                    <th>Fulfillment</th>
                    <th>Quantity</th>
                    <th>Estimate</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($cartLines as $line): ?>
                    <tr>
                      <td>
                        <?php if ($line['item'] !== null): ?>
                          <div class="merch-cart-item">
                            <?php if ($line['item']['image_path'] !== ''): ?>
                              <img src="<?= e($line['item']['image_path']) ?>" alt="<?= e($line['item']['name']) ?>" class="merch-admin-thumb">
                            <?php endif; ?>
                            <div>
                              <a href="<?= e(merchItemUrl($line['item'])) ?>" class="merch-card-link"><strong><?= e($line['item']['name']) ?></strong></a>
                              <?php if ($line['variant'] !== ''): ?>
                                <div class="text-muted" style="font-size:0.85rem;">Variant: <?= e($line['variant']) ?></div>
                              <?php endif; ?>
                              <?php if ($line['reason'] !== ''): ?>
                                <div class="text-muted" style="font-size:0.85rem;color:#fca5a5;"><?= e($line['reason']) ?></div>
                              <?php endif; ?>
                            </div>
                          </div>
                        <?php else: ?>
                          <strong>Unavailable Item</strong>
                          <div class="text-muted" style="font-size:0.85rem;color:#fca5a5;"><?= e($line['reason']) ?></div>
                        <?php endif; ?>
                      </td>
                      <td><?= e(merchFormatFulfillmentLabel($line['fulfillment'])) ?></td>
                      <td>
                        <input type="number" name="quantity[<?= e((string) $line['entry_index']) ?>]" class="form-control merch-cart-qty" value="<?= e((string) $line['quantity']) ?>" min="1" max="<?= e((string) $checkoutMaxQuantity) ?>">
                      </td>
                      <td>
                        <?php if ($line['item'] !== null): ?>
                          <div><?= e(merchCartDisplayAmount($line['line_subtotal'], $storeSettings['paypal_currency'])) ?></div>
                          <?php if (merchAmountToMinorUnits($line['shipping_cost']) > 0): ?>
                            <div class="text-muted" style="font-size:0.85rem;">+ <?= e(merchCartDisplayAmount(merchAmountToMinorUnits($line['shipping_cost']), $storeSettings['paypal_currency'])) ?> shipping</div>
                          <?php endif; ?>
                        <?php else: ?>
                          —
                        <?php endif; ?>
                      </td>
                      <td>
                        <button type="submit" name="remove_index" value="<?= e((string) $line['entry_index']) ?>" class="btn btn-danger btn-sm">Remove</button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div class="d-flex gap-2 mt-2" style="flex-wrap:wrap;">
              <button type="submit" class="btn btn-outline">Update Quantities</button>
            </div>
          </div>
        </form>
        <form method="post" action="/merch-cart.php" class="mb-3">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="clear_cart">
          <button type="submit" class="btn btn-danger">Clear Cart</button>
        </form>

        <div class="card">
          <div class="card-body">
            <div class="merch-cart-summary">
              <div>
                <h2 style="font-size:1.1rem;">Checkout Summary</h2>
                <p class="text-muted">PayPal Standard checkout is configured with the store email only, so no API keys are required for this flow.</p>
                <?php if ($storeSettings['paypal_use_sandbox']): ?>
                  <div class="alert-inline alert-warning">
                    <div><strong>Sandbox checkout is enabled.</strong> <?= e(merchPaypalSandboxTestingHint()) ?></div>
                    <?php if ($storeSettings['paypal_email'] !== ''): ?>
                      <div class="mt-1">Current checkout target: <?= e(merchPaypalEnvironmentLabel($storeSettings)) ?> → <?= e($storeSettings['paypal_email']) ?></div>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
                <?php if ($lastPaypalAttempt !== null): ?>
                  <div class="alert-inline alert-info" style="margin-top:1rem;">
                    <div><strong>Most recent PayPal attempt:</strong> <code><?= e($lastPaypalAttempt['attempt_id']) ?></code></div>
                    <div style="margin-top:0.5rem;">Target: <?= e($lastPaypalAttempt['environment']) ?> → <code><?= e($lastPaypalAttempt['receiver_email']) ?></code></div>
                    <div style="margin-top:0.5rem;">Endpoint: <code><?= e($lastPaypalAttempt['target_url']) ?></code></div>
                    <div style="margin-top:0.5rem;">Log destination: <code><?= e($lastPaypalAttempt['log_label']) ?></code></div>
                    <div style="margin-top:0.5rem;">Log status: <?= e($lastPaypalAttempt['log_status']) ?></div>
                    <?php if ($lastPaypalAttempt['payload_preview'] !== ''): ?>
                      <details style="margin-top:0.75rem;">
                        <summary style="cursor:pointer;font-weight:600;">Show most recent PayPal diagnostic fields</summary>
                        <pre style="margin-top:0.75rem;padding:1rem;border-radius:0.75rem;background:rgba(15,23,42,0.7);overflow:auto;white-space:pre-wrap;word-break:break-word;"><?= e($lastPaypalAttempt['payload_preview']) ?></pre>
                      </details>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
                <?php if ($paypalAttemptId !== '' && ($paypalStatus === 'cancelled' || $paypalStatus === 'returned')): ?>
                  <div class="alert-inline alert-info" style="margin-top:1rem;">
                    Checkout attempt <strong><?= e($paypalAttemptId) ?></strong> was logged on the server before redirecting to PayPal.
                  </div>
                <?php endif; ?>
                <?php if ($storeSettings['paypal_email'] === ''): ?>
                  <div class="alert-inline alert-warning">Checkout is not configured yet. Please contact us if you would like to reserve these items.</div>
                <?php elseif (!$checkoutItems): ?>
                  <div class="alert-inline alert-warning">Remove unavailable items before checking out.</div>
                <?php else: ?>
                  <div class="merch-cart-totals">
                    <div><span>Subtotal</span><strong><?= e(merchCartDisplayAmount($subtotal, $storeSettings['paypal_currency'])) ?></strong></div>
                    <div><span>Shipping</span><strong><?= e(merchCartDisplayAmount($shippingTotal, $storeSettings['paypal_currency'])) ?></strong></div>
                    <div><span>Estimated Total</span><strong><?= e(merchCartDisplayAmount($subtotal + $shippingTotal, $storeSettings['paypal_currency'])) ?></strong></div>
                  </div>
                  <?php renderMerchCartCheckoutForm($checkoutItems, $storeSettings); ?>
                <?php endif; ?>
              </div>

              <div class="merch-item-notes">
                <p><strong>Need to know:</strong> Your PayPal cart is built from the current live merch catalog at checkout time.</p>
                <p>Shipping fees are flat per cart line item, matching the current merch configuration for each product.</p>
                <p>Orders are manually reviewed against the selected item, variant, and fulfillment details before fulfillment.</p>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
