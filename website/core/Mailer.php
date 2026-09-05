<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __BASEDIR__ . '/vendor/autoload.php';

class Mailer
{
    private static function getSetting(string $key, string $default = ''): string
    {
        try {
            if (function_exists('db_fetch_one')) {
                $row = db_fetch_one("SELECT setting_value FROM website_settings WHERE setting_key = ?", [$key]);
                if ($row && isset($row['setting_value']) && $row['setting_value'] !== '') {
                    return $row['setting_value'];
                }
            }
        } catch (Throwable $e) {
        }
        return env(strtoupper($key), $default);
    }

    protected static function getMailer()
    {
        $mail = new PHPMailer(true);

        $host     = self::getSetting('smtp_host', 'smtp.gmail.com');
        $port     = (int) self::getSetting('smtp_port', '587');
        $username = self::getSetting('smtp_username', 'bcasudipta@gmail.com');
        $password = self::getSetting('smtp_password', 'cgcw bvfe jgqv jbsm');
        $fromEmail = self::getSetting('smtp_from_email', $username);
        $fromName  = self::getSetting('smtp_from_name', 'Hazra EV');

        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $username;
        $mail->Password   = $password;
        $mail->SMTPSecure = ($port === 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $port;

        $mail->setFrom($fromEmail, $fromName);

        return $mail;
    }

    public static function send($to, $subject, $body, $isHtml = false)
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid recipient email: $to");
        }

        $mail = self::getMailer();
        try {
            $mail->addAddress($to);
            $mail->isHTML($isHtml);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->send();
            return true;
        } catch (Exception $e) {
            throw new Exception("Mailer Error: {$mail->ErrorInfo}");
        }
    }

    public static function VerifyMail($email)
    {
        $subject = "Email Verification";
        $body = "Please click the link below to verify your email address:\n\n" . 
                "https://yourdomain.com/verify?email=" . urlencode($email);
        return self::send($email, $subject, $body);
    }
}
