<?php

/**
 * Server-derived stops and visits, plus the reports a seller files.
 *
 * Denormalisation is deliberate and load-bearing: `report_sales` keeps the
 * product name and colour as they were, `visit_reports` keeps the company name
 * as typed. A catalogue edit or a delisting can never rewrite what was filed.
 */
class VisitReportTables
{
    protected $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function up()
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS stop_records (
                id          TEXT PRIMARY KEY,
                session_id  TEXT NOT NULL REFERENCES work_sessions(id) ON DELETE CASCADE,
                employee_id TEXT NOT NULL REFERENCES users(id),
                work_date   TEXT NOT NULL,
                arrival     TEXT NOT NULL,
                departure   TEXT,
                lat         REAL NOT NULL,
                lng         REAL NOT NULL,
                radius_m    REAL NOT NULL,
                -- No FK: stop_records.visit_id and company_visits.stop_id are a
                -- cycle, and SQLite has no deferrable constraints worth the cost.
                visit_id    TEXT,
                derived_from_config_version INTEGER NOT NULL DEFAULT 1
            );
            CREATE INDEX IF NOT EXISTS idx_stops_employee_date ON stop_records (employee_id, work_date);
            CREATE INDEX IF NOT EXISTS idx_open_stops ON stop_records (employee_id) WHERE departure IS NULL;
            CREATE INDEX IF NOT EXISTS idx_stops_session ON stop_records (session_id, arrival);

            CREATE TABLE IF NOT EXISTS company_visits (
                id             TEXT PRIMARY KEY,
                session_id     TEXT NOT NULL REFERENCES work_sessions(id) ON DELETE CASCADE,
                employee_id    TEXT NOT NULL REFERENCES users(id),
                work_date      TEXT NOT NULL,
                company_id     TEXT NOT NULL REFERENCES companies(id),
                branch_id      TEXT REFERENCES branches(id),
                stop_id        TEXT REFERENCES stop_records(id),
                arrival        TEXT NOT NULL,
                departure      TEXT,
                lat            REAL NOT NULL,
                lng            REAL NOT NULL,
                status         TEXT NOT NULL DEFAULT 'in_progress'
                                 CHECK (status IN ('in_progress','completed','unassigned')),
                deal_reference TEXT
            );
            CREATE INDEX IF NOT EXISTS idx_visits_employee_date ON company_visits (employee_id, work_date);
            CREATE INDEX IF NOT EXISTS idx_visits_company ON company_visits (company_id, arrival DESC);

            CREATE TABLE IF NOT EXISTS visit_reports (
                id               TEXT PRIMARY KEY,
                employee_id      TEXT NOT NULL REFERENCES users(id),
                client_id        TEXT NOT NULL,
                session_id       TEXT REFERENCES work_sessions(id),
                visit_id         TEXT REFERENCES company_visits(id),
                work_date        TEXT NOT NULL,
                company_name     TEXT NOT NULL,          -- as typed. never looked up
                branch_name      TEXT,
                company_id       TEXT REFERENCES companies(id),   -- optional link, map pin only
                branch_id        TEXT REFERENCES branches(id),
                title            TEXT NOT NULL,
                body             TEXT NOT NULL DEFAULT '',
                lat              REAL NOT NULL DEFAULT 0,
                lng              REAL NOT NULL DEFAULT 0,
                status           TEXT NOT NULL DEFAULT 'submitted'
                                   CHECK (status IN ('draft','queued','uploading','submitted','failed','reviewed')),
                deal_value       TEXT,                   -- free text, as typed
                payment_received TEXT,                   -- free text, as typed
                follow_up_on     TEXT,
                submitted_at     TEXT NOT NULL,
                UNIQUE (employee_id, client_id)
            );
            CREATE INDEX IF NOT EXISTS idx_reports_employee ON visit_reports (employee_id, submitted_at DESC);
            CREATE INDEX IF NOT EXISTS idx_reports_date ON visit_reports (work_date DESC, submitted_at DESC);
            CREATE INDEX IF NOT EXISTS idx_reports_visit ON visit_reports (visit_id);
            CREATE INDEX IF NOT EXISTS idx_reports_company_name ON visit_reports (company_name);

            CREATE TABLE IF NOT EXISTS report_sales (
                id           TEXT PRIMARY KEY,
                report_id    TEXT NOT NULL REFERENCES visit_reports(id) ON DELETE CASCADE,
                product_id   TEXT NOT NULL REFERENCES products(id),
                product_name TEXT NOT NULL,          -- denormalised: history must not move
                category     TEXT NOT NULL CHECK (category IN ('scooty','bike','bicycle','others')),
                color_name   TEXT NOT NULL DEFAULT '',
                color_argb   INTEGER NOT NULL DEFAULT 0,
                units        INTEGER NOT NULL CHECK (units > 0),
                position     INTEGER NOT NULL DEFAULT 0
            );
            CREATE INDEX IF NOT EXISTS idx_sales_report ON report_sales (report_id, position);
            CREATE INDEX IF NOT EXISTS idx_sales_product ON report_sales (product_id);

            CREATE TABLE IF NOT EXISTS report_images (
                id            TEXT PRIMARY KEY,
                report_id     TEXT NOT NULL REFERENCES visit_reports(id) ON DELETE CASCADE,
                url           TEXT NOT NULL,
                thumbnail_url TEXT,
                width         INTEGER,
                height        INTEGER,
                bytes         INTEGER,
                captured_at   TEXT,
                lat           REAL,
                lng           REAL,
                position      INTEGER NOT NULL DEFAULT 0
            );
            CREATE INDEX IF NOT EXISTS idx_images_report ON report_images (report_id, position);

            -- One review per report. Kept off visit_reports.status on purpose:
            -- that column is the upload vocabulary, and conflating the two
            -- would make the employee-side status lie.
            CREATE TABLE IF NOT EXISTS report_reviews (
                report_id   TEXT PRIMARY KEY REFERENCES visit_reports(id) ON DELETE CASCADE,
                decision    TEXT NOT NULL DEFAULT 'pending'
                              CHECK (decision IN ('pending','approved','rejected')),
                note        TEXT,
                reviewed_by TEXT REFERENCES users(id),
                reviewed_at TEXT
            );
            CREATE INDEX IF NOT EXISTS idx_pending_reviews ON report_reviews (report_id) WHERE decision = 'pending';
        ");
    }

    public function down()
    {
        foreach ([
            'report_reviews', 'report_images', 'report_sales', 'visit_reports',
            'company_visits', 'stop_records',
        ] as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
    }
}
