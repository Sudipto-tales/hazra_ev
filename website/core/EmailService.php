<?php

require_once __DIR__ . '/Mailer.php';

class EmailService
{
    private static function getEmailSettings(): array
    {
        $settings = [];
        try {
            $rows = db_fetch_all("SELECT setting_key, setting_value FROM website_settings WHERE group_name = 'email' OR group_name = 'general'");
            foreach ($rows as $r) {
                $settings[$r['setting_key']] = $r['setting_value'];
            }
        } catch (\Throwable $e) {
            error_log("[EmailService] Failed to load settings: " . $e->getMessage());
        }

        $defaults = [
            'smtp_from_name' => 'Hazra EV',
            'smtp_from_email' => 'info@hazraev.com',
            'support_email' => 'sales@hazraev.com',
            'notify_team_new_lead' => '1',
            'notify_customer_new_lead' => '1',
            'notify_team_on_approve' => '1',
            'notify_customer_on_approve' => '1',
            'notify_team_on_reject' => '0',
            'notify_customer_on_reject' => '1',
        ];

        return array_merge($defaults, $settings);
    }

    public static function notifyNewLead(array $lead): void
    {
        try {
            $cfg = self::getEmailSettings();
            $typeLabel = ucwords(str_replace('_', ' ', $lead['type'] ?? 'contact'));
            $name = $lead['name'] ?: 'Customer';
            $phone = $lead['phone'] ?: 'N/A';
            $email = $lead['email'] ?: 'N/A';
            $siteName = $cfg['smtp_from_name'] ?: 'Hazra EV';

            // 1. Notify Team
            if (!empty($cfg['notify_team_new_lead']) && $cfg['notify_team_new_lead'] !== '0') {
                $teamTarget = $cfg['support_email'] ?: $cfg['smtp_from_email'];
                if (filter_var($teamTarget, FILTER_VALIDATE_EMAIL)) {
                    $subject = "⚡ New {$typeLabel} Inquiry: {$name}";
                    $html = "
                    <div style='font-family: Arial, sans-serif; color: #333; line-height: 1.6; max-width: 600px; margin: 0 auto; border: 1px solid #eee; border-radius: 8px; overflow: hidden;'>
                        <div style='background: #111827; color: #fff; padding: 20px; text-align: center;'>
                            <h2 style='margin: 0; font-size: 20px;'>{$siteName} — New {$typeLabel} Lead</h2>
                        </div>
                        <div style='padding: 24px;'>
                            <p style='font-size: 16px;'>You have received a new lead submission from the website:</p>
                            <table style='width: 100%; border-collapse: collapse; margin-top: 16px;'>
                                <tr style='border-bottom: 1px solid #f0f0f0;'><td style='padding: 8px; font-weight: bold; width: 140px;'>Name:</td><td style='padding: 8px;'>{$name}</td></tr>
                                <tr style='border-bottom: 1px solid #f0f0f0;'><td style='padding: 8px; font-weight: bold;'>Phone:</td><td style='padding: 8px;'>{$phone}</td></tr>
                                <tr style='border-bottom: 1px solid #f0f0f0;'><td style='padding: 8px; font-weight: bold;'>Email:</td><td style='padding: 8px;'>{$email}</td></tr>
                                <tr style='border-bottom: 1px solid #f0f0f0;'><td style='padding: 8px; font-weight: bold;'>Type:</td><td style='padding: 8px;'>{$typeLabel}</td></tr>
                            </table>
                        </div>
                    </div>";
                    Mailer::send($teamTarget, $subject, $html, true);
                }
            }

            // 2. Notify Customer
            if (!empty($cfg['notify_customer_new_lead']) && $cfg['notify_customer_new_lead'] !== '0' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $subject = "Thank you for contacting {$siteName}";
                $html = "
                <div style='font-family: Arial, sans-serif; color: #333; line-height: 1.6; max-width: 600px; margin: 0 auto; border: 1px solid #eee; border-radius: 8px; overflow: hidden;'>
                    <div style='background: #10b981; color: #fff; padding: 20px; text-align: center;'>
                        <h2 style='margin: 0; font-size: 20px;'>Thank You, {$name}!</h2>
                    </div>
                    <div style='padding: 24px;'>
                        <p>We have received your <strong>{$typeLabel}</strong> request at {$siteName}.</p>
                        <p>Our team will review your inquiry and reach out to you shortly.</p>
                        <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                        <p style='font-size: 12px; color: #888;'>Best regards,<br>Team {$siteName}</p>
                    </div>
                </div>";
                Mailer::send($email, $subject, $html, true);
            }
        } catch (\Throwable $e) {
            error_log("[EmailService::notifyNewLead] Error: " . $e->getMessage());
        }
    }

