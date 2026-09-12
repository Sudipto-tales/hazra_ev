/* =========================================================
   Sidebar definition — the single source of truth for the
   panel's navigation. core/layout.js renders this into every
   page, so adding a screen means adding one line here, not
   editing 42 files.

   key   matches <body data-page="…">; also used to mark the
         active item when a form page declares its parent
         (e.g. doctor-form sets data-page="doctors")
   href  relative to /admin/, which is where every screen is
         served from — `doctors` resolves to /admin/doctors, and
         keeps resolving when the site is in a subdirectory.
         Nothing here may reference TMH.api.base: this file
         parses before core/api.js defines it.
   badge optional; a number renders as a red count bubble.
         A function is called at mount time, which is after the
         boot request, so the bubble tracks the database instead
         of drifting from it the moment someone clears an inbox.
   ========================================================= */
(function () {
    /* Counts what the collection actually holds, at the moment the shell is
       painted. TMH.boot is what guarantees there is something to count. */
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
            { key: 'dashboard', label: 'Dashboard', icon: 'fa-house-medical', href: 'dashboard' },
            { key: 'analytics', label: 'Web Analytics', icon: 'fa-chart-line', href: 'analytics' },
        ],
    },
    {
        label: 'Content',
        items: [
            { key: 'doctors', label: 'Doctors', icon: 'fa-user-doctor', href: 'doctors' },
            { key: 'leadership', label: 'Leadership', icon: 'fa-user-tie', href: 'leadership' },
            { key: 'departments', label: 'Departments', icon: 'fa-hospital', href: 'departments' },
            { key: 'facilities', label: 'Facilities', icon: 'fa-bed-pulse', href: 'facilities' },
            { key: 'lab-tests', label: 'Lab Tests', icon: 'fa-flask-vial', href: 'lab-tests' },
            { key: 'blog', label: 'Blog & News', icon: 'fa-newspaper', href: 'blog' },
            { key: 'blog-categories', label: 'Categories', icon: 'fa-tags', href: 'blog-categories' },
            { key: 'testimonials', label: 'Testimonials', icon: 'fa-comment-medical', href: 'testimonials' },
            { key: 'faqs', label: 'FAQs', icon: 'fa-circle-question', href: 'faqs' },
            { key: 'gallery-items', label: 'Our Gallery', icon: 'fa-photo-film', href: 'gallery-items' },
            { key: 'gallery', label: 'Media Gallery', icon: 'fa-images', href: 'gallery' },
        ],
    },
    {
        label: 'Pages',
        items: [
            { key: 'pages', label: 'All Pages', icon: 'fa-file-lines', href: 'pages' },
            { key: 'page-home', label: 'Home Page', icon: 'fa-house', href: 'page-home' },
            { key: 'page-about', label: 'About Page', icon: 'fa-circle-info', href: 'page-about' },
            { key: 'page-contact', label: 'Contact Page', icon: 'fa-map-location-dot', href: 'page-contact' },
            { key: 'page-careers', label: 'Careers Page', icon: 'fa-briefcase', href: 'page-careers' },
            { key: 'stats', label: 'Counters & Numbers', icon: 'fa-arrow-up-9-1', href: 'stats' },
        ],
    },
    {
        label: 'Careers',
        items: [
            { key: 'jobs', label: 'Vacancies', icon: 'fa-bullhorn', href: 'jobs' },
            {
                key: 'applications', label: 'Applications', icon: 'fa-file-signature', href: 'applications',
                badge: () => window.TMH_NAV_COUNT('applications', (a) => a.stage === 'new'),
            },
        ],
    },
    {
        label: 'Growth',
        items: [
            {
                key: 'enquiries', label: 'Enquiries', icon: 'fa-envelope-open-text', href: 'enquiries',
                badge: () => window.TMH_NAV_COUNT('enquiries', (e) => e.status === 'new'),
            },
            /* No badge: the screen is read-only, so a count would be nagging
               the user about work the panel gives them no way to do. */
            { key: 'appointments', label: 'Appointments', icon: 'fa-calendar-check', href: 'appointments' },
            { key: 'seo', label: 'SEO Manager', icon: 'fa-magnifying-glass-chart', href: 'seo' },
            { key: 'navigation', label: 'Navigation', icon: 'fa-sitemap', href: 'navigation' },
            { key: 'redirects', label: 'Redirects', icon: 'fa-right-left', href: 'redirects' },
        ],
    },
    {
        label: 'System',
        items: [
            { key: 'settings-general', label: 'General Settings', icon: 'fa-sliders', href: 'settings-general' },
            { key: 'settings-contact', label: 'Contact Details', icon: 'fa-address-book', href: 'settings-contact' },
            { key: 'settings-social', label: 'Social Links', icon: 'fa-share-nodes', href: 'settings-social' },
            { key: 'settings-integrations', label: 'Integrations', icon: 'fa-plug', href: 'settings-integrations' },
            { key: 'settings-theme', label: 'Theme & Branding', icon: 'fa-palette', href: 'settings-theme' },
            { key: 'settings-popups', label: 'Popups & Cookie Bar', icon: 'fa-rectangle-ad', href: 'settings-popups' },
            { key: 'users', label: 'Users & Roles', icon: 'fa-user-shield', href: 'users' },
            { key: 'activity-log', label: 'Activity Log', icon: 'fa-clock-rotate-left', href: 'activity-log' },
            { key: 'profile', label: 'My Profile', icon: 'fa-circle-user', href: 'profile' },
        ],
    },
];
