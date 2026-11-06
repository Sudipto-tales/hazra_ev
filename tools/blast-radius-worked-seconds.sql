-- ===========================================================================
-- Blast radius of the session dark-time fix, on stored attendance_days rows.
--
-- READ-ONLY. Three SELECT statements and nothing else: no INSERT, UPDATE,
-- DELETE, CREATE, DROP, ALTER, no temporary tables, no views, no stored
-- routines, no session-variable writes. Safe to paste into a production
-- console. It reports what WOULD change; it changes nothing.
--
-- Target: MariaDB 11.8 (production). See the dialect notes at the bottom
-- before running it anywhere else — `{ts}` columns are VARCHAR(30) holding
-- ISO-8601 with a trailing Z, not DATETIME, so every timestamp is parsed
-- explicitly with STR_TO_DATE.
--
-- Rehearsed by website/tests/worked-time-blast-radius.php, which runs these
-- same three statements against a throwaway SQLite fixture and cross-checks
-- every `evidence_seconds` against what Engine::rollupDay actually writes.
--
-- WHY: Engine::sessionWorkedSeconds now bounds a session's worked time by the
-- last fix it has evidence for. A trailing dark tail longer than
-- offlineThresholdMinutes no longer counts; an interior dark gap still does;
-- a session with no fixes contributes 0. Stored attendance_days rows were
-- written under the old rule (start -> ended_at, or start -> now while open)
-- and do NOT move on deploy — rollupDay only rewrites a day when something
-- re-derives it (a fix batch, a report, a closeout, a config change, a
-- reopen, `days:close`, or an explicit backfill). These queries say which
-- rows are carrying a number the current rule disagrees with.
--
-- Rows the fix does not reach are deliberately out of scope:
--   * today's rows (a.work_date < CURDATE()), because an open session's stored
--     figure is a snapshot of whenever the last rollup ran;
--   * synthesised absent/holiday/weekend rows with no work_sessions at all,
--     which the JOIN drops because there is nothing to re-derive.
--
-- 14400 below is Engine::FULL_DAY_SECONDS (4 h), the present/partial line.
-- Only a `present` row can flip to `partial`: classifyDay lets holiday and
-- weekend win over presence, and an absent day has no sessions.
-- ===========================================================================


-- ---------------------------------------------------------------------------
-- 1. Headline: how many rows move, and by how much.
-- ---------------------------------------------------------------------------
WITH last_fix AS (
    SELECT session_id, MAX(recorded_at) AS last_fix
      FROM location_fixes
     GROUP BY session_id
),
evidence AS (
    SELECT a.employee_id,
           a.work_date,
           a.status         AS stored_status,
           a.worked_seconds AS stored_seconds,
           SUM(CASE
                 -- No evidence at all: contributes nothing.
                 WHEN lf.last_fix IS NULL THEN 0
                 -- Open: capped at the last fix, never at the clock.
                 WHEN s.ended_at IS NULL THEN GREATEST(0, TIMESTAMPDIFF(SECOND, STR_TO_DATE(s.started_at, '%Y-%m-%dT%H:%i:%sZ'), STR_TO_DATE(lf.last_fix, '%Y-%m-%dT%H:%i:%sZ')))
                 -- Closed with a dark tail that EXCEEDS the threshold: trimmed
                 -- back to the last fix. Strict >, as in the Engine.
                 WHEN TIMESTAMPDIFF(SECOND, STR_TO_DATE(lf.last_fix, '%Y-%m-%dT%H:%i:%sZ'), STR_TO_DATE(s.ended_at, '%Y-%m-%dT%H:%i:%sZ')) > c.offline_threshold_minutes * 60
                   THEN GREATEST(0, TIMESTAMPDIFF(SECOND, STR_TO_DATE(s.started_at, '%Y-%m-%dT%H:%i:%sZ'), STR_TO_DATE(lf.last_fix, '%Y-%m-%dT%H:%i:%sZ')))
                 -- Otherwise the employee's own declaration stands, interior
                 -- dark gaps and all.
                 ELSE GREATEST(0, TIMESTAMPDIFF(SECOND, STR_TO_DATE(s.started_at, '%Y-%m-%dT%H:%i:%sZ'), STR_TO_DATE(s.ended_at, '%Y-%m-%dT%H:%i:%sZ')))
               END) AS evidence_seconds
      FROM attendance_days a
      JOIN work_sessions s ON s.employee_id = a.employee_id AND s.work_date = a.work_date
      JOIN users u ON u.id = a.employee_id
      JOIN tracking_configs c ON c.org_id = u.org_id
      LEFT JOIN last_fix lf ON lf.session_id = s.id
     WHERE a.work_date < CURDATE()
     GROUP BY a.employee_id, a.work_date, a.status, a.worked_seconds
),
moved AS (
    SELECT employee_id, work_date, stored_status, stored_seconds, evidence_seconds,
           stored_seconds - evidence_seconds AS removed_seconds
      FROM evidence
     WHERE evidence_seconds <> stored_seconds
),
ranked AS (
    SELECT removed_seconds,
           ROW_NUMBER() OVER (ORDER BY removed_seconds) AS rn,
           COUNT(*) OVER () AS n
      FROM moved
)
SELECT (SELECT COUNT(*) FROM evidence) AS days_examined,
       MAX(n)                                                             AS days_that_move,
       ROUND(SUM(removed_seconds) / 3600.0, 1)                            AS hours_removed_total,
       -- Ascending order, so the first row at or past each rank is the
       -- percentile. Integer arithmetic on purpose: no CEIL, no percentile
       -- function, nothing version-dependent.
       ROUND(MIN(CASE WHEN rn * 2  >= n     THEN removed_seconds END) / 3600.0, 2) AS median_hours_removed,
       ROUND(MIN(CASE WHEN rn * 10 >= n * 9 THEN removed_seconds END) / 3600.0, 2) AS p90_hours_removed,
       ROUND(MAX(removed_seconds) / 3600.0, 2)                            AS max_hours_removed,
       ROUND(AVG(removed_seconds) / 3600.0, 2)                            AS mean_hours_removed,
       (SELECT COUNT(*) FROM moved
         WHERE stored_status = 'present' AND evidence_seconds < 14400)     AS days_present_to_partial
  FROM ranked;


