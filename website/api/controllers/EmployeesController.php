<?php

require_once __DIR__ . '/../support/V1Controller.php';
require_once __DIR__ . '/../support/Users.php';

/**
 * One route serves the roster, the admin dashboard and member-by-id.
 *
 *   GET /employees                                     -> Employee[]
 *   GET /employees?include=liveStatus,summary&date=…   -> TeamMember[], with the
 *                                                         whole TeamOverview
 *                                                         aggregate in meta.totals
 *   GET /employees/{id}?include=liveStatus,summary     -> the n = 1 case
 *
 * So the admin dashboard is this endpoint, not a separate /dashboard. PATCH
 * covers both the edit form and the active toggle, so setEmployeeActive is not
 * its own route either.
 *
 * Credentials are the exception and do get their own route, because a password
 * is not a field of the record: it is never readable, PATCH cannot round-trip
 * it, and setting one has a side effect (killing sessions) that no other edit
 * has.
 *
 *   POST /employees                  -> optional `password`, else generated
 *   POST /employees/{id}/password    -> admin reset
 */
final class EmployeesController extends V1Controller
{
    private const PROFILE_FIELDS = [
        'employeeCode' => 'employee_code',
        'designation'  => 'designation',
        'department'   => 'department',
        'region'       => 'region',
        'joinedOn'     => 'joined_on',
        'bloodGroup'   => 'blood_group',
        'address'      => 'address',
        'bannerUrl'    => 'banner_url',
    ];

    public function index(): never
    {
        Envelope::noStore();
        $this->requireAdmin();

        $where = ["u.org_id = ?", "u.role = 'employee'"];
        $params = [Ctx::orgId()];

        // Matches name / employeeCode / region / department — all four.
        if ($query = $this->query('query')) {
            $where[] = '(u.name LIKE ? OR ep.employee_code LIKE ? OR ep.region LIKE ? OR ep.department LIKE ?)';
            array_push($params, "%{$query}%", "%{$query}%", "%{$query}%", "%{$query}%");
        }

        if ($this->hasQuery('active')) {
            $where[] = 'u.active = ?';
            $params[] = Wire::bool($this->query('active')) ? 1 : 0;
        }

        $employees = Users::many('WHERE ' . implode(' AND ', $where) . ' ORDER BY u.name', $params);

        if (!$this->wants('liveStatus') && !$this->wants('summary')) {
            $page = $this->paginate($employees);
            Envelope::ok(array_map([Present::class, 'employee'], $page['rows']), $page['meta']);
        }

        $date = $this->dateParam();
        $members = array_map(fn(array $e) => $this->member($e, $date), $employees);

        // Status is derived, so it can only be filtered after derivation.
        if ($status = $this->query('status')) {
            $members = array_values(array_filter($members, static fn(array $m) => $m['status'] === $status));
        }

        $page = $this->paginate($members);

        Envelope::ok(
            array_map([Present::class, 'teamMember'], $page['rows']),
            $page['meta'] + ['date' => $date, 'totals' => $this->totals($members, $date)],
        );
    }

    public function show(): never
    {
        Envelope::noStore();

        $id = (string) $this->param('id');

        // An employee may read itself through this route; anything else is admin.
        if (!Ctx::isAdmin() && $id !== Ctx::id()) {
            Envelope::forbidden('An employee token can only read its own record');
        }

        $employee = Users::byId($id);

        if (!$employee || $employee['org_id'] !== Ctx::orgId() || $employee['role'] !== 'employee') {
            Envelope::notFound('EMPLOYEE_NOT_FOUND', 'No such employee');
        }

        if (!$this->wants('liveStatus') && !$this->wants('summary')) {
            Envelope::ok(Present::employee($employee));
        }

        $date = $this->dateParam();

        Envelope::ok(Present::teamMember($this->member($employee, $date)), ['date' => $date]);
    }

