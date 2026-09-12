(function () {
    'use strict';

    const { util: U, store, layout } = window.TMH;
    const SITE = window.TMH.api.base;

    window.TMH.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Main' }, { label: 'Dashboard' }],
            title: 'Dashboard',
            accent: 'Overview',
            sub: 'Welcome to Hazra EV Admin Control Panel.',
            actions: `
                <a class="btn btn--ghost" href="${SITE}" target="_blank" rel="noopener">
                    <i class="fa-solid fa-globe"></i> View Website</a>
                <a class="btn btn--primary" href="products">
                    <i class="fa-solid fa-charging-station"></i> Manage Products</a>`,
        });

        document.getElementById('view').innerHTML = `
            <section class="grid-stats" id="statStrip"></section>
            <div class="grid grid--2 gap-lg mt-xl">
                <div class="card p-lg">
                    <h3>Quick Actions</h3>
                    <div class="quick-actions-grid mt-md">
                        <a href="product-form" class="btn btn--outline"><i class="fa-solid fa-plus"></i> Add Product</a>
                        <a href="blog-form" class="btn btn--outline"><i class="fa-solid fa-pen"></i> New Blog Post</a>
                        <a href="news-form" class="btn btn--outline"><i class="fa-solid fa-newspaper"></i> Publish News</a>
                        <a href="job-form" class="btn btn--outline"><i class="fa-solid fa-briefcase"></i> Post Vacancy</a>
                    </div>
                </div>
                <div class="card p-lg">
                    <h3>System Info</h3>
                    <p class="text-sub mt-md">Hazra EV Control Panel running v2.0 (Teresa Shell Pattern)</p>
                </div>
            </div>
        `;

        paintStats();
    }

    async function paintStats() {
        let stats = {
            products: 0,
            blogs: 0,
            news: 0,
            gallery: 0,
            open_jobs: 0,
            test_drive_leads: 0,
            dealership_leads: 0,
            contact_leads: 0,
            career_applications: 0
        };

        try {
            const res = await store.summary();
            if (res && res.stats) {
                stats = Object.assign(stats, res.stats);
            }
        } catch (e) {
            console.warn('Could not load summary stats:', e);
        }

        const cards = [
            ['fa-charging-station', 'navy', stats.products, 'Products', 'Active EV Models', 'products'],
            ['fa-newspaper', 'blue', stats.blogs, 'Blog Posts', 'Published & Drafts', 'blogs'],
            ['fa-bullhorn', 'magenta', stats.news, 'News Updates', 'Latest News Posts', 'news'],
            ['fa-images', 'red', stats.gallery, 'Gallery Items', 'Photos & Banners', 'gallery'],
            ['fa-briefcase', 'green', stats.open_jobs, 'Open Jobs', 'Active Openings', 'jobs'],
            ['fa-car-side', 'navy', stats.test_drive_leads, 'Test Drive Leads', 'Bookings', 'test-drive'],
            ['fa-store', 'blue', stats.dealership_leads, 'Dealership Leads', 'Partner Inquiries', 'dealership'],
            ['fa-envelope-open-text', 'magenta', stats.contact_leads, 'Contact Messages', 'General Inquiries', 'contact'],
            ['fa-file-signature', 'green', stats.career_applications, 'Job Applications', 'Submitted Resumes', 'career-apps'],
        ];

        document.getElementById('statStrip').innerHTML = cards.map(([icon, tone, val, label, sub, href]) => `
            <a href="${href}" class="card stat anim-item" style="text-decoration:none;color:inherit;">
                <div class="stat__icon ${tone}"><i class="fa-solid ${icon}"></i></div>
                <h3>${U.num(val)}</h3>
                <p>${U.esc(label)}</p>
                <span class="delta flat">${U.esc(sub)}</span>
            </a>
        `).join('');

        U.stagger(document.getElementById('statStrip'));
    }
}());
