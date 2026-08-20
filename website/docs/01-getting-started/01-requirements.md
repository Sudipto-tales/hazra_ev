# Requirements

## System Requirements

| Requirement | Minimum Version |
|-------------|----------------|
| PHP | 8.1+ |
| Composer | 2.x |
| cURL extension | Recommended (required for `Async::parallel()`) |
| PDO extension | Required for SQLite/MySQL |
| MongoDB extension | Required only if using MongoDB |

## PHP Extensions

| Extension | Required | Used By |
|-----------|----------|---------|
| `pdo_sqlite` | If using SQLite | `config/db.php` |
| `pdo_mysql` | If using MySQL | `config/db.php` |
| `mongodb` | If using MongoDB | `config/db.php` |
| `curl` | Recommended | `Async`, `Helpers` |
| `mbstring` | Recommended | String handling |
| `openssl` | Recommended | `JwtAuth`, `Mailer` |

## Composer Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| `phpmailer/phpmailer` | ^6.10 | SMTP email sending |
| `vlucas/phpdotenv` | ^5.6 | `.env` file loading |
| `firebase/php-jwt` | ^7.0 | JWT token generation & validation |
