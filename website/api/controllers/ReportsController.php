<?php

require_once __DIR__ . '/../support/V1Controller.php';
require_once __DIR__ . '/../support/Users.php';

/**
 * One route serves three lists — the employee's today, the employee's history
 * and the admin review inbox — plus the badge count via `?limit=0`.
 *
 * `company=` filters on the free-text companyName, and the filter chips are
 * built from `meta.facets.companies` returned alongside: with an open text
 * field there is no master list to enumerate.
 */
final class ReportsController extends V1Controller
{
    private const MAX_IMAGE_BYTES = 8 * 1024 * 1024;
    private const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function index(): never
    {
        Envelope::noStore();

        $subject = $this->subject();

        [$clause, $params] = $this->inClause('r.employee_id', $subject['ids']);
        $where = [$clause];

        if ($this->hasQuery('date')) {
            $where[] = 'r.work_date = ?';
            $params[] = $this->dateParam();
        } elseif ($this->hasQuery('from') || $this->hasQuery('to')) {
            $from = Wire::date((string) $this->query('from', '1970-01-01'));
            $to   = Wire::date((string) $this->query('to', Ctx::today()));
            $where[] = 'r.work_date BETWEEN ? AND ?';
            array_push($params, $from, $to);
        }

        if ($status = Wire::enumIn((string) $this->query('status', ''), ['draft', 'queued', 'uploading', 'submitted', 'failed', 'reviewed'])) {
            $where[] = 'r.status = ?';
            $params[] = $status;
        }

        if ($decision = Wire::enumIn((string) $this->query('decision', ''), ['pending', 'approved', 'rejected'])) {
            $where[] = "COALESCE(rv.decision, 'pending') = ?";
            $params[] = $decision;
        }

        if ($company = $this->query('company')) {
            $where[] = 'r.company_name = ?';
            $params[] = $company;
        }

        if ($query = $this->query('query')) {
            $where[] = '(r.title LIKE ? OR r.body LIKE ? OR r.company_name LIKE ?)';
            array_push($params, "%{$query}%", "%{$query}%", "%{$query}%");
        }

        $clause = implode(' AND ', $where);
        $join = "FROM visit_reports r LEFT JOIN report_reviews rv ON rv.report_id = r.id WHERE {$clause}";

        $total = (int) (db_fetch_one("SELECT COUNT(*) AS n {$join}", $params)['n'] ?? 0);

        // limit=0 returns meta.total with an empty data array. That is how the
        // nav badge is fetched, and it replaces pendingReviewCount().
        $limitRaw = $this->query('limit');

        if (Cursor::isCountOnly($limitRaw)) {
            Envelope::ok([], ['total' => $total, 'facets' => $this->facets($join, $params)]);
        }

        $limit = Cursor::limit($limitRaw);
        $cursor = Cursor::decode($this->query('cursor'));

        $pageParams = $params;
        $pageClause = $clause;

        if ($cursor !== null) {
            $pageClause .= ' AND (r.submitted_at < ? OR (r.submitted_at = ? AND r.id < ?))';
            array_push($pageParams, $cursor['t'], $cursor['t'], $cursor['id']);
        }

        $rows = db_fetch_all(
            "SELECT r.*, rv.decision, rv.note, rv.reviewed_by, rv.reviewed_at
               FROM visit_reports r
               LEFT JOIN report_reviews rv ON rv.report_id = r.id
              WHERE {$pageClause}
              ORDER BY r.submitted_at DESC, r.id DESC
              LIMIT ?",
            [...$pageParams, $limit + 1],
        );

        $next = null;
        if (count($rows) > $limit) {
            $rows = array_slice($rows, 0, $limit);
            $last = $rows[count($rows) - 1];
            $next = Cursor::encode(['t' => $last['submitted_at'], 'id' => $last['id']]);
        }

        Envelope::ok($this->hydrate($rows), [
            'total'      => $total,
            'nextCursor' => $next,
            'facets'     => $this->facets($join, $params),
        ]);
    }

