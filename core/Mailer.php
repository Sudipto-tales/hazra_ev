<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __BASEDIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/messaging/Registry.php';

class Mailer
{
    /**
     * SMTP configuration, from MessagingCredentialStore: the encrypted store
     * first, then the old website_settings rows the Settings tab wrote, then
     * SMTP_* in the environment.
     *
     * This method used to fall back to a real Gmail address and app password
     * written into the source of a public repository. They are gone: an
     * unconfigured mailer now fails loudly instead of quietly sending from
     * somebody's personal mailbox. Enter the credentials in
     * Admin → Settings → Messaging.
     */
    private static function getSetting(string $key, string $default = ''): string
    {
        $value = MessagingRegistry::value('email.smtp', $key);

        return $value !== '' ? $value : $default;
    }

    protected static function getMailer()
    {
        $mail = new PHPMailer(true);

        $host     = self::getSetting('host');
        $port     = (int) (self::getSetting('port') ?: 587);
        $username = self::getSetting('username');
        $password = self::getSetting('password');
        $fromEmail = self::getSetting('from_email', $username);
        $fromName  = self::getSetting('from_name', 'Hazra EV');
        $encryption = self::getSetting('encryption', 'tls');

        if ($host === '' || $username === '' || $password === '') {
            throw new Exception('SMTP is not configured. Set the host, username and password in Admin → Settings → Messaging.');
        }

        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $username;
        $mail->Password   = $password;
        $mail->Port       = $port;

        // The explicit setting wins; the port is only a fallback guess for
        // configurations saved before the field existed.
        if ($encryption === 'none') {
            $mail->SMTPSecure = false;
            $mail->SMTPAutoTLS = false;
        } else {
            $mail->SMTPSecure = ($encryption === 'ssl' || $port === 465)
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->setFrom($fromEmail !== '' ? $fromEmail : $username, $fromName);

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
