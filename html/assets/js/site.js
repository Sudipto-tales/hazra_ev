/* ============================================================
   Hazra EV — site data + shared chrome (header / footer / FABs)
   Injected with template strings so the prototype works over file://
   ============================================================ */
(function () {
  'use strict';

  /* ---------- image helper (Unsplash placeholders) ---------- */
  const U = (id, w = 1200, h = 0) =>
    `https://images.unsplash.com/photo-${id}?auto=format&fit=crop&w=${w}${h ? '&h=' + h : ''}&q=80`;

  const IMG = {
    hero: [
      '1768907489904-1d72e205d37b',
      '1554223789-df81106a45ed',
      '1671866604416-57a3fab71706',
      '1611956292173-c2445aa61709',
      '1538895490524-0ded232a96d8'
    ],
    spin: [
      '1768907489904-1d72e205d37b','1591122519484-70428711810d','1657008846502-e03a84f49baa',
      '1648204834832-78e68052c04f','1519750292352-c9fc17322ed7','1564605776084-c13421c561b4',
      '1675980892208-7b6ba39120ea','1590417396467-6cca3ebbff9b'
    ],
    charge: ['1593941707874-ef25b8b4a92b','1593941707882-a5bba14938c7','1704475336842-0ab3798abf0e',
      '1615829386703-e2bb66a7cb7d','1671785120538-c24cbe823ccc','1646753020826-c518face72ad'],
    showroom: ['1692406069831-0bb7ea297645','1593941707874-ef25b8b4a92b','1615829386703-e2bb66a7cb7d',
      '1536421469767-80559bb6f5e1','1626198226928-617fc6c6203e','1617347454431-f49d7ff5c3b1'],
    factory: ['1717386255773-1e3037c81788','1647427060118-4911c9821b82','1610891015188-5369212db097',
      '1496247749665-49cf5b1022e9','1589793463357-5fb813435467','1581091212991-8891c7d4bd9b'],
    people: ['1506863530036-1efeddceb993','1494790108377-be9c29b29330','1581841064838-a470c740e8ee',
      '1604072366595-e75dc92d6bdc','1543132220-3ec99c6094dc','1532171875345-9712d9d4f65a'],
    team: ['1606857521015-7f9fcf423740','1603201667141-5a2d4c673378','1560264280-88b68371db39',
      '1521737711867-e3b97375f902'],
    rider: ['1617347454431-f49d7ff5c3b1','1695654390723-479197a8c4a3','1572195577046-2f25894c06fc',
      '1621972750749-0fbb1abb7736','1526367790999-0150786686a2'],
    city: ['1571679654681-ba01b9e1e117','1558431382-27e303142255','1536421469767-80559bb6f5e1',
      '1626198226928-617fc6c6203e','1603813507806-0d311a6eecd1']
  };

  /* ---------- lineup ---------- */
  const PRODUCTS = [
    {
      slug:'vega-1000', name:'HAZRA VEGA 1000', tag:'High Speed', range:'120 km', top:'75 km/h',
      charge:'3.5 h', motor:'2000 W BLDC', battery:'72V / 38Ah',
      graphene:98999, lithium:112499, rating:4.8, reviews:412, spin:true,
      colors:[['Electric Blue','#2F6BFF'],['Graphite','#334155'],['Pearl White','#F1F5F9'],['Eco Green','#16A34A']],
      img:['1768907489904-1d72e205d37b','1591122519484-70428711810d','1495031178007-13cac321fbcf'],
      blurb:'Our flagship. Highway-legal speed, a 120 km real-world range, and a frame tuned on Kolkata potholes.'
    },
    {
      slug:'urja-pro', name:'HAZRA URJA PRO', tag:'Low Speed', range:'95 km', top:'25 km/h',
      charge:'4 h', motor:'250 W BLDC', battery:'60V / 32Ah',
      graphene:72499, lithium:84999, rating:4.7, reviews:658,
      colors:[['Midnight','#0F172A'],['Electric Blue','#2F6BFF'],['Cherry','#DC2626'],['Sand','#E7E0D4']],
      img:['1554223789-df81106a45ed','1657008846502-e03a84f49baa','1648204834832-78e68052c04f'],
      blurb:'No licence, no registration, no fuel bill. The daily-commute workhorse of the Urja line.'
    },
    {
      slug:'urja-eco', name:'HAZRA URJA ECO', tag:'Low Speed', range:'140 km', top:'25 km/h',
      charge:'4.5 h', motor:'250 W BLDC', battery:'60V / 42Ah',
      graphene:69999, lithium:81499, rating:4.9, reviews:521, spin:true,
      colors:[['Eco Green','#16A34A'],['Ice','#E2E8F0'],['Electric Blue','#2F6BFF'],['Amber','#F59E0B']],
      img:['1671866604416-57a3fab71706','1519750292352-c9fc17322ed7','1611956292173-c2445aa61709'],
      blurb:'The longest range we build. 140 km on one charge — a full week of city riding for most owners.'
    },
    {
      slug:'urja-plus', name:'HAZRA URJA PLUS', tag:'Low Speed', range:'85 km', top:'25 km/h',
      charge:'4 h', motor:'250 W BLDC', battery:'60V / 30Ah',
      graphene:78999, lithium:90499, rating:4.6, reviews:289,
      colors:[['Steel Grey','#64748B'],['Electric Blue','#2F6BFF'],['Pearl White','#F8FAFC']],
      img:['1538895490524-0ded232a96d8','1675980892208-7b6ba39120ea','1564605776084-c13421c561b4'],
      blurb:'Wider floorboard, taller windshield, telescopic front. Built for two-up riding with luggage.'
    },
    {
      slug:'neo', name:'HAZRA NEO', tag:'Low Speed', range:'75 km', top:'25 km/h',
      charge:'3.5 h', motor:'250 W BLDC', battery:'60V / 26Ah',
      graphene:61999, lithium:73499, rating:4.5, reviews:734,
      colors:[['Sunset','#F59E0B'],['Electric Blue','#2F6BFF'],['Mint','#22C55E'],['Ink','#0F172A']],
      img:['1590417396467-6cca3ebbff9b','1586868224911-ee2d89558c54','1521118702313-63fca89fe1fd']  ,
      blurb:'Lightest and cheapest in the range. Removable battery you can carry up three floors.'
    },
    {
      slug:'cargo-7', name:'HAZRA CARGO 7', tag:'Commercial', range:'70 km', top:'25 km/h',
      charge:'5 h', motor:'250 W BLDC', battery:'60V / 32Ah',
      graphene:84999, lithium:96499, rating:4.7, reviews:163,
      colors:[['Fleet Blue','#1E4FD8'],['Utility Grey','#475569'],['Hi-Vis','#F59E0B']],
      img:['1617347454431-f49d7ff5c3b1','1695654390723-479197a8c4a3','1621972750749-0fbb1abb7736'],
      blurb:'150 kg payload, reinforced rear rack, fleet telematics built in. Runs with the Hazra EV field app.'
    }
  ];

  const rupee = n => '₹' + n.toLocaleString('en-IN');

  /* ---------- navigation ---------- */
  const NAV = [
    { label:'Home', href:'index.html' },
    { label:'About', items:[
      ['about.html','Our Story','How Hazra EV started in Kolkata'],
      ['about.html#vision','Vision & Mission','Where we are heading'],
      ['careers.html','Careers','Build the ride with us'],
      ['faq.html','FAQ','Answers to common questions']
    ]},
    { label:'Scooters', wide:true, items:PRODUCTS.map(p =>
      [`product.html?m=${p.slug}`, p.name, `${p.range} range · ${p.tag}`, p.img[0]]) },
    { label:'Ownership', items:[
      ['faq.html#battery','Battery Care','Get 5+ years from your pack'],
      ['faq.html#charging','Charging Guide','Home, office and fast charge'],
      ['faq.html#service','Service & Warranty','4-year coverage explained'],
      ['contact.html#finance','Finance & EMI','From ₹2,199 a month']
    ]},
    { label:'Dealers', items:[
      ['dealers.html','Dealer Locator','Find your nearest showroom'],
      ['dealers.html#become','Become a Dealer','Join the network']
    ]},
    { label:'Contact', href:'contact.html' }
  ];

  const PHONE = '+919147102945';
  const WHATSAPP = '918420297103';

  /* ---------- chrome markup ---------- */
  const brand = (sub = 'Electric Vehicles') => `
    <a class="logo" href="index.html" aria-label="Hazra EV home">
      <span class="logo__bolt"><span class="material-symbols-outlined">bolt</span></span>
      <span>HAZRA<span class="logo__ev">EV</span><small>${sub}</small></span>
    </a>`;

  const megaItem = ([href, title, desc, thumb]) => `
    <a class="mega__link" href="${href}">
      ${thumb
        ? `<img class="mega__thumb" src="${U(thumb, 160)}" alt="" loading="lazy">`
        : `<span class="mega__ico"><span class="material-symbols-outlined">chevron_right</span></span>`}
      <span><span class="mega__t">${title}</span><span class="mega__d">${desc}</span></span>
    </a>`;

  function header() {
    const page = (location.pathname.split('/').pop() || 'index.html');
    const items = NAV.map(n => {
      const current = n.href === page ? ' is-current' : '';
      if (!n.items) return `<div class="nav__item"><a class="nav__link${current}" href="${n.href}">${n.label}</a></div>`;
      return `<div class="nav__item" data-dropdown>
        <button class="nav__link" aria-expanded="false">${n.label}
          <span class="material-symbols-outlined">expand_more</span></button>
        <div class="mega${n.wide ? ' mega--wide' : ''}">${n.items.map(megaItem).join('')}</div>
      </div>`;
    }).join('');

    return `<header class="header" id="header"><div class="header__inner">
      ${brand()}
      <nav class="nav">${items}</nav>
      <div class="nav-cta">
        <a class="btn btn--sm" href="contact.html#testride">
          <span class="material-symbols-outlined">two_wheeler</span>Book Test Ride</a>
        <button class="burger" id="burger" aria-label="Open menu">
          <span class="material-symbols-outlined">menu</span></button>
      </div>
    </div></header>

    <div class="drawer__scrim" id="scrim"></div>
    <aside class="drawer" id="drawer">
      <div class="drawer__head">${brand()}
        <button class="burger" id="drawerClose" aria-label="Close menu" style="display:grid">
          <span class="material-symbols-outlined">close</span></button>
      </div>
      ${NAV.map(n => n.items
        ? `<div class="drawer__group"><button class="drawer__trigger">${n.label}
             <span class="material-symbols-outlined">expand_more</span></button>
             <div class="drawer__panel"><div>${n.items.map(i => `<a href="${i[0]}">${i[1]}</a>`).join('')}</div></div>
           </div>`
        : `<div class="drawer__group"><a class="drawer__trigger" href="${n.href}">${n.label}</a></div>`).join('')}
      <a class="btn btn--block" href="contact.html#testride" style="margin-top:24px">
        <span class="material-symbols-outlined">two_wheeler</span>Book a Test Ride</a>
      <a class="btn btn--ghost btn--block" href="tel:${PHONE}" style="margin-top:10px">
        <span class="material-symbols-outlined">call</span>${PHONE}</a>
    </aside>`;
  }

  function footer() {
    const col = (title, links) =>
      `<div><h5>${title}</h5><ul>${links.map(l => `<li><a href="${l[0]}">${l[1]}</a></li>`).join('')}</ul></div>`;
    return `<footer class="footer"><div class="wrap wrap-wide">
      <div class="footer__grid">
        <div class="footer__about">
          ${brand()}
          <p>Hazra EV builds electric two-wheelers in Kolkata — quiet, clean, and engineered for Indian roads.</p>
          <h5>Get range tips &amp; offers</h5>
          <form class="newsletter" data-fake-form>
            <input type="email" placeholder="you@email.com" required aria-label="Email">
            <button type="submit" aria-label="Subscribe">
              <span class="material-symbols-outlined">arrow_forward</span></button>
          </form>
        </div>
        ${col('Company', [['about.html','Our Story'],['about.html#vision','Vision &amp; Mission'],
          ['careers.html','Careers'],['contact.html','Contact'],['faq.html','FAQs']])}
        ${col('Scooters', PRODUCTS.map(p => [`product.html?m=${p.slug}`, p.name]))}
        ${col('Ownership', [['contact.html#testride','Book a Test Ride'],['dealers.html','Find a Dealer'],
          ['dealers.html#become','Become a Dealer'],['faq.html#battery','Battery Care'],
          ['faq.html#service','Service &amp; Warranty']])}
        ${col('Policy', [['#','Privacy Policy'],['#','Terms &amp; Conditions'],['#','Refund &amp; Cancellation'],
          ['#','Shipping Policy'],['#','Warranty Registration']])}
      </div>
      <div class="footer__bottom">
        <span>© 2026 Hazra E-Vehicles Private Limited. All rights reserved.</span>
        <div class="socials">
          ${['public','photo_camera','play_circle','group','forum'].map(i =>
            `<a href="#" aria-label="social"><span class="material-symbols-outlined">${i}</span></a>`).join('')}
        </div>
      </div>
    </div></footer>

    <a class="whatsapp-fab" href="https://wa.me/${WHATSAPP}" aria-label="WhatsApp">
      <span class="material-symbols-outlined">chat</span></a>
    <button class="to-top" id="toTop" aria-label="Back to top">
      <span class="material-symbols-outlined">arrow_upward</span></button>
    <nav class="action-bar">
      <a href="tel:${PHONE}"><span class="material-symbols-outlined">call</span>Call</a>
      <a href="contact.html#testride"><span class="material-symbols-outlined">two_wheeler</span>Test Ride</a>
      <a href="dealers.html"><span class="material-symbols-outlined">store</span>Dealers</a>
    </nav>
    <div class="toast" id="toast"><span class="material-symbols-outlined">check_circle</span>
      <span id="toastMsg">Done</span></div>`;
  }

  /* ---------- mount ---------- */
  function mount() {
    document.body.insertAdjacentHTML('afterbegin', `
      <div class="preloader" id="preloader"><div class="preloader__mark">
        <div class="preloader__bolt"><span class="material-symbols-outlined">bolt</span></div>
        <div class="preloader__bar"><i></i></div>
      </div></div>
      <div class="scroll-progress" id="scrollProgress"></div>` + header());
    document.body.insertAdjacentHTML('beforeend', footer());
    document.dispatchEvent(new CustomEvent('hazra:chrome'));
  }

  window.HAZRA = { U, IMG, PRODUCTS, NAV, rupee, PHONE, WHATSAPP };
  document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', mount)
    : mount();
})();
