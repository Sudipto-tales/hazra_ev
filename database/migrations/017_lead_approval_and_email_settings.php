<?php

/**
 * Migration 017: Lead approval fields (scheduled_at, admin_note)
 * and email / SMTP settings defaults.
 */
class LeadApprovalAndEmailSettings extends Migration
{
    public function up()
    {
        // 1. Add scheduled_at and admin_note to website_leads
        $this->addColumn('website_leads', 'scheduled_at', '{str:64}');
        $this->addColumn('website_leads', 'admin_note', '{text}');

        // 2. Default email settings
        $defaults = [
            // SMTP Config
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => '587',
            'smtp_encryption' => 'tls',
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_from_email' => 'info@hazraev.com',
            'smtp_from_name' => 'Hazra EV',
            'support_email' => 'sales@hazraev.com',

            // Toggles: New Lead
            'notify_team_new_lead' => '1',
            'notify_customer_new_lead' => '1',

            // Toggles: Lead Approval
            'notify_team_on_approve' => '1',
            'notify_customer_on_approve' => '1',

            // Toggles: Lead Rejection
            'notify_team_on_reject' => '0',
            'notify_customer_on_reject' => '1',
        ];

        $now = gmdate('Y-m-d H:i:s');
        foreach ($defaults as $key => $val) {
            $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
            
            try {
                db_execute(
                    "INSERT INTO website_settings (id, group_name, setting_key, setting_value, updated_at)
                     VALUES (?, 'email', ?, ?, ?)
                     ON CONFLICT(group_name, setting_key) DO NOTHING",
                    [$uuid, $key, (string) $val, $now]
                );
            } catch (\Throwable $e) {
                // Ignore duplicate or fallback
            }
        }
    }

    public function down()
    {
        // Non-destructive down
    }
}
