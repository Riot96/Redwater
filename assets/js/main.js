/**
 * RedWater Entertainment - Main JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {

  // ── Mobile Nav Toggle ─────────────────────────────────────────────────────
  const navToggle = document.querySelector('.nav-toggle');
  const navMenu   = document.querySelector('.nav-menu');
  if (navToggle && navMenu) {
    navToggle.addEventListener('click', function () {
      const open = !navMenu.classList.contains('open');
      navMenu.classList.toggle('open', open);
      navToggle.classList.toggle('open', open);
      navToggle.setAttribute('aria-expanded', open);
    });
    // Close on outside click
    document.addEventListener('click', function (e) {
      if (!navToggle.contains(e.target) && !navMenu.contains(e.target)) {
        navMenu.classList.remove('open');
        navToggle.classList.remove('open');
        navToggle.setAttribute('aria-expanded', false);
      }
    });
  }

  // ── Nav User Dropdown ─────────────────────────────────────────────────────
  document.querySelectorAll('.nav-dropdown').forEach(function (dropdown) {
    const btn = dropdown.querySelector('.nav-user-btn');
    if (!btn) return;
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      const open = !dropdown.classList.contains('open');
      document.querySelectorAll('.nav-dropdown.open').forEach(function (d) {
        d.classList.remove('open');
        d.querySelector('.nav-user-btn')?.setAttribute('aria-expanded', false);
      });
      dropdown.classList.toggle('open', open);
      btn.setAttribute('aria-expanded', open);
    });
    document.addEventListener('click', function () {
      dropdown.classList.remove('open');
      btn.setAttribute('aria-expanded', false);
    });
  });

  // ── Flash Message Close ───────────────────────────────────────────────────
  document.querySelectorAll('.alert-close').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const alert = btn.closest('.alert');
      alert.style.opacity = '0';
      alert.style.transform = 'translateX(100%)';
      alert.style.transition = 'all 0.3s ease';
      setTimeout(function () { alert.remove(); }, 300);
    });
  });

  // Auto-dismiss flash messages
  setTimeout(function () {
    document.querySelectorAll('.flash-container .alert').forEach(function (alert) {
      alert.style.opacity = '0';
      alert.style.transform = 'translateX(100%)';
      alert.style.transition = 'all 0.4s ease';
      setTimeout(function () { alert.remove(); }, 400);
    });
  }, 5000);

  // ── Gallery Lightbox ──────────────────────────────────────────────────────
  const lightbox      = document.getElementById('lightbox');
  const lightboxMedia = document.getElementById('lightbox-media');
  const lightboxInfo  = document.getElementById('lightbox-info');

  if (lightbox) {
    const galleryItems = Array.from(document.querySelectorAll('.gallery-item[data-lightbox]'));
    let currentIndex   = 0;

    function openLightbox(index) {
      currentIndex = index;
      const item   = galleryItems[index];
      const type   = item.dataset.type;
      const src    = item.dataset.src;
      const title  = item.dataset.title || '';
      const desc   = item.dataset.desc  || '';
      const uploader = item.dataset.uploader || '';

      lightboxMedia.innerHTML = '';

      if (type === 'photo') {
        const img = document.createElement('img');
        img.src   = src;
        img.alt   = title;
        img.className = 'lightbox-media';
        lightboxMedia.appendChild(img);
      } else if (type === 'video-embed') {
        const iframe = document.createElement('iframe');
        iframe.src   = src;
        iframe.className = 'lightbox-embed';
        iframe.setAttribute('allowfullscreen', '');
        iframe.setAttribute('allow', 'autoplay; encrypted-media');
        lightboxMedia.appendChild(iframe);
      } else {
        const video = document.createElement('video');
        video.src   = src;
        video.controls = true;
        video.className = 'lightbox-media';
        lightboxMedia.appendChild(video);
      }

      if (lightboxInfo) {
        lightboxInfo.innerHTML =
          (title    ? `<h3>${escHtml(title)}</h3>` : '') +
          (desc     ? `<p>${escHtml(desc)}</p>`   : '') +
          (uploader ? `<p class="text-muted" style="font-size:0.8rem">Uploaded by ${escHtml(uploader)}</p>` : '');
      }

      lightbox.classList.add('open');
      document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
      lightbox.classList.remove('open');
      document.body.style.overflow = '';
      lightboxMedia.innerHTML = '';
    }

    galleryItems.forEach(function (item, index) {
      item.addEventListener('click', function () { openLightbox(index); });
    });

    // Nav arrows
    document.getElementById('lightbox-prev')?.addEventListener('click', function () {
      currentIndex = (currentIndex - 1 + galleryItems.length) % galleryItems.length;
      openLightbox(currentIndex);
    });
    document.getElementById('lightbox-next')?.addEventListener('click', function () {
      currentIndex = (currentIndex + 1) % galleryItems.length;
      openLightbox(currentIndex);
    });

    document.getElementById('lightbox-close')?.addEventListener('click', closeLightbox);
    lightbox.addEventListener('click', function (e) {
      if (e.target === lightbox) closeLightbox();
    });
    document.addEventListener('keydown', function (e) {
      if (!lightbox.classList.contains('open')) return;
      if (e.key === 'Escape') closeLightbox();
      if (e.key === 'ArrowLeft') { currentIndex = (currentIndex - 1 + galleryItems.length) % galleryItems.length; openLightbox(currentIndex); }
      if (e.key === 'ArrowRight') { currentIndex = (currentIndex + 1) % galleryItems.length; openLightbox(currentIndex); }
    });
  }

  // ── Gallery Filters ───────────────────────────────────────────────────────
  document.querySelectorAll('.filter-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.filter-btn').forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      const filter = btn.dataset.filter;
      document.querySelectorAll('.gallery-item').forEach(function (item) {
        if (filter === 'all' || item.dataset.type === filter ||
            (filter === 'photo' && item.dataset.type === 'photo-link') ||
            (filter === 'video' && (item.dataset.type === 'video-embed' || item.dataset.type === 'video-upload' || item.dataset.type === 'video-link'))) {
          item.style.display = '';
        } else {
          item.style.display = 'none';
        }
      });
    });
  });

  // ── Modals ────────────────────────────────────────────────────────────────
  document.querySelectorAll('[data-modal-open]').forEach(function (trigger) {
    trigger.addEventListener('click', function () {
      const modal = document.getElementById(trigger.dataset.modalOpen);
      if (modal) modal.classList.add('open');
    });
  });
  document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const modal = btn.closest('.modal-backdrop');
      if (modal) modal.classList.remove('open');
    });
  });
  document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
    backdrop.addEventListener('click', function (e) {
      if (e.target === backdrop) backdrop.classList.remove('open');
    });
  });

  // ── Drag & Drop Upload ────────────────────────────────────────────────────
  document.querySelectorAll('.dropzone').forEach(function (zone) {
    const input = zone.querySelector('input[type="file"]');
    zone.addEventListener('click', function () { input?.click(); });
    zone.addEventListener('dragover', function (e) { e.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', function () { zone.classList.remove('drag-over'); });
    zone.addEventListener('drop', function (e) {
      e.preventDefault();
      zone.classList.remove('drag-over');
      if (input && e.dataTransfer.files.length) {
        input.files = e.dataTransfer.files;
        zone.querySelector('p')?.textContent && (zone.querySelector('p').textContent = e.dataTransfer.files[0].name);
      }
    });
    if (input) {
      input.addEventListener('change', function () {
        if (input.files.length) {
          zone.querySelector('p') && (zone.querySelector('p').textContent = input.files[0].name);
        }
      });
    }
  });

  // ── Confirm Delete ────────────────────────────────────────────────────────
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!confirm(el.dataset.confirm || 'Are you sure?')) {
        e.preventDefault();
        e.stopPropagation();
      }
    });
  });

  // ── Admin: Sponsor tier enable/disable fields ─────────────────────────────
  document.querySelectorAll('.tier-form-toggle').forEach(function (checkbox) {
    checkbox.addEventListener('change', function () {
      const target = document.querySelector(checkbox.dataset.target);
      if (target) target.disabled = !checkbox.checked;
    });
  });

  // ── Scroll animations ─────────────────────────────────────────────────────
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1 });
    document.querySelectorAll('.animate-on-scroll').forEach(function (el) {
      observer.observe(el);
    });
  }

  // ── Utility: escape HTML ──────────────────────────────────────────────────
  function escHtml(str) {
    const el = document.createElement('div');
    el.textContent = str;
    return el.innerHTML;
  }

  // ── Policies content editor (simple, no heavy dep) ────────────────────────
  const editorBtns = document.querySelectorAll('.editor-toolbar [data-cmd]');
  editorBtns.forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      const cmd = btn.dataset.cmd;
      const val = btn.dataset.val || null;
      document.execCommand(cmd, false, val);
    });
  });

  // Sync contenteditable to hidden input before form submit
  document.querySelectorAll('form[data-editor-form]').forEach(function (form) {
    form.addEventListener('submit', function () {
      const editable = form.querySelector('[contenteditable]');
      const hidden   = form.querySelector('input[name="' + (editable?.dataset.syncTo || 'content_html') + '"]');
      if (editable && hidden) hidden.value = editable.innerHTML;
    });
  });

});
