<?php

require_once __DIR__ . '/../support/V1Controller.php';

/**
 * The admin holiday calendar — the source the binary attendance model reads
 * 'holiday' from (Engine::classifyDay / fallbackStatus), and the thing that
 * refuses a session on a public holiday (TrackingController::openSession's
 * NON_WORKING_DAY guard).
 *
 * A holiday is either 'one_time' (its stored `date` is the single occurrence) or
 * 'yearly' (only the month-day of `date` matters, projected onto whatever year
 * is read). The PK is (org_id, date), so `date` is both the anchor and the
 * uniqueness key; a POST upserts on it.
 *
 * Weekly rest days are NOT stored here — they are the org's `weekend_days`
 * config, applied at read time by the same Engine helpers. This controller owns
 * dated public holidays only.
 *
 * Writing the calendar re-derives the stored status of every employee-day that
 * was actually worked on an affected date, so a day already rolled up as
 * 'present' flips to 'holiday' (and back on delete). Days with no rollup row
 * render from the calendar at read time and need no re-derivation — the same
 * reasoning ConfigController::rederiveOpenDays rests on.
 */
final class HolidaysController extends V1Controller
{
    private const RECURRENCES = ['one_time', 'yearly'];

    /**
     * GET /admin/holidays?year=YYYY
     *
     * The calendar for one year: one_time entries whose year matches, plus every
     * yearly entry projected onto that year. `date` is the concrete yyyy-mm-dd
     * for that year, so the client never does calendar arithmetic. A yearly
     * holiday anchored on Feb 29 simply has no occurrence in a non-leap year and
     * is omitted for it.
     */
    public function index(): never
    {
        $this->requireAdmin();

        $year = $this->yearParam();

        $out = [];

        foreach (db_fetch_all(
            "SELECT date, name, recurrence FROM holidays WHERE org_id = ? ORDER BY date",
            [Ctx::orgId()],
        ) as $row) {
            $anchor     = substr((string) $row['date'], 0, 10);
            $recurrence = (string) $row['recurrence'];

            if ($recurrence === 'yearly') {
                $month = (int) substr($anchor, 5, 2);
                $day   = (int) substr($anchor, 8, 2);

                if (!checkdate($month, $day, $year)) {
                    continue;   // Feb 29 in a non-leap year: no occurrence
                }

                $out[] = [
                    'date'       => sprintf('%04d-%02d-%02d', $year, $month, $day),
                    'name'       => (string) $row['name'],
                    'recurrence' => 'yearly',
                ];
                continue;
            }

            if (substr($anchor, 0, 4) === sprintf('%04d', $year)) {
                $out[] = [
                    'date'       => $anchor,
                    'name'       => (string) $row['name'],
                    'recurrence' => 'one_time',
                ];
            }
        }

        usort($out, static fn(array $a, array $b): int => strcmp($a['date'], $b['date']));

        Envelope::ok($out, ['year' => $year, 'total' => count($out)]);
    }

    /**
     * POST /admin/holidays — body {date, name, recurrence}. Upserts on the PK, so
     * re-posting a date edits its name/recurrence rather than erroring.
     */
    public function store(): never
    {
        $this->requireAdmin();

        $body = ApiRequest::body();

        $date = Wire::date((string) ($body['date'] ?? ''));
        if ($date === null) {
            Envelope::invalid('date must be yyyy-mm-dd', 'date');
        }

        $name = trim((string) ($body['name'] ?? ''));
        if ($name === '') {
            Envelope::invalid('name is required', 'name');
        }

        $recurrence = (string) ($body['recurrence'] ?? 'one_time');
        if (!in_array($recurrence, self::RECURRENCES, true)) {
            Envelope::invalid('recurrence must be one_time or yearly', 'recurrence');
        }

        db_execute(
            "INSERT INTO holidays (org_id, date, name, recurrence)
             VALUES (?, ?, ?, ?)
             ON CONFLICT (org_id, date) DO UPDATE SET
                name = excluded.name,
                recurrence = excluded.recurrence",
            [Ctx::orgId(), $date, $name, $recurrence],
        );

        $this->rederive($this->affectedDates($date, $recurrence));

        Envelope::created(['date' => $date, 'name' => $name, 'recurrence' => $recurrence]);
    }

    /**
     * DELETE /admin/holidays?date=YYYY-MM-DD — by PK.
     *
     * Forgiving on a yearly holiday: the list projects it onto a concrete year,
     * so a delete passing that projected date still resolves to the stored anchor
     * row by month-day. An exact PK hit always wins.
     */
    public function destroy(): never
    {
        $this->requireAdmin();

        $date = Wire::date((string) $this->query('date', ''));
        if ($date === null) {
            Envelope::invalid('date query parameter must be yyyy-mm-dd', 'date');
        }

        $existing = db_fetch_one(
            "SELECT date, recurrence FROM holidays
              WHERE org_id = ?
                AND ( date = ?
                      OR (recurrence = 'yearly' AND SUBSTR(date, 6, 5) = SUBSTR(?, 6, 5)) )
              ORDER BY CASE WHEN date = ? THEN 0 ELSE 1 END
              LIMIT 1",
            [Ctx::orgId(), $date, $date, $date],
        );

        if (!$existing) {
            Envelope::notFound('HOLIDAY_NOT_FOUND', 'No holiday on that date');
        }

        $anchor = substr((string) $existing['date'], 0, 10);

        db_execute(
            "DELETE FROM holidays WHERE org_id = ? AND date = ?",
            [Ctx::orgId(), $anchor],
        );

        $this->rederive($this->affectedDates($anchor, (string) $existing['recurrence']));

        Envelope::noContent();
    }