    public function store(): never
    {
        $this->requireAdmin();

        $body = ApiRequest::body();
        $this->validateDraft($body, true);

        $id = Uuid::v4();
        $now = Wire::now();

        // The account has to be reachable. `password` is optional: type one, or
        // let the server make one and read it off the response exactly once.
        // Previously neither happened — the fallback hashed random bytes nobody
        // ever saw, so every account created here was active and unloggable.
        $generated = !isset($body['password']) || $body['password'] === '';
        $password = $generated
            ? Password::generate()
            : Password::validate($body['password']);

        try {
            db_execute(
                "INSERT INTO users (id, org_id, role, name, email, phone, avatar_url, password_hash,
                                    must_change_password, active, created_at, updated_at)
                 VALUES (?, ?, 'employee', ?, ?, ?, '', ?, ?, 1, ?, ?)",
                [
                    $id, Ctx::orgId(), (string) $body['name'], (string) $body['email'],
                    (string) ($body['phone'] ?? ''),
                    Password::hash($password),
                    $generated ? 1 : 0,
                    $now, $now,
                ],
            );
        } catch (PDOException) {
            Envelope::conflict('EMAIL_TAKEN', 'That email is already registered in this organisation');
        }

        try {
            db_execute(
                "INSERT INTO employee_profiles
                    (user_id, employee_code, designation, department, region, banner_url, joined_on,
                     reports_to_id, blood_group, address)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $id,
                    (string) $body['employeeCode'],
                    (string) ($body['designation'] ?? ''),
                    (string) ($body['department'] ?? ''),
                    (string) ($body['region'] ?? ''),
                    (string) ($body['bannerUrl'] ?? ''),
                    Wire::date((string) $body['joinedOn']),
                    $this->managerId($body['reportingTo'] ?? null),
                    (string) ($body['bloodGroup'] ?? ''),
                    (string) ($body['address'] ?? ''),
                ],
            );
        } catch (PDOException) {
            db_execute("DELETE FROM users WHERE id = ?", [$id]);
            Envelope::conflict('EMPLOYEE_CODE_TAKEN', 'That employee code is already in use');
        }

        db_execute("INSERT INTO user_preferences (user_id) VALUES (?)", [$id]);

        $created = Present::employee(Users::byId($id));

        // Audit the record, never the credential. `$created` is the clean
        // presenter output; the password is bolted onto the response below and
        // goes nowhere near audit_log.
        Ctx::audit('employee', $id, 'create', null, $created);

        if ($generated) {
            // The only time this value is ever readable. It is not stored in
            // plaintext and no route will hand it back — a lost one is reset,
            // not recovered.
            $created['temporaryPassword'] = $password;
        }

        Envelope::created($created);
    }

    /**
     * POST /employees/{id}/password — admin reset.
     *
     * The recovery path for a forgotten password: there is no email flow, so an
     * admin issues a new credential and passes it on. Every existing session
     * dies with the old password, which is the point of a reset.
     */
    public function password(): never
    {
        $this->requireAdmin();

        $id = (string) $this->param('id');
        $employee = Users::byId($id);

        if (!$employee || $employee['org_id'] !== Ctx::orgId() || $employee['role'] !== 'employee') {
            Envelope::notFound('EMPLOYEE_NOT_FOUND', 'No such employee');
        }

        $body = ApiRequest::body();

        $generated = !isset($body['password']) || $body['password'] === '';
        $password = $generated
            ? Password::generate()
            : Password::validate($body['password']);

        Password::set($id, $password, mustChange: $generated);
        Password::revokeSessions($id);

        // Action only. A before/after pair here would put the credential — or
        // its hash — in a table built to be read by people.
        Ctx::audit('employee', $id, 'password_reset', null, null);

        Envelope::ok([
            'id'                 => $id,
            'temporaryPassword'  => $generated ? $password : null,
            'mustChangePassword' => $generated,
        ]);
    }

    /** Covers the edit form and `{"active": false}` alike. */
    public function update(): never
    {
        $this->requireAdmin();

        $id = (string) $this->param('id');
        $employee = Users::byId($id);

        if (!$employee || $employee['org_id'] !== Ctx::orgId() || $employee['role'] !== 'employee') {
            Envelope::notFound('EMPLOYEE_NOT_FOUND', 'No such employee');
        }

        $before = Present::employee($employee);
        $body = ApiRequest::body();
        $this->validateDraft($body, false);

        $userSets = [];
        $userParams = [];

        foreach (['name' => 'name', 'email' => 'email', 'phone' => 'phone', 'avatarUrl' => 'avatar_url'] as $wire => $column) {
            if (array_key_exists($wire, $body)) {
                $userSets[] = "{$column} = ?";
                $userParams[] = (string) $body[$wire];
            }
        }

        $deactivating = false;
        if (array_key_exists('active', $body)) {
            $active = Wire::bool($body['active']);
            $deactivating = !$active && $before['active'];
            $userSets[] = 'active = ?';
            $userParams[] = $active ? 1 : 0;
        }

        if ($userSets) {
            $userParams[] = Wire::now();
            $userParams[] = $id;

            try {
                db_execute('UPDATE users SET ' . implode(', ', $userSets) . ', updated_at = ? WHERE id = ?', $userParams);
            } catch (PDOException) {
                Envelope::conflict('EMAIL_TAKEN', 'That email is already registered in this organisation');
            }
        }

        $profileSets = [];
        $profileParams = [];

        foreach (self::PROFILE_FIELDS as $wire => $column) {
            if (!array_key_exists($wire, $body)) {
                continue;
            }

            $profileSets[] = "{$column} = ?";
            // department and region accept '' and are simply omitted when the
            // form did not touch them — one representation of "not set".
            $profileParams[] = $wire === 'joinedOn'
                ? Wire::date((string) $body[$wire])
                : (string) $body[$wire];
        }

        if (array_key_exists('reportingTo', $body)) {
            $profileSets[] = 'reports_to_id = ?';
            $profileParams[] = $this->managerId($body['reportingTo']);
        }

        if ($profileSets) {
            $profileParams[] = $id;

            try {
                db_execute(
                    'UPDATE employee_profiles SET ' . implode(', ', $profileSets) . ' WHERE user_id = ?',
                    $profileParams,
                );
            } catch (PDOException) {
                Envelope::conflict('EMPLOYEE_CODE_TAKEN', 'That employee code is already in use');
            }
        }

        $after = Present::employee(Users::byId($id));
        Ctx::audit('employee', $id, $deactivating ? 'deactivate' : 'update', $before, $after);

        Envelope::ok($after);
    }

    // ------------------------------------------------------------- internals

    /** One live roster row: an employee plus today's tracking state. */
    private function member(array $employee, string $date): array
    {
        $live = Engine::liveStatus($employee['id'], $date);

        $day = db_fetch_one(
            "SELECT * FROM attendance_days WHERE employee_id = ? AND work_date = ?",
            [$employee['id'], $date],
        );

        $openStop = db_fetch_one(
            "SELECT s.arrival, v.company_id
               FROM stop_records s
               LEFT JOIN company_visits v ON v.id = s.visit_id
              WHERE s.employee_id = ? AND s.work_date = ? AND s.departure IS NULL
              ORDER BY s.arrival DESC LIMIT 1",
            [$employee['id'], $date],
        );

        return [
            'employee'          => Present::employee($employee),
            'status'            => $live['status'],
            'movement'          => $live['movement'],
            'locationHealth'    => $live['locationHealth'],
            'summary'           => Present::daySummary($day ?: null),
            'attendanceStatus'  => Wire::enum($day['status'] ?? 'no_data'),
            'active'            => (bool) $employee['active'],
            'lastFix'           => Present::fix($live['lastFix']),
            'activeSince'       => $live['activeSince'],
            'openStopDuration'  => $openStop ? Wire::seconds($openStop['arrival'], Wire::now()) : null,
            'openStopCompanyId' => $openStop['company_id'] ?? null,
        ];
    }

    /** The TeamOverview aggregate, carried in meta rather than as its own route. */
    private function totals(array $members, string $date): array
    {
        $longStopMinutes = (int) Ctx::config()['long_stop_threshold_minutes'];

        $counts = ['present' => 0, 'absent' => 0, 'working' => 0, 'idle' => 0, 'offline' => 0, 'longStop' => 0];
        $distance = 0.0;
        $visits = 0;
        $reports = 0;

        foreach ($members as $member) {
            $distance += $member['summary']['distanceKm'];
            $visits   += $member['summary']['companiesVisited'];
            $reports  += $member['summary']['reportsSubmitted'];

            match ($member['attendanceStatus']) {
                'present', 'partial' => $counts['present']++,
                'absent'             => $counts['absent']++,
                default              => null,
            };

            match ($member['status']) {
                'working'                        => $counts['working']++,
                'idle'                           => $counts['idle']++,
                'offline', 'locationUnavailable' => $counts['offline']++,
                default                          => null,
            };

            // Deactivated employees stay in the roster and are excluded from alerts.
            if ($member['active']
                && $member['openStopDuration'] !== null
                && $member['openStopDuration'] >= $longStopMinutes * 60) {
                $counts['longStop']++;
            }
        }

        $headcount = count($members);

        $pending = (int) (db_fetch_one(
            "SELECT COUNT(*) AS n FROM report_reviews rv
               JOIN visit_reports r ON r.id = rv.report_id
               JOIN users u ON u.id = r.employee_id
              WHERE rv.decision = 'pending' AND u.org_id = ?",
            [Ctx::orgId()],
        )['n'] ?? 0);

        return [
            'headcount'         => $headcount,
            'presentCount'      => $counts['present'],
            'absentCount'       => $counts['absent'],
            'workingCount'      => $counts['working'],
            'idleCount'         => $counts['idle'],
            'offlineCount'      => $counts['offline'],
            'totalDistanceKm'   => Wire::float($distance),
            'totalVisits'       => $visits,
            'totalReports'      => $reports,
            'pendingReviewCount'=> $pending,
            'longStopCount'     => $counts['longStop'],
            'attendancePercent' => $headcount === 0 ? 0.0 : Wire::float($counts['present'] * 100 / $headcount),
        ];
    }

    /** Roster paging is offset-free: the sort key is the name, which is stable. */
    private function paginate(array $rows): array
    {
        $total = count($rows);
        $limitRaw = $this->query('limit');

        if (Cursor::isCountOnly($limitRaw)) {
            return ['rows' => [], 'meta' => ['total' => $total, 'nextCursor' => null]];
        }

        $limit = Cursor::limit($limitRaw);
        $cursor = Cursor::decode($this->query('cursor'));
        $offset = $cursor['o'] ?? 0;

        $page = array_slice($rows, $offset, $limit);
        $next = ($offset + $limit) < $total ? Cursor::encode(['o' => $offset + $limit]) : null;

        return ['rows' => $page, 'meta' => ['total' => $total, 'nextCursor' => $next]];
    }

    /** `reportingTo` accepts a manager id; anything unrecognised clears the link. */
    private function managerId(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $manager = db_fetch_one(
            "SELECT id FROM users WHERE id = ? AND org_id = ?",
            [$value, Ctx::orgId()],
        );

        return $manager['id'] ?? null;
    }

    private function validateDraft(array $body, bool $creating): void
    {
        $required = ['name', 'employeeCode', 'email', 'joinedOn'];

        foreach ($required as $field) {
            if ($creating && empty($body[$field])) {
                Envelope::invalid("{$field} is required", $field);
            }
        }

        if (isset($body['name']) && mb_strlen(trim((string) $body['name'])) < 3) {
            Envelope::invalid('name must be at least 3 characters', 'name');
        }

        if (isset($body['email']) && !filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
            Envelope::invalid('email must be a valid address', 'email');
        }

        if (isset($body['employeeCode']) && !preg_match('/^EMP-\d{3,5}$/', (string) $body['employeeCode'])) {
            Envelope::invalid('employeeCode must look like EMP-1042', 'employeeCode');
        }

        if (isset($body['phone']) && strlen(preg_replace('/\D/', '', (string) $body['phone'])) < 10) {
            Envelope::invalid('phone must have at least 10 digits', 'phone');
        }

        if (isset($body['joinedOn'])) {
            $joined = Wire::date((string) $body['joinedOn']);

            if ($joined === null) {
                Envelope::invalid('joinedOn must be a date', 'joinedOn');
            }

            if ($joined > Ctx::today()) {
                Envelope::invalid('joinedOn must not be in the future', 'joinedOn');
            }
        }

        if (!empty($body['bloodGroup'])
            && !in_array($body['bloodGroup'], ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'], true)) {
            Envelope::invalid('bloodGroup must be a standard group or empty', 'bloodGroup');
        }
    }
}
