// Exercise the real editor script with browser boundaries stubbed; no provider requests.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
class Element {
    constructor() { this.value = ''; this.hidden = false; this.disabled = false; this.dataset = {}; this.children = []; this.listeners = {}; }
    addEventListener(event, fn) { this.listeners[event] = fn; }
    setAttribute() {}
    append(child) { this.children.push(child); }
    replaceChildren() { this.children = []; this.textContent = ''; }
    checkValidity() { return true; }
    reportValidity() { return true; }
    reset() {}
    async fire(event, data = {}) { return this.listeners[event]?.(data); }
}
const controls = Object.fromEntries(['name','state','district','city','address','pincode','phone','hours','type','status','lat','lng'].map((name) => [name, new Element()]));
const elements = new Map();
const el = (id) => { if (!elements.has(id)) elements.set(id, new Element()); return elements.get(id); };
el('dealerForm').elements = { namedItem: (name) => controls[name] };
let boot, geolocationSuccess, geolocationError, fetchResponse, saved;
const map = { setView() { return this; }, getZoom() { return 7; }, on(event, fn) { this[event] = fn; } };
const feature = { geometry:{ coordinates:[88.36,22.57] }, properties:{ name:'Test showroom', city:'Kolkata', state:'West Bengal', district:'Kolkata', postcode:'700001', country:'India' } };
const context = {
    document:{ readyState:'complete', getElementById:el, createElement:() => new Element() },
    window:{ isSecureContext:true, HAZRA_DEALER_MAP:{ searchUrl:'https://example.invalid/', tileUrl:'tiles', attribution:'OSM' },
        HAZRA:{ boot:(fn) => { boot = fn; }, api:{ get:async () => ({ data:[] }), post:async (url, body) => { saved = body; return { data:{ ...body, id:'created' } }; } },
            util:{ esc:(s) => String(s) }, layout:{ pageHead:() => '' }, toast:{ error() {}, success() {} } },
        L:true },
    navigator:{ geolocation:{ getCurrentPosition:(success, error) => { geolocationSuccess = success; geolocationError = error; } } },
    L:{ map:() => map, tileLayer:() => ({ addTo() { return this; }, on() {} }), marker:() => ({ addTo() { return this; }, on() {}, setLatLng() {}, remove() {} }) },
    URL, AbortController, setTimeout, clearTimeout,
    fetch:async () => ({ ok:true, json:async () => fetchResponse }),
};
vm.runInNewContext(fs.readFileSync(__dirname + '/../assets/admin/js/pages/dealer-locations.js', 'utf8'), context);
(async () => {
    await boot();
    assert.equal(controls.status.value, 'draft');
    el('placeSearch').value = 'Kolkata'; fetchResponse = { features:[feature] };
    await el('searchPlace').fire('click'); assert.equal(el('placeResults').children.length, 1);
    await el('placeResults').children[0].fire('click');
    assert.equal(controls.lat.value, '22.570000'); assert.equal(controls.lng.value, '88.360000');
    assert.equal(controls.city.value, 'Kolkata'); assert.equal(controls.pincode.value, '700001');
    assert.match(el('previewDirections').href, /destination=22.57%2C88.36/);
    await el('currentLocation').fire('click');
    await geolocationSuccess({ coords:{ latitude:23.1, longitude:87.2, accuracy:20 } });
    assert.equal(controls.lat.value, '23.100000', 'Reverse lookup must preserve measured position');
    assert.match(el('locationNotice').textContent, /20 m/);
    await el('currentLocation').fire('click'); geolocationError({ code:1 });
    assert.match(el('locationNotice').textContent, /permission denied/);
    await el('currentLocation').fire('click');
    map.click({ latlng:{ lat:24, lng:86 } });
    await geolocationSuccess({ coords:{ latitude:23, longitude:87, accuracy:10 } });
    assert.equal(controls.lat.value, '24.000000', 'Late geolocation must not overwrite a later manual selection');
    controls.name.value = 'Hazra dealer'; await el('dealerForm').fire('submit', { preventDefault() {} });
    assert.equal(saved.lat, '24.000000'); assert.equal(saved.name, 'Hazra dealer');
    context.window.isSecureContext = false; await el('currentLocation').fire('click');
    assert.match(el('locationNotice').textContent, /HTTPS/);
    console.log('Location search selection, current location, permission errors, stale callbacks and save checks passed.');
})().catch((e) => { console.error(e); process.exitCode = 1; });
