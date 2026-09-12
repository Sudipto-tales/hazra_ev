/* Dashboard — docs/03-page-specs.md §3.

   One call: store.summary() is GET /api/dashboard/summary. Every figure on
   this screen is worked out server-side, because "drafts untouched for a
   week" across nine collections is a question the browser can only answer by
   downloading nine collections. */
(function () {
    'use strict';

    const { util: U, store, layout, toast } = window.TMH;

    /* The public site's root, absolute — see core/layout.js. */
    const SITE = window.TMH.api.base;

    /* Where an entity name coming back from the API opens. The server names
       collections; which screen edits them is the panel's business. */
    const SCREEN = {
        doctors: 'doctors',
        leadership: 'leadership',
        departments: 'departments',
        facilities: 'facilities',
        'lab-tests': 'lab-tests',
        posts: 'blog',
        categories: 'blog-categories',
        testimonials: 'testimonials',
        faqs: 'faqs',
        counters: 'stats',
        'nav-items': 'navigation',
        redirects: 'redirects',
        jobs: 'jobs',
        applications: 'applications',
        enquiries: 'enquiries',
        appointments: 'appointments',
        users: 'users',
        roles: 'users',
        media: 'gallery',
        pages: 'pages',
        settings: 'settings-general',
        auth: 'activity-log',
    };

    const NOUN = {
        doctors: 'Doctors', leadership: 'Leadership', departments: 'Departments',
        facilities: 'Facilities', 'lab-tests': 'Lab tests', posts: 'Blog',
        categories: 'Categories', testimonials: 'Testimonials', faqs: 'FAQs',
        counters: 'Counters', 'nav-items': 'Navigation', redirects: 'Redirects',
        jobs: 'Vacancies', applications: 'Applications', enquiries: 'Enquiries',
        appointments: 'Appointments', users: 'Users', roles: 'Roles',
        media: 'Media', pages: 'Pages', settings: 'Settings', auth: 'Account',
    };

    const TILE_ICON = {
        enquiries: ['fa-envelope-open-text', 'red'],
        appointmentRequests: ['fa-calendar-check', 'navy'],
        publishedPosts: ['fa-newspaper', 'blue'],
        activeVacancies: ['fa-bullhorn', 'magenta'],
    };

    const ATTENTION_ICON = {
        staleDrafts: 'fa-pen-ruler',
        unansweredEnquiries: 'fa-envelope-open-text',
        closingVacancies: 'fa-hourglass-half',
        mediaMissingAlt: 'fa-image',
    };

    const ACTION_TONE = {
        create: 'ok', update: 'info', publish: 'ok',
        delete: 'off', restore: 'info', login: 'off', logout: 'off', view: 'off',
    };

    const screenFor = (entity) => SCREEN[entity] || 'dashboard';
    const nounFor = (entity) => NOUN[entity] || entity;

    /* The SEO meter's figures.

       Placeholder, and labelled as one on the card. There is no audit
       endpoint yet — nothing crawls the site for indexed pages or measures
       Core Web Vitals — so these are the mockup's numbers, kept in one place
       so that wiring them up later is a change to seoHealth() and nothing
       else. They are not presented as live: a hospital's panel claiming a
       score it never measured is worse than a panel with a gap in it. */
    const SEO_PLACEHOLDER = {
        score: 82,
        rows: [
            ['Core Web Vitals', 88],
            ['Indexed pages', 74],
            ['Meta coverage', 61],
        ],
    };

    window.TMH.boot(init);

    async function init() {
        const who = window.TMH.session ? window.TMH.session.currentSync() : null;
        const hour = new Date().getHours();
        const greeting = hour < 12 ? 'Good morning' : (hour < 17 ? 'Good afternoon' : 'Good evening');
        const firstName = (who && who.name ? who.name.split(' ')[0] : 'there');

        /* The greeting has moved into the welcome card below, so the page head
           is a plain title — two greetings a screen apart read as a bug. */
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            title: 'Admin',
            accent: 'Overview',
            crumb: [{ label: 'Main' }, { label: 'Dashboard' }],
            actions: `<a class="btn btn--ghost" href="${SITE}" target="_blank" rel="noopener">`
                + '<i class="fa-solid fa-arrow-up-right-from-square"></i> View website</a>',
        });

        const view = document.getElementById('view');
        view.innerHTML = skeleton();

        let data;
        try {
            data = await store.summary();
        } catch (e) {
            view.innerHTML = `
                <article class="card"><div class="empty">
                    <div class="empty__art"><i class="fa-solid fa-plug-circle-xmark"></i></div>
                    <h3>The dashboard could not load</h3>
                    <p>${U.esc(e.message || 'Something went wrong.')}</p>
                    <button type="button" class="btn btn--primary" id="retry">Try again</button>
                </div></article>`;
            const retry = document.getElementById('retry');
            if (retry) retry.addEventListener('click', init);
            return;
        }

        /* Served out of the boot cache — api.js already holds the settings
           document, so this is not a second request. A failure here only
           costs the card its hospital name, which is why it is not inside the
           try above that replaces the whole screen. */
        let settings = {};
        try {
            settings = await store.getDoc('settings');
        } catch (e) { /* the fallback name below is enough */ }

        const siteName = (settings.general && settings.general.name) || 'this hospital';

        view.innerHTML = `
            <div class="bento mb-4">
                ${welcomeHtml(siteName, `${greeting}, ${firstName}.`, data.stats)}
                ${seoHealthHtml()}
            </div>
            ${statsHtml(data.stats)}
            ${data.setup && !data.setup.complete ? setupHtml(data.setup) : ''}
            <div class="bento">
                <article class="card c8">
                    <div class="card__head">
                        <h2>Recent enquiries</h2>
                        <a class="btn btn--ghost btn--sm" href="enquiries">All enquiries</a>
                    </div>
                    ${enquiriesHtml(data.recentEnquiries)}
                </article>

                <article class="card c4">
                    <div class="card__head"><h2>Needs attention</h2></div>
                    ${attentionHtml(data.attention)}
                </article>

                <article class="card c4">
                    <div class="card__head"><h2>Quick actions</h2></div>
                    ${quickActionsHtml()}
                </article>

                <article class="card c8">
                    <div class="card__head">
                        <h2>Recently edited</h2>
                        <a class="btn btn--ghost btn--sm" href="activity-log">Activity log</a>
                    </div>
                    ${activityHtml(data.recentActivity)}
                </article>
            </div>`;

        /* One delegated listener rather than a handler per row — the table is
           rewritten wholesale on every load, and per-row handlers would have
           to be rebound with it. */
        view.addEventListener('click', (e) => {
            const row = e.target.closest('tr[data-href]');
            if (row && !e.target.closest('a')) location.href = row.dataset.href;
        });

        U.stagger(view);
    }

    /* ---------------------------------------------------------
       Stat strip
       --------------------------------------------------------- */

    /* Not util.statStrip(): that one renders a flat note, and these tiles
       carry a signed delta that has to be coloured and to read differently
       when there is nothing to compare against. */
    function statsHtml(stats) {
        return `<div class="bento mb-4">${(stats || []).map((s) => {
            const [icon, tone] = TILE_ICON[s.key] || ['fa-chart-simple', 'navy'];
            return `
            <article class="card stat c3 anim-item">
                <div class="stat__icon ${U.esc(tone)}"><i class="fa-solid ${U.esc(icon)}"></i></div>
                <h3>${U.esc(U.num(s.value))}</h3>
                <p>${U.esc(s.label)}</p>
                ${deltaHtml(s)}
            </article>`;
        }).join('')}</div>`;
    }

    /**
     * The delta chip.
     *
     * A zero delta is flat, not green: "no change" is not good news and not
     * bad news. And a first-ever period says so rather than showing an
     * infinite rise — deltaPercent is null exactly when there is nothing to
     * divide by, which is why the server sends null instead of 0.
     */
    function deltaHtml(s) {
        if (typeof s.delta !== 'number') return '<span class="delta flat"></span>';

        if (s.previous === 0 && s.delta === 0) {
            return `<span class="delta flat">Nothing yet — against ${U.esc(s.deltaOf)}</span>`;
        }

        if (s.previous === 0) {
            return `<span class="delta up"><i class="fa-solid fa-arrow-up"></i> First against ${U.esc(s.deltaOf)}</span>`;
        }

        const tone = s.delta === 0 ? 'flat' : (s.delta > 0 ? 'up' : 'down');
        const arrow = s.delta === 0 ? '' : `<i class="fa-solid fa-arrow-${s.delta > 0 ? 'up' : 'down'}"></i> `;
        const size = s.deltaPercent === null
            ? `${s.delta > 0 ? '+' : ''}${s.delta}`
            : `${s.deltaPercent > 0 ? '+' : ''}${s.deltaPercent}%`;

        return `<span class="delta ${tone}">${arrow}${U.esc(s.delta === 0 ? 'No change' : size)}`
            + ` <span class="muted">vs ${U.esc(s.deltaOf)}</span></span>`;
    }

    /* ---------------------------------------------------------
       Welcome card
       --------------------------------------------------------- */

    function welcomeHtml(siteName, greetingLine, stats) {
        return `
        <article class="card welcome-card c8 anim-item">
            <div class="welcome-card__copy">
                <h2 class="welcome-card__hii">Hii <span class="welcome-card__wave">👋</span></h2>
                <p class="welcome-card__sub">Welcome to the <b>${U.esc(siteName)}</b></p>
                <p class="welcome-card__slogan" lang="bn">মানুষের সাথে ..... মানুষের পাশে</p>
                <p class="welcome-card__note">${U.esc(greetingLine)} Here is what has come in, and what is waiting on you.</p>
                ${welcomeBadgesHtml(stats)}
            </div>
            <div class="welcome-card__art" aria-hidden="true">
                <span class="splash splash--a"></span>
                <span class="splash splash--b"></span>
                <span class="orbit orbit--a"></span>
                <span class="orbit orbit--b"></span>
                <span class="spark spark--1"></span>
                <span class="spark spark--2"></span>
                <span class="spark spark--3"></span>
                ${doctorSvg()}
            </div>
        </article>`;
    }

    /**
     * The three chips under the greeting.
     *
     * The mockup had "12.4% Visitors / 8.2% Bookings / 5.6% Revenue" written
     * into the markup. None of those are numbers this panel holds, so the
     * chips carry the movers out of the stat strip instead — same shape, real
     * figures. A period with nothing to compare against has no percentage,
     * and a flat one is not news, so both are skipped; if that leaves nothing,
     * the row is dropped rather than padded.
     */
    function welcomeBadgesHtml(stats) {
        const chips = (stats || [])
            .filter((s) => typeof s.deltaPercent === 'number' && s.deltaPercent !== 0)
            .slice(0, 3)
            .map((s) => {
                const up = s.deltaPercent > 0;
                return `
                <span class="gbadge ${up ? 'up' : 'down'}">
                    <i class="fa-solid fa-arrow-trend-${up ? 'up' : 'down'}"></i>
                    ${U.esc(`${Math.abs(s.deltaPercent)}% ${s.label}`)}
                </span>`;
            }).join('');

        return chips ? `<div class="badge-row">${chips}</div>` : '';
    }

    /* ---------------------------------------------------------
       SEO health meter
       --------------------------------------------------------- */

    function seoHealthHtml() {
        const { score, rows } = SEO_PLACEHOLDER;

        return `
        <article class="card meter-card c4 anim-item">
            <div class="card__head">
                <h3>Website SEO Health</h3>
                <span class="pill" title="These figures are placeholders until the site is audited.">Sample</span>
            </div>
            <div class="gauge-wrap">
                <div class="gauge" style="--val:${score}" role="img"
                     aria-label="SEO health score ${score} out of 100">
                    <div class="gauge__core"><strong>${score}</strong><small>score</small></div>
                </div>
                <ul class="meter-list">
                    ${rows.map(([label, value]) => `
                        <li>
                            <span>${U.esc(label)}</span>
                            <div class="track"><i style="width:${value}%"></i></div>
                            <b>${value}</b>
                        </li>`).join('')}
                </ul>
            </div>
        </article>`;
    }

    /* The illustration, straight from the mockup. Inline rather than an <img>
       because it is themed by the same tokens as the card and animates two of
       its own groups — the waving arm and the eyes. */
    function doctorSvg() {
        return `
        <svg class="welcome-card__doctor" viewBox="0 0 300 340" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="gSkin" x1="0" y1="0" x2="0.6" y2="1">
                    <stop offset="0%" stop-color="#FAD3B0"/>
                    <stop offset="60%" stop-color="#F0B688"/>
                    <stop offset="100%" stop-color="#DE9C6C"/>
                </linearGradient>
                <linearGradient id="gCoat" x1="0.2" y1="0" x2="0.9" y2="1">
                    <stop offset="0%" stop-color="#FFFFFF"/>
                    <stop offset="55%" stop-color="#F2F7F6"/>
                    <stop offset="100%" stop-color="#D2E2DF"/>
                </linearGradient>
                <linearGradient id="gScrub" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#00B39F"/>
                    <stop offset="100%" stop-color="#00695F"/>
                </linearGradient>
                <linearGradient id="gHair" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#4A3A33"/>
                    <stop offset="100%" stop-color="#201713"/>
                </linearGradient>
                <radialGradient id="gGlow" cx="50%" cy="45%" r="55%">
                    <stop offset="0%" stop-color="#00C4AE" stop-opacity=".38"/>
                    <stop offset="100%" stop-color="#00C4AE" stop-opacity="0"/>
                </radialGradient>
            </defs>

            <circle cx="150" cy="150" r="128" fill="url(#gGlow)"/>
            <ellipse cx="150" cy="322" rx="84" ry="12" fill="#00695F" opacity=".16"/>

            <path d="M150 172 c-30 0 -46 14 -54 40 l-18 62 c-4 14 2 22 16 24 c34 6 78 6 112 0 c14 -2 20 -10 16 -24
                     l-18 -62 c-8 -26 -24 -40 -54 -40 z" fill="url(#gCoat)"/>

            <path d="M104 202 C 88 232, 84 262, 90 286" stroke="#E4EFED" stroke-width="27"
                  stroke-linecap="round" fill="none"/>
            <path d="M104 202 C 88 232, 84 262, 90 286" stroke="url(#gCoat)" stroke-width="23"
                  stroke-linecap="round" fill="none"/>
            <circle cx="92" cy="296" r="13.5" fill="url(#gSkin)"/>
            <path d="M150 176 l-21 9 l21 47 l21 -47 z" fill="url(#gScrub)"/>
            <path d="M150 232 L150 296" stroke="#D7E6E3" stroke-width="3" stroke-linecap="round"/>
            <circle cx="150" cy="252" r="3.2" fill="#C3D6D3"/>
            <circle cx="150" cy="276" r="3.2" fill="#C3D6D3"/>

            <rect x="176" y="216" width="19" height="26" rx="5" fill="#F57C00" opacity=".92"/>
            <rect x="180" y="222" width="11" height="3" rx="1.5" fill="#fff" opacity=".8"/>
            <rect x="180" y="229" width="8" height="3" rx="1.5" fill="#fff" opacity=".55"/>

            <path d="M129 180 C 118 220, 126 248, 145 259" stroke="#00695F" stroke-width="6"
                  stroke-linecap="round" fill="none"/>
            <path d="M171 180 C 182 214, 172 244, 155 257" stroke="#00695F" stroke-width="6"
                  stroke-linecap="round" fill="none"/>
            <circle cx="150" cy="266" r="11" fill="#8FA6AC"/>
            <circle cx="150" cy="266" r="6" fill="#5D7076"/>

            <path d="M136 146 h28 v28 q-14 10 -28 0 z" fill="#E09B6B"/>
            <ellipse cx="106" cy="122" rx="8" ry="10" fill="url(#gSkin)"/>
            <ellipse cx="194" cy="122" rx="8" ry="10" fill="url(#gSkin)"/>
            <ellipse cx="150" cy="118" rx="44" ry="47" fill="url(#gSkin)"/>
            <path d="M106 108 c2 -36 26 -52 44 -52 c18 0 42 16 44 52 c-9 -17 -26 -23 -44 -23 c-18 0 -35 6 -44 23 z"
                  fill="url(#gHair)"/>
            <ellipse cx="132" cy="130" rx="8" ry="5" fill="#F08C7A" opacity=".28"/>
            <ellipse cx="168" cy="130" rx="8" ry="5" fill="#F08C7A" opacity=".28"/>
            <path d="M124 106 q10 -6 20 -1" stroke="#33261F" stroke-width="3.4" fill="none" stroke-linecap="round"/>
            <path d="M156 105 q10 -5 20 1" stroke="#33261F" stroke-width="3.4" fill="none" stroke-linecap="round"/>
            <ellipse class="eye" cx="134" cy="119" rx="4.6" ry="6" fill="#2A2320"/>
            <ellipse class="eye" cx="166" cy="119" rx="4.6" ry="6" fill="#2A2320"/>
            <circle cx="135.6" cy="117" r="1.6" fill="#fff" opacity=".9"/>
            <circle cx="167.6" cy="117" r="1.6" fill="#fff" opacity=".9"/>
            <path d="M138 137 q12 12 24 0" stroke="#9A5F3C" stroke-width="4" fill="none" stroke-linecap="round"/>

            <g class="wave-arm">
                <path d="M194 208 C 224 198, 241 177, 245 153" stroke="url(#gCoat)" stroke-width="26"
                      stroke-linecap="round" fill="none"/>
                <circle cx="247" cy="141" r="15" fill="url(#gSkin)"/>
            </g>
        </svg>`;
    }

    /* ---------------------------------------------------------
       Cards
       --------------------------------------------------------- */

    function setupHtml(setup) {
        const done = setup.steps.filter((s) => s.done).length;

        return `
        <article class="card mb-4 anim-item">
            <div class="card__head">
                <h2>Finish setting up</h2>
                <span class="pill">${done} of ${setup.steps.length} done</span>
            </div>
            <ul class="checklist">
                ${setup.steps.map((s) => `
                    <li class="checklist__item${s.done ? ' is-done' : ''}">
                        <i class="fa-solid fa-${s.done ? 'circle-check' : 'circle'}"></i>
                        ${s.done
                            ? `<span>${U.esc(s.label)}</span>`
                            : `<a href="${U.esc(s.href)}">${U.esc(s.label)}</a>`}
                    </li>`).join('')}
            </ul>
        </article>`;
    }

    function enquiriesHtml(rows) {
        if (!rows || !rows.length) {
            return '<div class="empty--sm">No enquiries yet. They land here the moment the contact form is used.</div>';
        }

        return `
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>From</th><th>Subject</th><th>Status</th><th>Received</th></tr></thead>
                <tbody>
                    ${rows.map((r) => `
                        <tr data-href="enquiry-view?id=${U.esc(encodeURIComponent(r.id))}">
                            <td>
                                <span class="cell-main">${U.esc(r.name)}</span>
                                <span class="cell-sub">${U.esc(r.email || r.phone || '')}</span>
                            </td>
                            <td>${U.esc(r.subject || '—')}</td>
                            <td><span class="tag ${r.status === 'new' ? 'warn' : (r.status === 'replied' ? 'ok' : 'off')}">${U.esc(r.status)}</span></td>
                            <td><span class="muted" title="${U.esc(U.fmtDateTime(r.receivedAt))}">${U.esc(U.ago(r.receivedAt))}</span></td>
                        </tr>`).join('')}
                </tbody>
            </table>
        </div>`;
    }

    function attentionHtml(items) {
        if (!items || !items.length) {
            return '<div class="empty--sm"><i class="fa-solid fa-mug-hot"></i> Nothing is waiting on you.</div>';
        }

        return `<ul class="feed">${items.map((it) => {
            /* An attention item may name a filtered destination — "4 enquiries
               with no reply" should land on those four, not on the full list. */
            const qs = it.query
                ? '?' + Object.entries(it.query).map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`).join('&')
                : '';
            const href = it.entity ? screenFor(it.entity) + qs : '';
            const label = `<b>${U.esc(it.label)}</b>`;

            return `
            <li class="feed__item">
                <span class="feed__icon warn"><i class="fa-solid ${U.esc(ATTENTION_ICON[it.key] || 'fa-triangle-exclamation')}"></i></span>
                <span class="feed__body">
                    ${href ? `<a href="${U.esc(href)}">${label}</a>` : label}
                    ${it.breakdown && it.breakdown.length
                        ? `<span class="feed__meta">${it.breakdown.map((b) =>
                            `<a href="${U.esc(screenFor(b.entity))}?status=draft">${U.esc(nounFor(b.entity))} ${b.count}</a>`).join(' · ')}</span>`
                        : ''}
                </span>
            </li>`;
        }).join('')}</ul>`;
    }

    function quickActionsHtml() {
        const actions = [
            ['fa-user-doctor', 'Add a doctor', 'doctor-form'],
            ['fa-pen-nib', 'Write a post', 'blog-form'],
            ['fa-bullhorn', 'Post a vacancy', 'job-form'],
            ['fa-address-book', 'Edit contact details', 'settings-contact'],
        ];

        return `<div class="quick-actions">${actions.map(([icon, label, href]) => `
            <a class="quick-action" href="${U.esc(href)}">
                <i class="fa-solid ${U.esc(icon)}"></i>
                <span>${U.esc(label)}</span>
            </a>`).join('')}</div>`;
    }

    function activityHtml(rows) {
        if (!rows || !rows.length) {
            return '<div class="empty--sm">Nothing has been edited yet.</div>';
        }

        /* userName is on the row because the log stores it — an account can be
           deleted and the entry still has to say who. The lookup is only a
           fallback for the seeded rows, which carry userId alone. */
        const users = store.available('users') ? store.allSync('users') : [];
        const nameOf = (row) => row.userName
            || (users.find((u) => u.id === row.userId) || {}).name
            || 'Someone';

        return `<ul class="feed">${rows.map((r) => `
            <li class="feed__item">
                <span class="feed__icon ${U.esc(ACTION_TONE[r.action] || 'info')}">
                    <i class="fa-solid fa-${r.action === 'delete' ? 'trash' : (r.action === 'create' ? 'plus' : 'pen')}"></i>
                </span>
                <span class="feed__body">
                    <b>${U.esc(nameOf(r))}</b>
                    <span>${U.esc(r.summary || `${r.action} ${nounFor(r.entity)}`)}</span>
                    <span class="feed__meta">
                        ${r.entity ? `<a href="${U.esc(screenFor(r.entity))}">${U.esc(nounFor(r.entity))}</a> · ` : ''}
                        <span title="${U.esc(U.fmtDateTime(r.at))}">${U.esc(U.ago(r.at))}</span>
                    </span>
                </span>
            </li>`).join('')}</ul>`;
    }

    /* ---------------------------------------------------------
       Loading
       --------------------------------------------------------- */

    /* Skeletons in the shape of what arrives, not a spinner over the page —
       the global loading rule in docs/03-page-specs.md. */
    function skeleton() {
        const line = (w, h) => `<div class="skel" style="width:${w}%${h ? `;height:${h}px` : ''}"></div>`;
        const tile = `<article class="card stat c3" style="gap:var(--s3)">
            <div class="skel skel--circle"></div>${line(45, 22)}${line(60)}</article>`;
        const block = (span) => `<article class="card ${span}" style="display:flex;flex-direction:column;gap:var(--s4)">
            ${line(35, 16)}${line(100)}${line(90)}${line(95)}${line(70)}</article>`;

        /* The welcome card is 238px tall and the meter beside it is not, so
           the top row is held open rather than left to collapse and shove the
           rest of the screen up when the summary lands. */
        const hero = `<article class="card c8" style="min-height:238px"></article>`
            + `<article class="card c4" style="display:flex;flex-direction:column;gap:var(--s4)">
                ${line(45, 16)}${line(100, 56)}</article>`;

        return `<div class="bento mb-4">${hero}</div>`
            + `<div class="bento mb-4">${tile.repeat(4)}</div>`
            + `<div class="bento">${block('c8')}${block('c4')}</div>`;
    }
}());
