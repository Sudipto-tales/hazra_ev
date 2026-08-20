# Directory Structure

```
vayu/
├── index.php                    # Single entry point
├── .env.example                 # Environment variable template
├── .htaccess                    # Apache rewrite rules
├── composer.json                # PHP dependencies
│
├── config/
│   ├── bootstrap.php            # Autoloader — loads env, config, db, all core files
│   ├── config.php               # App constants & route merging (frontend + API)
│   ├── db.php                   # Database connections (SQLite/MySQL/MongoDB)
│   ├── env.php                  # env() helper function
│   ├── framework.php            # Framework metadata (name, version, author)
│   ├── migrate.php              # Migration runner
│   ├── migration.php            # Migration base class
│   └── route.php                # Route dispatcher — calls RouteManager::dispatch()
│
├── core/
│   ├── App.php                  # Component system (render, component, exists)
│   ├── ApiController.php        # Base class for API controllers
│   ├── ApiRequest.php           # Request parser (body, query, headers, bearer token)
│   ├── ApiResponse.php          # JSON response helpers (success, error, 404, 401, etc.)
│   ├── Async.php                # Parallel HTTP requests & deferred tasks
│   ├── Auth.php                 # Session-based authentication
│   ├── BaseController.php       # Base class for frontend controllers
│   ├── Cors.php                 # CORS header management
│   ├── Helpers.php              # API request helpers & view utilities
│   ├── HttpMethod.php           # HTTP method enum (GET, POST, PUT, etc.)
│   ├── JwtAuth.php              # JWT token generation & validation
│   ├── Mailer.php               # PHPMailer wrapper for SMTP emails
│   ├── RouteManager.php         # URL → Controller dispatcher
│   ├── RouteProvider.php        # Abstract base for route definitions
│   └── Validator.php            # Input validation (required, email, min, max, etc.)
│
├── app/
│   ├── view.php                 # Frontend route definitions (ViewRouteProvider)
│   ├── controllers/             # Frontend controllers
│   │   └── Welcome.php
│   ├── components/              # Reusable UI components
│   │   ├── head.php
│   │   ├── footer.php
│   │   └── hero.php
│   └── page/                    # View/page templates
│       └── welcome.php
│
├── api/
│   ├── gateway.php              # API route definitions (ApiGatewayProvider)
│   └── controllers/             # API controllers
│       └── UserController.php
│
├── assets/
│   ├── css/style.css            # Stylesheets
│   └── js/script.js             # Client-side JavaScript
│
├── database/
│   └── migrations/              # Database migration files
│       └── UsersTable.php
│
├── storage/                     # File uploads & application data
├── docs/                        # Documentation
└── vendor/                      # Composer dependencies (git-ignored)
```

## Key Directories

| Directory | Purpose |
|-----------|---------|
| `config/` | Framework configuration, bootstrap, database setup |
| `core/` | Framework engine — all classes auto-loaded by bootstrap |
| `app/` | Your application — controllers, views, components |
| `api/` | API layer — gateway routes and API controllers |
| `database/` | Migrations and seeders |
| `assets/` | Static files served directly |
| `storage/` | Runtime files (uploads, logs, cache) |
