<?php

class MigrateCommand
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
        $fresh = in_array('--fresh', $args, true);
        $seed  = in_array('--seed', $args, true);
        $demo  = in_array('--demo', $args, true);

        // Bind first: db.php assigns $pdo in whatever scope requires it, and
        // its db_* helpers read the global.
        global $pdo;

        if (in_array('--create-database', $args, true)) {
            // db.php reads this and creates a missing MySQL database instead of
            // failing. Off by default: a typo in DB_DATABASE should be an
            // error, not a second empty database.
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

        if ($demo && env('APP_ENV') === 'production') {
            echo PHP_EOL . "  \033[31m--demo refused.\033[0m APP_ENV is production." . PHP_EOL . PHP_EOL;
            exit(1);
        }

        echo PHP_EOL;
        echo "  \033[2mdriver: " . Dialect::driver() . "\033[0m" . PHP_EOL . PHP_EOL;

        if ($fresh) {
            echo "  \033[1mRolling back\033[0m" . PHP_EOL;
            migrations_down($pdo);
            echo PHP_EOL;
        }

        echo "  \033[1mMigrating\033[0m" . PHP_EOL;
        $count = migrations_up($pdo);

        if ($count === 0) {
            echo "  \033[2mnothing to migrate\033[0m" . PHP_EOL;
        }

        // Demo data goes in before the seeders so the bootstrap admin lands in
        // the same organisation the demo created.
        if ($demo) {
            echo PHP_EOL . "  \033[1mDemo data\033[0m" . PHP_EOL;
            migrations_demo($pdo);
        }

        if ($seed) {
            echo PHP_EOL . "  \033[1mSeeding\033[0m" . PHP_EOL;
            if (migrations_seed($pdo) === 0) {
                echo "  \033[2mnothing to seed\033[0m" . PHP_EOL;
            }
        }

        echo PHP_EOL;
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
