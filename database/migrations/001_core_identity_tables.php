<?php

/**
 * Identity: organisations, the one shared user table, the two profile tables,
 * devices, preferences and the auth side-tables.
 *
 * Port note — docs/03-DATABASE-SCHEMA.md is written for PostgreSQL. This is the
 * portable SQLite/MySQL port of it. Column types are Dialect tokens rather than
 * concrete types (see config/dialect.php); the mapping is:
 *   uuid        -> {uuid}   TEXT | CHAR(36), application-generated v4
 *   timestamptz -> {ts}     ISO-8601 UTC with a trailing Z, stored as text
 *   enum        -> {str:N} + CHECK, storing the snake_case value from §2
 *   citext      -> {email}  TEXT COLLATE NOCASE | VARCHAR utf8mb4_unicode_ci
 *   text[]      -> a JSON array in a string column
 *
 * Foreign keys are declared table-level, not inline: MySQL parses an inline
 * REFERENCES clause and then silently ignores it, so an inline ON DELETE
 * CASCADE would quietly stop cascading.
 */
class CoreIdentityTables extends Migration
{
    public function up()
    {
        $this->exec("
            CREATE TABLE IF NOT EXISTS organizations (
                id           {uuid} PRIMARY KEY,
                name         {str} NOT NULL,
                timezone     {str:64} NOT NULL DEFAULT 'Asia/Dhaka',
                weekend_days {str:64} NOT NULL DEFAULT '[5,6]',   -- JSON array of ISO weekday numbers
                created_at   {ts} NOT NULL
            ) {opts};

            -- Shared identity. Both shells authenticate here.
            CREATE TABLE IF NOT EXISTS users (
                id            {uuid} PRIMARY KEY,
                org_id        {uuid} NOT NULL,
                role          {str:16} NOT NULL CHECK (role IN ('employee','admin')),
                name          {str} NOT NULL,
                email         {email} NOT NULL,
                phone         {str:32} NOT NULL DEFAULT '',
                avatar_url    {str:512} NOT NULL DEFAULT '',
                password_hash {str} NOT NULL,
                active        {bool} NOT NULL DEFAULT 1,
                created_at    {ts} NOT NULL,
                updated_at    {ts} NOT NULL,
                UNIQUE (org_id, email),
                FOREIGN KEY (org_id) REFERENCES organizations(id)
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_users_org_role ON users (org_id, role);
            CREATE INDEX IF NOT EXISTS idx_users_name ON users (name);

            -- Employee-only fields.
            CREATE TABLE IF NOT EXISTS employee_profiles (
                user_id       {uuid} PRIMARY KEY,
                employee_code {str:64} NOT NULL,
                designation   {str} NOT NULL DEFAULT '',
                department    {str} NOT NULL DEFAULT '',   -- optional, free text, '' when unset
                region        {str} NOT NULL DEFAULT '',   -- optional, free text, '' when unset
                banner_url    {str:512} NOT NULL DEFAULT '',
                joined_on     {date} NOT NULL,
                reports_to_id {uuid},
                blood_group   {str:16} NOT NULL DEFAULT '',
                address       {str:512} NOT NULL DEFAULT '',
                -- The doc pattern is EMP- followed by 3 to 5 digits. Neither
                -- driver has a portable regex CHECK, so the app validator
                -- enforces the exact format and this catches typos.
                CHECK (employee_code LIKE 'EMP-%'),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (reports_to_id) REFERENCES users(id)
            ) {opts};
            CREATE UNIQUE INDEX IF NOT EXISTS idx_employee_code ON employee_profiles (employee_code);

            -- Admin-only fields.
            CREATE TABLE IF NOT EXISTS admin_profiles (
                user_id    {uuid} PRIMARY KEY,
                admin_code {str:64} NOT NULL UNIQUE,
                role_title {str} NOT NULL DEFAULT '',
                region     {str} NOT NULL DEFAULT '',
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) {opts};

            CREATE TABLE IF NOT EXISTS devices (
                id           {uuid} PRIMARY KEY,
                user_id      {uuid} NOT NULL,
                platform     {str:32} NOT NULL,
                app_version  {str:32} NOT NULL DEFAULT '',
                push_token   {str:255},
                last_seen_at {ts},
                UNIQUE (user_id, platform, push_token),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) {opts};

            CREATE TABLE IF NOT EXISTS user_preferences (
                user_id               {uuid} PRIMARY KEY,
                theme_mode            {str:32} NOT NULL DEFAULT 'system',
                language              {str:64} NOT NULL DEFAULT 'English',
                notifications_enabled {bool} NOT NULL DEFAULT 1,
                report_reminders      {bool} NOT NULL DEFAULT 1,
                session_reminders     {bool} NOT NULL DEFAULT 1,
                system_notifications  {bool} NOT NULL DEFAULT 1,
                high_accuracy_mode    {bool} NOT NULL DEFAULT 1,
                sync_on_mobile_data   {bool} NOT NULL DEFAULT 1,
                battery_saver         {bool} NOT NULL DEFAULT 0,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) {opts};

            -- Auth side-tables. §13 of the data doc lists these as missing.
            CREATE TABLE IF NOT EXISTS refresh_tokens (
                id         {uuid} PRIMARY KEY,
                user_id    {uuid} NOT NULL,
                token_hash {str:255} NOT NULL UNIQUE,
                device_id  {uuid},
                expires_at {ts} NOT NULL,
                revoked_at {ts},
                created_at {ts} NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_refresh_user ON refresh_tokens (user_id);

            -- Backs the Idempotency-Key header (API plan §1.5). The stored
            -- response is replayed verbatim so a retry is indistinguishable.
            CREATE TABLE IF NOT EXISTS idempotency_keys (
                scope      {str:64} NOT NULL,
                user_id    {uuid} NOT NULL,
                idem_key   {str:128} NOT NULL,
                response   {json} NOT NULL,
                created_at {ts} NOT NULL,
                PRIMARY KEY (scope, user_id, idem_key)
            ) {opts};
        ");
    }

    public function down()
    {
        $this->drop([
            'idempotency_keys', 'refresh_tokens', 'user_preferences', 'devices',
            'admin_profiles', 'employee_profiles', 'users', 'organizations',
        ]);
    }
}
