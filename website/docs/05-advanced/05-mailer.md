# Mailer

The `Mailer` class (`core/Mailer.php`) wraps PHPMailer for SMTP email sending.

## Basic Usage

```php
// Plain text
Mailer::send('user@example.com', 'Welcome!', 'Thanks for signing up.');

// HTML
Mailer::send('user@example.com', 'Welcome!', '<h1>Thanks!</h1>', true);
```

## Configuration

SMTP credentials are configured in `core/Mailer.php`. The framework is pre-configured for Gmail SMTP.

Recommended `.env` keys (you may need to update `Mailer.php` to read from these):

```env
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_FROM_NAME=MyApp
```

## Integration with Auth

The `Auth::register()` method automatically sends a verification email using `Mailer::send()`. The email contains a verification token link.

## Gmail App Passwords

To use Gmail SMTP, you need an App Password (not your regular password):

1. Enable 2-Step Verification on your Google account
2. Go to Security > App Passwords
3. Generate a password for "Mail"
4. Use that password in your SMTP config
