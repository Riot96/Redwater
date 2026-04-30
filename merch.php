<?php
/**
 * RedWater Entertainment - Merch Store
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Merch';
$seoDescription = 'Shop RedWater Entertainment merchandise with online PayPal checkout, shipping, and local pickup options.';
$storeSettings = getMerchStoreSettings();
$items = getMerchItems(true);
$cartItemCount = getMerchCartItemCount();
$selectedCategory = trim(getString('category'));
$selectedTag = trim(getString('tag'));

$categoryMap = [];
$tags = [];
foreach ($items as $item) {
    if ($item['category'] !== '') {
        $categoryKey = strtolower($item['category']);
        if (!isset($categoryMap[$categoryKey])) {
            $categoryMap[$categoryKey] = $item['category'];
        }
    }

    foreach (parseTags($item['tags']) as $tag) {
        if (!in_array($tag, $tags, true)) {
            $tags[] = $tag;
        }
    }
}
$categories = array_values($categoryMap);
usort($categories, 'strcasecmp');
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

<main class="page-wrapper">
  <div class="page-header">
    <div class="container">
      <div class="merch-page-header">
        <div>
          <h1>Merch <span style="color:var(--blue)">Shop</span></h1>
          <p>Browse apparel, accessories, and event merch, then add products to your cart for PayPal checkout.</p>
        </div>
        <a href="/merch-cart.php" class="btn btn-outline">View Cart<?= $cartItemCount > 0 ? ' (' . $cartItemCount . ')' : '' ?></a>
      </div>
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
              <a href="<?= e(merchItemUrl($item)) ?>" class="merch-card-image-link">
                <?php if ($item['image_path'] !== ''): ?>
                  <img src="<?= e($item['image_path']) ?>" alt="<?= e($item['name']) ?>" class="card-img merch-card-img">
                <?php else: ?>
                  <div class="merch-card-img merch-card-img-placeholder">No Image Available</div>
                <?php endif; ?>
              </a>

              <div class="card-body">
                <div class="merch-card-header">
                  <div>
                    <?php if ($item['category'] !== ''): ?>
                      <div class="merch-category"><?= e($item['category']) ?></div>
                    <?php endif; ?>
                    <h2 class="card-title"><a href="<?= e(merchItemUrl($item)) ?>" class="merch-card-link"><?= e($item['name']) ?></a></h2>
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
                  <p class="merch-order-verification-note">Orders are reviewed against the live catalog details before fulfillment.</p>
                  <div class="merch-card-actions">
                    <a href="<?= e(merchItemUrl($item)) ?>" class="btn btn-outline">View Product</a>
                  </div>
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
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
