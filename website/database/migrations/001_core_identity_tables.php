<?php

/**
 * Identity: organisations, the one shared user table, the two profile tables,
 * devices, preferences and the auth side-tables.
 *
 * Port note — docs/03-DATABASE-SCHEMA.md is written for PostgreSQL. This is the
 * SQLite development port of it:
 *   uuid        -> TEXT (application-generated v4, see api/support/Uuid.php)
 *   timestamptz -> TEXT, ISO-8601 UTC with a trailing Z
 *   enum        -> TEXT + CHECK, storing the snake_case value from §2
 *   citext      -> TEXT COLLATE NOCASE
 *   text[]      -> TEXT holding a JSON array
 */
class CoreIdentityTables
{
    protected $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function up()
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS organizations (
                id           TEXT PRIMARY KEY,
                name         TEXT NOT NULL,
                timezone     TEXT NOT NULL DEFAULT 'Asia/Dhaka',
                weekend_days TEXT NOT NULL DEFAULT '[5,6]',   -- JSON array of ISO weekday numbers
                created_at   TEXT NOT NULL
            );

            -- Shared identity. Both shells authenticate here.
            CREATE TABLE IF NOT EXISTS users (
                id            TEXT PRIMARY KEY,
                org_id        TEXT NOT NULL REFERENCES organizations(id),
                role          TEXT NOT NULL CHECK (role IN ('employee','admin')),
                name          TEXT NOT NULL,
                email         TEXT NOT NULL COLLATE NOCASE,
                phone         TEXT NOT NULL DEFAULT '',
                avatar_url    TEXT NOT NULL DEFAULT '',
                password_hash TEXT NOT NULL,
                active        INTEGER NOT NULL DEFAULT 1,
                created_at    TEXT NOT NULL,
                updated_at    TEXT NOT NULL,
                UNIQUE (org_id, email)
            );
            CREATE INDEX IF NOT EXISTS idx_users_org_role ON users (org_id, role);
            CREATE INDEX IF NOT EXISTS idx_users_name ON users (name);

            -- Employee-only fields.
            CREATE TABLE IF NOT EXISTS employee_profiles (
                user_id       TEXT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
                employee_code TEXT NOT NULL,
                designation   TEXT NOT NULL DEFAULT '',
                department    TEXT NOT NULL DEFAULT '',   -- optional, free text, '' when unset
                region        TEXT NOT NULL DEFAULT '',   -- optional, free text, '' when unset
                banner_url    TEXT NOT NULL DEFAULT '',
                joined_on     TEXT NOT NULL,              -- date, yyyy-mm-dd
                reports_to_id TEXT REFERENCES users(id),
                blood_group   TEXT NOT NULL DEFAULT '',
                address       TEXT NOT NULL DEFAULT '',
                -- The doc's '^EMP-[0-9]{3,5}$' has no SQLite equivalent; the
                -- app validator enforces the exact format, this catches typos.
                CHECK (employee_code LIKE 'EMP-%')
            );
            CREATE UNIQUE INDEX IF NOT EXISTS idx_employee_code ON employee_profiles (employee_code);

            -- Admin-only fields.
            CREATE TABLE IF NOT EXISTS admin_profiles (
                user_id    TEXT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
                admin_code TEXT NOT NULL UNIQUE,
                role_title TEXT NOT NULL DEFAULT '',
                region     TEXT NOT NULL DEFAULT ''
            );

            CREATE TABLE IF NOT EXISTS devices (
                id           TEXT PRIMARY KEY,
                user_id      TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                platform     TEXT NOT NULL,
                app_version  TEXT NOT NULL DEFAULT '',
                push_token   TEXT,
                last_seen_at TEXT,
                UNIQUE (user_id, platform, push_token)
            );

            CREATE TABLE IF NOT EXISTS user_preferences (
                user_id               TEXT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
                theme_mode            TEXT    NOT NULL DEFAULT 'system',
                language              TEXT    NOT NULL DEFAULT 'English',
                notifications_enabled INTEGER NOT NULL DEFAULT 1,
                report_reminders      INTEGER NOT NULL DEFAULT 1,
                session_reminders     INTEGER NOT NULL DEFAULT 1,
                system_notifications  INTEGER NOT NULL DEFAULT 1,
                high_accuracy_mode    INTEGER NOT NULL DEFAULT 1,
                sync_on_mobile_data   INTEGER NOT NULL DEFAULT 1,
                battery_saver         INTEGER NOT NULL DEFAULT 0
            );

            -- Auth side-tables. §13 of the data doc lists these as missing.
            CREATE TABLE IF NOT EXISTS refresh_tokens (
                id         TEXT PRIMARY KEY,
                user_id    TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                token_hash TEXT NOT NULL UNIQUE,
                device_id  TEXT,
                expires_at TEXT NOT NULL,
                revoked_at TEXT,
                created_at TEXT NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_refresh_user ON refresh_tokens (user_id);

            -- Backs the Idempotency-Key header (API plan §1.5). The stored
            -- response is replayed verbatim so a retry is indistinguishable.
            CREATE TABLE IF NOT EXISTS idempotency_keys (
                scope      TEXT NOT NULL,
                user_id    TEXT NOT NULL,
                idem_key   TEXT NOT NULL,
                response   TEXT NOT NULL,
                created_at TEXT NOT NULL,
                PRIMARY KEY (scope, user_id, idem_key)
            );
        ");
    }

    public function down()
    {
        foreach ([
            'idempotency_keys', 'refresh_tokens', 'user_preferences', 'devices',
            'admin_profiles', 'employee_profiles', 'users', 'organizations',
        ] as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
    }
}
