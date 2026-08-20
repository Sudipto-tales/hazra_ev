<?php

require_once __DIR__ . '/../support/V1Controller.php';
require_once __DIR__ . '/../support/Users.php';

/**
 * Server-sent events for the two genuinely live admin surfaces: the dashboard
 * and the roster.
 *
 * Event types are `member.status`, `member.fix`, `report.filed` and
 * `alert.longStop`. The payloads are the same objects the polling endpoints
 * return, so there is no second model — `GET /employees?include=liveStatus`
 * stays the fallback and the cold-start path.
 *
 * Development note: PHP's built-in server handles one request at a time, so a
 * stream held open blocks every other call. The connection therefore closes
 * after `ttl` seconds (default 30, max 300) and the client reconnects — which
 * is what EventSource does on its own anyway.
 */
final class StreamController extends V1Controller
{
    private const POLL_SECONDS = 3;

    public function index(): never
    {
        $this->requireAdmin();

        $ttl = max(5, min(300, (int) $this->query('ttl', 30)));
        $date = $this->dateParam();

        $this->openStream();

        $seen = [];
        $lastReportAt = Wire::now();
        $deadline = time() + $ttl;

        $longStopMinutes = (int) Ctx::config()['long_stop_threshold_minutes'];

        while (time() < $deadline) {
            if (connection_aborted()) {
                break;
            }

            foreach (Ctx::teamIds() as $employeeId) {
                $live = Engine::liveStatus($employeeId, $date);

                $signature = $live['status'] . '|' . $live['movement'] . '|' . $live['locationHealth'];

                if (($seen[$employeeId]['status'] ?? null) !== $signature) {
                    $seen[$employeeId]['status'] = $signature;

                    $this->emit('member.status', [
                        'employeeId'     => $employeeId,
                        'status'         => $live['status'],
                        'movement'       => $live['movement'],
                        'locationHealth' => $live['locationHealth'],
                        'activeSince'    => $live['activeSince'],
                    ]);
                }

                $fix = Present::fix($live['lastFix']);

                if ($fix !== null && ($seen[$employeeId]['fix'] ?? null) !== $fix['id']) {
                    $seen[$employeeId]['fix'] = $fix['id'];
                    $this->emit('member.fix', ['employeeId' => $employeeId, 'lastFix' => $fix]);
                }

                $openStop = db_fetch_one(
                    "SELECT s.id, s.arrival, v.company_id
                       FROM stop_records s
                       LEFT JOIN company_visits v ON v.id = s.visit_id
                      WHERE s.employee_id = ? AND s.work_date = ? AND s.departure IS NULL
                      ORDER BY s.arrival DESC LIMIT 1",
                    [$employeeId, $date],
                );

                if ($openStop) {
                    $duration = Wire::seconds($openStop['arrival'], Wire::now());

                    if ($duration >= $longStopMinutes * 60 && ($seen[$employeeId]['longStop'] ?? null) !== $openStop['id']) {
                        $seen[$employeeId]['longStop'] = $openStop['id'];

                        $this->emit('alert.longStop', [
                            'employeeId'       => $employeeId,
                            'stopId'           => $openStop['id'],
                            'openStopDuration' => $duration,
                            'companyId'        => $openStop['company_id'],
                        ]);
                    }
                }
            }

            $reports = db_fetch_all(
                "SELECT r.id, r.employee_id, r.company_name, r.title, r.submitted_at
                   FROM visit_reports r JOIN users u ON u.id = r.employee_id
                  WHERE u.org_id = ? AND r.submitted_at > ?
                  ORDER BY r.submitted_at",
                [Ctx::orgId(), $lastReportAt],
            );

            foreach ($reports as $report) {
                $lastReportAt = $report['submitted_at'];

                $this->emit('report.filed', [
                    'reportId'    => $report['id'],
                    'employeeId'  => $report['employee_id'],
                    'companyName' => $report['company_name'],
                    'title'       => $report['title'],
                    'submittedAt' => Wire::ts($report['submitted_at']),
                ]);
            }

            $this->comment('keep-alive');
            sleep(self::POLL_SECONDS);
        }

        $this->emit('stream.closing', ['reason' => 'ttl', 'reconnect' => true]);
        exit;
    }

    private function openStream(): void
    {
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-store');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        set_time_limit(0);
        ignore_user_abort(false);
    }

    private function emit(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_SLASHES) . "\n\n";
        flush();
    }

    private function comment(string $text): void
    {
        echo ": {$text}\n\n";
        flush();
    }
}
