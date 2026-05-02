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
    <form method="post" action="<?= e(merchPaypalCheckoutUrl($storeSettings)) ?>" class="merch-cart-checkout-form">
      <input type="hidden" name="cmd" value="_cart">
      <input type="hidden" name="upload" value="1">
      <input type="hidden" name="business" value="<?= e($storeSettings['paypal_email']) ?>">
      <input type="hidden" name="currency_code" value="<?= e($storeSettings['paypal_currency']) ?>">
      <?php foreach ($checkoutItems as $index => $entry): ?>
        <?php
        $paypalIndex = $index + 1;
        ?>
        <input type="hidden" name="item_name_<?= $paypalIndex ?>" value="<?= e($entry['item']['name']) ?>">
        <input type="hidden" name="item_number_<?= $paypalIndex ?>" value="<?= e($entry['item']['id']) ?>">
        <input type="hidden" name="amount_<?= $paypalIndex ?>" value="<?= e(merchNormalizeAmount($entry['item']['price'])) ?>">
        <input type="hidden" name="quantity_<?= $paypalIndex ?>" value="<?= e((string) $entry['quantity']) ?>">
        <?php if (merchAmountToMinorUnits($entry['shipping_cost']) > 0): ?>
          <input type="hidden" name="shipping_<?= $paypalIndex ?>" value="<?= e($entry['shipping_cost']) ?>">
        <?php endif; ?>
        <?php if ($entry['variant'] !== ''): ?>
          <input type="hidden" name="on0_<?= $paypalIndex ?>" value="Variant">
          <input type="hidden" name="os0_<?= $paypalIndex ?>" value="<?= e($entry['variant']) ?>">
          <input type="hidden" name="on1_<?= $paypalIndex ?>" value="Fulfillment">
          <input type="hidden" name="os1_<?= $paypalIndex ?>" value="<?= e(merchFormatFulfillmentLabel($entry['fulfillment'])) ?>">
        <?php else: ?>
          <input type="hidden" name="on0_<?= $paypalIndex ?>" value="Fulfillment">
          <input type="hidden" name="os0_<?= $paypalIndex ?>" value="<?= e(merchFormatFulfillmentLabel($entry['fulfillment'])) ?>">
        <?php endif; ?>
      <?php endforeach; ?>
      <button type="submit" class="btn btn-primary w-full">Checkout with PayPal</button>
      <p class="merch-checkout-note">This cart uses PayPal Standard, so only the store email is required for checkout. Orders are still reviewed against the live catalog before fulfillment.</p>
    </form>
    <?php
}

function merchAmountToMinorUnits(string $amount): int {
    return (int) str_replace('.', '', merchNormalizeAmount($amount));
}

function merchMinorUnitsToAmountString(int $minorUnits): string {
    return number_format($minorUnits / 100, 2, '.', '');
}

function merchCartDisplayAmount(int $minorUnits, string $currency): string {
    return merchFormatAmount(merchMinorUnitsToAmountString($minorUnits), $currency);
}

$storeSettings = getMerchStoreSettings();
$cartEntries = getMerchCart();
$catalogItems = getMerchItems(true);
$cartItemCount = getMerchCartItemCount();
$pageTitle = 'Merch Cart';
$seoDescription = 'Review your RedWater Entertainment merch cart and continue to PayPal checkout.';

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
                            <div class="text-muted" style="font-size:0.85rem;">+ <?= e(merchFormatAmount($line['shipping_cost'], $storeSettings['paypal_currency'])) ?> shipping</div>
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
