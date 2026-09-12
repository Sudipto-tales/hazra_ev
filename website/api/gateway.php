<?php

require_once __DIR__ . '/../core/RouteProvider.php';

/**
 * The API contract, as built. `docs/02-API-PLAN.md` is authoritative — this
 * table is its route section made executable, in the same order.
 *
 * Target is one endpoint per data shape, not one per screen: where two callers
 * want the same shape at a different scope, the scope is the `subject`
 * parameter; where a caller wants an extra join, the join is an `include`.
 *
 * The third element is the framework's route middleware. 'auth' means a valid
 * bearer token is required. Role is NOT enforced here — RouteManager knows only
 * 'auth' — so every admin-only action calls Ctx::requireAdmin() itself, and
 * `subject` is authorised inside Ctx rather than at the edge.
 *
 * The framework's demo UserController is intentionally unrouted: it serves the
 * scaffold's `users_tbl`, and its POST /auth/login collides with the real one
 * below. The file is left in place; nothing dispatches to it.
 */
class ApiGatewayProvider extends RouteProvider
{
    public static function routes(): array
    {
        return [
            // --- Auth (public) -------------------------------------------- §3.1
            'POST:api/v1/auth/login'    => ['AuthController', 'login'],
            'POST:api/v1/auth/refresh'  => ['AuthController', 'refresh'],
            'POST:api/v1/auth/logout'   => ['AuthController', 'logout',  'auth'],
            'PUT:api/v1/me/device'      => ['AuthController', 'device',  'auth'],

            // --- Bootstrap ------------------------------------------------- §2
            'GET:api/v1/bootstrap'      => ['BootstrapController', 'index', 'auth'],

            // --- Principal and preferences --------------------------------- §3.2
            'GET:api/v1/me'                => ['MeController', 'show',              'auth'],
            'PATCH:api/v1/me'              => ['MeController', 'update',            'auth'],
            'GET:api/v1/me/preferences'    => ['MeController', 'preferences',       'auth'],
            'PATCH:api/v1/me/preferences'  => ['MeController', 'updatePreferences', 'auth'],
            'POST:api/v1/me/password'      => ['MeController', 'changePassword',    'auth'],

            // --- Employees: roster, dashboard and member-by-id -------------- §3.3
            'GET:api/v1/employees'         => ['EmployeesController', 'index',  'auth'],
            'POST:api/v1/employees'        => ['EmployeesController', 'store',  'auth'],
            'GET:api/v1/employees/{id}'    => ['EmployeesController', 'show',   'auth'],
            'PATCH:api/v1/employees/{id}'  => ['EmployeesController', 'update', 'auth'],
            'POST:api/v1/employees/{id}/password' => ['EmployeesController', 'password', 'auth'],

            // --- Days: employee Home and the admin day view ----------------- §3.4
            'GET:api/v1/days/{subject}'           => ['DaysController', 'show',     'auth'],
            'POST:api/v1/days/{subject}/closeout' => ['DaysController', 'closeout', 'auth'],
            'POST:api/v1/days/{id}/reopen'        => ['DaysController', 'reopen',   'auth'],

            // --- Attendance: both calendars and the team matrix -------------- §3.5
            'GET:api/v1/attendance'        => ['AttendanceController', 'index', 'auth'],

            // --- Statistics: one route, two scopes --------------------------- §3.6
            'GET:api/v1/statistics'        => ['StatisticsController', 'index', 'auth'],

            // --- Routes: a single track is n = 1 ----------------------------- §3.7
            'GET:api/v1/routes'            => ['RoutesController', 'index', 'auth'],

            // --- Reports: one route serves three lists ----------------------- §3.8
            'GET:api/v1/reports'                => ['ReportsController', 'index',  'auth'],
            'POST:api/v1/reports'               => ['ReportsController', 'store',  'auth'],
            'GET:api/v1/reports/{id}'           => ['ReportsController', 'show',   'auth'],
            'POST:api/v1/reports/{id}/images'   => ['ReportsController', 'images', 'auth'],
            'POST:api/v1/reports/{id}/review'   => ['ReportsController', 'review', 'auth'],

            // --- Catalogue: one route, role decides visibility ---------------- §3.9
            'GET:api/v1/products'          => ['ProductsController', 'index',  'auth'],
            'POST:api/v1/products'         => ['ProductsController', 'store',  'auth'],
            'GET:api/v1/products/{id}'     => ['ProductsController', 'show',   'auth'],
            'PATCH:api/v1/products/{id}'   => ['ProductsController', 'update', 'auth'],

            // --- Companies --------------------------------------------------- §3.10
            'GET:api/v1/companies'         => ['CompaniesController', 'index', 'auth'],

            // --- Tracking write path ------------------------------------------ §3.11
            'POST:api/v1/tracking/sessions'       => ['TrackingController', 'openSession',  'auth'],
            'PATCH:api/v1/tracking/sessions/{id}' => ['TrackingController', 'closeSession', 'auth'],
            'POST:api/v1/tracking/locations'      => ['TrackingController', 'locations',    'auth'],
            'POST:api/v1/tracking/health'         => ['TrackingController', 'health',       'auth'],

            // --- Configuration -------------------------------------------------- §3.12
            'GET:api/v1/config'            => ['ConfigController', 'show',   'auth'],
            'PUT:api/v1/config'            => ['ConfigController', 'update', 'auth'],

            // --- Notifications --------------------------------------------------- §3.13
            'GET:api/v1/notifications'           => ['NotificationsController', 'index',   'auth'],
            'PATCH:api/v1/notifications/{id}'    => ['NotificationsController', 'update',  'auth'],
            'POST:api/v1/notifications/read-all' => ['NotificationsController', 'readAll', 'auth'],

            // --- Realtime ---------------------------------------------------------- §1.6
            'GET:api/v1/stream'            => ['StreamController', 'index', 'auth'],

            // --- Website Public & Admin Management --------------------------------
            'GET:api/v1/website/products'        => ['WebsiteController', 'products'],
            'GET:api/v1/website/settings'        => ['WebsiteController', 'settings'],
            'POST:api/v1/website/settings'       => ['WebsiteController', 'updateSettings', 'auth'],
            'POST:api/v1/website/leads'          => ['WebsiteController', 'storeLead'],
            'GET:api/v1/website/leads'           => ['WebsiteController', 'leads', 'auth'],
            'PATCH:api/v1/website/leads/{id}'    => ['WebsiteController', 'updateLeadStatus', 'auth'],

            // --- Admin Content: Posts (Blogs & News) ----------------------------
            'GET:api/v1/posts'              => ['ContentController', 'listPosts'],
            'GET:api/v1/posts/{id}'         => ['ContentController', 'showPost'],
            'POST:api/v1/posts'             => ['ContentController', 'storePost',  'auth'],
            'PATCH:api/v1/posts/{id}'       => ['ContentController', 'updatePost', 'auth'],
            'DELETE:api/v1/posts/{id}'      => ['ContentController', 'deletePost', 'auth'],

            // --- Admin Content: Gallery -----------------------------------------
            'GET:api/v1/gallery'            => ['ContentController', 'listGallery'],
            'GET:api/v1/gallery/{id}'       => ['ContentController', 'showGallery'],
            'POST:api/v1/gallery'           => ['ContentController', 'storeGallery',  'auth'],
            'PATCH:api/v1/gallery/{id}'     => ['ContentController', 'updateGallery', 'auth'],
            'DELETE:api/v1/gallery/{id}'    => ['ContentController', 'deleteGallery', 'auth'],

            // --- Admin Content: Jobs --------------------------------------------
            'GET:api/v1/jobs'               => ['ContentController', 'listJobs'],
            'GET:api/v1/jobs/{id}'          => ['ContentController', 'showJob'],
            'POST:api/v1/jobs'              => ['ContentController', 'storeJob',  'auth'],
            'PATCH:api/v1/jobs/{id}'        => ['ContentController', 'updateJob', 'auth'],
            'DELETE:api/v1/jobs/{id}'       => ['ContentController', 'deleteJob', 'auth'],

            // --- Admin Shell Auth & Dashboard -----------------------------
            'GET:api/v1/admin/me'        => ['AdminAuthController', 'me'],
            'POST:api/v1/admin/login'    => ['AdminAuthController', 'login'],
            'POST:api/v1/admin/logout'   => ['AdminAuthController', 'logout'],
            'GET:api/v1/admin/bootstrap' => ['AdminController', 'bootstrap'],
            'GET:api/v1/admin/summary'   => ['AdminController', 'summary'],
            'GET:api/v1/settings'        => ['AdminController', 'getSettings'],
            'PATCH:api/v1/settings/{group}' => ['AdminController', 'patchSettings'],

            // --- Admin Content: Job Applications --------------------------------
            'GET:api/v1/job-applications'           => ['ContentController', 'listApplications',  'auth'],
            'POST:api/v1/job-applications'          => ['ContentController', 'storeApplication'],
            'PATCH:api/v1/job-applications/{id}'    => ['ContentController', 'updateApplication', 'auth'],
            'DELETE:api/v1/job-applications/{id}'   => ['ContentController', 'deleteApplication', 'auth'],
        ];
    }
}

return ApiGatewayProvider::routes();
