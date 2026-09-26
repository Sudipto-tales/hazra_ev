<?php

/**
 * Migration and seeder runner. Included by `php vayu migrate` and
 * `php vayu db:sync`; defines functions only, so including it never has a side
 * effect.
 *
 * Migrations live in database/migrations/, seeders in database/seeds/. Both are
 * ordered by a leading numeric prefix and recorded in a ledger table once they
 * have run, so both are additive and safe to re-run: a fresh checkout on the
 * server applies only what it has not applied before.
 *
 * The prefix is stripped when deriving a migration's class name, so
 * 004_session_location_tables.php must declare class SessionLocationTables. A
 * seeder is not a class — it returns a closure taking the PDO connection.
 */

require_once __DIR__ . '/dialect.php';

function migration_files(): array
{
    $files = glob(__DIR__ . '/../database/migrations/*.php');
    sort($files);
    return $files;
}

function seeder_files(): array
{
    $files = glob(__DIR__ . '/../database/seeds/*.php') ?: [];
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

/**
 * Creates a ledger table if it is missing. `migrations` and `seeders` have the
 * same shape and differ only in the name column, which keeps their queries
 * readable at the call site.
 */
function migration_ledger(PDO $pdo, string $table, string $column): void
{
    $pdo->exec(Dialect::ddl(
        "CREATE TABLE IF NOT EXISTS {$table} (
            id        {autoid},
            {$column} {str} NOT NULL,
            batch     {int}
        ) {opts}"
    ));
}

function ledger_applied(PDO $pdo, string $table, string $column): array
{
    return array_column($pdo->query("SELECT {$column} FROM {$table}")->fetchAll(), $column);
}

/** Filenames, without extension, that have not run yet. */
function migrations_pending(PDO $pdo): array
{
    migration_ledger($pdo, 'migrations', 'migration');
    $ran = ledger_applied($pdo, 'migrations', 'migration');

    return array_values(array_filter(
        array_map(fn(string $f): string => basename($f, '.php'), migration_files()),
        fn(string $name): bool => !in_array($name, $ran, true),
    ));
}

function seeders_pending(PDO $pdo): array
{
    migration_ledger($pdo, 'seeders', 'seeder');
    $ran = ledger_applied($pdo, 'seeders', 'seeder');

    return array_values(array_filter(
        array_map(fn(string $f): string => basename($f, '.php'), seeder_files()),
        fn(string $name): bool => !in_array($name, $ran, true),
    ));
}

function migrations_up(PDO $pdo): int
{
    migration_ledger($pdo, 'migrations', 'migration');

    $ran = ledger_applied($pdo, 'migrations', 'migration');
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

    // MySQL enforces foreign keys at DROP time and the down() order alone is
    // not enough across files, so the constraint check is lifted for the run.
    $mysql = Dialect::isMysql();
    if ($mysql) {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    }

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
    $pdo->exec("DROP TABLE IF EXISTS seeders");

    if ($mysql) {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    return $count;
}

/**
 * Runs every seeder that has not run yet, in order, recording each one.
 *
 * Seeders are expected to be idempotent on their own as well — the ledger is
 * the primary guard, the in-seeder check is the belt.
 */
function migrations_seed(PDO $pdo): int
{
    migration_ledger($pdo, 'seeders', 'seeder');

    $ran = ledger_applied($pdo, 'seeders', 'seeder');
    $files = seeder_files();
    $batch = time();
    $count = 0;

    if (!$files) {
        echo "  \033[31mno seeders\033[0m in database/seeds/\n";
        return 0;
    }

    foreach ($files as $file) {
        $name = basename($file, '.php');
        if (in_array($name, $ran, true)) {
            continue;
        }

        $seeder = require $file;

        if (!is_callable($seeder)) {
            echo "  \033[31mskipped\033[0m {$name} — did not return a callable\n";
            continue;
        }

        $seeder($pdo);

        $stmt = $pdo->prepare("INSERT INTO seeders (seeder, batch) VALUES (?, ?)");
        $stmt->execute([$name, $batch]);

        echo "  \033[32mseeded\033[0m {$name}\n";
        $count++;
    }

    return $count;
}

/**
 * The development demo dataset — four employees, the reference companies, the
 * catalogue and five days of GPS. Never recorded in the ledger and never run by
 * db:sync; it exists so a developer can get a populated app in one command.
 */
function migrations_demo(PDO $pdo): void
{
    $demo = __DIR__ . '/../database/demo/seed.php';

    if (!file_exists($demo)) {
        echo "  \033[31mno demo seeder\033[0m at database/demo/seed.php\n";
        return;
    }

    (require $demo)($pdo);
}