    public function show(): never
    {
        Envelope::noStore();

        $row = $this->find((string) $this->param('id'));

        Envelope::ok($this->hydrate([$row])[0]);
    }

    /**
     * Carries a device-generated clientId, so an offline retry cannot create a
     * duplicate. The Idempotency-Key header replays the original response.
     */
    public function store(): never
    {
        if (Ctx::isAdmin()) {
            Envelope::forbidden('Reports are filed by employees');
        }

        $body = ApiRequest::body();

        foreach (['companyName', 'title'] as $field) {
            if (empty($body[$field])) {
                Envelope::invalid("{$field} is required", $field);
            }
        }

        $clientId = (string) ($body['clientId'] ?? Uuid::v4());

        $existing = db_fetch_one(
            "SELECT id FROM visit_reports WHERE employee_id = ? AND client_id = ?",
            [Ctx::id(), $clientId],
        );

        if ($existing) {
            // Same clientId, same report. A retry is not a second visit.
            Envelope::ok($this->hydrate([$this->find($existing['id'])])[0]);
        }

        $this->idempotent('reports.store', fn() => $this->create($body, $clientId));
    }

    /** Multipart upload. A report can submit while its images are still queued. */
    public function images(): never
    {
        $report = $this->find((string) $this->param('id'));

        if ($report['employee_id'] !== Ctx::id() && !Ctx::isAdmin()) {
            Envelope::forbidden('Only the author can attach images');
        }

        $files = $_FILES['images'] ?? $_FILES['image'] ?? null;

        if (!$files) {
            Envelope::invalid('Expected a multipart field named "images"', 'images');
        }

        $names = is_array($files['name']) ? $files['name'] : [$files['name']];
        $tmp   = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
        $sizes = is_array($files['size']) ? $files['size'] : [$files['size']];

        $directory = __BASEDIR__ . '/storage/uploads/reports/' . $report['id'];

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            Envelope::fail('UPLOAD_FAILED', 'Could not create the upload directory', 500);
        }

        $position = (int) (db_fetch_one(
            "SELECT COALESCE(MAX(position), -1) AS p FROM report_images WHERE report_id = ?",
            [$report['id']],
        )['p'] ?? -1);

        $stored = [];

        foreach ($names as $i => $name) {
            if (!is_uploaded_file($tmp[$i])) {
                continue;
            }

            if ($sizes[$i] > self::MAX_IMAGE_BYTES) {
                Envelope::invalid('Each image must be 8 MB or smaller', 'images');
            }

            $info = @getimagesize($tmp[$i]);
            $mime = $info['mime'] ?? '';

            if (!isset(self::IMAGE_TYPES[$mime])) {
                Envelope::invalid('Images must be JPEG, PNG or WebP', 'images');
            }

            $id = Uuid::v4();
            $filename = $id . '.' . self::IMAGE_TYPES[$mime];

            if (!move_uploaded_file($tmp[$i], $directory . '/' . $filename)) {
                Envelope::fail('UPLOAD_FAILED', 'Could not store the upload', 500);
            }

            $url = 'storage/uploads/reports/' . $report['id'] . '/' . $filename;

            db_execute(
                "INSERT INTO report_images (id, report_id, url, width, height, bytes, position)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$id, $report['id'], $url, $info[0] ?? null, $info[1] ?? null, $sizes[$i], ++$position],
            );

            $stored[] = Present::image(db_fetch_one("SELECT * FROM report_images WHERE id = ?", [$id]));
        }

        if (!$stored) {
            Envelope::invalid('No usable image in the request', 'images');
        }