    public static function notifyLeadApproved(array $lead): void
    {
        try {
            $cfg = self::getEmailSettings();
            $typeLabel = ucwords(str_replace('_', ' ', $lead['type'] ?? 'contact'));
            $name = $lead['name'] ?: 'Customer';
            $phone = $lead['phone'] ?: 'N/A';
            $email = $lead['email'] ?: 'N/A';
            $scheduledAt = $lead['scheduled_at'] ?: 'As arranged';
            $adminNote = $lead['admin_note'] ?: 'No additional notes.';
            $siteName = $cfg['smtp_from_name'] ?: 'Hazra EV';

            // 1. Notify Team on Approve
            if (!empty($cfg['notify_team_on_approve']) && $cfg['notify_team_on_approve'] !== '0') {
                $teamTarget = $cfg['support_email'] ?: $cfg['smtp_from_email'];
                if (filter_var($teamTarget, FILTER_VALIDATE_EMAIL)) {
                    $subject = "✅ Approved {$typeLabel}: {$name}";
                    $html = "
                    <div style='font-family: Arial, sans-serif; color: #333; line-height: 1.6; max-width: 600px; margin: 0 auto; border: 1px solid #eee; border-radius: 8px; overflow: hidden;'>
                        <div style='background: #059669; color: #fff; padding: 20px; text-align: center;'>
                            <h2 style='margin: 0; font-size: 20px;'>{$typeLabel} Approved</h2>
                        </div>
                        <div style='padding: 24px;'>
                            <p>The following inquiry has been approved by admin:</p>
                            <table style='width: 100%; border-collapse: collapse; margin-top: 16px;'>
                                <tr style='border-bottom: 1px solid #f0f0f0;'><td style='padding: 8px; font-weight: bold;'>Customer:</td><td style='padding: 8px;'>{$name} ({$phone})</td></tr>
                                <tr style='border-bottom: 1px solid #f0f0f0;'><td style='padding: 8px; font-weight: bold;'>Scheduled Date/Time:</td><td style='padding: 8px; color: #059669; font-weight: bold;'>{$scheduledAt}</td></tr>
                                <tr style='border-bottom: 1px solid #f0f0f0;'><td style='padding: 8px; font-weight: bold;'>Admin Note:</td><td style='padding: 8px;'>{$adminNote}</td></tr>
                            </table>
                        </div>
                    </div>";
                    Mailer::send($teamTarget, $subject, $html, true);
                }
            }

            // 2. Notify Customer on Approve
            if (!empty($cfg['notify_customer_on_approve']) && $cfg['notify_customer_on_approve'] !== '0' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $subject = "🎉 Your {$typeLabel} Appointment Confirmation — {$siteName}";
                $html = "
                <div style='font-family: Arial, sans-serif; color: #333; line-height: 1.6; max-width: 600px; margin: 0 auto; border: 1px solid #eee; border-radius: 8px; overflow: hidden;'>
                    <div style='background: #059669; color: #fff; padding: 24px; text-align: center;'>
                        <h2 style='margin: 0; font-size: 22px;'>Your {$typeLabel} is Confirmed!</h2>
                    </div>
                    <div style='padding: 24px;'>
                        <p style='font-size: 16px;'>Dear <strong>{$name}</strong>,</p>
                        <p>We are delighted to inform you that your request for a <strong>{$typeLabel}</strong> has been approved!</p>
                        <div style='background: #f0fdf4; border-left: 4px solid #059669; padding: 16px; margin: 20px 0; border-radius: 4px;'>
                            <p style='margin: 0 0 8px 0;'><strong>📅 Scheduled Date & Time:</strong> {$scheduledAt}</p>
                            " . ($lead['admin_note'] ? "<p style='margin: 0;'><strong>📝 Note from Hazra EV:</strong> {$adminNote}</p>" : "") . "
                        </div>
                        <p>If you have any questions or need to reschedule, please contact us at {$cfg['smtp_from_email']} or call our representative.</p>
                        <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                        <p style='font-size: 12px; color: #888;'>Best regards,<br>Team {$siteName}</p>
                    </div>
                </div>";
                Mailer::send($email, $subject, $html, true);
            }
        } catch (\Throwable $e) {
            error_log("[EmailService::notifyLeadApproved] Error: " . $e->getMessage());
        }
    }

    public static function notifyLeadRejected(array $lead): void
    {
        try {
            $cfg = self::getEmailSettings();
            $typeLabel = ucwords(str_replace('_', ' ', $lead['type'] ?? 'contact'));
            $name = $lead['name'] ?: 'Customer';
            $email = $lead['email'] ?: 'N/A';
            $siteName = $cfg['smtp_from_name'] ?: 'Hazra EV';

            if (!empty($cfg['notify_customer_on_reject']) && $cfg['notify_customer_on_reject'] !== '0' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $subject = "Update regarding your {$typeLabel} request — {$siteName}";
                $html = "
                <div style='font-family: Arial, sans-serif; color: #333; line-height: 1.6; max-width: 600px; margin: 0 auto; border: 1px solid #eee; border-radius: 8px; overflow: hidden;'>
                    <div style='background: #374151; color: #fff; padding: 20px; text-align: center;'>
                        <h2 style='margin: 0; font-size: 20px;'>{$typeLabel} Update</h2>
                    </div>
                    <div style='padding: 24px;'>
                        <p>Dear <strong>{$name}</strong>,</p>
                        <p>Thank you for reaching out to {$siteName}. Regrettably, we are unable to process your request at this time.</p>
                        " . ($lead['admin_note'] ? "<p><strong>Note:</strong> {$lead['admin_note']}</p>" : "") . "
                        <p>If you believe this is an error or have further questions, please reply to this email.</p>
                        <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                        <p style='font-size: 12px; color: #888;'>Best regards,<br>Team {$siteName}</p>
                    </div>
                </div>";
                Mailer::send($email, $subject, $html, true);
            }
        } catch (\Throwable $e) {
            error_log("[EmailService::notifyLeadRejected] Error: " . $e->getMessage());
        }
    }
}
