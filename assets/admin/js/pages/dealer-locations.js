(function () {
    'use strict';
    const { api, util: U, layout, toast } = window.HAZRA;
    const config = window.HAZRA_DEALER_MAP;
    const endpoint = 'api/v1/admin/dealer-locations';
    const fields = ['name', 'state', 'district', 'city', 'address', 'pincode', 'phone', 'hours', 'type', 'status', 'lat', 'lng'];
    let rows = [], editing = null, map = null, pin = null, searchController = null, locationVersion = 0, busy = false;
    const $ = (id) => document.getElementById(id);
    window.HAZRA.boot(init);
    async function init() {
        $('pageHead').innerHTML = layout.pageHead({ title: 'Dealer Locations', sub: 'Add verified showrooms and service centres to the public dealer map.' });
        $('view').innerHTML = `<div class="dealer-manager">
            <article class="card"><h3>Locations</h3><button id="newDealer" type="button" class="btn btn--primary">Add location</button>
            <label><span>Find saved dealer</span><input id="dealerFilter" type="search" placeholder="Name, city or PIN code"></label><div id="dealerList" class="dealer-list"></div></article>
            <article class="card"><h3 id="editorTitle">Add location</h3>
            <label for="placeSearch"><span>Search a location</span></label>
            <div class="dealer-actions"><input id="placeSearch" type="search" placeholder="Showroom, street, city or PIN code" style="flex:1;min-width:180px">
            <button id="searchPlace" type="button" class="btn btn--ghost">Search</button>
            <button id="currentLocation" type="button" class="btn btn--ghost">Use current location</button></div>
            <div id="locationNotice" class="dealer-notice" role="status" aria-live="polite"></div>
            <div id="placeResults" class="dealer-results"></div><div id="dealerMap" aria-label="Select dealership location on the map"></div>
            <p class="dealer-hint">Search and select a result, click the map, or drag the pin to the showroom entrance. Check the address before publishing. Search data: Google Maps.</p>
            <form id="dealerForm"><div class="dealer-fields">
            ${input('name', 'Dealer name', true, 'wide')}${input('phone', 'Public phone')}
            <label><span>Location type</span><select name="type"><option value="showroom">Showroom</option><option value="service">Service centre</option><option value="both">Showroom & service</option></select></label>
            ${input('state', 'State')}${input('district', 'District')}${input('city', 'City')}${input('pincode', 'PIN code')}
            <label class="wide"><span>Full address</span><textarea name="address" rows="3" maxlength="500"></textarea></label>
            ${input('lat', 'Latitude', false, '', 'number')}${input('lng', 'Longitude', false, '', 'number')}
            ${input('hours', 'Opening hours', false, 'wide')}
            <label><span>Visibility</span><select name="status"><option value="draft">Draft</option><option value="published">Published</option><option value="inactive">Inactive</option></select></label>
            </div><p class="dealer-hint">Publishing requires complete address, phone and map coordinates. Drafts and inactive locations stay hidden from visitors.</p>
            <div class="dealer-actions"><button id="saveDealer" class="btn btn--primary" type="submit">Save location</button>
            <a id="previewDirections" class="btn btn--ghost" target="_blank" rel="noopener" hidden>Check directions</a></div>
            <div id="saveNotice" class="dealer-notice" role="status" aria-live="polite"></div></form></article></div>`;
        $('newDealer').addEventListener('click', () => edit(null));
        $('dealerFilter').addEventListener('input', renderList);
        $('searchPlace').addEventListener('click', search);
        $('placeSearch').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); search(); } });
        $('currentLocation').addEventListener('click', locate);
        $('dealerForm').addEventListener('submit', save);
        ['lat', 'lng'].forEach((f) => control(f).addEventListener('change', manualPosition));
        edit(null);
        try {
            await loadGoogle();
            map = new google.maps.Map($('dealerMap'), { center:{lat:22.57, lng:88.36}, zoom:7, scrollwheel:false, mapTypeControl:false });
            if (control('lat').value !== '' && control('lng').value !== '') position(Number(control('lat').value), Number(control('lng').value));
            map.addListener('click', (e) => { locationVersion++; position(e.latLng.lat(), e.latLng.lng()); notice('Pin selected. Enter or verify the address below.'); });
        } catch (e) { notice(e.message, true); }
        await load();
    }
    function input(name, label, required = false, className = '', type = 'text') {
        const limits = { name:160, phone:32, state:100, district:100, city:100, pincode:6, hours:255 };
        return `<label class="${className}"><span>${label}</span><input name="${name}" type="${type}" ${required ? 'required' : ''} ${type === 'number' ? `step="any" min="-${name === 'lat' ? 90 : 180}" max="${name === 'lat' ? 90 : 180}"` : `maxlength="${limits[name]}"`}></label>`;
    }
    function control(name) { return $('dealerForm').elements.namedItem(name); }
    function notice(text, error = false) { $('locationNotice').textContent = text; $('locationNotice').dataset.error = String(error); }
    async function load() {
        try { rows = (await api.get(endpoint)).data || []; renderList(); }
        catch (e) { $('dealerList').textContent = 'Could not load locations. Reload to retry.'; toast.error(e.message); }
    }
    function renderList() {
        const query = $('dealerFilter').value.trim().toLowerCase();
        const list = rows.filter((r) => [r.name, r.city, r.state, r.pincode].join(' ').toLowerCase().includes(query));
        $('dealerList').replaceChildren();
        if (!list.length) $('dealerList').textContent = 'No saved locations found.';
        list.forEach((r) => {
            const button = document.createElement('button'); button.type = 'button';
            button.setAttribute('aria-pressed', String(editing === r.id));
            button.innerHTML = `<strong>${U.esc(r.name)}</strong><small>${U.esc([r.city, r.state].filter(Boolean).join(', '))} · ${U.esc(r.status)}</small>`;
            button.addEventListener('click', () => edit(r)); $('dealerList').append(button);
        });
    }
    function edit(record) {
        if (busy) return;
        locationVersion++; searchController?.abort();
        editing = record?.id || null; $('dealerForm').reset();
        fields.forEach((f) => { control(f).value = record?.[f] ?? ({ type:'showroom', status:'draft' }[f] || ''); });
        $('editorTitle').textContent = editing ? 'Edit location' : 'Add location';
        $('placeResults').replaceChildren(); $('placeSearch').value = ''; $('saveNotice').textContent = ''; notice('');
        if (pin) { pin.setMap(null); pin = null; }
        $('previewDirections').hidden = true;
        if (record?.lat != null && record?.lng != null) position(Number(record.lat), Number(record.lng));
        else if (map) { map.setCenter({lat:22.57, lng:88.36}); map.setZoom(7); }
        renderList();
    }
    function position(lat, lng) {
        if (!Number.isFinite(lat) || !Number.isFinite(lng) || Math.abs(lat) > 90 || Math.abs(lng) > 180) return;
        control('lat').value = lat.toFixed(6); control('lng').value = lng.toFixed(6);
        if (map) {
            if (!pin) {
                pin = new google.maps.Marker({ position:{lat, lng}, draggable:true, map });
                pin.addListener('dragend', () => { locationVersion++; const p = pin.getPosition(); position(p.lat(), p.lng()); notice('Pin moved. Verify the address before saving.'); });
            } else pin.setPosition({lat, lng});
            map.setCenter({lat, lng}); map.setZoom(Math.max(map.getZoom(), 15));
        }
        $('previewDirections').href = `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(lat + ',' + lng)}`;
        $('previewDirections').hidden = false;
    }
    function manualPosition() {
        locationVersion++;
        if (control('lat').value !== '' && control('lng').value !== '' && control('lat').checkValidity() && control('lng').checkValidity()) {
            position(Number(control('lat').value), Number(control('lng').value));
        } else { pin?.setMap(null); pin = null; $('previewDirections').hidden = true; }
    }
    let googleReady;
    function loadGoogle() {
        if (window.google?.maps?.importLibrary) return Promise.resolve();
        if (!config.googleKey) return Promise.reject(new Error('Google Maps key is missing. Configure GOOGLE_MAPS_BROWSER_KEY.'));
        if (!googleReady) googleReady = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            const timer = setTimeout(() => reject(new Error('Google Maps could not load. Check your connection or enter coordinates manually.')), 15000);
            window.hazraGoogleReady = () => { clearTimeout(timer); resolve(); };
            script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(config.googleKey)}&loading=async&libraries=places&v=weekly&callback=hazraGoogleReady`;
            script.onerror = () => { clearTimeout(timer); reject(new Error('Google Maps could not load. Enter coordinates manually.')); };
            document.head.append(script);
        });
        return googleReady;
    }
    function googleFeature(place) {
        const components = place.addressComponents || [];
        const component = (...types) => types.map((t) => components.find((c) => c.types.includes(t))?.longText).find(Boolean) || '';
        return { geometry:{coordinates:[place.location.lng(), place.location.lat()]}, properties:{
            name:place.displayName || '', fullAddress:place.formattedAddress || '',
            state:component('administrative_area_level_1'), district:component('administrative_area_level_3', 'administrative_area_level_2'),
            city:component('locality', 'postal_town', 'sublocality_level_1'), postcode:component('postal_code')
        } };
    }
    async function withTimeout(promise) {
        let timer;
        try {
            return await Promise.race([promise, new Promise((_, reject) => {
                timer = setTimeout(() => reject(new DOMException('Search timed out', 'AbortError')), 15000);
            })]);
        } finally { clearTimeout(timer); }
    }
    async function geocode(path, params) {
        searchController?.abort(); searchController = new AbortController();
        const signal = searchController.signal;
        await loadGoogle();
        let results;
        if (path === 'reverse') {
            const response = await new google.maps.Geocoder().geocode({ location:{lat:params.lat, lng:params.lon}, language:'en' });
            results = response.results.slice(0, 1).map((r) => googleFeature({ location:r.geometry.location, formattedAddress:r.formatted_address,
                addressComponents:r.address_components.map((c) => ({ types:c.types, longText:c.long_name })) }));
        } else {
            const { Place } = await google.maps.importLibrary('places');
            const response = await Place.searchByText({ textQuery:params.q, fields:['displayName', 'formattedAddress', 'location', 'addressComponents'],
                language:'en', region:'in', maxResultCount:6 });
            results = (response.places || []).filter((p) => p.location).map(googleFeature);
        }
        if (signal.aborted) throw new DOMException('Search superseded', 'AbortError');
        return results;
    }
    function label(feature) {
        const p = feature.properties || {};
        if (p.fullAddress) return [p.name, p.fullAddress].filter(Boolean).join(', ');
        return [...new Set([p.name, [p.housenumber, p.street].filter(Boolean).join(' '), p.city || p.town || p.village, p.district || p.county, p.state, p.postcode, p.country].filter(Boolean))].join(', ');
    }
    function useFeature(feature) {
        const [lng, lat] = feature.geometry?.coordinates || [];
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) { notice('This result has no valid coordinates.', true); return; }
        locationVersion++; position(lat, lng);
        const p = feature.properties || {};
        // Replace address details together so an earlier selection cannot leave stale fields.
        const values = { state:p.state || '', district:p.district || p.county || '', city:p.city || p.town || p.village || '', address:p.fullAddress || label(feature), pincode:/^[1-9][0-9]{5}$/.test(p.postcode || '') ? p.postcode : '' };
        Object.entries(values).forEach(([key, value]) => { control(key).value = value; });
        $('placeResults').replaceChildren(); notice('Location selected. Check the district, address and PIN code before publishing.');
    }
    async function search() {
        const query = $('placeSearch').value.trim();
        if (query.length < 3) { notice('Enter at least three characters to search.', true); return; }
        const version = ++locationVersion;
        $('searchPlace').disabled = true; $('placeResults').replaceChildren(); notice('Searching locations…');
        try {
            const results = await withTimeout(geocode('api/', { q:query, limit:6, lang:'en' }));
            if (version !== locationVersion) return;
            notice(results.length ? 'Select the correct location below.' : 'No locations found. Try a nearby street or place a pin on the map.');
            results.forEach((feature) => {
                const button = document.createElement('button'); button.type = 'button'; button.textContent = label(feature);
                button.addEventListener('click', () => useFeature(feature)); $('placeResults').append(button);
            });
        } catch (e) { if (version === locationVersion) notice(e.name === 'AbortError' ? 'Search timed out. Try again or use the map.' : 'Google location search failed. Check that Places API (New) is enabled and the key permits this website, or enter coordinates manually.', true); }
        finally { $('searchPlace').disabled = false; }
    }
    function locate() {
        if (!window.isSecureContext || !navigator.geolocation) { notice('Current location needs HTTPS and a browser with location support. Use search or the map instead.', true); return; }
        const version = ++locationVersion; searchController?.abort(); $('currentLocation').disabled = true; notice('Waiting for your browser location permission…');
        navigator.geolocation.getCurrentPosition(async ({ coords }) => {
            $('currentLocation').disabled = false;
            if (version !== locationVersion) return;
            position(coords.latitude, coords.longitude);
            ['address', 'state', 'district', 'city', 'pincode'].forEach((f) => { control(f).value = ''; });
            notice(`Current location selected (accuracy about ${Math.round(coords.accuracy)} m). Looking up address…`);
            try {
                const results = await withTimeout(geocode('reverse', { lat:coords.latitude, lon:coords.longitude, limit:1, lang:'en' }));
                if (version !== locationVersion) return;
                if (results[0]) {
                    useFeature(results[0]);
                    // Reverse results describe nearby features; keep the measured position as the pin.
                    position(coords.latitude, coords.longitude);
                }
                notice(`Current location selected (accuracy about ${Math.round(coords.accuracy)} m). Verify the address and adjust the pin if needed.`);
            } catch (e) { if (version === locationVersion) notice('Current location selected. Address lookup failed; enter the address manually.'); }
        }, (e) => {
            $('currentLocation').disabled = false;
            if (version === locationVersion) notice(e.code === 1 ? 'Location permission denied. Use search or click the map.' : 'Could not get your location. Try again or use search.', true);
        }, { enableHighAccuracy:true, timeout:15000, maximumAge:0 });
    }
    async function save(e) {
        e.preventDefault(); if (busy || !$('dealerForm').reportValidity()) return;
        busy = true; locationVersion++; searchController?.abort(); $('saveDealer').disabled = true; $('saveNotice').textContent = 'Saving…';
        const data = Object.fromEntries(fields.map((f) => [f, control(f).value.trim()]));
        try {
            const response = editing ? await api.patch(`${endpoint}/${encodeURIComponent(editing)}`, data) : await api.post(endpoint, data);
            editing = response.data.id;
            $('editorTitle').textContent = 'Edit location';
            $('saveNotice').textContent = data.status === 'published' ? 'Saved and published to the dealer locator.' : 'Saved. This location is hidden from visitors.';
            toast.success('Location saved'); await load();
        } catch (err) { $('saveNotice').textContent = err.message; toast.error(err.message); }
        finally { busy = false; $('saveDealer').disabled = false; }
    }
}());
