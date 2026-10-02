<?php

require_once __DIR__ . '/../support/V1Controller.php';
require_once __DIR__ . '/../support/Users.php';

/**
 * GET /bootstrap — replaces profile() + config() + the notification badge +
 * pendingReviewCount() at launch. Both shells call it; `principal.type` decides
 * which one boots.
 */
final class BootstrapController extends V1Controller
{
    public function index(): never
    {
        Envelope::noStore();

        $principal = Ctx::principal();

        $counters = [
            'unreadNotifications' => (int) (db_fetch_one(
                "SELECT COUNT(*) AS n FROM notification_recipients WHERE user_id = ? AND read_at IS NULL",
                [$principal['id']],
            )['n'] ?? 0),
        ];

        // The review inbox is an admin surface; an employee has no pending count.
        if (Ctx::isAdmin()) {
            $counters['pendingReviews'] = (int) (db_fetch_one(
                "SELECT COUNT(*) AS n
                   FROM report_reviews rv
                   JOIN visit_reports r ON r.id = rv.report_id
                   JOIN users u ON u.id = r.employee_id
                  WHERE rv.decision = 'pending' AND u.org_id = ?",
                [Ctx::orgId()],
            )['n'] ?? 0);
        }

        Envelope::ok([
            'principal'   => Present::principal(Users::byId($principal['id'])),
            'config'      => Present::config(Ctx::config()),
            'preferences' => Present::preferences(Users::preferences($principal['id'])),
            'counters'    => $counters,
            'serverTime'  => Wire::now(),
        ]);
    }
}
