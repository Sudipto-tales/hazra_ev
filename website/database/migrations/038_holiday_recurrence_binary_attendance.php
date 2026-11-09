<?php

/**
 * Binary attendance + a recurrence on the holiday calendar.
 *
 * Two confirmed product decisions land together here.
 *
 * **Attendance is binary.** Any started session makes a day 'present'; a past
 * working day with no session is 'absent'; there is no worked-time threshold
 * and no 'partial' status any more (that split lived in Engine::classifyDay
 * against FULL_DAY_SECONDS and is removed in the same change). Historic rows
 * carrying 'partial' fold one-way into 'present' — confirmed acceptable: the
 * very session that earned the old partial verdict is exactly what now earns
 * present, so nothing is lost that the new rule would not re-derive anyway.
 *
 * **Holidays gain a recurrence.** `recurrence` is 'one_time' (the stored `date`
 * is the single occurrence) or 'yearly' (only the month-day of `date` is
 * significant, projected onto whichever year is being read). The PK stays
 * (org_id, date) — `date` remains the anchor and the uniqueness key — and the
 * column defaults to 'one_time' so every holiday already on the calendar keeps
 * its exact present meaning with no backfill.
 *
 * Dialect note: addColumn introspects and is re-runnable, the same contract
 * CREATE TABLE IF NOT EXISTS gives (see config/migration.php). `{str}` is the
 * portable string token 002 used for `holidays.name`.
 */
class HolidayRecurrenceBinaryAttendance extends Migration
{
    public function up()
    {
        $this->addColumn('holidays', 'recurrence', "{str} NOT NULL DEFAULT 'one_time'");

        // One-way, confirmed acceptable. A plain portable UPDATE — no Dialect
        // type tokens to expand — so it runs verbatim on both drivers.
        $this->pdo->exec("UPDATE attendance_days SET status = 'present' WHERE status = 'partial'");
    }

    public function down()
    {
        // The column is left in place: SQLite cannot drop one and the
        // partial -> present fold is not reversible regardless. This is the
        // call 037, 036, 031 and 010 already make for an added column.
    }
}
