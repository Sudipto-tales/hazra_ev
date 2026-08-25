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

    /** Drops in the given order; foreign keys mean order is not decoration. */
    protected function drop(array $tables): void
    {
        foreach ($tables as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
    }
}
