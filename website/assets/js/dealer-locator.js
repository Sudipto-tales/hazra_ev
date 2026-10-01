document.addEventListener('DOMContentLoaded', async () => {
    'use strict';
    const $ = (id) => document.getElementById(id);
    const config = window.HAZRA_DEALER_MAP;
    const state = $('stateSelect'), district = $('districtSelect'), cards = $('floatCards');
    let dealers = [], currentType = 'all', map = null, markers = new Map(), userPosition = null, userMarker = null;
    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
    const directions = (d) => `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(d.lat + ',' + d.lng)}`;
    function selectDealer(d, scroll = false) {
        document.querySelectorAll('.dl-fcard').forEach((card) => card.classList.toggle('is-active', card.dataset.id === d.id));
        const marker = markers.get(d.id);
        if (marker) { map.setView([d.lat, d.lng], 14); map.panBy([0, 110], {animate:false}); marker.openPopup(); }
        if (scroll) [...cards.children].find((c) => c.dataset.id === d.id)?.scrollIntoView({ behavior:'smooth', block:'nearest', inline:'nearest' });
    }
    const carouselMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
    $('dealerPrevious').addEventListener('click', () => cards.scrollBy({left:-(cards.querySelector('.dl-fcard')?.offsetWidth + 14 || 294), behavior:carouselMotion()}));
    $('dealerNext').addEventListener('click', () => cards.scrollBy({left:cards.querySelector('.dl-fcard')?.offsetWidth + 14 || 294, behavior:carouselMotion()}));
    function updateCarousel() {
        $('dealerPrevious').disabled = cards.scrollLeft <= 1;
        $('dealerNext').disabled = cards.scrollLeft + cards.clientWidth >= cards.scrollWidth - 1;
    }
    cards.addEventListener('scroll', updateCarousel, {passive:true});
    window.addEventListener('resize', updateCarousel);
    function distance(d) {
        const rad = (n) => n * Math.PI / 180;
        const a = Math.sin(rad(d.lat - userPosition.lat) / 2) ** 2 + Math.cos(rad(userPosition.lat)) * Math.cos(rad(d.lat)) * Math.sin(rad(d.lng - userPosition.lng) / 2) ** 2;
        return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }
    function applyFilters() {
        const query = $('dealerSearch').value.trim().toLowerCase();
        const list = dealers.filter((d) => (!state.value || d.state === state.value)
            && (!district.value || d.district === district.value)
            && (currentType === 'all' || d.type === currentType || d.type === 'both')
            && [d.name, d.city, d.district, d.address, d.pincode].join(' ').toLowerCase().includes(query));
        if (userPosition) list.sort((a, b) => distance(a) - distance(b));
        $('resultCount').textContent = `${list.length} ${list.length === 1 ? 'location' : 'locations'} found`;
        $('statDealers').textContent = list.length;
        $('statCities').textContent = new Set(list.map((d) => d.city)).size;
        $('stateStats').classList.add('is-visible');
        cards.replaceChildren();
        markers.forEach((m) => m.remove()); markers = new Map();
        if (!list.length) {
            const p = document.createElement('p'); p.className = 'dl-empty';
            p.textContent = dealers.length ? 'No dealers match your filters. Try another area or reset the filters.' : 'Dealer locations will appear here once published.';
            cards.append(p);
        }
        list.forEach((d) => {
            const card = document.createElement('article'); card.className = 'dl-fcard'; card.dataset.id = d.id;
            const km = userPosition ? `<span>${distance(d).toFixed(1)} km away in a straight line</span>` : '';
            card.innerHTML = `<div class="dl-fcard__body"><button type="button" class="dl-select-dealer">${esc(d.name)}</button>
                <div class="dl-fcard__meta"><span>${esc(d.city)} · ${d.type === 'both' ? 'Showroom & service' : d.type === 'service' ? 'Service centre' : 'Showroom'}</span>
                <span>${esc(d.address)} · ${esc(d.pincode)}</span>${d.hours ? `<span>${esc(d.hours)}</span>` : ''}${km}</div>
                <div class="dl-card-actions"><a href="tel:${esc(d.phone.replace(/[^+0-9]/g, ''))}" class="dl-fcard__cta">Call dealer</a>
                <a href="${directions(d)}" target="_blank" rel="noopener" class="dl-fcard__cta">Get directions</a></div></div>`;
            card.querySelector('button').addEventListener('click', () => selectDealer(d)); cards.append(card);
            if (map) {
                const icon = L.divIcon({ className:'', html:'<div class="dl-pin"></div>', iconSize:[26,26], iconAnchor:[13,26] });
                const marker = L.marker([d.lat, d.lng], { icon, title:d.name }).addTo(map);
                const popup = document.createElement('div');
                popup.innerHTML = `<strong>${esc(d.name)}</strong><p>${esc(d.address)}</p><a href="${directions(d)}" target="_blank" rel="noopener">Get directions</a>`;
                marker.bindPopup(popup); marker.on('click', () => selectDealer(d, true)); markers.set(d.id, marker);
            }
        });
        cards.scrollLeft = 0; requestAnimationFrame(updateCarousel);
        if (map) {
            const points = list.map((d) => [d.lat, d.lng]);
            if (userPosition) points.push([userPosition.lat, userPosition.lng]);
            if (points.length) map.fitBounds(points, { paddingTopLeft:[35,35], paddingBottomRight:[35,270], maxZoom:13 });
            else map.setView([22.5,78.5], 5);
        }
    }
    function refreshDistricts() {
        district.replaceChildren(new Option('All districts', '')); district.disabled = !state.value;
        [...new Set(dealers.filter((d) => d.state === state.value).map((d) => d.district))].sort().forEach((d) => district.add(new Option(d, d)));
        applyFilters();
    }
    if (window.L) {
        map = L.map('map', { center:[22.5,78.5], zoom:5, scrollWheelZoom:false });
        const tiles = L.tileLayer(config.tileUrl, { attribution:config.attribution, subdomains:'abcd', maxZoom:19 }).addTo(map);
        tiles.on('tileerror', () => { $('mapStatus').textContent = 'Map tiles could not load. Dealer addresses and directions remain available.'; });
    } else $('mapStatus').textContent = 'Map could not load. Use the dealer list for addresses and directions.';
    state.addEventListener('change', refreshDistricts); district.addEventListener('change', applyFilters);
    $('dealerSearch').addEventListener('input', applyFilters); $('applyFilter').addEventListener('click', applyFilters);
    $('typeChips').addEventListener('click', (e) => {
        const chip = e.target.closest('[data-type]'); if (!chip) return;
        currentType = chip.dataset.type;
        document.querySelectorAll('.dl-chip').forEach((c) => { c.classList.toggle('is-on', c === chip); c.setAttribute('aria-pressed', String(c === chip)); });
        applyFilters();
    });
    $('resetFilters').addEventListener('click', () => {
        state.value = ''; $('dealerSearch').value = ''; currentType = 'all'; userPosition = null; userMarker?.remove(); userMarker = null;
        $('locationStatus').textContent = '';
        document.querySelectorAll('.dl-chip').forEach((c) => { c.classList.toggle('is-on', c.dataset.type === 'all'); c.setAttribute('aria-pressed', String(c.dataset.type === 'all')); });
        refreshDistricts();
    });
    let locationRequest = 0;
    $('resetFilters').addEventListener('click', () => { locationRequest++; });
    $('useLocation').addEventListener('click', () => {
        if (!window.isSecureContext || !navigator.geolocation) { $('locationStatus').textContent = 'Current location requires HTTPS and browser location support.'; return; }
        const request = ++locationRequest; $('useLocation').disabled = true;
        $('locationStatus').textContent = 'Waiting for browser location permission…';
        navigator.geolocation.getCurrentPosition(({ coords }) => {
            $('useLocation').disabled = false; if (request !== locationRequest) return;
            userPosition = { lat:coords.latitude, lng:coords.longitude };
            state.value = ''; district.value = ''; $('dealerSearch').value = ''; refreshDistricts();
            userMarker?.remove();
            if (map) userMarker = L.circleMarker([userPosition.lat,userPosition.lng], { radius:7, color:'#2563eb', fillOpacity:1 }).addTo(map).bindPopup('Your current location');
            $('locationStatus').textContent = 'Dealers sorted by distance from your location.';
        }, (e) => {
            $('useLocation').disabled = false; if (request !== locationRequest) return;
            $('locationStatus').textContent = e.code === 1 ? 'Location permission denied. Search by city or PIN code instead.' : 'Could not get your location. Search by city or PIN code instead.';
        }, { enableHighAccuracy:true, timeout:15000, maximumAge:60000 });
    });
    async function load() {
        cards.textContent = 'Loading dealer locations…'; $('retryDealers').hidden = true;
        try {
            const response = await fetch(config.locationsUrl, { headers:{ Accept:'application/json' } });
            if (!response.ok) throw new Error('Could not load dealers');
            const payload = await response.json();
            if (!Array.isArray(payload.data)) throw new Error('Invalid locations response');
            dealers = payload.data.map((d) => ({ ...d, lat:Number(d.lat), lng:Number(d.lng) })).filter((d) => Number.isFinite(d.lat) && Number.isFinite(d.lng) && Math.abs(d.lat) <= 90 && Math.abs(d.lng) <= 180);
            state.replaceChildren(new Option('All states', ''));
            [...new Set(dealers.map((d) => d.state))].sort().forEach((s) => state.add(new Option(s, s)));
            const totals = [dealers.length, new Set(dealers.map((d) => d.city)).size, new Set(dealers.map((d) => d.state)).size];
            document.querySelectorAll('.dl-stat__num').forEach((el, i) => { el.textContent = totals[i]; });
            refreshDistricts();
        } catch (e) { cards.textContent = 'Dealer locations could not load. Please try again.'; $('retryDealers').hidden = false; requestAnimationFrame(updateCarousel); }
    }
    $('retryDealers').addEventListener('click', load);
    await load(); if (window.lucide) window.lucide.createIcons();
});