-- ---------------------------------------------------------------------------
-- 2. The worst 25 employee-days. This is the list to eyeball before deciding
--    whether to backfill: these are the rows whose number visibly changes.
-- ---------------------------------------------------------------------------
WITH last_fix AS (
    SELECT session_id, MAX(recorded_at) AS last_fix
      FROM location_fixes
     GROUP BY session_id
),
evidence AS (
    SELECT a.employee_id,
           a.work_date,
           a.status         AS stored_status,
           a.worked_seconds AS stored_seconds,
           SUM(CASE
                 WHEN lf.last_fix IS NULL THEN 0
                 WHEN s.ended_at IS NULL THEN GREATEST(0, TIMESTAMPDIFF(SECOND, STR_TO_DATE(s.started_at, '%Y-%m-%dT%H:%i:%sZ'), STR_TO_DATE(lf.last_fix, '%Y-%m-%dT%H:%i:%sZ')))
                 WHEN TIMESTAMPDIFF(SECOND, STR_TO_DATE(lf.last_fix, '%Y-%m-%dT%H:%i:%sZ'), STR_TO_DATE(s.ended_at, '%Y-%m-%dT%H:%i:%sZ')) > c.offline_threshold_minutes * 60
                   THEN GREATEST(0, TIMESTAMPDIFF(SECOND, STR_TO_DATE(s.started_at, '%Y-%m-%dT%H:%i:%sZ'), STR_TO_DATE(lf.last_fix, '%Y-%m-%dT%H:%i:%sZ')))
                 ELSE GREATEST(0, TIMESTAMPDIFF(SECOND, STR_TO_DATE(s.started_at, '%Y-%m-%dT%H:%i:%sZ'), STR_TO_DATE(s.ended_at, '%Y-%m-%dT%H:%i:%sZ')))
               END) AS evidence_seconds
      FROM attendance_days a
      JOIN work_sessions s ON s.employee_id = a.employee_id AND s.work_date = a.work_date
      JOIN users u ON u.id = a.employee_id
      JOIN tracking_configs c ON c.org_id = u.org_id
      LEFT JOIN last_fix lf ON lf.session_id = s.id
     WHERE a.work_date < CURDATE()
     GROUP BY a.employee_id, a.work_date, a.status, a.worked_seconds
),
moved AS (
    SELECT employee_id, work_date, stored_status, stored_seconds, evidence_seconds,
           stored_seconds - evidence_seconds AS removed_seconds
      FROM evidence
     WHERE evidence_seconds <> stored_seconds
)
SELECT m.work_date,
       u.name AS employee,
       ROUND(m.stored_seconds   / 3600.0, 2) AS stored_hours,
       ROUND(m.evidence_seconds / 3600.0, 2) AS evidence_hours,
       ROUND(m.removed_seconds  / 3600.0, 2) AS hours_removed,
       m.stored_status,
       CASE WHEN m.stored_status = 'present' AND m.evidence_seconds < 14400
            THEN 'partial' ELSE m.stored_status END AS status_after
  FROM moved m
  JOIN users u ON u.id = m.employee_id
 ORDER BY m.removed_seconds DESC, m.work_date DESC
 LIMIT 25;


