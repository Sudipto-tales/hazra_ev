<?php
App::render('head', [
    'pageTitle'       => 'Dealer Locator — Hazra Electrical Bike',
    'pageDescription' => 'Find the nearest Hazra dealer, book a test ride, and start your electric journey today.',
    'extraCss'        => [
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
        'assets/css/styles/pages/dealer-locator.css'
    ],
]);

App::render('header', ['isStickyOnly' => true]);
?>

<!-- ========== UNIQUE PAGE CONTENT START ========== -->
<section class="dl-hero">
  <div class="dl-hero__in">
    <p class="dl-hero__tag">Dealer Network</p>
    <h1 class="dl-hero__title">Find Your Nearest<br>Hazra Dealer</h1>
    <p class="dl-hero__lead">Experience the ride before you buy. Locate authorized dealers across India, book a test ride, and start your electric journey.</p>
    <div class="dl-stats" id="stats">
      <div class="dl-stat"><div class="dl-stat__num" data-target="150">0</div><div class="dl-stat__label">Dealers</div></div>
      <div class="dl-stat"><div class="dl-stat__num" data-target="80">0</div><div class="dl-stat__label">Cities</div></div>
      <div class="dl-stat"><div class="dl-stat__num" data-target="25">0</div><div class="dl-stat__label">States</div></div>
      <div class="dl-stat"><div class="dl-stat__num" data-target="50">0</div><div class="dl-stat__label">k+ Riders</div></div>
    </div>
  </div>
</section>

<section class="dl-collage">
  <div class="dl-collage__in">
    <div class="dl-collage__head"><h2 class="dl-collage__title">Our showrooms &amp; community</h2></div>
    <div class="dl-collage__grid">
      <div class="dl-collage__item"><img src="https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800&q=80" alt="EV showroom" loading="lazy"></div>
      <div class="dl-collage__item"><img src="https://images.unsplash.com/photo-1571068316344-75bc76f77890?w=600&q=80" alt="Electric scooter" loading="lazy"></div>
      <div class="dl-collage__item"><img src="https://images.unsplash.com/photo-1593941707882-a5bba14938c7?w=600&q=80" alt="City charging" loading="lazy"></div>
      <div class="dl-collage__item"><img src="https://images.unsplash.com/photo-1558981403-c5f9899a28bc?w=600&q=80" alt="Rider on road" loading="lazy"></div>
      <div class="dl-collage__item"><img src="https://images.unsplash.com/photo-1568605117035-3bdf3e3f0f0a?w=600&q=80" alt="Modern dealership" loading="lazy"></div>
    </div>
  </div>
</section>

<section class="dl-locator">
  <div class="dl-locator__in">
    <div class="dl-locator__head">
      <p class="dl-locator__eyebrow">Locate</p>
      <h2 class="dl-locator__title">Dealers near you</h2>
    </div>
    <div class="dl-shell">
      <aside class="dl-panel">
        <h3 class="dl-panel__title"><i data-lucide="sliders-horizontal"></i> Filters</h3>
        <div class="dl-group">
          <span class="dl-group__label">State</span>
          <div class="dl-field"><select id="stateSelect"><option value="">All states</option></select></div>
        </div>
        <div class="dl-group">
          <span class="dl-group__label">District</span>
          <div class="dl-field"><select id="districtSelect" disabled><option value="">All districts</option></select></div>
        </div>
        <div class="dl-group">
          <span class="dl-group__label">Dealer type</span>
          <div class="dl-chips" id="typeChips">
            <button class="dl-chip is-on" data-type="all">All</button>
            <button class="dl-chip" data-type="showroom">Showroom</button>
            <button class="dl-chip" data-type="service">Service</button>
          </div>
        </div>
        <div class="dl-stats-box" id="stateStats">
          <div class="dl-stats-box__item"><div class="dl-stats-box__num" id="statDealers">0</div><div class="dl-stats-box__label">Dealers</div></div>
          <div class="dl-stats-box__item"><div class="dl-stats-box__num" id="statCities">0</div><div class="dl-stats-box__label">Cities</div></div>
        </div>
        <button class="dl-apply" id="applyFilter">Apply Filters</button>
      </aside>
      <div class="dl-map-area">
        <div id="map"></div>
        <div class="dl-float-cards" id="floatCards"></div>
      </div>
    </div>
  </div>
</section>

