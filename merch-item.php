<?php
/**
 * RedWater Entertainment - Merch Item
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$itemId = trim(getString('item'));
$item = getMerchItemById($itemId);

if ($item === null) {
    http_response_code(404);
    $pageTitle = 'Merch Item Not Found';
    $seoDescription = 'The merch item you requested is not available.';
    include __DIR__ . '/includes/header.php';
    ?>
    <main class="page-wrapper">
      <section class="section-sm">
        <div class="container">
          <div class="merch-placeholder">
            <div class="icon">🛒</div>
            <h1>Item Not Found</h1>
            <p>The merch item you requested is not available right now.</p>
            <a href="/merch.php" class="btn btn-primary">Back to Merch</a>
          </div>
        </div>
      </section>
    </main>
    <?php
    include __DIR__ . '/includes/footer.php';
    return;
}

$storeSettings = getMerchStoreSettings();
$itemTags = parseTags($item['tags']);
$cartItemCount = getMerchCartItemCount();
$pageTitle = $item['seo_title'] !== '' ? $item['seo_title'] : $item['name'];
$seoDescription = $item['seo_description'] !== '' ? $item['seo_description'] : ($item['description'] !== '' ? $item['description'] : 'Shop ' . $item['name'] . ' from RedWater Entertainment.');

include __DIR__ . '/includes/header.php';
?>

<main class="page-wrapper">
  <div class="page-header">
    <div class="container">
      <div class="merch-page-header">
        <div>
          <a href="/merch.php" class="merch-breadcrumb-link">← Back to Merch</a>
          <h1><?= e($item['name']) ?></h1>
          <p>View the full product details, choose shipping or pickup, and add this item to your cart for checkout.</p>
        </div>
        <a href="/merch-cart.php" class="btn btn-outline">View Cart<?= $cartItemCount > 0 ? ' (' . $cartItemCount . ')' : '' ?></a>
      </div>
    </div>
  </div>

  <section class="section-sm">
    <div class="container">
      <article class="card merch-product-card">
        <div class="merch-product-layout">
          <div>
            <?php if ($item['image_path'] !== ''): ?>
              <img src="<?= e($item['image_path']) ?>" alt="<?= e($item['name']) ?>" class="card-img merch-product-image">
            <?php else: ?>
              <div class="merch-card-img merch-card-img-placeholder merch-product-image">No Image Available</div>
            <?php endif; ?>
          </div>

          <div class="card-body merch-product-copy">
            <?php if ($item['category'] !== ''): ?>
              <div class="merch-category"><?= e($item['category']) ?></div>
            <?php endif; ?>
            <div class="merch-product-price"><?= e(merchFormatAmount($item['price'], $storeSettings['paypal_currency'])) ?></div>

            <?php if ($item['description'] !== ''): ?>
              <div class="merch-product-description"><?= nl2br(e($item['description'])) ?></div>
            <?php endif; ?>

            <?php if ($itemTags): ?>
              <div class="tags merch-tags">
                <?php foreach ($itemTags as $tag): ?>
                  <a href="/merch.php?tag=<?= urlencode($tag) ?>" class="tag"><?= e($tag) ?></a>
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

            <?php if ($storeSettings['shipping_notice'] !== '' || $storeSettings['pickup_notice'] !== ''): ?>
              <div class="merch-store-notices mb-2">
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

            <?php if ($storeSettings['paypal_email'] === ''): ?>
              <div class="alert-inline alert-warning merch-unavailable">
                Online checkout is not active yet. Please contact us directly if you want to reserve this item.
              </div>
            <?php else: ?>
              <p class="merch-order-verification-note">Orders are reviewed against the live catalog details before fulfillment.</p>
              <div class="merch-checkout-grid">
                <?php if ($item['shipping_enabled']): ?>
                  <?php renderMerchAddToCartForm($item, 'shipping', $storeSettings['paypal_currency']); ?>
                <?php endif; ?>
                <?php if ($item['pickup_enabled']): ?>
                  <?php renderMerchAddToCartForm($item, 'pickup', $storeSettings['paypal_currency']); ?>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </article>
    </div>
  </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
