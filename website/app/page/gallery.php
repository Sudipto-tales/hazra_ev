<?php
App::render('head', [
    'pageTitle'       => 'Our Gallery — Hazra Electrical Bike',
    'pageDescription' => 'Explore the Hazra EV photo gallery featuring our electric scooter lineup, manufacturing highlights, events, and community riders.',
    'extraCss'        => ['assets/css/styles/pages/gallery.css'],
]);

App::render('header', ['isStickyOnly' => true]);
?>

<!-- ══════════ HERO SECTION ══════════ -->
<section class="gal-hero">
  <div class="gal-hero__eyebrow">
    <i></i> HAZRA ELECTRIC MOBILITY
  </div>
  <h1 class="gal-hero__title">OUR GALLERY</h1>
  <p class="gal-hero__subtitle">
    Explore the Hazra EV journey — from high-speed electric scooters and factory precision to rider meets across India.
  </p>
</section>

<!-- ══════════ FILTER CHIPS ══════════ -->
<div class="gal-filters-wrap">
  <div class="gal-filters" id="galleryFilters">
    <button type="button" class="gal-chip active" data-category="all">
      All <span class="gal-chip__count" id="count-all">0</span>
    </button>
    <!-- Dynamic category chips will be injected here -->
  </div>
</div>

<!-- ══════════ MASONRY GRID ══════════ -->
<main class="gal-grid-wrap">
  <div class="gal-grid" id="galleryGrid">
    <!-- Skeleton Loaders -->
    <div class="gal-skeleton gal-item--lg"></div>
    <div class="gal-skeleton gal-item--wide"></div>
    <div class="gal-skeleton gal-item--tall"></div>
    <div class="gal-skeleton gal-item--sm"></div>
    <div class="gal-skeleton gal-item--sm"></div>
    <div class="gal-skeleton gal-item--wide"></div>
  </div>
</main>

<!-- ══════════ LIGHTBOX MODAL ══════════ -->
<div class="gal-lightbox" id="galLightbox" role="dialog" aria-modal="true" aria-hidden="true">
  <button type="button" class="gal-lightbox__close" id="lightboxClose" aria-label="Close modal">&times;</button>
  <button type="button" class="gal-lightbox__nav gal-lightbox__nav--prev" id="lightboxPrev" aria-label="Previous item">&#10094;</button>
  <button type="button" class="gal-lightbox__nav gal-lightbox__nav--next" id="lightboxNext" aria-label="Next item">&#10095;</button>
  
  <div class="gal-lightbox__card">
    <img src="" alt="" class="gal-lightbox__img" id="lightboxImg">
    <div class="gal-lightbox__info">
      <div class="gal-lightbox__meta">
        <span class="gal-item__cat" id="lightboxCat">Category</span>
      </div>
      <h3 class="gal-lightbox__title" id="lightboxTitle">Image Title</h3>
      <p class="gal-lightbox__caption" id="lightboxCaption"></p>
    </div>
  </div>
</div>

