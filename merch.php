<?php
/**
 * RedWater Entertainment - Merch Store
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

/**
 * @param array{
 *   paypal_email: string,
 *   paypal_currency: string,
 *   paypal_use_sandbox: bool,
 *   shipping_notice: string,
 *   pickup_notice: string
 * } $storeSettings
 */
function merchPaypalCheckoutUrl(array $storeSettings): string {
    return $storeSettings['paypal_use_sandbox']
        ? 'https://www.sandbox.paypal.com/cgi-bin/webscr'
        : 'https://www.paypal.com/cgi-bin/webscr';
}

/**
 * @param array{
 *   id: string,
 *   slug: string,
 *   name: string,
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
 * } $item
 * @param array{
 *   paypal_email: string,
 *   paypal_currency: string,
 *   paypal_use_sandbox: bool,
 *   shipping_notice: string,
 *   pickup_notice: string
 * } $storeSettings
 */
function renderMerchCheckoutForm(array $item, array $storeSettings, string $fulfillmentMode): void {
    $isShipping = $fulfillmentMode === 'shipping';
    $fulfillmentLabel = $isShipping ? 'Shipping' : 'Local Pickup';
    $shippingCost = $isShipping ? merchNormalizeAmount($item['shipping_cost']) : '0.00';
    $fieldSuffix = merchSlugify($item['id'] . '-' . $fulfillmentMode);
    $variantFieldId = 'variant-' . $fieldSuffix;
    $quantityFieldId = 'quantity-' . $fieldSuffix;
    ?>
    <form method="post" action="<?= e(merchPaypalCheckoutUrl($storeSettings)) ?>" target="_blank" class="merch-checkout-form">
      <input type="hidden" name="cmd" value="_xclick">
      <input type="hidden" name="business" value="<?= e($storeSettings['paypal_email']) ?>">
      <input type="hidden" name="currency_code" value="<?= e($storeSettings['paypal_currency']) ?>">
      <input type="hidden" name="item_name" value="<?= e($item['name']) ?>">
      <input type="hidden" name="amount" value="<?= e(merchNormalizeAmount($item['price'])) ?>">
      <?php if ($isShipping && (float) $shippingCost > 0): ?>
        <input type="hidden" name="shipping" value="<?= e($shippingCost) ?>">
      <?php endif; ?>
      <?php if ($item['variants']): ?>
        <input type="hidden" name="on0" value="Variant">
        <label class="form-label" for="<?= e($variantFieldId) ?>">Variant</label>
        <select id="<?= e($variantFieldId) ?>" name="os0" class="form-control" required>
          <?php foreach ($item['variants'] as $variant): ?>
            <option value="<?= e($variant) ?>"><?= e($variant) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="hidden" name="on1" value="Fulfillment">
        <input type="hidden" name="os1" value="<?= e($fulfillmentLabel) ?>">
      <?php else: ?>
        <input type="hidden" name="on0" value="Fulfillment">
        <input type="hidden" name="os0" value="<?= e($fulfillmentLabel) ?>">
      <?php endif; ?>
      <label class="form-label" for="<?= e($quantityFieldId) ?>">Quantity</label>
      <input id="<?= e($quantityFieldId) ?>" type="number" name="quantity" class="form-control" value="1" min="1" max="<?= MERCH_CHECKOUT_MAX_QUANTITY ?>">
      <button type="submit" class="btn btn-primary w-full">
        <?= $isShipping ? 'Checkout + Shipping' : 'Checkout for Pickup' ?>
      </button>
      <?php if ($isShipping && (float) $shippingCost > 0): ?>
        <p class="merch-checkout-note">Flat shipping fee: <?= e(merchFormatAmount($shippingCost, $storeSettings['paypal_currency'])) ?></p>
      <?php endif; ?>
    </form>
    <?php
}

$pageTitle = 'Merch';
$seoDescription = 'Shop RedWater Entertainment merchandise with online PayPal checkout, shipping, and local pickup options.';
$storeSettings = getMerchStoreSettings();
$items = getMerchItems(true);
$selectedCategory = trim(getString('category'));
$selectedTag = trim(getString('tag'));

$categories = [];
$tags = [];
foreach ($items as $item) {
    if ($item['category'] !== '' && !in_array($item['category'], $categories, true)) {
        $categories[] = $item['category'];
    }

    foreach (parseTags($item['tags']) as $tag) {
        if (!in_array($tag, $tags, true)) {
            $tags[] = $tag;
        }
    }
}
sort($categories);
sort($tags);

$filteredItems = [];
foreach ($items as $item) {
    $matchesCategory = $selectedCategory === '' || strcasecmp($item['category'], $selectedCategory) === 0;
    $matchesTag = $selectedTag === '' || in_array($selectedTag, parseTags($item['tags']), true);
    if ($matchesCategory && $matchesTag) {
        $filteredItems[] = $item;
    }
}

include __DIR__ . '/includes/header.php';
?>