<section class="dl-news" id="dealer-news">
  <div class="dl-news__in">
    <div class="dl-news__head">
      <div>
        <p class="dl-news__eyebrow">Dealer News</p>
        <h2 class="dl-news__title">Updates from the network</h2>
      </div>
      <a class="dl-news__link" href="<?= e(base_url('blog')) ?>">View all <i data-lucide="arrow-up-right"></i></a>
    </div>
    <div class="dl-posts">
      <a class="dl-post" href="<?= e(base_url('blog')) ?>">
        <div class="dl-post__media"><img src="https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=600&q=80" alt="" loading="lazy"></div>
        <div class="dl-post__body">
          <span class="dl-post__cat">Expansion</span>
          <h3 class="dl-post__t">New showrooms open across Maharashtra &amp; Karnataka</h3>
          <p class="dl-post__d">Eight new touchpoints this quarter, bringing test rides and service closer to riders in Pune, Thane and Bengaluru.</p>
          <span class="dl-post__go">Read <i data-lucide="arrow-up-right"></i></span>
        </div>
      </a>
      <a class="dl-post" href="<?= e(base_url('blog')) ?>">
        <div class="dl-post__media"><img src="https://images.unsplash.com/photo-1571068316344-75bc76f77890?w=600&q=80" alt="" loading="lazy"></div>
        <div class="dl-post__body">
          <span class="dl-post__cat">Partnership</span>
          <h3 class="dl-post__t">Why EV dealerships are a high-growth local business</h3>
          <p class="dl-post__d">Demand signals, service revenue and local trust — what actually makes a two-wheeler EV counter work.</p>
          <span class="dl-post__go">Read <i data-lucide="arrow-up-right"></i></span>
        </div>
      </a>
      <a class="dl-post" href="<?= e(base_url('become-a-dealer')) ?>">
        <div class="dl-post__media"><img src="https://images.unsplash.com/photo-1568605117035-3bdf3e3f0f0a?w=600&q=80" alt="" loading="lazy"></div>
        <div class="dl-post__body">
          <span class="dl-post__cat">Opportunity</span>
          <h3 class="dl-post__t">Become a Hazra dealer — applications open for Q4</h3>
          <p class="dl-post__d">Join a network built on transparent range figures, strong service support and real rider demand.</p>
          <span class="dl-post__go">Apply <i data-lucide="arrow-up-right"></i></span>
        </div>
      </a>
    </div>
  </div>
