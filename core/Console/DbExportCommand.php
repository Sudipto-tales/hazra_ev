<?php

/**
 * `php vayu db:export` — render the whole schema, plus the bootstrap admin, as
 * a plain .sql file for import through phpMyAdmin.
 *
 * The normal path to a server database is `php vayu db:sync`. This exists for
 * the case where nothing can reach MySQL over the network — shared hosting with
 * remote access closed, a firewall in the way — and the only door open is the
 * control panel's import form.
 *
 * It needs no database connection: the DDL is rendered from the same migration
 * files db:sync would run, against an in-memory SQLite handle that exists only
 * to satisfy Dialect. What comes out is the schema db:sync would have built.
 *
 * The `migrations` and `seeders` ledgers are written and filled too, so a later
 * db:sync against the imported database correctly finds nothing to do.
 */
class DbExportCommand
{
    private string $baseDir;
    private array $framework;

    public function __construct(string $baseDir, array $framework)
    {
        $this->baseDir = $baseDir;
        $this->framework = $framework;
    }

    public function run(array $args): void
    {
        require_once $this->baseDir . '/config/dialect.php';
        require_once $this->baseDir . '/config/migration.php';
        require_once $this->baseDir . '/api/support/Uuid.php';
        require_once $this->baseDir . '/api/support/Wire.php';

        $schemaOnly = in_array('--schema-only', $args, true);
        $email      = $this->option($args, 'admin-email') ?? 'admin@hazraev.com';
        $password   = $this->option($args, 'admin-password') ?? 'admin123';

        // Paren-form TEXT defaults. Valid on MySQL 8.0.13+ and MariaDB 10.2+,
        // which is every server this could plausibly be imported into, so the
        // output does not have to know which one it is going to.
        $this->bootDialectForMysql('8.0.36');

        $statements = $this->renderMigrations();

        $now     = Wire::now();
        $batch   = time();
        $orgId   = Uuid::v4();
        $adminId = Uuid::v4();

        $out = [];
        $out[] = $this->header($email, $password, $schemaOnly);
        $out[] = 'SET NAMES utf8mb4;';
        $out[] = 'SET FOREIGN_KEY_CHECKS = 0;';

        foreach ($statements as $statement) {
            $out[] = rtrim($statement) . ';';
        }

        $out[] = '-- ------------------------------------------------------------- ledgers';
        $out[] = rtrim(Dialect::ddl(
            "CREATE TABLE IF NOT EXISTS migrations (
            id        {autoid},
            migration {str} NOT NULL,
            batch     {int}
        ) {opts}"
        )) . ';';
        $out[] = rtrim(Dialect::ddl(
            "CREATE TABLE IF NOT EXISTS seeders (
            id     {autoid},
            seeder {str} NOT NULL,
            batch  {int}
        ) {opts}"
        )) . ';';

        $rows = implode(",\n    ", array_map(
            fn(string $n): string => '(' . $this->quote($n) . ", {$batch})",
            $this->migrationNames(),
        ));
        $out[] = "INSERT INTO migrations (migration, batch) VALUES\n    {$rows};";

        if (!$schemaOnly) {
            $out[] = "INSERT INTO seeders (seeder, batch) VALUES\n    "
                . '(' . $this->quote('001_bootstrap_admin') . ", {$batch});";

            $out[] = '-- ---------------------------------------------------------------- data';
            $out[] = $this->bootstrapData($orgId, $adminId, $email, $password, $now);
        }

        $out[] = 'SET FOREIGN_KEY_CHECKS = 1;';

        $dir = $this->baseDir . '/database/exports';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir . '/hazra_ev_mysql' . ($schemaOnly ? '_schema' : '_bootstrap') . '.sql';
        file_put_contents($path, implode("\n\n", $out) . "\n");

        echo PHP_EOL;
        echo "  \033[32mwritten\033[0m database/exports/" . basename($path) . PHP_EOL;
        echo "  \033[2m" . count($statements) . " schema statements · "
            . number_format(filesize($path)) . " bytes\033[0m" . PHP_EOL;

        if (!$schemaOnly) {
            echo "  \033[2madmin: {$email} / {$password}\033[0m" . PHP_EOL;
        }

        echo PHP_EOL;
    }

    /**
     * Dialect normally learns the driver from a live connection. Here there is
     * none, so it is pointed at a throwaway in-memory handle and told the
     * target is MySQL. The handle is never queried for schema.
     */
    private function bootDialectForMysql(string $version): void
    {
        Dialect::boot(new PDO('sqlite::memory:'), 'mysql');

        $reflection = new ReflectionClass('Dialect');

        $v = $reflection->getProperty('version');
        $v->setAccessible(true);
        $v->setValue(null, $version);

        // A null PDO makes the CREATE INDEX IF NOT EXISTS emulation answer
        // "does not exist" instead of querying information_schema.
        $p = $reflection->getProperty('pdo');
        $p->setAccessible(true);
        $p->setValue(null, null);
    }

    /** @return string[] every DDL statement, in migration order */
    private function renderMigrations(): array
    {
        // Migration::exec() calls $this->pdo->exec(), and the constructor is
        // typed PDO — so this collects statements by being one.
        $recorder = new class extends PDO {
            public array $seen = [];

            public function __construct()
            {
                parent::__construct('sqlite::memory:');
            }

            public function exec(string $statement): int|false
            {
                $this->seen[] = $statement;
                return 0;
            }
        };

        foreach ($this->migrationFiles() as $file) {
            require_once $file;

            $stem = preg_replace('/^\d+_/', '', basename($file, '.php'));
            $class = implode('', array_map('ucfirst', explode('_', $stem)));

            (new $class($recorder))->up();
        }

        return $recorder->seen;
    }

    private function migrationFiles(): array
    {
        $files = glob($this->baseDir . '/database/migrations/*.php');
        sort($files);
        return $files;
    }

    /** @return string[] */
    private function migrationNames(): array
    {
        return array_map(fn(string $f): string => basename($f, '.php'), $this->migrationFiles());
    }

    private function bootstrapData(
        string $orgId,
        string $adminId,
        string $email,
        string $password,
        string $now,
    ): string {
        $hash = password_hash($password, PASSWORD_BCRYPT);

        return implode("\n\n", [
            'INSERT INTO organizations (id, name, timezone, weekend_days, created_at) VALUES'
                . "\n    (" . $this->quote($orgId) . ', ' . $this->quote('Hazra EV') . ', '
                . $this->quote('Asia/Kolkata') . ', ' . $this->quote('[7]') . ', '
                . $this->quote($now) . ');',

            'INSERT INTO tracking_configs (org_id, updated_at) VALUES'
                . "\n    (" . $this->quote($orgId) . ', ' . $this->quote($now) . ');',

            'INSERT INTO users (id, org_id, role, name, email, phone, avatar_url, password_hash,'
                . ' active, created_at, updated_at) VALUES'
                . "\n    (" . $this->quote($adminId) . ', ' . $this->quote($orgId) . ", 'admin', "
                . $this->quote('Admin') . ', ' . $this->quote($email) . ", '', '', "
                . $this->quote($hash) . ', 1, ' . $this->quote($now) . ', ' . $this->quote($now) . ');',

            'INSERT INTO admin_profiles (user_id, admin_code, role_title, region) VALUES'
                . "\n    (" . $this->quote($adminId) . ', ' . $this->quote('ADM-001') . ', '
                . $this->quote('Administrator') . ", '');",

            'INSERT INTO user_preferences (user_id) VALUES'
                . "\n    (" . $this->quote($adminId) . ');',
        ]);
    }

    private function header(string $email, string $password, bool $schemaOnly): string
    {
        $data = $schemaOnly
            ? '-- Schema only — no organisation, no admin, no data of any kind.'
            : "-- Login after import:  {$email}  /  {$password}\n"
              . '-- Change that password before the first employee is added.';

        return "-- ---------------------------------------------------------------------------\n"
            . "-- hazra-ev — MySQL / MariaDB schema, generated by `php vayu db:export`\n"
            . "--\n"
            . "-- Import once into an EMPTY database:\n"
            . "--   phpMyAdmin -> Import -> Choose File -> Go\n"
            . "--\n"
            . "-- The `migrations` and `seeders` ledgers are filled as part of the import, so\n"
            . "-- `php vayu db:sync` on the server afterwards reports \"nothing to migrate /\n"
            . "-- nothing to seed\" rather than trying to build the schema a second time.\n"
            . "--\n"
            . $data . "\n"
            . '-- ---------------------------------------------------------------------------';
    }

    private function quote(string $value): string
    {
        return "'" . str_replace(['\\', "'"], ['\\\\', "''"], $value) . "'";
    }

    private function option(array $args, string $name): ?string
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, "--{$name}=")) {
                return substr($arg, strlen($name) + 3);
            }
        }

        return null;
    }
}
