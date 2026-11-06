# CMS dashboard "latest activity" feed is real

**Agent:** web-agent · **Date:** 2026-11-06

## What changed (website/api/controllers/AdminController.php only)

`AdminController::summary` returned `'recent' => []` hardcoded, so the admin
dashboard's "latest activity across your EV business" panel rendered a dead strip.
New private `recentActivity($limit = 10)` merges the newest rows from
`website_leads`, `job_applications` and `warranty_registrations`, sorts them
newest-first (created_at is ISO-8601 UTC, so a string sort is chronological), and
caps at ten. Each row is flat `{kind, title, subtitle, at, href}` the panel renders
without a join. `warranty_pending` was already in `stats` but had no tile — the
design side now renders it.

## Contract

This is the **website-only admin surface** (02 §3.15), not the 29-path mobile
contract — no mobile consumer. The JS that reads it (`dashboard.js`) is updated in
the same change set (design column).

## Verification

New `website/tests/admin-dashboard-recent.php` (throwaway SQLite, real migrations,
reflection on the private method): four inbound rows across the three tables merge
newest-first, the feed is flat/renderable, and the limit caps it. **All 25 PHP
suites pass**, `php -l` clean.
