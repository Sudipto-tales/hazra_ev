<?php

require_once __DIR__ . '/dialect.php';

/**
 * Base for everything in database/migrations/.
 *
 * Subclasses write one SQL body per up(), using Dialect's type tokens instead
 * of concrete column types, and pass it to $this->exec(). That single call
 * expands the tokens for the connected driver, splits the body into individual
 * statements (PDO's MySQL driver will not run them as one), and lets Dialect
 * skip or rewrite the statements MySQL cannot take verbatim.
 */
abstract class Migration
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    abstract public function up();
    public function down() {}

    protected function exec(string $sql): void
    {
        foreach (Dialect::statements(Dialect::ddl($sql)) as $statement) {
            $statement = Dialect::guard($statement);

            if ($statement === null) {
                continue;
            }

            $this->pdo->exec($statement);
        }
    }

    /**
     * Adds a column to an existing table, once.
     *
     * `ADD COLUMN IF NOT EXISTS` is not portable — SQLite has never had it and
     * MySQL only gained it in 8.0.29 — so this introspects instead and returns
     * early when the column is already there. That keeps a migration re-runnable
     * the same way `CREATE TABLE IF NOT EXISTS` does, which is what the ledger
     * in config/migrate.php assumes of every migration in the folder.
     *
     * The definition is a Dialect-token string, exactly as in exec().
     */
    protected function addColumn(string $table, string $column, string $definition): void
    {
        if ($this->hasColumn($table, $column)) {
            return;
        }

        $this->pdo->exec(Dialect::ddl("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}"));
    }

    protected function hasColumn(string $table, string $column): bool
    {
        if (Dialect::isMysql()) {
            $statement = $this->pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.columns
                  WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?"
            );
            $statement->execute([$table, $column]);

            return (int) $statement->fetchColumn() > 0;
        }

        // PRAGMA takes no bound parameters, hence the identifier check rather
        // than a placeholder. Both values are migration literals, never input.
        foreach (['table' => $table, 'column' => $column] as $what => $value) {
            if (!preg_match('/^[A-Za-z0-9_]+$/', $value)) {
                throw new InvalidArgumentException("Unsafe {$what} name: {$value}");
            }
        }

        foreach ($this->pdo->query("PRAGMA table_info({$table})") as $row) {
            if (($row['name'] ?? null) === $column) {
                return true;
            }
        }

        return false;
    }

    /** Drops in the given order; foreign keys mean order is not decoration. */
    protected function drop(array $tables): void
    {
        foreach ($tables as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
    }
}