<!-- ══════════ GALLERY CLIENT LOGIC ══════════ -->
<script>
document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();

  const gridEl = document.getElementById('galleryGrid');
  const filtersEl = document.getElementById('galleryFilters');
  const lightbox = document.getElementById('galLightbox');
  const lightboxImg = document.getElementById('lightboxImg');
  const lightboxTitle = document.getElementById('lightboxTitle');
  const lightboxCaption = document.getElementById('lightboxCaption');
  const lightboxCat = document.getElementById('lightboxCat');
  const closeBtn = document.getElementById('lightboxClose');
  const prevBtn = document.getElementById('lightboxPrev');
  const nextBtn = document.getElementById('lightboxNext');

  let galleryItems = [];
  let currentFilteredItems = [];
  let activeIndex = 0;
  let activeCategory = 'all';

  const SIZE_PATTERN = ['lg', 'wide', 'tall', 'sm', 'sm', 'wide', 'tall', 'lg', 'wide'];

  async function fetchGallery() {
    try {
      const apiPath = <?= json_encode(base_url('api/v1/gallery')) ?>;
      const response = await fetch(apiPath);
      if (!response.ok) throw new Error('API request failed');

      const res = await response.json();
      const rawData = res.data || [];

      // Filter only published items (or default status)
      galleryItems = rawData.filter(item => !item.status || item.status === 'published' || item.status === 'active');

      renderFilters();
      renderGrid();
    } catch (err) {
      console.error('Gallery load error:', err);
      gridEl.innerHTML = `
        <div class="gal-empty" style="grid-column: 1 / -1;">
          <div class="gal-empty__icon"><i data-lucide="image-off"></i></div>
          <h3 class="gal-empty__title">Unable to load gallery</h3>
          <p class="gal-empty__text">Please check back shortly or refresh the page.</p>
        </div>
      `;
      if (window.lucide) lucide.createIcons();
    }
  }

  function renderFilters() {
    const counts = { all: galleryItems.length };
    galleryItems.forEach(item => {
      const cat = (item.category || item.album || 'General').trim();
      counts[cat] = (counts[cat] || 0) + 1;
    });

    const categories = Object.keys(counts).filter(c => c !== 'all');

    let html = `
      <button type="button" class="gal-chip ${activeCategory === 'all' ? 'active' : ''}" data-category="all">
        All <span class="gal-chip__count">${counts.all}</span>
      </button>
    `;

    categories.forEach(cat => {
      html += `
        <button type="button" class="gal-chip ${activeCategory === cat ? 'active' : ''}" data-category="${escapeHtml(cat)}">
          ${escapeHtml(cat)} <span class="gal-chip__count">${counts[cat]}</span>
        </button>
      `;
    });

    filtersEl.innerHTML = html;

    filtersEl.querySelectorAll('.gal-chip').forEach(btn => {
      btn.addEventListener('click', () => {
        filtersEl.querySelectorAll('.gal-chip').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        activeCategory = btn.dataset.category;
        renderGrid();
      });
    });
  }

  function renderGrid() {
    if (activeCategory === 'all') {
      currentFilteredItems = galleryItems;
    } else {
      currentFilteredItems = galleryItems.filter(item => {
        const cat = (item.category || item.album || 'General').trim();
        return cat.toLowerCase() === activeCategory.toLowerCase();
      });
    }

    if (currentFilteredItems.length === 0) {
      gridEl.innerHTML = `
        <div class="gal-empty" style="grid-column: 1 / -1;">
          <div class="gal-empty__icon"><i data-lucide="image"></i></div>
          <h3 class="gal-empty__title">No images in this category</h3>
          <p class="gal-empty__text">Select another filter chip to view more gallery items.</p>
        </div>
      `;
      if (window.lucide) lucide.createIcons();
      return;
    }

    gridEl.innerHTML = currentFilteredItems.map((item, idx) => {
      const rawSize = (item.size || '').toLowerCase();
      const validSizes = ['sm', 'wide', 'tall', 'lg'];
      const sizeClass = validSizes.includes(rawSize) ? rawSize : SIZE_PATTERN[idx % SIZE_PATTERN.length];

      const catName = (item.category || item.album || 'Hazra EV').trim();
      const title = item.title || 'Hazra Electrical Bike';
      const caption = item.caption || '';
      const rawImg = item.image_url || item.url || item.image_path || item.image || '';
      const imgUrl = resolveMediaUrl(rawImg);

      return `
        <article class="gal-item gal-item--${sizeClass}" data-index="${idx}">
          <img class="gal-item__img" src="${escapeHtml(imgUrl)}" alt="${escapeHtml(title)}" loading="lazy" onerror="this.src='${<?= json_encode(base_url('assets/hazraev.png')) ?>}'; this.style.objectFit='contain';">
          <div class="gal-item__overlay">
            <div class="gal-item__top">
              <span class="gal-item__cat">${escapeHtml(catName)}</span>
              <div class="gal-item__zoom-icon"><i data-lucide="maximize-2"></i></div>
            </div>
            <div class="gal-item__bottom">
              <h3 class="gal-item__title">${escapeHtml(title)}</h3>
              ${caption ? `<p class="gal-item__caption">${escapeHtml(caption)}</p>` : ''}
            </div>
          </div>
        </article>
      `;
    }).join('');

    if (window.lucide) lucide.createIcons();

    gridEl.querySelectorAll('.gal-item').forEach(tile => {
      tile.addEventListener('click', () => {
        const idx = parseInt(tile.dataset.index, 10);
        openLightbox(idx);
      });
    });
  }

  function resolveMediaUrl(url) {
    if (!url) return <?= json_encode(base_url('assets/hazraev.png')) ?>;
    if (url.startsWith('http://') || url.startsWith('https://') || url.startsWith('//')) return url;
    const baseUrl = <?= json_encode(base_url('')) ?>;
    return baseUrl.replace(/\/$/, '') + '/' + url.replace(/^\//, '');
  }

  function openLightbox(index) {
    if (!currentFilteredItems[index]) return;
    activeIndex = index;
    const item = currentFilteredItems[activeIndex];

    const rawImg = item.image_url || item.url || item.image_path || item.image || '';
    lightboxImg.src = resolveMediaUrl(rawImg);
    lightboxTitle.textContent = item.title || 'Hazra Electrical Bike';
    lightboxCaption.textContent = item.caption || '';
    lightboxCat.textContent = (item.category || item.album || 'Hazra EV').trim();

    lightbox.classList.add('is-open');
    lightbox.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function closeLightbox() {
    lightbox.classList.remove('is-open');
    lightbox.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  function nextLightbox() {
    if (currentFilteredItems.length <= 1) return;
    activeIndex = (activeIndex + 1) % currentFilteredItems.length;
    openLightbox(activeIndex);
  }

  function prevLightbox() {
    if (currentFilteredItems.length <= 1) return;
    activeIndex = (activeIndex - 1 + currentFilteredItems.length) % currentFilteredItems.length;
    openLightbox(activeIndex);
  }

  closeBtn.addEventListener('click', closeLightbox);
  nextBtn.addEventListener('click', nextLightbox);
  prevBtn.addEventListener('click', prevLightbox);

  lightbox.addEventListener('click', (e) => {
    if (e.target === lightbox) closeLightbox();
  });

  document.addEventListener('keydown', (e) => {
    if (!lightbox.classList.contains('is-open')) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowRight') nextLightbox();
    if (e.key === 'ArrowLeft') prevLightbox();
  });

  function escapeHtml(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  fetchGallery();
});
</script>

<?php App::render('footer'); ?>
