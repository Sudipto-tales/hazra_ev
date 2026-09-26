<?php

/**
 * Migration 015: website_settings table
 */
class SettingsTable extends Migration
{
    public function up()
    {
        $this->exec("
            CREATE TABLE IF NOT EXISTS website_settings (
                id            {uuid} PRIMARY KEY,
                group_name    {str:64} NOT NULL DEFAULT 'general',
                setting_key   {str:128} NOT NULL,
                setting_value {text},
                updated_at    {ts} NOT NULL,
                CONSTRAINT uq_group_key UNIQUE (group_name, setting_key)
            ) {opts};
        ");
    }

    public function down()
    {
        $this->drop(['website_settings']);
    }
}
