<?php
/**
 * RedWater Entertainment - Gallery Page
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle    = 'Gallery';
$seoDescription = 'Browse photos and videos from RedWater Entertainment events, behind-the-scenes, and more.';

include __DIR__ . '/includes/header.php';

$items = getGalleryItems(true);
?>

<main class="page-wrapper">
  <div class="page-header">
    <div class="container">
      <h1>Photo &amp; Video <span style="color:var(--blue)">Gallery</span></h1>
      <p>A collection of moments from our events, performances, and adventures.</p>
    </div>
  </div>

  <section class="section-sm">
    <div class="container">

      <?php if (isAdmin()): ?>
        <div class="mb-3 d-flex gap-2 justify-between align-center">
          <div></div>
          <div>
            <a href="/admin/gallery.php" class="btn btn-outline btn-sm">⚙️ Manage Gallery</a>
          </div>
        </div>
      <?php elseif (isMember()): ?>
        <div class="mb-3 text-center">
          <a href="/member/gallery.php" class="btn btn-secondary btn-sm">+ Upload Content</a>
        </div>
      <?php endif; ?>

      <?php if (!empty($items)): ?>
        <!-- Filters -->
        <div class="gallery-filters">
          <button class="filter-btn active" data-filter="all">All</button>
          <button class="filter-btn" data-filter="photo">Photos</button>
          <button class="filter-btn" data-filter="video">Videos</button>
        </div>

        <div class="gallery-grid">
          <?php foreach ($items as $item): ?>
            <?php
            $filePath = stringValue($item['file_path'] ?? '');
            $videoUrl = stringValue($item['video_url'] ?? '');
            $linkUrl = stringValue($item['link_url'] ?? '');
            $tagsText = stringValue($item['tags'] ?? '');
            $isVideo    = $item['type'] === 'video';
            $sourceType = getGalleryItemSourceType($item);
            $isLinked   = $sourceType === 'link';
            $isEmbed    = $isVideo && $sourceType === 'embed';
            $dataType   = $item['type'] === 'photo' ? ($isLinked ? 'photo-link' : 'photo') : ($isLinked ? 'video-link' : ($isEmbed ? 'video-embed' : 'video-upload'));
            $dataSrc    = '';
            if ($isLinked) {
                $dataSrc = '';
            } elseif ($item['type'] === 'photo') {
                $dataSrc = '/' . ltrim($filePath, '/');
            } elseif ($isEmbed) {
                $dataSrc = getVideoEmbedUrl($videoUrl);
            } else {
                $dataSrc = '/' . ltrim($filePath, '/');
            }
            $tags = parseTags($tagsText);
            ?>
            <div class="gallery-item"
                 <?php if (!$isLinked): ?>data-lightbox="true"<?php endif; ?>
                 data-type="<?= e($dataType) ?>"
                 data-src="<?= e($dataSrc) ?>"
                 data-title="<?= e($item['title'] ?? '') ?>"
                 data-desc="<?= e($item['description'] ?? '') ?>"
                 data-uploader="<?= e($item['uploader_name'] ?? '') ?>">

              <?php if ($isLinked): ?>
                <div style="width:100%;height:100%;background:var(--bg-card2);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:0.5rem;text-align:center;padding:1rem;">
                  <div style="font-size:3rem;line-height:1;">🔗</div>
                  <div><?= e($isVideo ? 'Linked Video' : 'Linked Photo') ?></div>
                </div>
                <a href="<?= e($linkUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="Open linked gallery item" style="position:absolute;inset:0;z-index:2;"></a>
              <?php elseif ($item['type'] === 'photo'): ?>
                <img src="<?= e('/' . ltrim($filePath, '/')) ?>"
                     alt="<?= e($item['alt_text'] ?: ($item['title'] ?: 'Gallery photo')) ?>"
                     loading="lazy">
              <?php elseif ($isEmbed): ?>
                <?php
                // Show thumbnail for YouTube
                $thumbUrl = '';
                if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $videoUrl, $m)) {
                    $thumbUrl = 'https://img.youtube.com/vi/' . $m[1] . '/hqdefault.jpg';
                } elseif (preg_match('/vimeo\.com\/(\d+)/', $videoUrl, $m)) {
                    $thumbUrl = 'https://vumbnail.com/' . $m[1] . '.jpg';
                }
                ?>
                <?php if ($thumbUrl): ?>
                  <img src="<?= e($thumbUrl) ?>" alt="<?= e($item['alt_text'] ?: ($item['title'] ?: 'Video')) ?>" loading="lazy">
                <?php else: ?>
                  <div style="width:100%;height:100%;background:var(--bg-card2);display:flex;align-items:center;justify-content:center;font-size:3rem;">▶️</div>
                <?php endif; ?>
              <?php else: ?>
                <video src="<?= e('/' . ltrim($filePath, '/')) ?>" preload="metadata"></video>
              <?php endif; ?>

              <div class="gallery-item-overlay<?= $isLinked ? ' gallery-item-overlay-link' : '' ?>">
                <?php if (!empty($item['title'])): ?><div class="gallery-item-title"><?= e($item['title']) ?></div><?php endif; ?>
                <?php if (!empty($item['uploader_name'])): ?><div class="gallery-item-uploader">by <?= e($item['uploader_name']) ?></div><?php endif; ?>
                <?php if ($isLinked): ?><div class="gallery-item-uploader">opens externally ↗</div><?php endif; ?>
              </div>

              <?php if ($isVideo): ?>
                <div class="gallery-item-type-badge<?= $isLinked ? ' gallery-item-type-badge-linked' : '' ?>">Video</div>
              <?php endif; ?>
              <?php if ($isLinked): ?>
                <div class="gallery-item-type-badge gallery-item-type-badge-link">Link</div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>

      <?php else: ?>
        <div class="text-center" style="padding: 4rem 0; color: var(--text-muted);">
          <div style="font-size:4rem;margin-bottom:1rem;">📷</div>
          <h3>Gallery Coming Soon</h3>
          <p>Photos and videos from our events will appear here.</p>
          <?php if (isAdmin()): ?>
            <a href="/admin/gallery.php" class="btn btn-primary mt-2">Upload Content</a>
          <?php elseif (isMember()): ?>
            <a href="/member/gallery.php" class="btn btn-primary mt-2">Upload Content</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    </div>
  </section>
</main>

<!-- Lightbox -->
<div id="lightbox" class="lightbox" role="dialog" aria-modal="true" aria-label="Image viewer">
  <span id="lightbox-close" class="lightbox-close" aria-label="Close">&times;</span>
  <span id="lightbox-prev" class="lightbox-nav lightbox-prev" aria-label="Previous">&#8592;</span>
  <span id="lightbox-next" class="lightbox-nav lightbox-next" aria-label="Next">&#8594;</span>
  <div class="lightbox-inner">
    <div id="lightbox-media"></div>
    <div id="lightbox-info" class="lightbox-info"></div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
