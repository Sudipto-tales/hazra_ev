<?php

/**
 * `php vayu db:sync` — bring a database up to the checkout it is running from.
 *
 * This is the command to run on the server after every pull. It applies any
 * migration that has not run and any seeder that has not run, in order, and
 * does nothing else. There is no rollback path and no --fresh: it never drops a
 * table and never rewrites a row, so running it against live data is safe and
 * running it twice is a no-op.
 *
 * Development keeps `php vayu migrate` for the destructive shortcuts
 * (--fresh, --demo).
 */
class DbSyncCommand
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
        $status = in_array('--status', $args, true);
        $dry    = in_array('--dry', $args, true) || in_array('--dry-run', $args, true);

        global $pdo;

        if (in_array('--create-database', $args, true)) {
            $_ENV['DB_CREATE_DATABASE'] = '1';
        }

        try {
            require_once $this->baseDir . '/config/db.php';
        } catch (PDOException $e) {
            $this->connectionFailed($e);
        }

        require_once $this->baseDir . '/config/migration.php';
        require_once $this->baseDir . '/config/migrate.php';

        if (!isset($pdo)) {
            echo PHP_EOL . "  \033[31mNo PDO connection.\033[0m DB_TYPE must be sqlite or mysql." . PHP_EOL . PHP_EOL;
            exit(1);
        }

        $pendingMigrations = migrations_pending($pdo);
        $pendingSeeders    = seeders_pending($pdo);

        echo PHP_EOL;
        echo "  \033[1mdb:sync\033[0m \033[2m" . Dialect::driver();
        if (Dialect::isMysql()) {
            echo ' · ' . env('DB_DATABASE') . '@' . env('DB_HOST', '127.0.0.1');
        }
        echo "\033[0m" . PHP_EOL . PHP_EOL;

        if ($status || $dry) {
            $this->report('Migrations', migration_files(), $pendingMigrations);
            $this->report('Seeders', seeder_files(), $pendingSeeders);

            echo PHP_EOL;
            echo $dry
                ? "  \033[2mdry run — nothing was applied\033[0m" . PHP_EOL . PHP_EOL
                : PHP_EOL;

            exit(0);
        }

        echo "  \033[1mMigrations\033[0m" . PHP_EOL;
        if (migrations_up($pdo) === 0) {
            echo "  \033[2mnothing to migrate\033[0m" . PHP_EOL;
        }

        echo PHP_EOL . "  \033[1mSeeders\033[0m" . PHP_EOL;
        if (migrations_seed($pdo) === 0) {
            echo "  \033[2mnothing to seed\033[0m" . PHP_EOL;
        }

        echo PHP_EOL . "  \033[32min sync\033[0m" . PHP_EOL . PHP_EOL;
    }

    /** @param string[] $files absolute paths @param string[] $pending basenames */
    private function report(string $title, array $files, array $pending): void
    {
        echo "  \033[1m{$title}\033[0m" . PHP_EOL;

        if (!$files) {
            echo "    \033[2mnone\033[0m" . PHP_EOL;
            return;
        }

        foreach ($files as $file) {
            $name = basename($file, '.php');
            echo in_array($name, $pending, true)
                ? "    \033[33mpending\033[0m {$name}" . PHP_EOL
                : "    \033[32mapplied\033[0m {$name}" . PHP_EOL;
        }
    }

    /**
     * A database that cannot be reached is the most common failure here, and a
     * PDO stack trace says nothing useful about why.
     */
    private function connectionFailed(PDOException $e): never
    {
        $host = env('DB_HOST', '127.0.0.1');
        $port = env('DB_PORT', '3306');

        echo PHP_EOL;
        echo "  \033[31mCannot connect\033[0m to " . env('DB_TYPE', 'sqlite')
            . " at {$host}:{$port}" . PHP_EOL;
        echo "  \033[2m" . $e->getMessage() . "\033[0m" . PHP_EOL . PHP_EOL;

        if (str_contains($e->getMessage(), '2002')) {
            echo "  Nothing is answering on that host and port. Either the app is not" . PHP_EOL;
            echo "  running on the database server (use DB_HOST=localhost there), or the" . PHP_EOL;
            echo "  host firewall has not allowed this machine's IP for remote MySQL." . PHP_EOL;
        } elseif (str_contains($e->getMessage(), '1045')) {
            echo "  DB_USERNAME / DB_PASSWORD were rejected." . PHP_EOL;
        } elseif (str_contains($e->getMessage(), '1049')) {
            echo "  Database " . env('DB_DATABASE') . " does not exist. Create it as utf8mb4," . PHP_EOL;
            echo "  or re-run with \033[33m--create-database\033[0m." . PHP_EOL;
        }

        echo PHP_EOL;
        exit(1);
    }

}
