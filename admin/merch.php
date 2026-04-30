<?php
/**
 * RedWater Entertainment - Admin: Merch Store
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

/**
 * @param list<array{
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
 * }> $items
 * @return array{
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
 * }|null
 */
function findAdminMerchItemById(array $items, string $id): ?array {
    foreach ($items as $item) {
        if ($item['id'] === $id) {
            return $item;
        }
    }

    return null;
}

$settingsErrors = [];
$itemErrors = [];
$storeSettings = getMerchStoreSettings();
$items = getMerchItems(false);
$editId = trim(getString('edit'));
$editItem = $editId !== '' ? findAdminMerchItemById($items, $editId) : null;
$itemFormState = $editItem ?? normalizeMerchItem([
    'id' => '',
    'name' => '',
    'description' => '',
    'price' => '0.00',
    'category' => '',
    'tags' => '',
    'variants' => [],
    'image_path' => '',
    'shipping_enabled' => true,
    'shipping_cost' => '0.00',
    'shipping_notes' => '',
    'pickup_enabled' => true,
    'pickup_notes' => '',
    'sort_order' => 0,
    'is_active' => true,
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = postString('action');

    if ($action === 'save_settings') {
        $storeSettings = normalizeMerchStoreSettings([
            'paypal_email' => trim(postString('paypal_email')),
            'paypal_currency' => trim(postString('paypal_currency', 'USD')),
            'paypal_use_sandbox' => postBool('paypal_use_sandbox'),
            'shipping_notice' => trim(postString('shipping_notice')),
            'pickup_notice' => trim(postString('pickup_notice')),
        ]);

        if ($storeSettings['paypal_email'] === '' && trim(postString('paypal_email')) !== '') {
            $settingsErrors[] = 'Enter a valid PayPal email address.';
        }

        if (empty($settingsErrors)) {
            saveMerchStoreSettings($storeSettings);
            flashMessage('success', 'Merch store settings saved.');
            redirect('/admin/merch.php');
        }
    }

    if ($action === 'save_item') {
        $itemId = trim(postString('item_id'));
        $existingItem = $itemId !== '' ? findAdminMerchItemById($items, $itemId) : null;
        $name = trim(postString('name'));
        $description = trim(postString('description'));
        $price = merchNormalizeAmount(postString('price'));
        $category = trim(postString('category'));
        $tags = trim(postString('tags'));
        $variantsText = trim(postString('variants'));
        $variants = merchParseVariantLines($variantsText);
        $shippingEnabled = postBool('shipping_enabled');
        $shippingCost = merchNormalizeAmount(postString('shipping_cost'));
        $shippingNotes = trim(postString('shipping_notes'));
        $pickupEnabled = postBool('pickup_enabled');
        $pickupNotes = trim(postString('pickup_notes'));
        $sortOrder = postInt('sort_order');
        $isActive = postBool('is_active');
        $imageUrl = trim(postString('image_url'));
        $removeImage = postBool('remove_image');
        $imageUpload = uploadedFile('image_upload');

        $itemFormState = normalizeMerchItem([
            'id' => $itemId,
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'category' => $category,
            'tags' => $tags,
            'variants' => $variants,
            'image_path' => $existingItem['image_path'] ?? '',
            'shipping_enabled' => $shippingEnabled,
            'shipping_cost' => $shippingCost,
            'shipping_notes' => $shippingNotes,
            'pickup_enabled' => $pickupEnabled,
            'pickup_notes' => $pickupNotes,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
        ]);

        if ($name === '') {
            $itemErrors[] = 'Item name is required.';
        }
        if (!$shippingEnabled && !$pickupEnabled) {
            $itemErrors[] = 'Enable shipping, local pickup, or both.';
        }
        if ($imageUrl !== '' && !isSupportedMerchImageUrl($imageUrl)) {
            $itemErrors[] = 'External product images must use HTTPS.';
        }

        $finalImagePath = stringValue($existingItem['image_path'] ?? '');
        $hasUpload = hasUploadedFile($imageUpload);

        if (empty($itemErrors) && $hasUpload) {
            assert($imageUpload !== null);
            $upload = handleFileUpload(
                $imageUpload,
                __DIR__ . '/../uploads/merch',
                ALLOWED_IMAGE_TYPES
            );
            if (!$upload['success']) {
                $itemErrors[] = $upload['error'];
            } else {
                deleteManagedMerchImage($finalImagePath);
                $finalImagePath = '/uploads/merch/' . $upload['filename'];
            }
        }

        if (empty($itemErrors) && !$hasUpload) {
            if ($imageUrl !== '') {
                if ($finalImagePath !== $imageUrl) {
                    deleteManagedMerchImage($finalImagePath);
                }
                $finalImagePath = $imageUrl;
            } elseif ($removeImage) {
                deleteManagedMerchImage($finalImagePath);
                $finalImagePath = '';
            }
        }

        $itemFormState['image_path'] = $finalImagePath;

        if (empty($itemErrors)) {
            $savedItem = normalizeMerchItem([
                'id' => $itemId !== '' ? $itemId : merchGenerateItemId(),
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'category' => $category,
                'tags' => $tags,
                'variants' => $variants,
                'image_path' => $finalImagePath,
                'shipping_enabled' => $shippingEnabled,
                'shipping_cost' => $shippingCost,
                'shipping_notes' => $shippingNotes,
                'pickup_enabled' => $pickupEnabled,
                'pickup_notes' => $pickupNotes,
                'sort_order' => $sortOrder,
                'is_active' => $isActive,
            ]);

            $updatedItems = [];
            $wasUpdated = false;
            foreach ($items as $item) {
                if ($savedItem['id'] === $item['id']) {
                    $updatedItems[] = $savedItem;
                    $wasUpdated = true;
                    continue;
                }
                $updatedItems[] = $item;
            }
            if (!$wasUpdated) {
                $updatedItems[] = $savedItem;
            }

            saveMerchItems($updatedItems);
            flashMessage('success', $wasUpdated ? 'Merch item updated.' : 'Merch item added.');
            redirect('/admin/merch.php');
        }
    }

    if ($action === 'delete_item') {
        $itemId = trim(postString('item_id'));
        $updatedItems = [];
        foreach ($items as $item) {
            if ($item['id'] === $itemId) {
                deleteManagedMerchImage($item['image_path']);
                continue;
            }
            $updatedItems[] = $item;
        }

        saveMerchItems($updatedItems);
        flashMessage('success', 'Merch item deleted.');
        redirect('/admin/merch.php');
    }
}

$items = getMerchItems(false);
$pageTitle = 'Manage Merch';
include __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <main class="admin-main">
    <div class="d-flex justify-between align-center mb-3">
      <h1 class="admin-page-title" style="margin:0;border:none;padding:0;">Manage <span>Merch</span></h1>
      <div class="d-flex gap-1">
        <a href="/merch.php" class="btn btn-outline btn-sm" target="_blank">Preview Store</a>
        <a href="#item-form" class="btn btn-primary btn-sm"><?= $editItem ? 'Edit Item' : '+ Add Item' ?></a>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-body">
        <div class="d-flex justify-between align-center mb-2" style="gap:1rem;flex-wrap:wrap;">
          <div>
            <h3 style="font-size:1rem;margin-bottom:0.35rem;">Store Checkout Settings</h3>
            <p class="text-muted">Configure the PayPal destination and the store-wide shipping or pickup notes shown on the public merch page.</p>
          </div>
          <span class="status-badge <?= $storeSettings['paypal_email'] !== '' ? 'status-approved' : 'status-pending' ?>">
            <?= $storeSettings['paypal_email'] !== '' ? 'PayPal Ready' : 'PayPal Not Configured' ?>
          </span>
        </div>

        <?php if ($settingsErrors): ?>
          <div class="alert-inline alert-error">
            <?php foreach ($settingsErrors as $error): ?>
              <div><?= e($error) ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="/admin/merch.php">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="save_settings">

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">PayPal Email</label>
              <input type="email" name="paypal_email" class="form-control" value="<?= e($storeSettings['paypal_email']) ?>" placeholder="payments@example.com">
            </div>
            <div class="form-group">
              <label class="form-label">Currency Code</label>
              <input type="text" name="paypal_currency" class="form-control" value="<?= e($storeSettings['paypal_currency']) ?>" maxlength="3" placeholder="USD">
              <div class="form-hint">Use a three-letter PayPal currency code such as USD.</div>
            </div>
          </div>

          <div class="form-group">
            <label class="form-check">
              <input type="checkbox" name="paypal_use_sandbox" value="1" <?= $storeSettings['paypal_use_sandbox'] ? 'checked' : '' ?>>
              Use PayPal sandbox checkout links for testing
            </label>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Shipping Notice</label>
              <textarea name="shipping_notice" class="form-control" rows="3" placeholder="Example: Orders ship every Friday."><?= e($storeSettings['shipping_notice']) ?></textarea>
            </div>
            <div class="form-group">
              <label class="form-label">Local Pickup Notice</label>
              <textarea name="pickup_notice" class="form-control" rows="3" placeholder="Example: Pickup is available in Sebring with confirmation."><?= e($storeSettings['pickup_notice']) ?></textarea>
            </div>
          </div>

          <button type="submit" class="btn btn-primary">Save Store Settings</button>
        </form>
      </div>
    </div>

    <div class="card mb-3" id="item-form">
      <div class="card-body">
        <div class="d-flex justify-between align-center mb-2" style="gap:1rem;flex-wrap:wrap;">
          <div>
            <h3 style="font-size:1rem;margin-bottom:0.35rem;"><?= $editItem ? 'Edit Merch Item' : 'Add Merch Item' ?></h3>
            <p class="text-muted">Create products with categories, tags, photos, simple variant lists, and separate shipping or pickup options.</p>
          </div>
          <?php if ($editItem): ?>
            <a href="/admin/merch.php#item-form" class="btn btn-outline btn-sm">Cancel Edit</a>
          <?php endif; ?>
        </div>

        <?php if ($itemErrors): ?>
          <div class="alert-inline alert-error">
            <?php foreach ($itemErrors as $error): ?>
              <div><?= e($error) ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="/admin/merch.php#item-form" enctype="multipart/form-data">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="save_item">
          <input type="hidden" name="item_id" value="<?= e($itemFormState['id']) ?>">

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Item Name</label>
              <input type="text" name="name" class="form-control" value="<?= e($itemFormState['name']) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Price</label>
              <input type="text" name="price" class="form-control" value="<?= e($itemFormState['price']) ?>" placeholder="25.00" required>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="4" placeholder="Describe the item, fit, materials, and anything buyers should know."><?= e($itemFormState['description']) ?></textarea>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Category</label>
              <input type="text" name="category" class="form-control" value="<?= e($itemFormState['category']) ?>" placeholder="Apparel, Drinkware, Accessories">
            </div>
            <div class="form-group">
              <label class="form-label">Tags</label>
              <input type="text" name="tags" class="form-control" value="<?= e($itemFormState['tags']) ?>" placeholder="haunted, limited run, crew favorite">
              <div class="form-hint">Separate tags with commas.</div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Image Upload</label>
              <input type="file" name="image_upload" class="form-control" accept="image/*">
              <div class="form-hint">Upload a product photo or use the HTTPS image URL field below.</div>
            </div>
            <div class="form-group">
              <label class="form-label">External Image URL</label>
              <input type="url" name="image_url" class="form-control" value="<?= e(isManagedMerchImagePath($itemFormState['image_path']) ? '' : $itemFormState['image_path']) ?>" placeholder="https://...">
            </div>
          </div>

          <?php if ($itemFormState['image_path'] !== ''): ?>
            <?php $previewAlt = $itemFormState['name'] !== '' ? $itemFormState['name'] : 'Merch item preview'; ?>
            <div class="merch-admin-image-preview mb-2">
              <img src="<?= e($itemFormState['image_path']) ?>" alt="<?= e($previewAlt) ?>" class="merch-admin-thumb-lg">
              <label class="form-check">
                <input type="checkbox" name="remove_image" value="1">
                Remove current image
              </label>
            </div>
          <?php endif; ?>

          <div class="form-group">
            <label class="form-label">Variants</label>
            <textarea name="variants" class="form-control" rows="4" placeholder="One option per line&#10;Small&#10;Medium&#10;Large"><?= e(implode("\n", $itemFormState['variants'])) ?></textarea>
            <div class="form-hint">Variants are passed to PayPal as the selected option for the item.</div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-check">
                <input type="checkbox" name="shipping_enabled" value="1" <?= $itemFormState['shipping_enabled'] ? 'checked' : '' ?>>
                Allow shipping for this item
              </label>
              <label class="form-label mt-1">Shipping Fee</label>
              <input type="text" name="shipping_cost" class="form-control" value="<?= e($itemFormState['shipping_cost']) ?>" placeholder="0.00">
              <div class="form-hint">Flat shipping fee added to the PayPal checkout form.</div>
            </div>
            <div class="form-group">
              <label class="form-check">
                <input type="checkbox" name="pickup_enabled" value="1" <?= $itemFormState['pickup_enabled'] ? 'checked' : '' ?>>
                Allow local pickup for this item
              </label>
              <label class="form-label mt-1">Sort Order</label>
              <input type="number" name="sort_order" class="form-control" value="<?= e($itemFormState['sort_order']) ?>" min="0">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Item Shipping Notes</label>
              <textarea name="shipping_notes" class="form-control" rows="3" placeholder="Example: Ships in 3-5 business days."><?= e($itemFormState['shipping_notes']) ?></textarea>
            </div>
            <div class="form-group">
              <label class="form-label">Item Pickup Notes</label>
              <textarea name="pickup_notes" class="form-control" rows="3" placeholder="Example: Bring your PayPal receipt to pickup."><?= e($itemFormState['pickup_notes']) ?></textarea>
            </div>
          </div>

          <div class="form-group">
            <label class="form-check">
              <input type="checkbox" name="is_active" value="1" <?= $itemFormState['is_active'] ? 'checked' : '' ?>>
              Show this item on the public merch page
            </label>
          </div>

          <div class="d-flex gap-2" style="flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary"><?= $editItem ? 'Save Changes' : 'Add Item' ?></button>
            <?php if ($editItem): ?>
              <a href="/admin/merch.php#item-form" class="btn btn-outline">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="d-flex justify-between align-center mb-2" style="gap:1rem;flex-wrap:wrap;">
          <h3 style="font-size:1rem;">Current Catalog</h3>
          <span class="text-muted"><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?></span>
        </div>

        <?php if ($items): ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Image</th>
                  <th>Item</th>
                  <th>Price</th>
                  <th>Variants</th>
                  <th>Fulfillment</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($items as $item): ?>
                <tr>
                  <td>
                    <?php if ($item['image_path'] !== ''): ?>
                      <img src="<?= e($item['image_path']) ?>" alt="<?= e($item['name']) ?>" class="merch-admin-thumb">
                    <?php else: ?>
                      <div class="merch-admin-thumb merch-admin-thumb-placeholder">No Image</div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <strong><?= e($item['name']) ?></strong>
                    <div class="text-muted" style="font-size:0.8rem;">
                      <?= $item['category'] !== '' ? e($item['category']) : 'Uncategorized' ?>
                      <?php if ($item['tags'] !== ''): ?>
                        &middot; <?= e($item['tags']) ?>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td><?= e(merchFormatAmount($item['price'], $storeSettings['paypal_currency'])) ?></td>
                  <td><?= $item['variants'] ? e(implode(', ', $item['variants'])) : '—' ?></td>
                  <td>
                    <?php if ($item['shipping_enabled']): ?><span class="status-badge status-blue merch-inline-badge">Shipping</span><?php endif; ?>
                    <?php if ($item['pickup_enabled']): ?><span class="status-badge status-approved merch-inline-badge">Pickup</span><?php endif; ?>
                  </td>
                  <td>
                    <span class="status-badge <?= $item['is_active'] ? 'status-active' : 'status-inactive' ?>">
                      <?= $item['is_active'] ? 'Live' : 'Draft' ?>
                    </span>
                  </td>
                  <td>
                    <div class="td-actions">
                      <a href="/admin/merch.php?edit=<?= urlencode($item['id']) ?>#item-form" class="btn btn-outline btn-sm">Edit</a>
                      <form method="POST" action="/admin/merch.php" style="display:inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_item">
                        <input type="hidden" name="item_id" value="<?= e($item['id']) ?>">
                        <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete this merch item?">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="text-muted">No merch items configured yet. Add your first item above.</p>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
