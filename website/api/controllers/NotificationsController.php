<?php

require_once __DIR__ . '/../support/V1Controller.php';

/**
 * The in-app feed. Fan-out lives in notification_recipients, so one product
 * listing notifies the whole field team while each person's read state stays
 * their own.
 *
 * Badge count comes from /bootstrap at launch and from
 * `?limit=0&unreadOnly=true` afterwards.
 */
final class NotificationsController extends V1Controller
{
    public function index(): never
    {
        Envelope::noStore();

        $where = ['nr.user_id = ?'];
        $params = [Ctx::id()];

        if (Wire::bool($this->query('unreadOnly', false))) {
            $where[] = 'nr.read_at IS NULL';
        }

        if ($since = Wire::ts((string) $this->query('updatedSince', ''))) {
            $where[] = 'n.created_at > ?';
            $params[] = $since;
        }

        $clause = implode(' AND ', $where);

        $total = (int) (db_fetch_one(
            "SELECT COUNT(*) AS n FROM notification_recipients nr
               JOIN notifications n ON n.id = nr.notification_id
              WHERE {$clause}",
            $params,
        )['n'] ?? 0);

        $limitRaw = $this->query('limit');

        if (Cursor::isCountOnly($limitRaw)) {
            Envelope::ok([], ['total' => $total]);
        }

        $limit = Cursor::limit($limitRaw);
        $cursor = Cursor::decode($this->query('cursor'));

        if ($cursor !== null) {
            $clause .= ' AND (n.created_at < ? OR (n.created_at = ? AND n.id < ?))';
            $params[] = $cursor['t'];
            $params[] = $cursor['t'];
            $params[] = $cursor['id'];
        }

        $rows = db_fetch_all(
            "SELECT n.*, nr.read_at FROM notification_recipients nr
               JOIN notifications n ON n.id = nr.notification_id
              WHERE {$clause}
              ORDER BY n.created_at DESC, n.id DESC
              LIMIT ?",
            [...$params, $limit + 1],
        );

        $next = null;
        if (count($rows) > $limit) {
            $rows = array_slice($rows, 0, $limit);
            $last = $rows[count($rows) - 1];
            $next = Cursor::encode(['t' => $last['created_at'], 'id' => $last['id']]);
        }

        Envelope::ok(
            array_map([Present::class, 'notification'], $rows),
            ['total' => $total, 'nextCursor' => $next],
        );
    }

    public function update(): never
    {
        $id = (string) $this->param('id');
        $read = Wire::bool($this->input('read', true));

        $affected = db_execute(
            "UPDATE notification_recipients SET read_at = ? WHERE notification_id = ? AND user_id = ?",
            [$read ? Wire::now() : null, $id, Ctx::id()],
        );

        if ($affected === 0) {
            Envelope::notFound('NOTIFICATION_NOT_FOUND', 'No such notification for this user');
        }

        $row = db_fetch_one(
            "SELECT n.*, nr.read_at FROM notification_recipients nr
               JOIN notifications n ON n.id = nr.notification_id
              WHERE nr.notification_id = ? AND nr.user_id = ?",
            [$id, Ctx::id()],
        );

        Envelope::ok(Present::notification($row));
    }

    public function readAll(): never
    {
        $affected = db_execute(
            "UPDATE notification_recipients SET read_at = ? WHERE user_id = ? AND read_at IS NULL",
            [Wire::now(), Ctx::id()],
        );

        Envelope::ok(['marked' => $affected, 'unreadNotifications' => 0]);
    }
}
