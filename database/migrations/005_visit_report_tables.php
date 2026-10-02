<?php

/**
 * Server-derived stops and visits, plus the reports a seller files.
 *
 * Denormalisation is deliberate and load-bearing: `report_sales` keeps the
 * product name and colour as they were, `visit_reports` keeps the company name
 * as typed. A catalogue edit or a delisting can never rewrite what was filed.
 *
 * Types are Dialect tokens; see config/dialect.php and the port note in 001.
 */
class VisitReportTables extends Migration
{
    public function up()
    {
        $this->exec("
            CREATE TABLE IF NOT EXISTS stop_records (
                id          {uuid} PRIMARY KEY,
                session_id  {uuid} NOT NULL,
                employee_id {uuid} NOT NULL,
                work_date   {date} NOT NULL,
                arrival     {ts} NOT NULL,
                departure   {ts},
                lat         {float} NOT NULL,
                lng         {float} NOT NULL,
                radius_m    {float} NOT NULL,
                -- No FK: stop_records.visit_id and company_visits.stop_id are a
                -- cycle, and neither driver has deferrable constraints worth
                -- the cost.
                visit_id    {uuid},
                derived_from_config_version {int} NOT NULL DEFAULT 1,
                FOREIGN KEY (session_id) REFERENCES work_sessions(id) ON DELETE CASCADE,
                FOREIGN KEY (employee_id) REFERENCES users(id)
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_stops_employee_date ON stop_records (employee_id, work_date);
            CREATE INDEX IF NOT EXISTS idx_open_stops ON stop_records (employee_id) WHERE departure IS NULL;
            CREATE INDEX IF NOT EXISTS idx_stops_session ON stop_records (session_id, arrival);

            CREATE TABLE IF NOT EXISTS company_visits (
                id             {uuid} PRIMARY KEY,
                session_id     {uuid} NOT NULL,
                employee_id    {uuid} NOT NULL,
                work_date      {date} NOT NULL,
                company_id     {uuid} NOT NULL,
                branch_id      {uuid},
                stop_id        {uuid},
                arrival        {ts} NOT NULL,
                departure      {ts},
                lat            {float} NOT NULL,
                lng            {float} NOT NULL,
                status         {str:16} NOT NULL DEFAULT 'in_progress'
                                 CHECK (status IN ('in_progress','completed','unassigned')),
                deal_reference {str} ,
                FOREIGN KEY (session_id) REFERENCES work_sessions(id) ON DELETE CASCADE,
                FOREIGN KEY (employee_id) REFERENCES users(id),
                FOREIGN KEY (company_id) REFERENCES companies(id),
                FOREIGN KEY (branch_id) REFERENCES branches(id),
                FOREIGN KEY (stop_id) REFERENCES stop_records(id)
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_visits_employee_date ON company_visits (employee_id, work_date);
            CREATE INDEX IF NOT EXISTS idx_visits_company ON company_visits (company_id, arrival DESC);

            CREATE TABLE IF NOT EXISTS visit_reports (
                id               {uuid} PRIMARY KEY,
                employee_id      {uuid} NOT NULL,
                client_id        {str:128} NOT NULL,
                session_id       {uuid},
                visit_id         {uuid},
                work_date        {date} NOT NULL,
                company_name     {str} NOT NULL,          -- as typed. never looked up
                branch_name      {str},
                company_id       {uuid},                  -- optional link, map pin only
                branch_id        {uuid},
                title            {str} NOT NULL,
                body             {text} NOT NULL {default ''},
                lat              {float} NOT NULL DEFAULT 0,
                lng              {float} NOT NULL DEFAULT 0,
                status           {str:16} NOT NULL DEFAULT 'submitted'
                                   CHECK (status IN ('draft','queued','uploading','submitted','failed','reviewed')),
                deal_value       {str:64},                -- free text, as typed
                payment_received {str:64},                -- free text, as typed
                follow_up_on     {date},
                submitted_at     {ts} NOT NULL,
                UNIQUE (employee_id, client_id),
                FOREIGN KEY (employee_id) REFERENCES users(id),
                FOREIGN KEY (session_id) REFERENCES work_sessions(id),
                FOREIGN KEY (visit_id) REFERENCES company_visits(id),
                FOREIGN KEY (company_id) REFERENCES companies(id),
                FOREIGN KEY (branch_id) REFERENCES branches(id)
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_reports_employee ON visit_reports (employee_id, submitted_at DESC);
            CREATE INDEX IF NOT EXISTS idx_reports_date ON visit_reports (work_date DESC, submitted_at DESC);
            CREATE INDEX IF NOT EXISTS idx_reports_visit ON visit_reports (visit_id);
            CREATE INDEX IF NOT EXISTS idx_reports_company_name ON visit_reports (company_name);

            CREATE TABLE IF NOT EXISTS report_sales (
                id           {uuid} PRIMARY KEY,
                report_id    {uuid} NOT NULL,
                product_id   {uuid} NOT NULL,
                product_name {str} NOT NULL,          -- denormalised: history must not move
                category     {str:16} NOT NULL CHECK (category IN ('scooty','bike','bicycle','others')),
                color_name   {str:64} NOT NULL DEFAULT '',
                color_argb   {int} NOT NULL DEFAULT 0,
                units        {int} NOT NULL CHECK (units > 0),
                position     {int} NOT NULL DEFAULT 0,
                FOREIGN KEY (report_id) REFERENCES visit_reports(id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES products(id)
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_sales_report ON report_sales (report_id, position);
            CREATE INDEX IF NOT EXISTS idx_sales_product ON report_sales (product_id);

            CREATE TABLE IF NOT EXISTS report_images (
                id            {uuid} PRIMARY KEY,
                report_id     {uuid} NOT NULL,
                url           {str:512} NOT NULL,
                thumbnail_url {str:512},
                width         {int},
                height        {int},
                bytes         {int},
                captured_at   {ts},
                lat           {float},
                lng           {float},
                position      {int} NOT NULL DEFAULT 0,
                FOREIGN KEY (report_id) REFERENCES visit_reports(id) ON DELETE CASCADE
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_images_report ON report_images (report_id, position);

            -- One review per report. Kept off visit_reports.status on purpose:
            -- that column is the upload vocabulary, and conflating the two
            -- would make the employee-side status lie.
            CREATE TABLE IF NOT EXISTS report_reviews (
                report_id   {uuid} PRIMARY KEY,
                decision    {str:16} NOT NULL DEFAULT 'pending'
                              CHECK (decision IN ('pending','approved','rejected')),
                note        {text},
                reviewed_by {uuid},
                reviewed_at {ts},
                FOREIGN KEY (report_id) REFERENCES visit_reports(id) ON DELETE CASCADE,
                FOREIGN KEY (reviewed_by) REFERENCES users(id)
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_pending_reviews ON report_reviews (report_id) WHERE decision = 'pending';
        ");
    }

    public function down()
    {
        $this->drop([
            'report_reviews', 'report_images', 'report_sales', 'visit_reports',
            'company_visits', 'stop_records',
        ]);
    }
}