    /**
     * GET /admin/weekend-days -> {weekendDays: [ISO weekday, ...]} (1=Mon..7=Sun).
     *
     * The weekly half of the non-working calendar. It lives on the organizations
     * row (not tracking_configs), so it is served here beside the dated holidays
     * rather than through ConfigController — one calendar surface, one owner.
     */
    public function weekendDays(): never
    {
        $this->requireAdmin();

        Envelope::ok(['weekendDays' => Ctx::weekendDays()]);
    }

    /**
     * PUT /admin/weekend-days — body {weekendDays:[int,...]}. Each value must be
     * an ISO weekday 1..7; the set is deduped and sorted and persisted to
     * organizations.weekend_days as the JSON array the seeder writes.
     *
     * Read-time semantics, exactly like holidays and the tracking config: no
     * stored status is rewritten for a closed historical day. Only TODAY and any
     * open session are re-derived, so a day that just became a weekend flips its
     * live verdict while last month's signed-off days stand — the same stance
     * ConfigController::rederiveOpenDays takes.
     */
    public function updateWeekendDays(): never
    {
        $this->requireAdmin();

        $raw = ApiRequest::body()['weekendDays'] ?? null;

        if (!is_array($raw) || !array_is_list($raw)) {
            Envelope::invalid('weekendDays must be an array of ISO weekday numbers (1=Mon..7=Sun)', 'weekendDays');
        }

        $set = [];
        foreach ($raw as $value) {
            if (!is_numeric($value) || (float) $value !== (float) (int) $value) {
                Envelope::invalid('weekendDays values must be whole numbers 1..7', 'weekendDays');
            }

            $day = (int) $value;
            if ($day < 1 || $day > 7) {
                Envelope::invalid('weekendDays values must be 1..7 (1=Mon..7=Sun)', 'weekendDays');
            }

            $set[$day] = true;
        }

        $days = array_keys($set);
        sort($days);

        db_execute(
            "UPDATE organizations SET weekend_days = ? WHERE id = ?",
            [json_encode($days), Ctx::orgId()],
        );

        // Ctx caches the organizations row; drop the cache so the recompute below
        // (and the response) read the weekend_days just written, not the stale
        // one. Keeps the same principal.
        Ctx::impersonate(Ctx::principal());

        $this->rederiveOpenDays();

        Envelope::ok(['weekendDays' => $days]);
    }

    // ------------------------------------------------------------- internals

    /**
     * Re-derives today for every employee in the org with a session today or an
     * open session — the weekly-calendar twin of the dated-holiday rederive, and
     * identical to ConfigController::rederiveOpenDays: closed historical days are
     * deliberately left as they were classified.
     */
    private function rederiveOpenDays(): void
    {
        $today = Ctx::today();

        $rows = db_fetch_all(
            "SELECT DISTINCT s.employee_id
               FROM work_sessions s
               JOIN users u ON u.id = s.employee_id
              WHERE u.org_id = ? AND (s.work_date = ? OR s.ended_at IS NULL)",
            [Ctx::orgId(), $today],
        );

        foreach ($rows as $row) {
            Engine::recompute($row['employee_id'], $today);
        }
    }

    /** `?year=YYYY`, defaulting to the org's current year. */
    private function yearParam(): int
    {
        $raw = (string) $this->query('year', substr(Ctx::today(), 0, 4));

        if (!preg_match('/^\d{4}$/', $raw)) {
            Envelope::invalid('year must be YYYY', 'year');
        }

        return (int) $raw;
    }

    /**
     * The concrete dates a create/delete can flip.
     *
     * one_time: the date itself. yearly: the stored anchor date plus this year's
     * occurrence of the same month-day — and nothing else, because re-deriving
     * every historical year is both pointless (only this year's day is near
     * enough to have been worked) and the explicit non-goal.
     *
     * @return string[]
     */
    private function affectedDates(string $anchor, string $recurrence): array
    {
        $anchor = substr($anchor, 0, 10);

        if ($recurrence !== 'yearly') {
            return [$anchor];
        }

        $dates = [$anchor];

        $year  = (int) substr(Ctx::today(), 0, 4);
        $month = (int) substr($anchor, 5, 2);
        $day   = (int) substr($anchor, 8, 2);

        if (checkdate($month, $day, $year)) {
            $dates[] = sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        return array_values(array_unique($dates));
    }

    /**
     * Re-derives the stored rollup for every employee in this org who actually
     * worked one of the affected dates, so the attendance status flips. Driven
     * off work_sessions, exactly like ConfigController::rederiveOpenDays: a day
     * with no session has no stored status to correct.
     *
     * @param string[] $dates
     */
    private function rederive(array $dates): void
    {
        $dates = array_values(array_filter($dates, static fn($d): bool => $d !== ''));

        if (!$dates) {
            return;
        }

        [$clause, $params] = $this->inClause('s.work_date', $dates);

        $rows = db_fetch_all(
            "SELECT DISTINCT s.employee_id, s.work_date
               FROM work_sessions s
               JOIN users u ON u.id = s.employee_id
              WHERE u.org_id = ? AND {$clause}",
            [Ctx::orgId(), ...$params],
        );

        foreach ($rows as $row) {
            Engine::recompute($row['employee_id'], $row['work_date']);
        }
    }
}
