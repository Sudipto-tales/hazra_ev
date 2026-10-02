# Coder Framework

A lightweight PHP micro-framework for simple web applications and starter SaaS projects.

## What changed
- Added `app/Controllers/WelcomeController.php`
- Improved environment-driven DB config
- Added docs and usage guidance
- Updated Git ignore rules for vendor and local files

## Quick start
1. Copy `.env.example` to `.env`
2. Customize your environment values
3. Run `composer install`
4. Open `/` or `/hello`

## Project layout
```
app/
  bridge/
  page/
api/
config/
core/
database/
assets/
vendor/
```

## Documentation
- `docs/usage.md`
- `docs/architecture.md`
- `docs/features.md`

## Composer and production
- `composer.json` must be committed for any PHP project or package.
- `composer.lock` alone is not enough.
- Use `composer install` in production after `composer.json` is present.

## Remove dead code?
The project still contains optional files under `core/` such as `Auth.php` and `Mailer.php`. They are loaded by bootstrap but not required by the default routes. If you want, I can clean those up next.
