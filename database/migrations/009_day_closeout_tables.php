<?php

/**
 * The end-of-day declaration and the lock it puts on the day.
 *
 * The governing distinction, from the mobile spec: **a session ending is not a
 * day ending.** Sessions close all the time — on a break, or when GPS and
 * network are both gone. The day closes once, when the employee submits their
 * declaration, and after that only an admin can reopen it.
 *
 * `day_closeouts` is append-only. A day can be closed, reopened and closed
 * again, and the original declaration is kept every time — it is history, not a
 * mistake. So the day is locked iff a row exists with `reopened_at IS NULL`,
 * and there is deliberately no UNIQUE on (employee_id, work_date).
 *
 * Declared figures never overwrite `attendance_days`. Both the claim and the
 * measurement are stored side by side, because the gap between them is the
 * only thing worth looking at and merging them would destroy it.
 *
 * Types are Dialect tokens; see config/dialect.php and the port note in 001.
 */
class DayCloseoutTables extends Migration
{
    public function up()
    {
        $this->exec("
            CREATE TABLE IF NOT EXISTS day_closeouts (
                id                   {uuid} PRIMARY KEY,
                employee_id          {uuid} NOT NULL,
                work_date            {date} NOT NULL,
                -- Device-generated, doubles as the Idempotency-Key. A retry or a
                -- double tap must not close the day twice.
                client_id            {str:128} NOT NULL,
                state                {str:32} NOT NULL DEFAULT 'closed_by_employee'
                                       CHECK (state IN ('closed_by_employee','closed_by_system')),
                submitted_at         {ts} NOT NULL,
                ended_at             {ts} NOT NULL,
                -- What the employee says.
                declared_distance_km {float} NOT NULL DEFAULT 0,
                declared_visits      {int} NOT NULL DEFAULT 0,
                -- What the fix chain says, copied from attendance_days at submit
                -- time so the admin view never has to join two payloads.
                measured_distance_km {float} NOT NULL DEFAULT 0,
                measured_visits      {int} NOT NULL DEFAULT 0,
                rating               {int} NOT NULL DEFAULT 3 CHECK (rating >= 1 AND rating <= 5),
                tags                 {json} NOT NULL {default '[]'},   -- JSON array of snake_case tags
                feedback             {text},
                -- NULL means this row is the active lock. An admin reopen stamps
                -- it and leaves everything else untouched.
                reopened_at          {ts},
                UNIQUE (employee_id, client_id),
                FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_closeout_day ON day_closeouts (employee_id, work_date, submitted_at DESC);

            -- A reopen shifts numbers somebody will later be asked about, so the
            -- reason is mandatory and kept. Its own table rather than audit_log:
            -- reopen count and reason are queryable per day here, which a JSON
            -- before/after blob is not.
            CREATE TABLE IF NOT EXISTS day_reopen_audit (
                id          {autoid},
                closeout_id {uuid} NOT NULL,
                employee_id {uuid} NOT NULL,
                work_date   {date} NOT NULL,
                actor_id    {uuid} NOT NULL,
                reason      {text} NOT NULL,
                occurred_at {ts} NOT NULL,
                FOREIGN KEY (closeout_id) REFERENCES day_closeouts(id) ON DELETE CASCADE,
                FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (actor_id) REFERENCES users(id)
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_reopen_day ON day_reopen_audit (employee_id, work_date, occurred_at DESC);
        ");
    }

    public function down()
    {
        $this->drop(['day_reopen_audit', 'day_closeouts']);
    }
}
