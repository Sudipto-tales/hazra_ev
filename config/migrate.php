<?php

/**
 * Migration runner. Included by `php vayu migrate`; defines functions only, so
 * including it never has a side effect.
 *
 * Files live in database/migrations/. A leading numeric prefix orders them and
 * is stripped when deriving the class name, so 004_session_location_tables.php
 * must declare class SessionLocationTables.
 */

function migration_files(): array
{
    $files = glob(__DIR__ . '/../database/migrations/*.php');
    sort($files);
    return $files;
}

function migration_class(string $file): ?string
{
    $filename = basename($file, '.php');
    $stem = preg_replace('/^\d+_/', '', $filename);
    $class = implode('', array_map('ucfirst', explode('_', $stem)));

    require_once $file;

    return class_exists($class) ? $class : null;
}

function migrations_up(PDO $pdo): int
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        migration TEXT NOT NULL,
        batch INTEGER
    )");

    $ran = array_column($pdo->query("SELECT migration FROM migrations")->fetchAll(), 'migration');
    $batch = time();
    $count = 0;

    foreach (migration_files() as $file) {
        $filename = basename($file, '.php');
        if (in_array($filename, $ran, true)) {
            continue;
        }

        $class = migration_class($file);
        if ($class === null) {
            echo "  \033[31mskipped\033[0m {$filename} — no matching class\n";
            continue;
        }

        (new $class($pdo))->up();

        $stmt = $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
        $stmt->execute([$filename, $batch]);

        echo "  \033[32mmigrated\033[0m {$filename}\n";
        $count++;
    }

    return $count;
}

function migrations_down(PDO $pdo): int
{
    $count = 0;

    foreach (array_reverse(migration_files()) as $file) {
        $class = migration_class($file);
        if ($class === null) {
            continue;
        }

        $migration = new $class($pdo);
        if (method_exists($migration, 'down')) {
            $migration->down();
            echo "  \033[33mrolled back\033[0m " . basename($file, '.php') . "\n";
            $count++;
        }
    }

    $pdo->exec("DROP TABLE IF EXISTS migrations");

    return $count;
}

function migrations_seed(PDO $pdo): void
{
    $seeder = __DIR__ . '/../database/seed.php';

    if (!file_exists($seeder)) {
        echo "  \033[31mno seeder\033[0m at database/seed.php\n";
        return;
    }

    (require $seeder)($pdo);
}
