<?php

require_once __DIR__ . '/../support/V1Controller.php';

final class SystemController extends V1Controller
{
    private function authorize(): void
    {
        // 1. Check if admin is signed in
        if (!empty($_SESSION['admin_logged_in']) && (($_SESSION['user_role'] ?? '') === 'admin')) {
            return;
        }

        // 2. Check secret key from query, body, or header
        $configuredKey = env('MIGRATION_KEY', env('APP_KEY', 'hazra_migrate_2026'));
        $providedKey = $_GET['key'] ?? $_POST['key'] ?? $_SERVER['HTTP_X_MIGRATION_KEY'] ?? '';

        if (!empty($configuredKey) && hash_equals((string)$configuredKey, (string)$providedKey)) {
            return;
        }

        Envelope::forbidden('Migration access denied. Provide ?key=YOUR_KEY or sign in as admin.');
    }

    public function status(): never
    {
        $this->authorize();

        global $pdo;
        require_once __BASEDIR__ . '/config/migration.php';
        require_once __BASEDIR__ . '/config/migrate.php';

        $pendingMigrations = migrations_pending($pdo);
        $pendingSeeders = seeders_pending($pdo);

        $appliedMigrations = ledger_applied($pdo, 'migrations', 'migration');
        $appliedSeeders = ledger_applied($pdo, 'seeders', 'seeder');

        Envelope::ok([
            'driver' => Dialect::driver(),
            'database' => Dialect::isMysql() ? env('DB_DATABASE') : 'sqlite',
            'migrations' => [
                'pending' => $pendingMigrations,
                'applied' => $appliedMigrations,
                'pending_count' => count($pendingMigrations),
                'applied_count' => count($appliedMigrations),
            ],
            'seeders' => [
                'pending' => $pendingSeeders,
                'applied' => $appliedSeeders,
                'pending_count' => count($pendingSeeders),
                'applied_count' => count($appliedSeeders),
            ],
        ]);
    }

    public function migrate(): never
    {
        $this->authorize();

        global $pdo;
        require_once __BASEDIR__ . '/config/migration.php';
        require_once __BASEDIR__ . '/config/migrate.php';

        ob_start();
        $migratedCount = migrations_up($pdo);
        $migrationLogs = trim(ob_get_clean());

        // Run seeders unless explicitly set ?seed=0
        $seedParam = $_GET['seed'] ?? $_POST['seed'] ?? '1';
        $shouldSeed = filter_var($seedParam, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? ($seedParam !== '0');

        $seededCount = 0;
        $seedLogs = '';
        if ($shouldSeed) {
            ob_start();
            $seededCount = migrations_seed($pdo);
            $seedLogs = trim(ob_get_clean());
        }

        $appliedMigrations = ledger_applied($pdo, 'migrations', 'migration');
        $appliedSeeders = ledger_applied($pdo, 'seeders', 'seeder');

        Envelope::ok([
            'status' => 'success',
            'driver' => Dialect::driver(),
            'migrated_count' => $migratedCount,
            'seeded_count' => $seededCount,
            'migration_logs' => $migrationLogs,
            'seed_logs' => $seedLogs,
            'all_applied_migrations' => $appliedMigrations,
            'all_applied_seeders' => $appliedSeeders,
        ]);
    }
}