<main class="page-wrapper merch-page">
  <div class="page-header">
    <div class="container">
      <h1>Merch <span style="color:var(--blue)">Shop</span></h1>
      <p>Browse apparel, accessories, and event merch with simple PayPal checkout, shipping, or local pickup options.</p>
    </div>
  </div>

  <section class="section-sm">
    <div class="container">
      <?php if ($storeSettings['shipping_notice'] !== '' || $storeSettings['pickup_notice'] !== ''): ?>
        <div class="merch-store-notices mb-3">
          <?php if ($storeSettings['shipping_notice'] !== ''): ?>
            <div class="card">
              <div class="card-body">
                <h3 class="merch-note-title">Shipping</h3>
                <p><?= e($storeSettings['shipping_notice']) ?></p>
              </div>
            </div>
          <?php endif; ?>
          <?php if ($storeSettings['pickup_notice'] !== ''): ?>
            <div class="card">
              <div class="card-body">
                <h3 class="merch-note-title">Local Pickup</h3>
                <p><?= e($storeSettings['pickup_notice']) ?></p>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($categories || $tags): ?>
        <div class="merch-filter-bar mb-3">
          <div class="merch-filter-group">
            <span class="merch-filter-label">Categories</span>
            <a href="/merch.php<?= $selectedTag !== '' ? '?tag=' . urlencode($selectedTag) : '' ?>" class="page-link <?= $selectedCategory === '' ? 'active' : '' ?>">All</a>
            <?php foreach ($categories as $category): ?>
              <a href="/merch.php?category=<?= urlencode($category) ?><?= $selectedTag !== '' ? '&amp;tag=' . urlencode($selectedTag) : '' ?>" class="page-link <?= strcasecmp($selectedCategory, $category) === 0 ? 'active' : '' ?>">
                <?= e($category) ?>
              </a>
            <?php endforeach; ?>
          </div>
          <?php if ($tags): ?>
            <div class="merch-filter-group">
              <span class="merch-filter-label">Tags</span>
              <a href="/merch.php<?= $selectedCategory !== '' ? '?category=' . urlencode($selectedCategory) : '' ?>" class="page-link <?= $selectedTag === '' ? 'active' : '' ?>">All</a>
              <?php foreach ($tags as $tag): ?>
                <a href="/merch.php?tag=<?= urlencode($tag) ?><?= $selectedCategory !== '' ? '&amp;category=' . urlencode($selectedCategory) : '' ?>" class="page-link <?= $selectedTag === $tag ? 'active' : '' ?>">
                  <?= e($tag) ?>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if (!$items): ?>
        <div class="merch-placeholder">
          <div class="icon">👕</div>
          <h2>Store Setup in Progress</h2>
          <p>Our merch shop is almost ready. Check back soon for new RedWater Entertainment gear.</p>
          <a href="/contact.php" class="btn btn-secondary">Contact Us About Merch</a>
        </div>
      <?php elseif (!$filteredItems): ?>
        <div class="merch-placeholder">
          <div class="icon">🔍</div>
          <h2>No Matching Items</h2>
          <p>Try clearing one of the current filters to browse the full merch catalog.</p>
          <a href="/merch.php" class="btn btn-secondary">View All Merch</a>
        </div>
      <?php else: ?>
        <div class="merch-grid">
          <?php foreach ($filteredItems as $item): ?>
            <?php
            $itemTags = parseTags($item['tags']);
            $priceLabel = merchFormatAmount($item['price'], $storeSettings['paypal_currency']);
            ?>
            <article class="card merch-card" id="<?= e($item['slug']) ?>">
              <?php if ($item['image_path'] !== ''): ?>
                <img src="<?= e($item['image_path']) ?>" alt="<?= e($item['name']) ?>" class="card-img merch-card-img">
              <?php else: ?>
                <div class="merch-card-img merch-card-img-placeholder">No Image Available</div>
              <?php endif; ?>

              <div class="card-body">
                <div class="merch-card-header">
                  <div>
                    <?php if ($item['category'] !== ''): ?>
                      <div class="merch-category"><?= e($item['category']) ?></div>
                    <?php endif; ?>
                    <h2 class="card-title"><?= e($item['name']) ?></h2>
                  </div>
                  <div class="merch-price"><?= e($priceLabel) ?></div>
                </div>

                <?php if ($item['description'] !== ''): ?>
                  <p class="merch-description"><?= nl2br(e($item['description'])) ?></p>
                <?php endif; ?>

                <?php if ($itemTags): ?>
                  <div class="tags merch-tags">
                    <?php foreach ($itemTags as $tag): ?>
                      <a href="/merch.php?tag=<?= urlencode($tag) ?><?= $selectedCategory !== '' ? '&amp;category=' . urlencode($selectedCategory) : '' ?>" class="tag"><?= e($tag) ?></a>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

                <div class="merch-fulfillment">
                  <?php if ($item['shipping_enabled']): ?>
                    <span class="status-badge status-blue merch-inline-badge">Shipping Available</span>
                  <?php endif; ?>
                  <?php if ($item['pickup_enabled']): ?>
                    <span class="status-badge status-approved merch-inline-badge">Local Pickup</span>
                  <?php endif; ?>
                </div>

                <?php if ($item['shipping_notes'] !== '' || $item['pickup_notes'] !== ''): ?>
                  <div class="merch-item-notes">
                    <?php if ($item['shipping_notes'] !== ''): ?>
                      <p><strong>Shipping:</strong> <?= e($item['shipping_notes']) ?></p>
                    <?php endif; ?>
                    <?php if ($item['pickup_notes'] !== ''): ?>
                      <p><strong>Pickup:</strong> <?= e($item['pickup_notes']) ?></p>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                <?php if ($storeSettings['paypal_email'] === ''): ?>
                  <div class="alert-inline alert-warning merch-unavailable">
                    Online checkout is not active yet. Please contact us directly if you want to reserve this item.
                  </div>
                <?php else: ?>
                  <div class="merch-checkout-grid">
                    <?php if ($item['shipping_enabled']): ?>
                      <?php renderMerchCheckoutForm($item, $storeSettings, 'shipping'); ?>
                    <?php endif; ?>
                    <?php if ($item['pickup_enabled']): ?>
                      <?php renderMerchCheckoutForm($item, $storeSettings, 'pickup'); ?>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
