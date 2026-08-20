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

        // Bind first: db.php assigns $pdo in whatever scope requires it, and
        // its db_* helpers read the global.
        global $pdo;

        require_once $this->baseDir . '/config/db.php';
        require_once $this->baseDir . '/config/migration.php';
        require_once $this->baseDir . '/config/migrate.php';

        if (!isset($pdo)) {
            echo PHP_EOL . "  \033[31mNo PDO connection.\033[0m DB_TYPE must be sqlite or mysql." . PHP_EOL . PHP_EOL;
            exit(1);
        }

        echo PHP_EOL;

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

        if ($seed) {
            echo PHP_EOL . "  \033[1mSeeding\033[0m" . PHP_EOL;
            migrations_seed($pdo);
        }

        echo PHP_EOL;
    }
}
