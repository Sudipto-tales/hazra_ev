<?php

require_once __DIR__ . '/Uuid.php';
require_once __DIR__ . '/Wire.php';
require_once __DIR__ . '/Geo.php';
require_once __DIR__ . '/Polyline.php';
require_once __DIR__ . '/MapMatch.php';
require_once __DIR__ . '/Envelope.php';
require_once __DIR__ . '/Password.php';
require_once __DIR__ . '/Cursor.php';
require_once __DIR__ . '/Ctx.php';
require_once __DIR__ . '/Present.php';
require_once __DIR__ . '/Engine.php';

/**
 * Base for every /api/v1 controller.
 *
 * Extends the framework's ApiController for route params and body access, and
 * replaces its response helpers with the contract envelope. The three
 * parameters that collapse the route table — subject, include, and the time
 * scope — are parsed here so no controller re-implements them.
 */
abstract class V1Controller extends ApiController
{
    /** Query-string value. ApiController::input() reads the body, not the URL. */
    protected function query(string $key, mixed $default = null): mixed
    {
        $value = $_GET[$key] ?? null;
        return $value === null || $value === '' ? $default : $value;
    }

    protected function hasQuery(string $key): bool
    {
        return isset($_GET[$key]) && $_GET[$key] !== '';
    }

    /** `?include=a,b,c` — opt-in joins. Default is the smallest useful payload. */
    protected function includes(): array
    {
        $raw = (string) $this->query('include', '');

        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    protected function wants(string $name): bool
    {
        return in_array($name, $this->includes(), true);
    }

    protected function subject(bool $allowTeam = true): array
    {
        return Ctx::subject($this->query('subject'), $allowTeam);
    }

    protected function requireAdmin(): void
    {
        Ctx::requireAdmin();
    }

    // ------------------------------------------------------------ time scope

    /** `?date=` accepts `today` or yyyy-mm-dd; absent means today. */
    protected function dateParam(string $key = 'date'): string
    {
        $raw = (string) $this->query($key, 'today');

        if ($raw === 'today' || $raw === '') {
            return Ctx::today();
        }

        $date = Wire::date($raw);

        if ($date === null) {
            Envelope::invalid('Expected yyyy-mm-dd or "today"', $key);
        }

        return $date;
    }

    /** `?month=yyyy-mm` -> [firstDay, lastDay]. */
    protected function monthParam(): array
    {
        $raw = (string) $this->query('month', substr(Ctx::today(), 0, 7));

        if (!preg_match('/^\d{4}-\d{2}$/', $raw)) {
            Envelope::invalid('Expected yyyy-mm', 'month');
        }

        $first = $raw . '-01';
        $last  = (new DateTimeImmutable($first))->modify('last day of this month')->format('Y-m-d');

        return [$first, $last];
    }

    /**
     * `?range=thisWeek|thisMonth|lastMonth|custom` with `from`/`to` for custom.
     * Returns [from, to, label] — the label matches StatsRangeX.label exactly.
     */
    protected function rangeParam(): array
    {
        $range = (string) $this->query('range', 'thisMonth');
        $today = new DateTimeImmutable(Ctx::today());

        return match ($range) {
            'thisWeek' => [
                $today->modify('monday this week')->format('Y-m-d'),
                $today->modify('sunday this week')->format('Y-m-d'),
                'This week',
            ],
            'thisMonth' => [
                $today->modify('first day of this month')->format('Y-m-d'),
                $today->modify('last day of this month')->format('Y-m-d'),
                'This month',
            ],
            'lastMonth' => [
                $today->modify('first day of last month')->format('Y-m-d'),
                $today->modify('last day of last month')->format('Y-m-d'),
                'Last month',
            ],
            'custom' => $this->customRange(),
            default  => Envelope::invalid('Unknown range', 'range'),
        };
    }

    private function customRange(): array
    {
        $from = Wire::date((string) $this->query('from', ''));
        $to   = Wire::date((string) $this->query('to', ''));

        if ($from === null || $to === null) {
            Envelope::invalid('range=custom requires from and to', 'from');
        }

        if ($from > $to) {
            Envelope::invalid('from must not be after to', 'from');
        }

        return [$from, $to, 'Custom'];
    }

    // ---------------------------------------------------------- idempotency

    /**
     * Replays the original result for a repeated Idempotency-Key so a retry
     * from an offline queue cannot create a second record (API plan §1.5).
     */
    protected function idempotent(string $scope, callable $handler): never
    {
        $key = ApiRequest::header('Idempotency-Key');

        // No key means no replay protection, not a refusal. The caller still
        // gets its record — previously this ran the handler and then 500'd,
        // so the write landed while the client was told it had failed.
        if ($key === null || $key === '') {
            Envelope::ok($handler());
        }

        $existing = db_fetch_one(
            "SELECT response FROM idempotency_keys WHERE scope = ? AND user_id = ? AND idem_key = ?",
            [$scope, Ctx::id(), $key],
        );

        if ($existing) {
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            header('Idempotent-Replay: true');
            echo $existing['response'];
            exit;
        }

        $result = $handler();

        db_execute(
            "INSERT OR REPLACE INTO idempotency_keys (scope, user_id, idem_key, response, created_at)
             VALUES (?, ?, ?, ?, ?)",
            [$scope, Ctx::id(), $key, json_encode(['data' => $result, 'meta' => ['serverTime' => Wire::now()], 'error' => null]), Wire::now()],
        );

        Envelope::ok($result);
    }

    // -------------------------------------------------------------- helpers

    /** Validates against the framework Validator, failing into the envelope. */
    protected function check(array $rules): array
    {
        $validator = Validator::make(ApiRequest::body(), $rules);

        if ($validator->fails()) {
            Envelope::validation($validator->errors());
        }

        return $validator->validated();
    }

    /** Builds `WHERE col IN (?, ?, …)`, or a never-true clause for an empty set. */
    protected function inClause(string $column, array $values): array
    {
        if (!$values) {
            return ['1 = 0', []];
        }

        return [$column . ' IN (' . implode(',', array_fill(0, count($values), '?')) . ')', array_values($values)];
    }
}
