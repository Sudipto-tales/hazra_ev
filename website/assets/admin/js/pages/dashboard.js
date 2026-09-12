(function () {
    'use strict';

    const { util: U, store, layout } = window.TMH;
    const SITE = window.TMH.api.base;

    const STAT_TILES = [
        ['products', 'Products', 'fa-bicycle', 'red'],
        ['blogs', 'Blogs', 'fa-pen-nib', 'navy'],
        ['news', 'News', 'fa-newspaper', 'blue'],
        ['gallery', 'Gallery', 'fa-images', 'magenta'],
        ['open_jobs', 'Open jobs', 'fa-briefcase', 'red'],
        ['test_drive_leads', 'Test drive leads', 'fa-road', 'navy'],
        ['dealership_leads', 'Dealership leads', 'fa-store', 'blue'],
        ['contact_leads', 'Contact leads', 'fa-envelope', 'magenta'],
        ['career_applications', 'Career applications', 'fa-file-signature', 'red'],
    ];

    window.TMH.boot(init);

    async function init() {
        const who = window.TMH.session ? window.TMH.session.currentSync() : null;
        const hour = new Date().getHours();
        const greeting = hour < 12 ? 'Good morning' : (hour < 17 ? 'Good afternoon' : 'Good evening');
        const firstName = who && who.name ? who.name.split(' ')[0] : 'there';

        document.getElementById('pageHead').innerHTML = layout.pageHead({
            title: 'Dashboard',
            accent: 'Overview',
            crumb: [{ label: 'Main' }, { label: 'Dashboard' }],
            actions: `<a class="btn btn--ghost" href="${SITE}" target="_blank" rel="noopener">`
                + '<i class="fa-solid fa-arrow-up-right-from-square"></i> View website</a>',
        });

        const view = document.getElementById('view');
        view.innerHTML = '<article class="card"><div class="empty"><p>Loading dashboard...</p></div></article>';

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
            document.getElementById('retry').addEventListener('click', init);
            return;
        }

        let settings = {};
        try {
            settings = await store.getDoc('settings');
        } catch (e) { /* The dashboard still has a useful fallback name. */ }

        const general = settings.general || {};
        const siteName = general.site_name || general.name || 'Hazra EV';
        const stats = data.stats || {};

        view.innerHTML = `
            <div class="bento mb-4">
                ${welcomeHtml(siteName, `${greeting}, ${firstName}.`)}
                ${leadSummaryHtml(stats)}
            </div>
            ${statsHtml(stats)}
            <div class="bento">
                <article class="card c8">
                    <div class="card__head"><h2>Quick actions</h2></div>
                    ${quickActionsHtml()}
                </article>
                <article class="card c4">
                    <div class="card__head"><h2>Lead inbox</h2></div>
                    ${leadLinksHtml(stats)}
                </article>
            </div>`;

        U.stagger(view);
    }

    function welcomeHtml(siteName, greeting) {
        return `
        <article class="card welcome-card c8 anim-item">
            <div class="welcome-card__copy">
                <h2 class="welcome-card__hii">Welcome to <b>${U.esc(siteName)}</b></h2>
                <p class="welcome-card__note">${U.esc(greeting)} Here is the latest activity across your EV business.</p>
            </div>
            <div class="welcome-card__art welcome-card__art--ev" aria-hidden="true">
                <i class="fa-solid fa-bicycle"></i>
            </div>
        </article>`;
    }

    function leadSummaryHtml(stats) {
        const total = (stats.test_drive_leads || 0) + (stats.dealership_leads || 0) + (stats.contact_leads || 0);
        return `
        <article class="card meter-card c4 anim-item">
            <div class="card__head"><h3>Lead overview</h3></div>
            <div class="empty--sm"><strong>${U.esc(U.num(total))}</strong> incoming leads</div>
            <ul class="meter-list">
                ${leadRow('Test drive', stats.test_drive_leads, 'test-drive')}
                ${leadRow('Dealership', stats.dealership_leads, 'dealership')}
                ${leadRow('Contact', stats.contact_leads, 'contact')}
            </ul>
        </article>`;
    }

    function leadRow(label, value, href) {
        return `<li><a href="${href}"><span>${label}</span><b>${U.esc(U.num(value || 0))}</b></a></li>`;
    }

    function statsHtml(stats) {
        return `<div class="bento mb-4">${STAT_TILES.map(([key, label, icon, tone]) => `
            <article class="card stat c3 anim-item">
                <div class="stat__icon ${tone}"><i class="fa-solid ${icon}"></i></div>
                <h3>${U.esc(U.num(stats[key] || 0))}</h3>
                <p>${label}</p>
            </article>`).join('')}</div>`;
    }

    function quickActionsHtml() {
        const actions = [
            ['fa-plus', 'Add product', 'product-form'],
            ['fa-pen-nib', 'New blog', 'blog-form'],
            ['fa-briefcase', 'Post job', 'job-form'],
            ['fa-envelope-open-text', 'View leads', 'contact'],
        ];

        return `<div class="quick-actions">${actions.map(([icon, label, href]) => `
            <a class="quick-action" href="${href}">
                <i class="fa-solid ${icon}"></i><span>${label}</span>
            </a>`).join('')}</div>`;
    }

    function leadLinksHtml(stats) {
        return `<ul class="feed">
            <li class="feed__item"><span class="feed__icon info"><i class="fa-solid fa-road"></i></span><span class="feed__body"><a href="test-drive"><b>Test drive leads</b></a><span>${U.esc(U.num(stats.test_drive_leads || 0))} total</span></span></li>
            <li class="feed__item"><span class="feed__icon info"><i class="fa-solid fa-store"></i></span><span class="feed__body"><a href="dealership"><b>Dealership leads</b></a><span>${U.esc(U.num(stats.dealership_leads || 0))} total</span></span></li>
            <li class="feed__item"><span class="feed__icon info"><i class="fa-solid fa-envelope"></i></span><span class="feed__body"><a href="contact"><b>Contact leads</b></a><span>${U.esc(U.num(stats.contact_leads || 0))} total</span></span></li>
        </ul>`;
    }
}());
