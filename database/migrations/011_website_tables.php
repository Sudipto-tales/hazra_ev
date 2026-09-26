<?php

/**
 * Public website infrastructure:
 * - website_settings: Key-value configuration (SMTP settings, contact numbers, address, site copy)
 * - website_leads: Customer form submissions (test rides, dealer applications, contact form, dealership enquiries)
 */
class WebsiteTables extends Migration
{
    public function up()
    {
        $this->exec("
            CREATE TABLE IF NOT EXISTS website_settings (
                setting_key   {str:128} PRIMARY KEY,
                setting_value {text} NOT NULL DEFAULT '',
                updated_at    {ts} NOT NULL
            ) {opts};

            CREATE TABLE IF NOT EXISTS website_leads (
                id           {uuid} PRIMARY KEY,
                type         {str:32} NOT NULL DEFAULT 'contact',  -- 'contact', 'test_ride', 'dealer', 'dealership_enquiry'
                name         {str} NOT NULL DEFAULT '',
                phone        {str:64} NOT NULL DEFAULT '',
                email        {str} NOT NULL DEFAULT '',
                details_json {text} NOT NULL {default '{}'},
                status       {str:32} NOT NULL DEFAULT 'new',       -- 'new', 'read', 'archived', 'contacted'
                created_at   {ts} NOT NULL,
                updated_at   {ts} NOT NULL
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_website_leads_type_status ON website_leads (type, status);
            CREATE INDEX IF NOT EXISTS idx_website_leads_created ON website_leads (created_at);
        ");
    }

    public function down()
    {
        $this->drop(['website_leads', 'website_settings']);
    }
}
