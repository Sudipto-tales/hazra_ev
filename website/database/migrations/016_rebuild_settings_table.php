<?php

/**
 * Migration 016: Rebuild website_settings with group_name column.
 *
 * Migration 011 created website_settings as (setting_key PK, setting_value, updated_at).
 * Migration 015 tried to create a richer version with id, group_name, and a
 * UNIQUE(group_name, setting_key) constraint — but used CREATE TABLE IF NOT EXISTS,
 * which was a no-op because the table already existed.
 *
 * AdminController::patchSettings() writes group_name and uses
 * ON CONFLICT(group_name, setting_key), so the table must have that column and
 * constraint. This migration rebuilds the table, preserving existing rows by
 * defaulting their group_name to 'general'.
 */
class RebuildSettingsTable extends Migration
{
    public function up()
    {
        // 1. Copy existing rows into a temp table
        $this->exec("
            CREATE TABLE IF NOT EXISTS _settings_backup (
                setting_key   {str:128} NOT NULL,
                setting_value {text},
                updated_at    {ts} NOT NULL
            ) {opts};
        ");

        // Only copy if the source table exists and has rows
        try {
            $this->pdo->exec("INSERT INTO _settings_backup SELECT setting_key, setting_value, updated_at FROM website_settings");
        } catch (\Exception $e) {
            // Table might not exist or might already have the new schema — either way, continue
        }

        // 2. Drop the old table
        $this->pdo->exec("DROP TABLE IF EXISTS website_settings");

        // 3. Create with the correct schema
        $this->exec("
            CREATE TABLE website_settings (
                id            {uuid} PRIMARY KEY,
                group_name    {str:64} NOT NULL DEFAULT 'general',
                setting_key   {str:128} NOT NULL,
                setting_value {text},
                updated_at    {ts} NOT NULL,
                CONSTRAINT uq_group_key UNIQUE (group_name, setting_key)
            ) {opts};
        ");

        // 4. Restore rows with generated UUIDs and default group_name
        try {
            $rows = $this->pdo->query("SELECT setting_key, setting_value, updated_at FROM _settings_backup")->fetchAll();
            $stmt = $this->pdo->prepare(
                "INSERT OR IGNORE INTO website_settings (id, group_name, setting_key, setting_value, updated_at) VALUES (?, 'general', ?, ?, ?)"
            );
            foreach ($rows as $row) {
                $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
                $stmt->execute([$uuid, $row['setting_key'], $row['setting_value'], $row['updated_at']]);
            }
        } catch (\Exception $e) {
            // No rows to restore — fresh install
        }

        // 5. Clean up
        $this->pdo->exec("DROP TABLE IF EXISTS _settings_backup");
    }

    public function down()
    {
        // Reverting would lose group_name data; just drop
        $this->drop(['website_settings']);
    }
}
