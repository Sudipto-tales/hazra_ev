/* =========================================================
   Panel shell. Renders the sidebar and topbar into every page
   from window.TMH_NAV, so the navigation lives in one file
   rather than being copy-pasted into 42.

   Page markup is only ever:

     <body data-page="doctors">
       <div class="app">
         <div id="sidebar"></div>
         <div class="shell">
           <div id="topbar"></div>
           <main class="main"> … </main>
         </div>
       </div>
   ========================================================= */
(function (root) {
    'use strict';

    const esc = (s) => (root.TMH.util ? root.TMH.util.esc(s) : String(s));

    /* The public site's root, absolute. Panel screens are all served from
       /admin/, so a link to a *sibling* screen stays relative — but a link
       out to the site cannot be, because the site may sit in a subdirectory
       and its pages are not all one segment deep. core/api.js reads the base
       from <meta name="app-base"> and parses before this file. */
    const SITE = root.TMH.api.base;

    const THEME_KEY = 'hazra-admin-theme';
    const COLLAPSE_KEY = 'hazra-admin-collapsed';

    /* ---------------------------------------------------------
       THEME
       The pre-paint script in each page's <head> has already set
       data-theme; this only handles the toggle afterwards.
       --------------------------------------------------------- */
    const theme = {
        get() {
            return document.documentElement.dataset.theme || 'light';
        },
        set(value) {
            document.documentElement.dataset.theme = value;
            try {
                localStorage.setItem(THEME_KEY, value);
            } catch (e) { /* private mode — the choice just will not persist */ }
            root.dispatchEvent(new CustomEvent('tmh:theme', { detail: value }));
        },
        toggle() {
            theme.set(theme.get() === 'dark' ? 'light' : 'dark');
        },
    };

    /* ---------------------------------------------------------
       SIDEBAR
       --------------------------------------------------------- */
    /* The shell shows whoever is signed in. GET /api/auth/me has already
       answered by the time this mounts — see TMH.boot at the foot of the file
       — so the fallback is only what a browser sees if the shell is somehow
       painted without it. */
    function me() {
        const user = root.TMH.session ? root.TMH.session.currentSync() : null;
        const name = (user && user.name) || 'Signed in';
        return {
            name,
            role: user ? root.TMH.session.roleName(user.roleId) : '',
            initials: root.TMH.util ? root.TMH.util.initials(name) : '··',
            avatar: (user && user.avatar) || '',
        };
    }

    function sidebarHtml(activeKey) {
        /* A badge may be a number or a fn(); resolved here so the count is
           read after the store is loaded. A zero renders as no bubble. */
        const badgeOf = (item) => {
            const v = typeof item.badge === 'function' ? item.badge() : item.badge;
            return v ? `<span class="nav-item__badge">${esc(v)}</span>` : '';
        };

        const user = me();

        const groups = (root.TMH_NAV || []).map((group) => `
            <div class="nav-group">
                <div class="nav-group__label">${esc(group.label)}</div>
                ${group.items.map((item) => `
                    <a class="nav-item${item.key === activeKey ? ' active' : ''}"
                       href="${esc(item.href)}"
                       data-key="${esc(item.key)}"
                       data-label="${esc(item.label)}">
                        <i class="fa-solid ${esc(item.icon)}"></i>
                        <span>${esc(item.label)}</span>
                        ${badgeOf(item)}
                    </a>`).join('')}
            </div>`).join('');

        return `
        <aside class="sidebar" id="sidebarEl">
            <div class="sidebar__brand">
                <img class="sidebar__logo" src="${esc(base_url('assets/hazraevLogo.jpg'))}" alt="Hazra EV" width="32" height="32">
                <div class="sidebar__name">Hazra EV<small>Admin</small></div>
            </div>
            <nav class="sidebar__nav" id="navTrack" aria-label="Main">
                <span class="nav-pill no-anim" id="navPill"></span>
                ${groups}
            </nav>
            <div class="sidebar__foot">
                <span class="sidebar__me">${esc(user.initials)}</span>
                <span class="sidebar__user"><strong>${esc(user.name)}</strong><span>${esc(user.role)}</span></span>
                <button type="button" class="sidebar__collapse" id="collapseBtn"
                        aria-label="Collapse sidebar"><i class="fa-solid fa-angles-left"></i></button>
            </div>
        </aside>
        <div class="scrim" id="scrim"></div>`;
    }

    /* The pill is a single absolutely-positioned element behind the items, so
       it slides between them instead of blinking. It only needs the active
       item's offsetTop/offsetHeight — same approach as the original mockup. */
    function syncPill() {
        const pill = document.getElementById('navPill');
        const active = document.querySelector('.nav-item.active');
        if (!pill) return;
        if (!active) {
            pill.style.opacity = '0';
            return;
        }
        pill.style.opacity = '1';
        pill.style.top = `${active.offsetTop}px`;
        pill.style.height = `${active.offsetHeight}px`;
    }

    /* ---------------------------------------------------------
       GLOBAL SEARCH
       The server does the matching — GET /api/search, which was
       built for this at 4.5 — and this table is what is left of
       the panel's side of it: which icon a collection wears and
       which screen one of its records opens on. A form screen
       when the record has one, otherwise its list page
       pre-filtered through ?q= so the row is on screen on arrival.
       --------------------------------------------------------- */
    const form = (page) => (r) => `${page}?id=${encodeURIComponent(r.id)}`;
    const listAt = (page) => (r, q) => `${page}?q=${encodeURIComponent(q)}`;

    const SEARCH_SOURCES = {
        products: { group: 'Products', icon: 'fa-bicycle', href: form('product-form') },
        posts: { group: 'Blog and news', icon: 'fa-newspaper', href: form('blog-form') },
        jobs: { group: 'Jobs', icon: 'fa-briefcase', href: form('job-form') },
        media: { group: 'Gallery', icon: 'fa-images', href: listAt('gallery') },
        applications: { group: 'Career apps', icon: 'fa-file-signature', href: listAt('career-apps') },
    };

    const SEARCH_LIMIT = 8;

    /* Sidebar destinations are searchable too — "where do I set the favicon"
       is a navigation question, not a content one. They are matched here
       because the server has never heard of the sidebar. */
    function navHits(needle) {
        const out = [];
        (root.TMH_NAV || []).forEach((group) => group.items.forEach((item) => {
            const hay = `${item.label} ${group.label}`.toLowerCase();
            const at = hay.indexOf(needle);
            if (at < 0) return;
            out.push({
                group: 'Go to', icon: item.icon, title: item.label,
                sub: group.label, href: item.href, rank: at === 0 ? 0 : 2,
            });
        }));
        return out;
    }

    /**
     * GET /api/search — every collection at once, ranked and capped by the
     * server.
     *
     * The mock scanned the seed files the page had loaded, which is why the
     * prototype's version asked `store.available(entity)` first and quietly
     * skipped anything the screen had not seeded. There is nothing to skip
     * now: the endpoint searches the columns config/resources.php names, and
     * reaches pages and media, neither of which is a collection the panel
     * holds in memory.
     */
    async function searchAll(q) {
        const needle = q.trim().toLowerCase();
        if (needle.length < 2) return [];

        const hits = navHits(needle);
        let groups = [];

        try {
            groups = await root.TMH.store.search(q.trim());
        } catch (e) {
            /* A failed search is not worth a toast in the middle of typing —
               the sidebar matches above are still a useful answer. */
            console.warn('[search] request failed', e);
        }

        groups.forEach((group) => {
            const src = SEARCH_SOURCES[group.entity]
                || { group: group.entity, icon: 'fa-circle-dot', href: () => '#' };

            group.items.forEach((item) => {
                hits.push({
                    group: src.group,
                    icon: src.icon,
                    title: item.label || item.id,
                    sub: item.status && item.status !== 'published' ? item.status : '',
                    href: src.href(item, q.trim()),
                    rank: 1,
                });
            });
        });

        return hits.sort((a, b) => a.rank - b.rank).slice(0, SEARCH_LIMIT);
    }

    function wireGlobalSearch() {
        const input = document.getElementById('globalSearch');
        const panel = document.getElementById('searchResults');
        if (!input || !panel) return;

        let hits = [];
        let active = -1;
        /* Answers can overtake each other now that they come off the network.
           A stale one repainting over a newer one would show results for a
           prefix of what is in the box. */
        let seq = 0;

        const close = () => {
            panel.hidden = true;
            active = -1;
            input.setAttribute('aria-expanded', 'false');
        };

        function paintActive() {
            [...panel.querySelectorAll('[data-hit]')].forEach((el, i) => {
                el.classList.toggle('is-active', i === active);
                if (i === active) el.scrollIntoView({ block: 'nearest' });
            });
        }

        /* Only the results list is rewritten — the input keeps its node, so
           the caret never moves and no keystroke is lost mid-search. */
        async function render() {
            const q = input.value.trim();
            if (q.length < 2) {
                close();
                return;
            }

            const mine = seq + 1;
            seq = mine;

            const found = await searchAll(q);

            if (mine !== seq) return;

            hits = found;
            active = hits.length ? 0 : -1;

            panel.innerHTML = hits.length
                ? hits.map((h, i) => `
                    <a class="search-results__item${i === 0 ? ' is-active' : ''}" data-hit="${i}"
                       href="${esc(h.href)}" role="option" aria-selected="${i === 0}">
                        <i class="fa-solid ${esc(h.icon)}"></i>
                        <span class="search-results__text">
                            <b>${root.TMH.util.mark(h.title, q)}</b>
                            ${h.sub ? `<small>${esc(h.sub)}</small>` : ''}
                        </span>
                        <span class="search-results__group">${esc(h.group)}</span>
                    </a>`).join('')
                : `<p class="search-results__empty">Nothing matches “${esc(q)}”.</p>`;

            panel.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }

        input.addEventListener('input', root.TMH.util.debounce(render, 120));
        input.addEventListener('focus', () => {
            if (input.value.trim().length >= 2) render();
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                close();
                input.blur();
                return;
            }
            if (panel.hidden || !hits.length) {
                /* Enter inside the debounce window has nothing to open yet.
                   Search now and go straight to the best hit, so a fast typist
                   is not made to press Enter twice. */
                if (e.key === 'Enter') {
                    e.preventDefault();
                    render().then(() => {
                        if (hits.length) location.href = hits[0].href;
                    });
                }
                return;
            }
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                active = (active + (e.key === 'ArrowDown' ? 1 : hits.length - 1)) % hits.length;
                paintActive();
                return;
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                const hit = hits[active < 0 ? 0 : active];
                if (hit) location.href = hit.href;
            }
        });

        panel.addEventListener('mousemove', (e) => {
            const item = e.target.closest('[data-hit]');
            if (!item) return;
            active = Number(item.dataset.hit);
            paintActive();
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.topbar__search')) close();
        });
    }

    /* ---------------------------------------------------------
       TOPBAR
       --------------------------------------------------------- */
    function topbarHtml() {
        const user = me();
        return `
        <header class="topbar">
            <button type="button" class="topbar__burger" id="burger" aria-label="Open menu">
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="topbar__search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" id="globalSearch" placeholder="Search products, posts, leads..."
                       aria-label="Search the panel" role="combobox" autocomplete="off"
                       aria-controls="searchResults" aria-expanded="false">
                <kbd>/</kbd>
                <div class="search-results" id="searchResults" role="listbox"
                     aria-label="Search results" hidden></div>
            </div>

            <div class="topbar__spacer"></div>

            <button type="button" class="topbar__btn" id="themeBtn" aria-label="Toggle theme">
                <i class="fa-solid fa-moon"></i>
            </button>

            <button type="button" class="topbar__btn" id="bellBtn" aria-label="Notifications" aria-haspopup="true" aria-expanded="false">
                <i class="fa-solid fa-bell"></i><span class="dot" id="notifDot"></span>
            </button>
            <div class="menu hidden" id="notifMenu" role="menu" aria-label="Notifications">
                <div class="notif-header">
                    <h4>Notifications</h4>
                    <button type="button" class="btn btn--ghost btn--sm" id="markAllReadBtn">Mark all read</button>
                </div>
                <div class="notif-list" id="notifList"></div>
                <div class="notif-footer">
                    <a href="contact" role="menuitem">View all leads</a>
                </div>
            </div>

            <div class="menu-wrap">
                <button type="button" class="topbar__account" id="accountBtn" aria-haspopup="true" aria-expanded="false">
                    ${user.avatar
                        ? `<img class="avatar" src="${esc(user.avatar)}" alt="">`
                        : `<span class="avatar" style="display:grid;place-items:center;font-size:11px;font-weight:600;color:#fff">${esc(user.initials)}</span>`}
                    <b>${esc(user.name)}</b>
                    <i class="fa-solid fa-chevron-down"></i>
                </button>
                <div class="menu hidden" id="accountMenu" role="menu">
                    <a href="profile" role="menuitem"><i class="fa-solid fa-circle-user"></i> My profile</a>
                    <a href="profile?tab=security" role="menuitem"><i class="fa-solid fa-key"></i> Change password</a>
                    <a href="settings" role="menuitem"><i class="fa-solid fa-sliders"></i> Settings</a>
                    <a href="${SITE}" target="_blank" rel="noopener" role="menuitem"><i class="fa-solid fa-arrow-up-right-from-square"></i> View website</a>
                    <hr>
                    <!-- "Reset demo data" was here, and is gone with the mock
                         it emptied. There is no demo data to go back to now,
                         and a button that would have to mean "delete the
                         hospital's content" is not a menu item.

                         Sign out is GET /admin/logout — a link, not the API's
                         CSRF-guarded POST, because a link is what a menu item
                         is. It ends a session and creates nothing. -->
                    <a href="logout" class="danger" role="menuitem"><i class="fa-solid fa-arrow-right-from-bracket"></i> Sign out</a>
                </div>
            </div>
        </header>`;
    }

    /* ---------------------------------------------------------
       PAGE HEADER helper — every page calls this rather than
       hand-writing the same block.
       --------------------------------------------------------- */
    function pageHead(opts) {
        const o = opts || {};
        const crumbs = (o.crumb || []).map((c, i, arr) => (
            i === arr.length - 1
                ? `<span>${esc(c.label)}</span>`
                : `<a href="${esc(c.href || '#')}">${esc(c.label)}</a><i class="fa-solid fa-angle-right" style="font-size:8px"></i>`
        )).join('');

        return `
        <div class="page-head">
            <div>
                ${crumbs ? `<div class="page-head__crumb">${crumbs}</div>` : ''}
                <h1>${esc(o.title || '')} ${o.accent ? `<span>${esc(o.accent)}</span>` : ''}</h1>
                ${o.sub ? `<p class="page-head__sub">${esc(o.sub)}</p>` : ''}
            </div>
            <div class="page-head__actions">${o.actions || ''}</div>
        </div>`;
    }

    /* ---------------------------------------------------------
       BOOT
       --------------------------------------------------------- */
    function mount() {
        const app = document.querySelector('.app');
        const activeKey = document.body.dataset.page || '';

        const sidebarSlot = document.getElementById('sidebar');
        const topbarSlot = document.getElementById('topbar');
        if (sidebarSlot) sidebarSlot.outerHTML = sidebarHtml(activeKey);
        if (topbarSlot) topbarSlot.outerHTML = topbarHtml();

        /* ---- collapsed state ---- */
        try {
            if (localStorage.getItem(COLLAPSE_KEY) === '1') app.classList.add('is-collapsed');
        } catch (e) { /* ignore */ }

        const collapseBtn = document.getElementById('collapseBtn');
        if (collapseBtn) {
            collapseBtn.addEventListener('click', () => {
                app.classList.toggle('is-collapsed');
                const on = app.classList.contains('is-collapsed');
                collapseBtn.querySelector('i').className =
                    `fa-solid fa-angles-${on ? 'right' : 'left'}`;
                try {
                    localStorage.setItem(COLLAPSE_KEY, on ? '1' : '0');
                } catch (e) { /* ignore */ }
                requestAnimationFrame(syncPill);
            });
            if (app.classList.contains('is-collapsed')) {
                collapseBtn.querySelector('i').className = 'fa-solid fa-angles-right';
            }
        }

        /* ---- mobile drawer ---- */
        const burger = document.getElementById('burger');
        const scrim = document.getElementById('scrim');
        const closeDrawer = () => app.classList.remove('is-drawer-open');
        if (burger) burger.addEventListener('click', () => app.classList.toggle('is-drawer-open'));
        if (scrim) scrim.addEventListener('click', closeDrawer);
        root.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeDrawer();
        });

        /* ---- theme ---- */
        const themeBtn = document.getElementById('themeBtn');
        const paintThemeIcon = () => {
            if (!themeBtn) return;
            themeBtn.querySelector('i').className =
                `fa-solid fa-${theme.get() === 'dark' ? 'sun' : 'moon'}`;
        };
        paintThemeIcon();
        if (themeBtn) themeBtn.addEventListener('click', () => {
            theme.toggle();
            paintThemeIcon();
        });

        /* ---- account menu ---- */
        const accountBtn = document.getElementById('accountBtn');
        const accountMenu = document.getElementById('accountMenu');
        if (accountBtn && accountMenu) {
            accountBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                const open = accountMenu.classList.toggle('hidden');
                accountBtn.setAttribute('aria-expanded', String(!open));
            });
            document.addEventListener('click', () => {
                accountMenu.classList.add('hidden');
                accountBtn.setAttribute('aria-expanded', 'false');
            });
            accountMenu.addEventListener('click', (e) => e.stopPropagation());
        }

        /* ---- global search: "/" focuses it, like the public site's shortcut ---- */
        const search = document.getElementById('globalSearch');
        document.addEventListener('keydown', (e) => {
            const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)
                || e.target.isContentEditable;
            if (e.key === '/' && !typing && search) {
                e.preventDefault();
                search.focus();
            }
        });
        wireGlobalSearch();

        const bell = document.getElementById('bellBtn');
        const notifMenu = document.getElementById('notifMenu');
        const notifList = document.getElementById('notifList');
        const notifDot = document.getElementById('notifDot');
        const markAllReadBtn = document.getElementById('markAllReadBtn');

        async function loadNotifications() {
            if (!root.TMH.store) return;
            try {
                const data = await root.TMH.store.getNotifications?.() || { items: [], unread: 0 };
                renderNotifications(data);
            } catch (e) {
                console.warn('[notifications] failed to load', e);
            }
        }

        function renderNotifications(data) {
            if (!notifList) return;
            const items = data.items || [];
            const unread = data.unread || 0;

            notifDot.classList.toggle('has-unread', unread > 0);
            notifDot.setAttribute('data-count', unread);

            if (!items.length) {
                notifList.innerHTML = '<p class="notif-empty">No notifications yet</p>';
                return;
            }

            notifList.innerHTML = items.map(item => `
                <a class="notif-item${item.read ? '' : ' unread'}" href="${root.TMH.util.esc(item.href)}" role="menuitem" data-id="${root.TMH.util.esc(item.id)}">
                    <div class="notif-icon ${root.TMH.util.esc(item.type || 'info')}"><i class="fa-solid ${root.TMH.util.esc(item.icon || 'fa-bell')}"></i></div>
                    <div class="notif-content">
                        <p class="notif-title">${root.TMH.util.esc(item.title)}</p>
                        <p class="notif-time">${root.TMH.util.esc(item.time)}</p>
                    </div>
                    ${!item.read ? '<span class="notif-badge"></span>' : ''}
                </a>`).join('');
        }

        if (bell && notifMenu) {
            bell.addEventListener('click', (e) => {
                e.stopPropagation();
                const open = notifMenu.classList.toggle('hidden');
                bell.setAttribute('aria-expanded', String(!open));
                if (!open) loadNotifications();
            });

            document.addEventListener('click', (e) => {
                if (!e.target.closest('.topbar__btn#bellBtn') && !e.target.closest('#notifMenu')) {
                    notifMenu.classList.add('hidden');
                    bell.setAttribute('aria-expanded', 'false');
                }
            });

            notifMenu.addEventListener('click', (e) => e.stopPropagation());

            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', async () => {
                    try {
                        await root.TMH.store.markAllNotificationsRead?.();
                        loadNotifications();
                    } catch (e) {
                        console.warn('[notifications] mark all read failed', e);
                    }
                });
            }

            notifList?.addEventListener('click', async (e) => {
                const item = e.target.closest('.notif-item');
                if (!item) return;
                const id = item.dataset.id;
                if (id && !item.classList.contains('unread')) return;
                try {
                    await root.TMH.store.markNotificationRead?.(id);
                    item.classList.remove('unread');
                    const unreadCount = notifList.querySelectorAll('.notif-item.unread').length;
                    notifDot.classList.toggle('has-unread', unreadCount > 0);
                    notifDot.setAttribute('data-count', unreadCount);
                } catch (err) {
                    console.warn('[notifications] mark read failed', err);
                }
            });

            loadNotifications();
        }

        /* ---- pill: resync on every event that can move the item ---- */
        const pill = document.getElementById('navPill');
        requestAnimationFrame(() => {
            syncPill();
            requestAnimationFrame(() => pill && pill.classList.remove('no-anim'));
        });
        root.addEventListener('load', syncPill);
        root.addEventListener('resize', syncPill);
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(syncPill);
        const track = document.getElementById('navTrack');
        if (track && root.ResizeObserver) new ResizeObserver(syncPill).observe(track);

        /* Scroll the active item into view — the System group sits well below
           the fold on a short viewport. */
        const active = document.querySelector('.nav-item.active');
        if (active && track) {
            const top = active.offsetTop;
            if (top > track.clientHeight - 80) track.scrollTop = top - track.clientHeight / 2;
        }
    }

    root.TMH = root.TMH || {};
    root.TMH.layout = { mount, pageHead, syncPill, theme };

    /* Through TMH.boot rather than DOMContentLoaded, like every page script:
       the shell prints the signed-in name and two sidebar badges counted off
       the collections, and none of those exist until api.js has finished its
       boot request. */
    root.TMH.boot(mount);
}(window));