        Envelope::created($stored, ['imageCount' => $position + 1]);
    }

    /**
     * Returns the ReportReview and flips the report's status to `reviewed` in
     * the same response, so the client never re-reads.
     */
    public function review(): never
    {
        $this->requireAdmin();

        $report = $this->find((string) $this->param('id'));

        $decision = Wire::enumIn((string) ($this->input('decision') ?? ''), ['pending', 'approved', 'rejected']);

        if ($decision === null) {
            Envelope::invalid('decision must be pending, approved or rejected', 'decision');
        }

        $note = Wire::text($this->input('note'));
        $now = Wire::now();

        db_execute(
            "INSERT INTO report_reviews (report_id, decision, note, reviewed_by, reviewed_at)
             VALUES (?, ?, ?, ?, ?)
             ON CONFLICT (report_id) DO UPDATE SET
                decision = excluded.decision,
                note = excluded.note,
                reviewed_by = excluded.reviewed_by,
                reviewed_at = excluded.reviewed_at",
            [$report['id'], $decision, $note, Ctx::id(), $decision === 'pending' ? null : $now],
        );

        if ($decision !== 'pending') {
            db_execute("UPDATE visit_reports SET status = 'reviewed' WHERE id = ?", [$report['id']]);

            Engine::notifyTeam(
                'report_reviewed',
                $decision === 'approved' ? 'Report approved' : 'Report sent back',
                $report['title'],
                ['reportId' => $report['id'], 'userIds' => [$report['employee_id']]],
            );
        }

        Ctx::audit('report_review', $report['id'], 'update', null, ['decision' => $decision, 'note' => $note]);

        $row = db_fetch_one("SELECT * FROM report_reviews WHERE report_id = ?", [$report['id']]);

        Envelope::ok([
            'review' => Present::review($report['id'], $row),
            'report' => $this->hydrate([$this->find($report['id'])])[0],
        ]);
    }

    // ------------------------------------------------------------- internals

    private function create(array $body, string $clientId): array
    {
        $id = Uuid::v4();
        $now = Wire::now();
        $workDate = Ctx::today();

        $session = db_fetch_one(
            "SELECT id, work_date FROM work_sessions WHERE employee_id = ? AND id = ?",
            [Ctx::id(), (string) ($body['sessionId'] ?? '')],
        );

        if (!$session) {
            // A report filed outside a session still has to land somewhere, so
            // it attaches to the day's open session when there is one.
            $session = db_fetch_one(
                "SELECT id, work_date FROM work_sessions
                  WHERE employee_id = ? AND work_date = ? ORDER BY ended_at IS NULL DESC, seq DESC LIMIT 1",
                [Ctx::id(), $workDate],
            );
        }

        if ($session) {
            $workDate = $session['work_date'];
        }

        db_execute(
            "INSERT INTO visit_reports
                (id, employee_id, client_id, session_id, visit_id, work_date, company_name, branch_name,
                 company_id, branch_id, title, body, lat, lng, status, deal_value, payment_received,
                 follow_up_on, submitted_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted', ?, ?, ?, ?)",
            [
                $id, Ctx::id(), $clientId, $session['id'] ?? null,
                $body['visitId'] ?? null, $workDate,
                (string) $body['companyName'],
                Wire::text($body['branchName'] ?? null),
                $body['companyId'] ?? null,
                $body['branchId'] ?? null,
                (string) $body['title'],
                (string) ($body['body'] ?? ''),
                Wire::float($body['latitude'] ?? 0),
                Wire::float($body['longitude'] ?? 0),
                Wire::text($body['dealValue'] ?? null),
                Wire::text($body['paymentReceived'] ?? null),
                Wire::date((string) ($body['followUpOn'] ?? '')),
                $now,
            ],
        );

        db_execute("INSERT INTO report_reviews (report_id, decision) VALUES (?, 'pending')", [$id]);

        $this->writeSales($id, $body['sales'] ?? []);

        Engine::rebuildActivity(Ctx::id(), $workDate);
        Engine::rollupDay(Ctx::id(), $workDate);

        return $this->hydrate([$this->find($id)])[0];
    }

    /**
     * Product name, category and colour are denormalised onto the line so a
     * catalogue edit or a delisting can never change what the report says.
     */
    private function writeSales(string $reportId, mixed $sales): void
    {
        if (!is_array($sales)) {
            return;
        }

        foreach (array_values($sales) as $position => $line) {
            if (!is_array($line) || empty($line['productId'])) {
                continue;
            }

            $units = Wire::int($line['units'] ?? 0);

            if ($units <= 0) {
                Envelope::invalid('Each sale line needs a positive unit count', 'sales');
            }

            $product = db_fetch_one(
                "SELECT id, brand, name, category FROM products WHERE id = ? AND org_id = ?",
                [$line['productId'], Ctx::orgId()],
            );

            if (!$product) {
                Envelope::invalid('Unknown product on a sale line', 'sales');
            }

            db_execute(
                "INSERT INTO report_sales
                    (id, report_id, product_id, product_name, category, color_name, color_argb, units, position)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    Uuid::v4(), $reportId, $product['id'],
                    (string) ($line['productName'] ?? $product['brand'] . ' ' . $product['name']),
                    $product['category'],
                    (string) ($line['colorName'] ?? ''),
                    Wire::int($line['colorArgb'] ?? 0),
                    $units,
                    $position,
                ],
            );
        }
    }

    private function find(string $id): array
    {
        $row = db_fetch_one(
            "SELECT r.* FROM visit_reports r JOIN users u ON u.id = r.employee_id
              WHERE r.id = ? AND u.org_id = ?",
            [$id, Ctx::orgId()],
        );

        if (!$row) {
            Envelope::notFound('REPORT_NOT_FOUND', 'No such report');
        }

        if (!Ctx::isAdmin() && $row['employee_id'] !== Ctx::id()) {
            Envelope::forbidden('An employee token can only read its own reports');
        }

        return $row;
    }

    /** Attaches sales, image counts and the opt-in employee/review joins. */
    private function hydrate(array $rows): array
    {
        if (!$rows) {
            return [];
        }

        $ids = array_column($rows, 'id');
        $ph = implode(',', array_fill(0, count($ids), '?'));

        $sales = [];
        foreach (db_fetch_all("SELECT * FROM report_sales WHERE report_id IN ({$ph}) ORDER BY position", $ids) as $line) {
            $sales[$line['report_id']][] = $line;
        }

        $images = [];
        foreach (db_fetch_all(
            "SELECT report_id, COUNT(*) AS n FROM report_images WHERE report_id IN ({$ph}) GROUP BY report_id",
            $ids,
        ) as $row) {
            $images[$row['report_id']] = (int) $row['n'];
        }

        $wantsEmployee = $this->wants('employee');
        $wantsReview = $this->wants('review');

        $employees = [];
        if ($wantsEmployee) {
            foreach (Users::many("WHERE u.org_id = ?", [Ctx::orgId()]) as $employee) {
                $employees[$employee['id']] = Present::employee($employee);
            }
        }

        $reviews = [];
        if ($wantsReview) {
            foreach (db_fetch_all("SELECT * FROM report_reviews WHERE report_id IN ({$ph})", $ids) as $review) {
                $reviews[$review['report_id']] = $review;
            }
        }

        $out = [];

        foreach ($rows as $row) {
            $report = Present::report($row, $sales[$row['id']] ?? [], $images[$row['id']] ?? 0);

            if (!$wantsEmployee && !$wantsReview) {
                $out[] = $report;
                continue;
            }

            // The report model carries no owner field, so the inbox envelope is
            // where the join lives.
            $item = ['report' => $report, 'companyName' => $row['company_name'], 'branchName' => $row['branch_name']];

            if ($wantsEmployee) {
                $item['employee'] = $employees[$row['employee_id']] ?? null;
            }

            if ($wantsReview) {
                $item['review'] = Present::review($row['id'], $reviews[$row['id']] ?? null);
                $item['decision'] = $item['review']['decision'];
            }

            $out[] = $item;
        }

        return $out;
    }

    /** The filter chips, built from what was actually filed. */
    private function facets(string $join, array $params): array
    {
        $rows = db_fetch_all(
            "SELECT r.company_name AS name, COUNT(*) AS n {$join} GROUP BY r.company_name ORDER BY n DESC, name LIMIT 50",
            $params,
        );

        return [
            'companies' => array_map(
                static fn(array $r) => ['name' => $r['name'], 'count' => (int) $r['n']],
                $rows,
            ),
        ];
    }
}