-- ---------------------------------------------------------------------------
-- 3. Per employee, worst first — who is affected, and over what date range.
-- ---------------------------------------------------------------------------
WITH last_fix AS (
    SELECT session_id, MAX(recorded_at) AS last_fix
      FROM location_fixes
     GROUP BY session_id
),
evidence AS (
    SELECT a.employee_id,
           a.work_date,
           a.status         AS stored_status,
           a.worked_seconds AS stored_seconds,
           SUM(CASE
                 WHEN lf.last_fix IS NULL THEN 0
                 WHEN s.ended_at IS NULL THEN GREATEST(0, TIMESTAMPDIFF(SECOND, STR_TO_DATE(s.started_at, '%Y-%m-%dT%H:%i:%sZ'), STR_TO_DATE(lf.last_fix, '%Y-%m-%dT%H:%i:%sZ')))
                 WHEN TIMESTAMPDIFF(SECOND, STR_TO_DATE(lf.last_fix, '%Y-%m-%dT%H:%i:%sZ'), STR_TO_DATE(s.ended_at, '%Y-%m-%dT%H:%i:%sZ')) > c.offline_threshold_minutes * 60
                   THEN GREATEST(0, TIMESTAMPDIFF(SECOND, STR_TO_DATE(s.started_at, '%Y-%m-%dT%H:%i:%sZ'), STR_TO_DATE(lf.last_fix, '%Y-%m-%dT%H:%i:%sZ')))
                 ELSE GREATEST(0, TIMESTAMPDIFF(SECOND, STR_TO_DATE(s.started_at, '%Y-%m-%dT%H:%i:%sZ'), STR_TO_DATE(s.ended_at, '%Y-%m-%dT%H:%i:%sZ')))
               END) AS evidence_seconds
      FROM attendance_days a
      JOIN work_sessions s ON s.employee_id = a.employee_id AND s.work_date = a.work_date
      JOIN users u ON u.id = a.employee_id
      JOIN tracking_configs c ON c.org_id = u.org_id
      LEFT JOIN last_fix lf ON lf.session_id = s.id
     WHERE a.work_date < CURDATE()
     GROUP BY a.employee_id, a.work_date, a.status, a.worked_seconds
),
moved AS (
    SELECT employee_id, work_date, stored_status, stored_seconds, evidence_seconds,
           stored_seconds - evidence_seconds AS removed_seconds
      FROM evidence
     WHERE evidence_seconds <> stored_seconds
)
SELECT u.name AS employee,
       COUNT(*)                                     AS days_that_move,
       ROUND(SUM(m.removed_seconds) / 3600.0, 1)    AS hours_removed,
       ROUND(MAX(m.removed_seconds) / 3600.0, 2)    AS worst_day_hours,
       MIN(m.work_date)                             AS first_affected,
       MAX(m.work_date)                             AS last_affected,
       SUM(CASE WHEN m.stored_status = 'present' AND m.evidence_seconds < 14400
                THEN 1 ELSE 0 END)                  AS days_present_to_partial
  FROM moved m
  JOIN users u ON u.id = m.employee_id
 GROUP BY m.employee_id, u.name
 ORDER BY SUM(m.removed_seconds) DESC;


-- ===========================================================================
-- Dialect notes (MariaDB 11.8 in production vs the SQLite rehearsal)
--
-- Three constructs differ, and the rehearsal harness in
-- website/tests/worked-time-blast-radius.php translates exactly these three
-- and then asserts no MariaDB-only token is left behind:
--
--   1. TIMESTAMPDIFF(SECOND, STR_TO_DATE(x, '%Y-%m-%dT%H:%i:%sZ'), ...)
--      -> (strftime('%s', y) - strftime('%s', x))
--      MariaDB cannot be trusted to coerce 'YYYY-MM-DDTHH:MM:SSZ' to a
--      DATETIME on its own (the trailing Z is not a valid datetime suffix),
--      which is why the format string is explicit rather than relying on an
--      implicit cast. TIMESTAMPDIFF rather than UNIX_TIMESTAMP deliberately:
--      it is pure datetime arithmetic and cannot be shifted by the session
--      time zone.
--   2. GREATEST(0, x) -> MAX(0, x)   (SQLite spells scalar max MAX)
--   3. CURDATE()      -> date('now')
--
-- Everything else is common ground: WITH, window functions (ROW_NUMBER /
-- COUNT(*) OVER ()) and ROUND are all available in MariaDB 10.2+ and
-- SQLite 3.25+. No percentile function is used, precisely so that neither
-- engine's version matters.
--
-- One honest limitation of the rehearsal: SQLite's strftime happily parses
-- the ISO string, so the rehearsal cannot prove MariaDB's STR_TO_DATE format
-- is right. If statement 1 returns NULLs for every hour column on production,
-- that is the thing to check first — run
--   SELECT STR_TO_DATE(recorded_at, '%Y-%m-%dT%H:%i:%sZ') FROM location_fixes LIMIT 1;
-- which must return a datetime and not NULL.
-- ===========================================================================
