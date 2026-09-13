/* =========================================================
   Sidebar definition — the single source of truth for the
   panel's navigation. core/layout.js renders this into every
   page, so adding a screen means adding one line here, not
   editing 42 files.

         key   matches <body data-page="…" />; also used to mark the
         active item when a form page declares its parent.
         href  relative to /admin/, which is where every screen is
         served from — `products` resolves to /admin/products, and
         keeps resolving when the site is in a subdirectory.
         Nothing here may reference HAZRA.api.base: this file
         parses before core/api.js defines it.
   badge optional; a number renders as a red count bubble.
         A function is called at mount time, which is after the
         boot request, so the bubble tracks the database instead
         of drifting from it the moment someone clears an inbox.
   ========================================================= */
(function () {
    /* Counts what the collection actually holds, at the moment the shell is
       painted. HAZRA.boot is what guarantees there is something to count. */
    function count(entity, test) {
        try {
            return (window.HAZRA.store.allSync(entity) || []).filter(test).length;
        } catch (e) {
            return 0;
        }
    }

    window.HAZRA_NAV_COUNT = count;
}());

window.HAZRA_NAV = [
    {
        label: 'Main',
        items: [
            { key: 'dashboard', label: 'Dashboard', icon: 'fa-house', href: '/admin/dashboard' },
            { key: 'products', label: 'Products', icon: 'fa-bicycle', href: '/admin/products' },
            { key: 'blogs', label: 'Blogs', icon: 'fa-pen-nib', href: '/admin/blogs' },
            { key: 'news', label: 'News', icon: 'fa-newspaper', href: '/admin/news' },
            { key: 'gallery', label: 'Gallery', icon: 'fa-images', href: '/admin/gallery' },
            { key: 'jobs', label: 'Jobs', icon: 'fa-briefcase', href: '/admin/jobs' },
            { key: 'test-drive', label: 'Test Drive', icon: 'fa-road', href: '/admin/test-drive' },
            { key: 'dealership', label: 'Dealership', icon: 'fa-store', href: '/admin/dealership' },
            { key: 'contact', label: 'Contact', icon: 'fa-envelope', href: '/admin/contact' },
            { key: 'career-apps', label: 'Career Apps', icon: 'fa-file-signature', href: '/admin/career-apps' },
        ],
    },
    {
        label: 'System',
        items: [
            { key: 'settings', label: 'Settings', icon: 'fa-sliders', href: '/admin/settings' },
        ],
    },
];