</section>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();

  function animateCounters() {
    document.querySelectorAll('.dl-stat__num').forEach(el => {
      const target = +el.dataset.target;
      const start = performance.now();
      const duration = 1600;
      function tick(now) {
        const p = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - p, 3);
        const v = Math.floor(eased * target);
        el.textContent = v + (target === 50 ? '' : '+');
        if (p < 1) requestAnimationFrame(tick);
        else el.textContent = target + (target === 50 ? '' : '+');
      }
      requestAnimationFrame(tick);
    });
  }
  const statsEl = document.getElementById('stats');
  if (statsEl) {
    const obs = new IntersectionObserver((ents) => {
      if (ents[0].isIntersecting) { animateCounters(); obs.disconnect(); }
    }, { threshold: 0.35 });
    obs.observe(statsEl);
  }

  const dealersData = [
    { id:1, name:"Hazra Delhi NCR", state:"Delhi", district:"New Delhi", city:"New Delhi", address:"Connaught Place, New Delhi 110001", phone:"+91 98290 16542", lat:28.6315, lng:77.2167, type:"showroom", img:"https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=500&q=80" },
    { id:2, name:"Hazra Gurgaon", state:"Haryana", district:"Gurugram", city:"Gurugram", address:"Sector 29, Gurugram 122001", phone:"+91 98123 45678", lat:28.4595, lng:77.0266, type:"showroom", img:"https://images.unsplash.com/photo-1568605117035-3bdf3e3f0f0a?w=500&q=80" },
    { id:3, name:"Hazra Noida", state:"Uttar Pradesh", district:"Gautam Buddha Nagar", city:"Noida", address:"Sector 18, Noida 201301", phone:"+91 98765 43210", lat:28.5700, lng:77.3200, type:"service", img:"https://images.unsplash.com/photo-1593941707882-a5bba14938c7?w=500&q=80" },
    { id:4, name:"Hazra Mumbai Central", state:"Maharashtra", district:"Mumbai", city:"Mumbai", address:"Lower Parel, Mumbai 400013", phone:"+91 90012 34567", lat:18.9940, lng:72.8250, type:"showroom", img:"https://images.unsplash.com/photo-1571068316344-75bc76f77890?w=500&q=80" },
    { id:5, name:"Hazra Thane", state:"Maharashtra", district:"Thane", city:"Thane", address:"Ghodbunder Road, Thane 400607", phone:"+91 91234 56789", lat:19.2183, lng:72.9781, type:"service", img:"https://images.unsplash.com/photo-1558981403-c5f9899a28bc?w=500&q=80" },
    { id:6, name:"Hazra Pune", state:"Maharashtra", district:"Pune", city:"Pune", address:"Hinjewadi Phase 1, Pune 411057", phone:"+91 78901 23456", lat:18.5912, lng:73.7389, type:"showroom", img:"https://images.unsplash.com/photo-1568605117035-3bdf3e3f0f0a?w=500&q=80" },
    { id:7, name:"Hazra Bangalore", state:"Karnataka", district:"Bengaluru Urban", city:"Bengaluru", address:"Whitefield, Bengaluru 560066", phone:"+91 80123 45678", lat:12.9698, lng:77.7500, type:"showroom", img:"https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=500&q=80" },
    { id:8, name:"Hazra Electronic City", state:"Karnataka", district:"Bengaluru Urban", city:"Bengaluru", address:"Electronic City Phase 2, Bengaluru 560100", phone:"+91 87654 32109", lat:12.8399, lng:77.6770, type:"service", img:"https://images.unsplash.com/photo-1593941707882-a5bba14938c7?w=500&q=80" },
    { id:9, name:"Hazra Jaipur", state:"Rajasthan", district:"Jaipur", city:"Jaipur", address:"C-Scheme, Jaipur 302001", phone:"+91 56789 01234", lat:26.9124, lng:75.7873, type:"showroom", img:"https://images.unsplash.com/photo-1571068316344-75bc76f77890?w=500&q=80" },
    { id:10, name:"Hazra Kolkata", state:"West Bengal", district:"Kolkata", city:"Kolkata", address:"Salt Lake Sector V, Kolkata 700091", phone:"+91 67890 12345", lat:22.5726, lng:88.3639, type:"showroom", img:"https://images.unsplash.com/photo-1558981403-c5f9899a28bc?w=500&q=80" },
    { id:11, name:"Hazra Hyderabad", state:"Telangana", district:"Hyderabad", city:"Hyderabad", address:"Hitech City, Hyderabad 500081", phone:"+91 99887 76655", lat:17.4435, lng:78.3772, type:"showroom", img:"https://images.unsplash.com/photo-1568605117035-3bdf3e3f0f0a?w=500&q=80" },
    { id:12, name:"Hazra Ahmedabad", state:"Gujarat", district:"Ahmedabad", city:"Ahmedabad", address:"SG Highway, Ahmedabad 380054", phone:"+91 88776 65544", lat:23.0225, lng:72.5714, type:"service", img:"https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=500&q=80" },
    { id:13, name:"Hazra Chandigarh", state:"Chandigarh", district:"Chandigarh", city:"Chandigarh", address:"Sector 17, Chandigarh 160017", phone:"+91 77665 54433", lat:30.7415, lng:76.7681, type:"showroom", img:"https://images.unsplash.com/photo-1593941707882-a5bba14938c7?w=500&q=80" },
    { id:14, name:"Hazra Lucknow", state:"Uttar Pradesh", district:"Lucknow", city:"Lucknow", address:"Hazratganj, Lucknow 226001", phone:"+91 66554 43322", lat:26.8467, lng:80.9462, type:"service", img:"https://images.unsplash.com/photo-1571068316344-75bc76f77890?w=500&q=80" },
    { id:15, name:"Hazra Indore", state:"Madhya Pradesh", district:"Indore", city:"Indore", address:"Vijay Nagar, Indore 452010", phone:"+91 55443 32211", lat:22.7196, lng:75.8577, type:"showroom", img:"https://images.unsplash.com/photo-1558981403-c5f9899a28bc?w=500&q=80" }
  ];

  const stateMap = {};
  dealersData.forEach(d => { if (!stateMap[d.state]) stateMap[d.state] = new Set(); stateMap[d.state].add(d.district); });
  const stateSelect = document.getElementById('stateSelect');
  const districtSelect = document.getElementById('districtSelect');
  const stateStats = document.getElementById('stateStats');
  const floatCards = document.getElementById('floatCards');
  let currentType = 'all';

  if (stateSelect) {
    Object.keys(stateMap).sort().forEach(s => { const o = document.createElement('option'); o.value = s; o.textContent = s; stateSelect.appendChild(o); });
    stateSelect.addEventListener('change', () => {
      const state = stateSelect.value;
      districtSelect.innerHTML = '<option value="">All districts</option>';
      districtSelect.disabled = !state;
      if (state) {
        [...stateMap[state]].sort().forEach(d => { const o = document.createElement('option'); o.value = d; o.textContent = d; districtSelect.appendChild(o); });
        const inState = dealersData.filter(d => d.state === state);
        document.getElementById('statDealers').textContent = inState.length;
        document.getElementById('statCities').textContent = new Set(inState.map(d => d.city)).size;
        stateStats?.classList.add('is-visible');
      } else stateStats?.classList.remove('is-visible');
    });
  }

  document.getElementById('typeChips')?.addEventListener('click', e => {
    const chip = e.target.closest('.dl-chip');
    if (!chip) return;
    document.querySelectorAll('.dl-chip').forEach(c => c.classList.remove('is-on'));
    chip.classList.add('is-on');
    currentType = chip.dataset.type;
  });

  const mapEl = document.getElementById('map');
  if (mapEl && window.L) {
    const map = L.map('map', { center: [22.5, 78.5], zoom: 5, zoomControl: true, scrollWheelZoom: false });
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
      attribution: '&copy; OpenStreetMap &copy; CARTO', subdomains: 'abcd', maxZoom: 19
    }).addTo(map);

    let markers = [];
    function clearMarkers() { markers.forEach(m => map.removeLayer(m)); markers = []; }
    function addMarkers(list) {
      clearMarkers();
      list.forEach(d => {
        const icon = L.divIcon({ className: '', html: '<div class="dl-pin"></div>', iconSize: [26, 26], iconAnchor: [13, 26] });
        const m = L.marker([d.lat, d.lng], { icon }).addTo(map).bindPopup('<strong>' + d.name + '</strong><br>' + d.address + '<br>' + d.phone);
        m.on('click', () => highlightCard(d.id));
        markers.push(m);
      });
      if (list.length === 1) map.setView([list[0].lat, list[0].lng], 12);
      else if (list.length > 1) { const g = L.featureGroup(markers); map.fitBounds(g.getBounds().pad(0.12)); }
      else map.setView([22.5, 78.5], 5);
    }
    function renderFloatCards(list) {
      if (!floatCards) return;
      floatCards.innerHTML = '';
      if (!list.length) { floatCards.innerHTML = '<div style="padding:20px;color:var(--ink-soft-0);font-size:14px;">No dealers match your filters.</div>'; return; }
      list.forEach(d => {
        const card = document.createElement('article');
        card.className = 'dl-fcard';
        card.dataset.id = d.id;
        card.innerHTML = '<div class="dl-fcard__img"><img src="' + d.img + '" alt="' + d.name + '" loading="lazy"><span class="dl-fcard__badge">' + d.city + '</span></div><div class="dl-fcard__body"><h4 class="dl-fcard__name">' + d.name + '</h4><div class="dl-fcard__meta"><span><i data-lucide="map-pin"></i>' + d.address + '</span><span><i data-lucide="phone"></i>' + d.phone + '</span></div><button class="dl-fcard__cta">Book Test Ride <i data-lucide="arrow-right"></i></button></div>';
        card.addEventListener('click', () => { map.setView([d.lat, d.lng], 13); highlightCard(d.id); });
        floatCards.appendChild(card);
      });
      if (window.lucide) lucide.createIcons();
    }
    function highlightCard(id) {
      document.querySelectorAll('.dl-fcard').forEach(c => c.classList.toggle('is-active', +c.dataset.id === id));
      const active = document.querySelector('.dl-fcard[data-id="' + id + '"]');
      if (active) active.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
    }
    function applyFilters() {
      const state = stateSelect.value;
      const district = districtSelect.value;
      let list = dealersData;
      if (state) list = list.filter(d => d.state === state);
      if (district) list = list.filter(d => d.district === district);
      if (currentType !== 'all') list = list.filter(d => d.type === currentType);
      renderFloatCards(list);
      addMarkers(list);
    }
    document.getElementById('applyFilter')?.addEventListener('click', applyFilters);
    districtSelect?.addEventListener('change', applyFilters);
    renderFloatCards(dealersData);
    addMarkers(dealersData);
  }
});
</script>
<!-- ========== UNIQUE PAGE CONTENT END ========== -->

<?php App::render('footer'); ?>
