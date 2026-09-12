(function () {
    function count(entity, test) {
        try {
            return (window.TMH.store.allSync(entity) || []).filter(test).length;
        } catch (e) {
            return 0;
        }
    }
    window.TMH_NAV_COUNT = count;
}());

window.TMH_NAV = [
    {
        label: 'Main',
        items: [
            { key: 'dashboard', label: 'Dashboard', icon: 'fa-gauge-high', href: 'dashboard' },
        ],
    },
    {
        label: 'Catalog & Content',
        items: [
            { key: 'products', label: 'Products', icon: 'fa-charging-station', href: 'products' },
            { key: 'blogs', label: 'Blogs', icon: 'fa-newspaper', href: 'blogs' },
            { key: 'news', label: 'News', icon: 'fa-bullhorn', href: 'news' },
            { key: 'gallery', label: 'Gallery', icon: 'fa-images', href: 'gallery' },
        ],
    },
    {
        label: 'Careers',
        items: [
            { key: 'jobs', label: 'Jobs', icon: 'fa-briefcase', href: 'jobs' },
        ],
    },
    {
        label: 'Leads & Applications',
        items: [
            { key: 'test-drive', label: 'Test Drive', icon: 'fa-car-side', href: 'test-drive' },
            { key: 'dealership', label: 'Dealership', icon: 'fa-store', href: 'dealership' },
            { key: 'contact', label: 'Contact', icon: 'fa-envelope-open-text', href: 'contact' },
            { key: 'career-apps', label: 'Career Apps', icon: 'fa-file-signature', href: 'career-apps' },
        ],
    },
    {
        label: 'System',
        items: [
            { key: 'settings', label: 'Settings', icon: 'fa-gear', href: 'settings' },
        ],
    },
];
