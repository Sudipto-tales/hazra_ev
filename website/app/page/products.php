<?php
App::render('head', [
    'pageTitle'       => 'Products — Hazra Electrical Bike',
    'pageDescription' => 'Browse the full Hazra Electrical Bike lineup. Filter by range, battery and series.',
    'extraCss'        => ['assets/css/styles/pages/products.css'],
]);

App::render('header', ['isStickyOnly' => true]);

$catalogData = [];
$heroProduct = null;

try {
    if (function_exists('db_fetch_all')) {
        $productsList = db_fetch_all("
            SELECT id, name, brand, model_code, slug, hero_image, range_km, top_speed_kmph, 
                   rating, warranty_years, warranty_note, battery_capacity, motor_power, 
                   charging_time, load_capacity_kg, category, is_featured, featured_order, updated_at
            FROM products 
            WHERE active = 1 
            ORDER BY is_featured DESC, featured_order ASC, updated_at DESC
        ");

        if (!empty($productsList)) {
            $heroProduct = $productsList[0] ?? null;

            $pIds = array_column($productsList, 'id');
            $cph = implode(',', array_fill(0, count($pIds), '?'));
            $colorsRows = db_fetch_all("
                SELECT c.id, c.product_id, c.name, c.argb, c.position, 
                       ci.url as image_url
                FROM product_colors c
                LEFT JOIN product_color_images ci ON ci.color_id = c.id
                WHERE c.product_id IN ($cph)
                ORDER BY c.position ASC, c.name ASC, ci.position ASC
            ", $pIds);

            $colorsByProduct = [];
            foreach ($colorsRows as $row) {
                $pid = $row['product_id'];
                $cid = $row['id'];
                if (!isset($colorsByProduct[$pid][$cid])) {
                    $hex = !empty($row['argb']) ? sprintf('#%06x', ((int)$row['argb']) & 0xFFFFFF) : '#1a1a1a';
                    $colorsByProduct[$pid][$cid] = [
                        'name'   => $row['name'] ?? '',
                        'hex'    => $hex,
                        'images' => [],
                    ];
                }
                if (!empty($row['image_url'])) {
                    $colorsByProduct[$pid][$cid]['images'][] = (str_starts_with($row['image_url'], 'http') || str_starts_with($row['image_url'], '/')) 
                        ? $row['image_url'] 
                        : base_url($row['image_url']);
                }
            }

            foreach ($productsList as $p) {
                $pId = $p['id'];
                $colors = [];
                if (isset($colorsByProduct[$pId])) {
                    foreach ($colorsByProduct[$pId] as $col) {
                        $colors[] = [
                            'name'  => $col['name'],
                            'hex'   => $col['hex'],
                            'image' => $col['images'][0] ?? '',
                        ];
                    }
                }

                $rangeKm = (int)($p['range_km'] ?? 0);
                $topSpeed = (int)($p['top_speed_kmph'] ?? 0);
                $rating = (float)($p['rating'] ?? 0);
                $warranty = (int)($p['warranty_years'] ?? 0);
                $batteryCap = trim($p['battery_capacity'] ?? '');
                $motorPower = trim($p['motor_power'] ?? '');

                $chips = [];
                if ($rangeKm > 0) {
                    $chips[] = "{$rangeKm} km Range";
                }
                if ($topSpeed > 0) {
                    $chips[] = ($topSpeed >= 55) ? 'High Speed' : 'City Speed';
                }
                if (!empty($batteryCap)) {
                    $chips[] = $batteryCap;
                }

                $primaryColor = !empty($colors[0]['hex']) ? $colors[0]['hex'] : '#1a1a1a';
                $primaryImg = !empty($colors[0]['image']) 
                    ? $colors[0]['image'] 
                    : (!empty($p['hero_image']) ? (str_starts_with($p['hero_image'], 'http') || str_starts_with($p['hero_image'], '/') ? $p['hero_image'] : base_url($p['hero_image'])) : base_url('assets/scutie_light.webp'));

                // Detect battery category
                $batteryTypes = [];
                $batLower = strtolower($batteryCap);
                if (str_contains($batLower, 'lithium') || str_contains($batLower, 'li-ion') || str_contains($batLower, 'nmc')) {
                    $batteryTypes[] = 'li-ion';
                }
                if (str_contains($batLower, 'graphene')) {
                    $batteryTypes[] = 'graphene';
                }

                $catalogData[] = [
                    'id'              => $p['id'],
                    'slug'            => !empty($p['slug']) ? $p['slug'] : $p['id'],
                    'name'            => $p['name'] ?? 'Scooter',
                    'series'          => $p['brand'] ?? '',
                    'company'         => $p['brand'] ?? 'Hazra',
                    'modelCode'       => $p['model_code'] ?? '',
                    'rangeKm'         => $rangeKm,
                    'topSpeedKmph'    => $topSpeed,
                    'speed'           => $topSpeed >= 55 ? 'high' : 'city',
                    'battery'         => $batteryTypes,
                    'batteryCapacity' => $batteryCap,
                    'motorPower'      => $motorPower,
                    'priceFrom'       => null,
                    'priceTo'         => null,
                    'rating'          => $rating,
                    'reviews'         => 0,
                    'color'           => $primaryColor,
                    'colors'          => $colors,
                    'badge'           => $warranty > 0 ? "{$warranty} Year Warranty" : '',
                    'chips'           => $chips,
                    'variants'        => [],
                    'image'           => $primaryImg,
                    'tags'            => array_values(array_filter([$p['name'] ?? '', $p['brand'] ?? '', $batteryCap, $motorPower])),
                ];
            }
        }
    }
} catch (Throwable $e) {}
?>

<!-- ========== UNIQUE PAGE CONTENT START ========== -->
<!-- SHOWCASE -->
<section class="pl-show">
  <div class="wrap pl-show__grid">
    <?php if ($heroProduct): ?>
    <a class="pl-show__hero" href="<?= e(base_url('product-detail?slug=' . urlencode($heroProduct['slug'] ?? $heroProduct['id']))) ?>">
      <img src="<?= e(!empty($heroProduct['hero_image']) ? (str_starts_with($heroProduct['hero_image'], 'http') || str_starts_with($heroProduct['hero_image'], '/') ? $heroProduct['hero_image'] : base_url($heroProduct['hero_image'])) : base_url('assets/scutie_light.webp')) ?>" alt="<?= e($heroProduct['name'] ?? 'Hazra signature scooter') ?>">
      <div class="pl-show__hero-copy">
        <p class="eyebrow"><i class="sq"></i>SIGNATURE</p>
        <h1>Signature<br>of the road</h1>
        <span class="pl-show__cta">Explore <?= e($heroProduct['name']) ?> <i data-lucide="arrow-up-right"></i></span>
      </div>
    </a>
    <?php else: ?>
    <div class="pl-show__hero">
      <img src="<?= e(base_url('assets/scutie_light.webp')) ?>" alt="Hazra signature scooter">
      <div class="pl-show__hero-copy">
        <p class="eyebrow"><i class="sq"></i>SIGNATURE</p>
        <h1>Signature<br>of the road</h1>
      </div>
    </div>
    <?php endif; ?>
    <a class="pl-show__promo" href="<?= e(base_url('index#test-ride')) ?>">
      <p class="pl-show__promo-kicker">This month</p>
      <h2>Book a free<br>test ride</h2>
      <p>Feel the torque map on your own route.</p>
      <span class="btn btn--ink">Schedule <i data-lucide="bike"></i></span>
    </a>
    <button type="button" class="pl-show__tile" data-filter-speed="city">
      <img src="<?= e(base_url('assets/storm.webp')) ?>" alt="City scooters">
      <span>City / Daily</span>
    </button>
  </div>
</section>

<!-- TOOLBAR + FILTERS + GRID -->
<section class="pl-main">
  <div class="wrap">
    <header class="pl-head">
      <div>
        <p class="eyebrow"><i class="sq"></i>HAZRA LINEUP</p>
        <h2 class="sec__title">Products that<br><span class="hl">earn the ride.</span></h2>
      </div>
      <p class="pl-count" id="plCount">Showing all models</p>
    </header>

    <div class="pl-toolbar">
      <div class="pl-search" id="plSearchWrap">
        <i data-lucide="search" class="pl-search__icon"></i>
        <input type="search" id="plSearch" placeholder="Search by name, series, battery, range…" autocomplete="off" enterkeyhint="search">
        <button type="button" class="pl-search__clear" id="plSearchClear" hidden aria-label="Clear search"><i data-lucide="x"></i></button>
        <div class="pl-suggest" id="plSuggest" hidden role="listbox"></div>
      </div>

      <button type="button" class="pl-filter-toggle" id="plFilterToggle" aria-expanded="false" aria-controls="plFilters">
        <i data-lucide="sliders-horizontal"></i>
        <span>Filters</span>
        <b class="pl-filter-badge" id="plFilterBadge" hidden>0</b>
      </button>

      <label class="pl-sort">
        <span>Sort</span>
        <select id="plSort">
          <option value="featured">Featured</option>
          <option value="range-desc">Range: high to low</option>
          <option value="rating-desc">Top rated</option>
          <option value="price-asc" class="sort-opt-price" hidden>Price: low to high</option>
          <option value="price-desc" class="sort-opt-price" hidden>Price: high to low</option>
        </select>
      </label>
    </div>

    <div class="pl-chips" id="plChips" hidden></div>

    <div class="pl-layout">
      <div class="pl-filters-backdrop" id="plFiltersBackdrop" hidden></div>
      <aside class="pl-filters" id="plFilters" aria-label="Product filters">
        <div class="pl-filters__head">
          <h3>Filters</h3>
          <button type="button" class="pl-filters__clear" id="plClearAll">Clear all</button>
        </div>

        <fieldset class="pl-fg" id="priceFilterGroup" hidden>
          <legend>Price (ex-showroom)</legend>
          <div class="pl-range-inputs">
            <label>Min ₹ <input type="number" id="priceMin" min="0" max="200000" step="1000" placeholder="0"></label>
            <label>Max ₹ <input type="number" id="priceMax" min="0" max="200000" step="1000" placeholder="150000"></label>
          </div>
          <input type="range" id="priceMaxSlider" min="40000" max="150000" step="1000" value="150000">
        </fieldset>

        <fieldset class="pl-fg">
          <legend>Range (km)</legend>
          <div class="pl-range-inputs">
            <label>Min <input type="number" id="rangeMin" min="0" max="200" step="5" placeholder="0"></label>
            <label>Max <input type="number" id="rangeMax" min="0" max="200" step="5" placeholder="200"></label>
          </div>
          <div class="pl-pills" data-group="rangePreset">
            <button type="button" data-rmin="0" data-rmax="80">&lt; 80 km</button>
            <button type="button" data-rmin="80" data-rmax="110">80–110</button>
            <button type="button" data-rmin="110" data-rmax="200">110+</button>
          </div>
        </fieldset>

        <fieldset class="pl-fg" id="seriesFilterGroup">
          <legend>Series</legend>
          <div class="pl-checks" id="filterSeries"></div>
        </fieldset>

        <fieldset class="pl-fg">
          <legend>Battery</legend>
          <div class="pl-checks">
            <label><input type="checkbox" name="battery" value="graphene"> Graphene</label>
            <label><input type="checkbox" name="battery" value="li-ion"> Li-ion / Lithium</label>
          </div>
        </fieldset>

        <fieldset class="pl-fg">
          <legend>Speed class</legend>
          <div class="pl-checks">
            <label><input type="checkbox" name="speed" value="high"> High speed</label>
            <label><input type="checkbox" name="speed" value="city"> City / low speed</label>
          </div>
        </fieldset>
      </aside>

      <div class="pl-grid-wrap">
        <div class="coll__grid pl-grid" id="plGrid"></div>
        <div class="pl-empty" id="plEmpty" hidden>
          <p>No models match these filters.</p>
          <button type="button" class="btn btn--ghost" id="plEmptyClear">Clear filters</button>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const baseUrl = <?= json_encode(rtrim(base_url(''), '/')) ?>;
  const CATALOG = <?= json_encode($catalogData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

  const state = {
    q: '', priceMin: null, priceMax: null, rangeMin: null, rangeMax: null,
    series: [], battery: [], speed: [], sort: 'featured'
  };

  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  const PARAMS = {
    q: 'q', priceMin: 'priceMin', priceMax: 'priceMax', rangeMin: 'rangeMin', rangeMax: 'rangeMax',
    series: 'series', battery: 'battery', speed: 'speed', sort: 'sort'
  };

  function parseList(v) { return v ? v.split(',').map(s => s.trim()).filter(Boolean) : []; }
  function numOrNull(v) { if (v == null || v === '') return null; const n = Number(v); return Number.isFinite(n) ? n : null; }

  function readURL() {
    const p = new URLSearchParams(location.search);
    state.q = p.get(PARAMS.q) || '';
    state.priceMin = numOrNull(p.get(PARAMS.priceMin));
    state.priceMax = numOrNull(p.get(PARAMS.priceMax));
    state.rangeMin = numOrNull(p.get(PARAMS.rangeMin));
    state.rangeMax = numOrNull(p.get(PARAMS.rangeMax));
    state.series = parseList(p.get(PARAMS.series));
    state.battery = parseList(p.get(PARAMS.battery));
    state.speed = parseList(p.get(PARAMS.speed));
    state.sort = p.get(PARAMS.sort) || 'featured';
  }

  function writeURL(replace = true) {
    const p = new URLSearchParams();
    if (state.q) p.set(PARAMS.q, state.q);
    if (state.priceMin != null) p.set(PARAMS.priceMin, String(state.priceMin));
    if (state.priceMax != null) p.set(PARAMS.priceMax, String(state.priceMax));
    if (state.rangeMin != null) p.set(PARAMS.rangeMin, String(state.rangeMin));
    if (state.rangeMax != null) p.set(PARAMS.rangeMax, String(state.rangeMax));
    if (state.series.length) p.set(PARAMS.series, state.series.join(','));
    if (state.battery.length) p.set(PARAMS.battery, state.battery.join(','));
    if (state.speed.length) p.set(PARAMS.speed, state.speed.join(','));
    if (state.sort && state.sort !== 'featured') p.set(PARAMS.sort, state.sort);

    const qs = p.toString();
    const url = qs ? `${location.pathname}?${qs}` : location.pathname;
    if (replace) history.replaceState({ filters: { ...state } }, '', url);
    else history.pushState({ filters: { ...state } }, '', url);
  }

  function matches(item) {
    if (state.q) {
      const hay = [
        item.name, 
        item.series, 
        item.company, 
        item.modelCode || '',
        item.batteryCapacity || '',
        item.motorPower || '',
        ...(item.tags || []), 
        ...(item.battery || []), 
        ...(item.chips || [])
      ].join(' ').toLowerCase();
      const tokens = state.q.toLowerCase().split(/\s+/).filter(Boolean);
      if (!tokens.every(t => hay.includes(t))) return false;
    }
    if (state.priceMin != null && item.priceTo != null && item.priceTo < state.priceMin) return false;
    if (state.priceMax != null && item.priceFrom != null && item.priceFrom > state.priceMax) return false;
    if (state.rangeMin != null && (item.rangeKm || 0) < state.rangeMin) return false;
    if (state.rangeMax != null && (item.rangeKm || 0) > state.rangeMax) return false;
    if (state.series.length && !state.series.includes(item.series)) return false;
    if (state.battery.length && (!item.battery || !state.battery.some(b => item.battery.includes(b)))) return false;
    if (state.speed.length && !state.speed.includes(item.speed)) return false;
    return true;
  }

  function sortList(list) {
    const arr = [...list];
    switch (state.sort) {
      case 'price-asc': return arr.sort((a, b) => (a.priceFrom || 0) - (b.priceFrom || 0));
      case 'price-desc': return arr.sort((a, b) => (b.priceFrom || 0) - (a.priceFrom || 0));
      case 'range-desc': return arr.sort((a, b) => (b.rangeKm || 0) - (a.rangeKm || 0));
      case 'rating-desc': return arr.sort((a, b) => (b.rating || 0) - (a.rating || 0));
      default: return arr;
    }
  }

  function filtered() { return sortList(CATALOG.filter(matches)); }

  function cardHTML(p) {
    const badgeHTML = p.badge
      ? `<span class="card__badge"><i data-lucide="shield-check"></i>${p.badge}</span>`
      : '';

    const ratingHTML = (p.rating && p.rating > 0)
      ? `<div class="card__rate"><i data-lucide="star"></i><b>${p.rating.toFixed(1)}</b>${p.reviews > 0 ? `<span>· ${p.reviews} reviews</span>` : ''}</div>`
      : '';

    const chipsHTML = (p.chips && p.chips.length > 0)
      ? `<div class="card__chips">${p.chips.map((c, i) => `<span><i data-lucide="${i === 0 ? 'battery-charging' : 'zap'}"></i>${c}</span>`).join('')}</div>`
      : '';

    let colorsHTML = '';
    if (p.colors && p.colors.length > 0) {
      const swatches = p.colors.map((c, idx) =>
        `<button class="sw ${idx === 0 ? 'is-on' : ''}" style="--c:${c.hex}" type="button" aria-label="${c.name || 'Colour'}" title="${c.name || ''}" data-img="${c.image || ''}"></button>`
      ).join('');
      colorsHTML = `<div class="card__colors" role="group" aria-label="Available colours">${swatches}</div>`;
    }

    let specsOrPriceHTML = '';
    if (p.variants && p.variants.length > 0) {
      const vars = p.variants.map(v => `<button class="var" type="button"><b>${v.price}</b><span>${v.label}</span></button>`).join('');
      specsOrPriceHTML = `
        <div class="card__price">
          <p class="card__plabel">Ex-showroom — starts at</p>
          <div class="card__vars">${vars}</div>
          <p class="card__note">*Without GST</p>
        </div>`;
    } else if (p.batteryCapacity || p.motorPower) {
      specsOrPriceHTML = `
        <div class="card__price">
          ${p.batteryCapacity ? `<p class="card__plabel">${p.batteryCapacity}</p>` : ''}
          ${p.motorPower ? `<p class="card__note">${p.motorPower}</p>` : ''}
        </div>`;
    }

    const detailUrl = `${baseUrl}/product-detail?slug=${encodeURIComponent(p.slug)}`;
    const testRideUrl = `${baseUrl}/index#test-ride`;

    return `
<article class="card is-in" style="--c:${p.color}" data-id="${p.id}">
  <div class="card__media">
    ${badgeHTML}
    <img class="card__img" src="${p.image}" alt="${p.name}" loading="lazy">
    <i class="card__shine"></i>
  </div>
  <div class="card__body">
    ${ratingHTML}
    <h3 class="card__name">${p.name}</h3>
    ${chipsHTML}
    ${colorsHTML}
    ${specsOrPriceHTML}
    <div class="card__acts">
      <a class="btn btn--ghost" href="${detailUrl}">Explore</a>
      <a class="btn btn--ink" href="${testRideUrl}"><span>Test Ride</span><i data-lucide="bike"></i></a>
    </div>
  </div>
</article>`;
  }

  function renderGrid() {
    const list = filtered();
    const grid = $('#plGrid');
    const empty = $('#plEmpty');
    const count = $('#plCount');

    if (!list.length) {
      grid.innerHTML = '';
      empty.hidden = false;
      count.textContent = 'No models found';
    } else {
      empty.hidden = true;
      grid.innerHTML = list.map(cardHTML).join('');
      count.textContent = list.length === CATALOG.length
        ? `Showing all ${list.length} models`
        : `Showing ${list.length} of ${CATALOG.length} models`;
      if (window.lucide) lucide.createIcons({ nodes: grid.querySelectorAll('[data-lucide]') });
    }
    renderChips();
    updateBadge();
  }

  function activeFilterCount() {
    let n = 0;
    if (state.q) n++;
    if (state.priceMin != null || state.priceMax != null) n++;
    if (state.rangeMin != null || state.rangeMax != null) n++;
    n += state.series.length + state.battery.length + state.speed.length;
    return n;
  }

  function updateBadge() {
    const b = $('#plFilterBadge');
    const n = activeFilterCount();
    if (n) { b.hidden = false; b.textContent = String(n); }
    else b.hidden = true;
  }

  function renderChips() {
    const wrap = $('#plChips');
    const chips = [];
    if (state.q) chips.push({ key: 'q', label: `“${state.q}”` });
    if (state.priceMin != null || state.priceMax != null) {
      const a = state.priceMin != null ? `₹${state.priceMin.toLocaleString('en-IN')}` : '0';
      const b = state.priceMax != null ? `₹${state.priceMax.toLocaleString('en-IN')}` : '∞';
      chips.push({ key: 'price', label: `Price ${a}–${b}` });
    }
    if (state.rangeMin != null || state.rangeMax != null) {
      chips.push({ key: 'range', label: `Range ${state.rangeMin ?? 0}–${state.rangeMax ?? '∞'} km` });
    }
    state.series.forEach(s => chips.push({ key: 'series', value: s, label: s }));
    state.battery.forEach(s => chips.push({ key: 'battery', value: s, label: s === 'li-ion' ? 'Li-ion / Lithium' : s }));
    state.speed.forEach(s => chips.push({ key: 'speed', value: s, label: s === 'high' ? 'High speed' : 'City' }));

    if (!chips.length) { wrap.hidden = true; wrap.innerHTML = ''; return; }
    wrap.hidden = false;
    wrap.innerHTML = chips.map(c =>
      `<button type="button" class="pl-chip" data-chip-key="${c.key}" data-chip-value="${c.value || ''}">${c.label} <i data-lucide="x"></i></button>`
    ).join('') + `<button type="button" class="pl-chip pl-chip--clear" data-chip-key="all">Clear all</button>`;
    if (window.lucide) lucide.createIcons({ nodes: wrap.querySelectorAll('[data-lucide]') });
  }

  function syncControlsFromState() {
    $('#plSearch').value = state.q;
    $('#plSearchClear').hidden = !state.q;
    $('#plSort').value = state.sort;
    $('#priceMin').value = state.priceMin ?? '';
    $('#priceMax').value = state.priceMax ?? '';
    if (state.priceMax != null) $('#priceMaxSlider').value = state.priceMax;
    $('#rangeMin').value = state.rangeMin ?? '';
    $('#rangeMax').value = state.rangeMax ?? '';

    $$('input[name="battery"]').forEach(el => { el.checked = state.battery.includes(el.value); });
    $$('input[name="speed"]').forEach(el => { el.checked = state.speed.includes(el.value); });
    $$('#filterSeries input').forEach(el => { el.checked = state.series.includes(el.value); });
  }

  function readControlsToState() {
    state.q = $('#plSearch').value.trim();
    state.sort = $('#plSort').value;
    state.priceMin = numOrNull($('#priceMin').value);
    state.priceMax = numOrNull($('#priceMax').value);
    state.rangeMin = numOrNull($('#rangeMin').value);
    state.rangeMax = numOrNull($('#rangeMax').value);
    state.battery = $$('input[name="battery"]:checked').map(el => el.value);
    state.speed = $$('input[name="speed"]:checked').map(el => el.value);
    state.series = $$('#filterSeries input:checked').map(el => el.value);
  }

  function apply({ push = false } = {}) {
    readControlsToState();
    writeURL(!push);
    renderGrid();
    $('#plSearchClear').hidden = !state.q;
  }

  function clearAll() {
    state.q = ''; state.priceMin = null; state.priceMax = null; state.rangeMin = null; state.rangeMax = null;
    state.series = []; state.battery = []; state.speed = []; state.sort = 'featured';
    syncControlsFromState();
    writeURL(true);
    renderGrid();
    $('#plSuggest').hidden = true;
  }

  function removeChip(key, value) {
    if (key === 'all') return clearAll();
    if (key === 'q') state.q = '';
    if (key === 'price') { state.priceMin = null; state.priceMax = null; }
    if (key === 'range') { state.rangeMin = null; state.rangeMax = null; }
    if (key === 'series') state.series = state.series.filter(s => s !== value);
    if (key === 'battery') state.battery = state.battery.filter(s => s !== value);
    if (key === 'speed') state.speed = state.speed.filter(s => s !== value);
    syncControlsFromState();
    writeURL(true);
    renderGrid();
  }

  function renderSuggest(q) {
    const box = $('#plSuggest');
    if (!q || q.length < 1) { box.hidden = true; box.innerHTML = ''; return; }
    const tokens = q.toLowerCase().split(/\s+/).filter(Boolean);
    const hits = CATALOG.filter(item => {
      const hay = [item.name, item.series, item.company, item.modelCode || '', ...(item.tags || [])].join(' ').toLowerCase();
      return tokens.every(t => hay.includes(t));
    }).slice(0, 6);
    if (!hits.length) {
      box.innerHTML = `<div class="pl-suggest__empty">No matches for “${q}”</div>`;
      box.hidden = false;
      return;
    }
    box.innerHTML = hits.map(h => {
      const sub = [];
      if (h.rangeKm > 0) sub.push(`${h.rangeKm} km`);
      if (h.topSpeedKmph > 0) sub.push(`${h.topSpeedKmph} km/h`);
      if (h.priceFrom && h.priceFrom > 0) sub.push(`from ₹${h.priceFrom.toLocaleString('en-IN')}`);
      const meta = sub.join(' · ') || (h.series || '');
      return `
      <button type="button" class="pl-suggest__row" data-slug="${h.slug}" role="option">
        <img src="${h.image}" alt="">
        <span><b>${h.name}</b>${meta ? `<small>${meta}</small>` : ''}</span>
      </button>`;
    }).join('');
    box.hidden = false;
  }

  function buildSeriesFilters() {
    const series = [...new Set(CATALOG.map(c => c.series).filter(Boolean))];
    const filterEl = $('#filterSeries');
    const group = $('#seriesFilterGroup');
    if (!filterEl) return;
    if (!series.length) {
      if (group) group.hidden = true;
      return;
    }
    if (group) group.hidden = false;
    filterEl.innerHTML = series.map(s => `<label><input type="checkbox" name="series" value="${s}"> ${s}</label>`).join('');
  }

  function syncPriceFilterVisibility() {
    const hasPrices = CATALOG.some(c => c.priceFrom != null && c.priceFrom > 0);
    const priceFg = $('#priceFilterGroup');
    if (priceFg) {
      priceFg.hidden = !hasPrices;
    }
    $$('.sort-opt-price').forEach(opt => {
      opt.hidden = !hasPrices;
    });
  }

  function detectBattery(str) {
    if (!str) return [];
    const s = str.toLowerCase();
    const res = [];
    if (s.includes('lithium') || s.includes('li-ion') || s.includes('nmc')) res.push('li-ion');
    if (s.includes('graphene')) res.push('graphene');
    return res;
  }

  function mapApiProduct(p) {
    const rangeKm = Number(p.range_km ?? p.rangeKm ?? 0);
    const topSpeed = Number(p.top_speed_kmph ?? p.topSpeedKmph ?? 0);
    const rating = Number(p.rating ?? 0);
    const warranty = Number(p.warranty_years ?? p.warrantyYears ?? 0);
    const batteryCap = p.battery_capacity || p.batteryCapacity || '';
    const motor = p.motor_power || p.motorPower || '';

    const chips = [];
    if (rangeKm > 0) chips.push(`${rangeKm} km Range`);
    if (topSpeed > 0) chips.push(topSpeed >= 55 ? 'High Speed' : 'City Speed');
    if (batteryCap) chips.push(batteryCap);

    const colors = (p.colors || []).map(c => ({
      name: c.name || '',
      hex: c.argb ? '#' + (Number(c.argb) & 0xFFFFFF).toString(16).padStart(6, '0') : '#1a1a1a',
      image: (c.imageUrls && c.imageUrls[0]) || ''
    }));

    const primaryColor = colors[0]?.hex || '#1a1a1a';
    const primaryImg = colors[0]?.image || p.image || p.heroImage || `${baseUrl}/assets/scutie_light.webp`;

    return {
      id: p.id,
      slug: p.slug || p.id,
      name: p.name || 'Scooter',
      series: p.brand || p.series || '',
      company: p.brand || 'Hazra',
      modelCode: p.model_code || p.modelCode || '',
      rangeKm: rangeKm,
      topSpeedKmph: topSpeed,
      speed: topSpeed >= 55 ? 'high' : 'city',
      battery: detectBattery(batteryCap),
      batteryCapacity: batteryCap,
      motorPower: motor,
      priceFrom: p.price_from ? Number(p.price_from) : null,
      priceTo: p.price_to ? Number(p.price_to) : null,
      rating: rating,
      reviews: Number(p.reviews || 0),
      color: primaryColor,
      colors: colors,
      badge: warranty > 0 ? `${warranty} Year Warranty` : '',
      chips: chips,
      variants: Array.isArray(p.variants) ? p.variants : [],
      image: primaryImg,
      tags: [p.name, p.brand, p.series, batteryCap, motor].filter(Boolean)
    };
  }

  function bind() {
    let t = null;
    $('#plSearch').addEventListener('input', () => {
      clearTimeout(t);
      t = setTimeout(() => {
        state.q = $('#plSearch').value.trim();
        renderSuggest(state.q);
        apply();
      }, 160);
    });
    $('#plSearch').addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        $('#plSuggest').hidden = true;
        $('#plSearch').blur();
      }
    });
    $('#plSearchClear').addEventListener('click', () => {
      state.q = ''; $('#plSearch').value = ''; $('#plSuggest').hidden = true; apply();
    });
    $('#plSuggest').addEventListener('click', e => {
      const row = e.target.closest('[data-slug]');
      if (!row) return;
      location.href = `${baseUrl}/product-detail?slug=${row.dataset.slug}`;
    });
    document.addEventListener('click', e => {
      if (!$('#plSearchWrap').contains(e.target)) $('#plSuggest').hidden = true;
    });

    ['priceMin', 'priceMax', 'rangeMin', 'rangeMax'].forEach(id => {
      const el = $(`#${id}`);
      if (el) el.addEventListener('change', () => apply());
    });
    $('#priceMaxSlider')?.addEventListener('input', e => { 
      const pMax = $('#priceMax');
      if (pMax) pMax.value = e.target.value; 
    });
    $('#priceMaxSlider')?.addEventListener('change', () => apply());

    $$('[data-group="rangePreset"] button').forEach(btn => {
      btn.addEventListener('click', () => {
        state.rangeMin = Number(btn.dataset.rmin);
        state.rangeMax = Number(btn.dataset.rmax);
        syncControlsFromState(); writeURL(true); renderGrid();
      });
    });

    $('#filterSeries').addEventListener('change', () => apply());
    $$('input[name="battery"], input[name="speed"]').forEach(el => el.addEventListener('change', () => apply()));
    $('#plSort').addEventListener('change', () => apply());

    $('#plClearAll').addEventListener('click', clearAll);
    $('#plEmptyClear').addEventListener('click', clearAll);
    $('#plChips').addEventListener('click', e => {
      const chip = e.target.closest('[data-chip-key]');
      if (!chip) return;
      removeChip(chip.dataset.chipKey, chip.dataset.chipValue);
    });

    // Swatch click on card delegation
    $('#plGrid')?.addEventListener('click', e => {
      const sw = e.target.closest('.sw');
      if (sw) {
        const card = sw.closest('.card');
        const group = sw.closest('.card__colors');
        if (group && card) {
          group.querySelectorAll('.sw').forEach(o => o.classList.remove('is-on'));
          sw.classList.add('is-on');
          const colorVal = sw.style.getPropertyValue('--c').trim();
          if (colorVal) card.style.setProperty('--c', colorVal);
          if (sw.dataset.img) {
            const img = card.querySelector('.card__img');
            if (img) img.src = sw.dataset.img;
          }
        }
      }
    });

    const backdrop = $('#plFiltersBackdrop');
    const setFiltersOpen = (open) => {
      $('#plFilters').classList.toggle('is-open', open);
      $('#plFilterToggle').setAttribute('aria-expanded', open ? 'true' : 'false');
      if (backdrop) {
        backdrop.hidden = !open;
        backdrop.classList.toggle('is-on', open);
      }
      document.body.style.overflow = open && window.innerWidth <= 960 ? 'hidden' : '';
    };
    $('#plFilterToggle').addEventListener('click', () => setFiltersOpen(!$('#plFilters').classList.contains('is-open')));
    backdrop?.addEventListener('click', () => setFiltersOpen(false));

    $$('[data-filter-speed]').forEach(btn => {
      btn.addEventListener('click', () => {
        state.speed = [btn.dataset.filterSpeed];
        syncControlsFromState(); writeURL(true); renderGrid();
        $('#plFilters').classList.add('is-open');
      });
    });

    window.addEventListener('popstate', () => {
      readURL(); syncControlsFromState(); renderGrid();
    });
  }

  buildSeriesFilters();
  syncPriceFilterVisibility();
  readURL();
  syncControlsFromState();
  bind();
  renderGrid();

  // Dynamically refresh live products from API if available
  fetch(baseUrl + '/api/v1/website/products')
    .then(r => r.json())
    .then(res => {
      if (res.data && Array.isArray(res.data) && res.data.length > 0) {
        const liveProducts = res.data.map(mapApiProduct);
        CATALOG.length = 0;
        CATALOG.push(...liveProducts);
        buildSeriesFilters();
        syncPriceFilterVisibility();
        renderGrid();
      }
    })
    .catch(() => {});

  if (window.lucide) lucide.createIcons();
});
</script>
<!-- ========== UNIQUE PAGE CONTENT END ========== -->

<?php App::render('footer'); ?>
