<?php

/**
 * Everything that differs between SQLite and MySQL, in one place.
 *
 * The app is written once and runs on both: SQLite is the zero-setup
 * development default, MySQL is the server. Two entry points do the work.
 *
 *   Dialect::ddl()  — migration SQL is written with type tokens ({uuid}, {ts},
 *                     {json}, ...) instead of concrete column types, and this
 *                     expands them for the connected driver.
 *
 *   Dialect::dml()  — every runtime query passes through db_query(), which
 *                     hands it here first. SQLite upsert syntax written in the
 *                     controllers is rewritten to the MySQL equivalent, so no
 *                     call site has to know which database it is talking to.
 *
 * Timestamps stay TEXT/VARCHAR ISO-8601 UTC on both drivers on purpose: the
 * wire format is the storage format, and lexicographic ordering of a Zulu
 * string is chronological ordering.
 */
final class Dialect
{
    private static ?PDO $pdo = null;
    private static string $driver = 'sqlite';
    private static ?string $version = null;
    private static array $dmlCache = [];

    /** Called by config/db.php the moment a connection exists. */
    public static function boot(PDO $pdo, string $driver): void
    {
        self::$pdo = $pdo;
        self::$driver = $driver === 'mysql' ? 'mysql' : 'sqlite';
        self::$dmlCache = [];

        try {
            self::$version = (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        } catch (Throwable) {
            self::$version = null;
        }
    }

    public static function driver(): string
    {
        return self::$driver;
    }

    public static function isMysql(): bool
    {
        return self::$driver === 'mysql';
    }

    public static function isMariaDb(): bool
    {
        return self::$version !== null && stripos(self::$version, 'mariadb') !== false;
    }

    /** Server version as an int, 8.0.36 -> 80036, for feature gates. */
    private static function versionId(): int
    {
        if (self::$version === null || !preg_match('/(\d+)\.(\d+)\.(\d+)/', self::$version, $m)) {
            return 0;
        }

        return ((int) $m[1] * 10000) + ((int) $m[2] * 100) + (int) $m[3];
    }

    /**
     * MySQL forbade DEFAULT on a TEXT/BLOB column until 8.0.13, which allows it
     * only as a parenthesised expression. MariaDB has allowed the plain form
     * since 10.2.
     */
    private static function textDefaultStyle(): string
    {
        if (!self::isMysql()) {
            return 'plain';
        }

        if (self::isMariaDb()) {
            return self::versionId() >= 100200 ? 'plain' : 'drop';
        }

        return self::versionId() >= 80013 ? 'paren' : 'drop';
    }

    public static function tableOptions(): string
    {
        if (!self::isMysql()) {
            return '';
        }

        $charset   = env('DB_CHARSET', 'utf8mb4');
        $collation = env('DB_COLLATION', 'utf8mb4_unicode_ci');

        return "ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}";
    }

    // ------------------------------------------------------------------ DDL

    /** Token -> concrete column type, per driver. */
    private static function types(): array
    {
        if (self::isMysql()) {
            return [
                '{uuid}'   => 'CHAR(36)',
                '{str}'    => 'VARCHAR(255)',
                '{email}'  => 'VARCHAR(191)',   // utf8mb4_unicode_ci is already case-insensitive
                '{ts}'     => 'VARCHAR(30)',    // ISO-8601 UTC with a trailing Z
                '{date}'   => 'VARCHAR(10)',    // yyyy-mm-dd
                '{text}'   => 'TEXT',
                '{json}'   => 'LONGTEXT',       // polylines and matched segments outgrow TEXT
                '{int}'    => 'INT',
                '{bool}'   => 'TINYINT(1)',
                '{float}'  => 'DOUBLE',
                '{autoid}' => 'BIGINT AUTO_INCREMENT PRIMARY KEY',
            ];
        }

        return [
            '{uuid}'   => 'TEXT',
            '{str}'    => 'TEXT',
            '{email}'  => 'TEXT COLLATE NOCASE',
            '{ts}'     => 'TEXT',
            '{date}'   => 'TEXT',
            '{text}'   => 'TEXT',
            '{json}'   => 'TEXT',
            '{int}'    => 'INTEGER',
            '{bool}'   => 'INTEGER',
            '{float}'  => 'REAL',
            '{autoid}' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        ];
    }

    /**
     * Expands the tokens in a migration's SQL.
     *
     * Beyond the type table there are three shapes:
     *   {str:64}          a length-capped string  (TEXT on SQLite)
     *   {default '[]'}    a default that may sit on a TEXT column
     *   {opts}            the trailing table options, after the closing paren
     */
    public static function ddl(string $sql): string
    {
        $sql = strtr($sql, self::types());

        $sql = preg_replace_callback(
            '/\{str:(\d+)\}/',
            fn(array $m): string => self::isMysql() ? "VARCHAR({$m[1]})" : 'TEXT',
            $sql,
        );

        $style = self::textDefaultStyle();
        $sql = preg_replace_callback(
            "/\{default\s+('(?:[^']|'')*')\}/",
            fn(array $m): string => match ($style) {
                'paren' => "DEFAULT ({$m[1]})",
                'plain' => "DEFAULT {$m[1]}",
                default => '',
            },
            $sql,
        );

        return str_replace('{opts}', self::tableOptions(), $sql);
    }

    /**
     * Splits a migration body into single statements.
     *
     * PDO's MySQL driver does not reliably run a multi-statement exec(), and
     * the bodies here are one long CREATE TABLE ...; CREATE INDEX ...; string.
     * The scanner has to know about `--` comments because more than one of them
     * contains both an apostrophe and a semicolon.
     */
    public static function statements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $inString = false;
        $inComment = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($inComment) {
                $buffer .= $char;
                if ($char === "\n") {
                    $inComment = false;
                }
                continue;
            }

            if ($inString) {
                $buffer .= $char;
                if ($char === "'") {
                    // '' is an escaped quote, not the end of the string.
                    if (($sql[$i + 1] ?? '') === "'") {
                        $buffer .= "'";
                        $i++;
                    } else {
                        $inString = false;
                    }
                }
                continue;
            }

            if ($char === "'") {
                $inString = true;
                $buffer .= $char;
                continue;
            }

            if ($char === '-' && ($sql[$i + 1] ?? '') === '-') {
                $inComment = true;
                $buffer .= $char;
                continue;
            }

            if ($char === ';') {
                if (trim($buffer) !== '') {
                    $statements[] = trim($buffer);
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }

        // A trailing comment with no statement after it would reach the server
        // as an empty query.
        return array_values(array_filter(
            $statements,
            fn(string $s): bool => trim(preg_replace('/--[^\n]*/', '', $s)) !== '',
        ));
    }

    /**
     * Last stop before a DDL statement is executed.
     *
     * MySQL has neither `CREATE INDEX IF NOT EXISTS` nor partial indexes, so
     * the guard checks information_schema itself and drops the WHERE clause.
     * A partial index that becomes a full one is still correct — only less
     * selective. Returns null when the statement should be skipped.
     */
    public static function guard(string $statement): ?string
    {
        if (!self::isMysql()) {
            return $statement;
        }

        // Comments ride along with the statement that follows them, so match
        // against a comment-free copy — several indexes are preceded by one.
        $probe = trim(preg_replace('/--[^\n]*/', '', $statement));

        if (!preg_match('/^\s*CREATE\s+(UNIQUE\s+)?INDEX\b/i', $probe)) {
            return $statement;
        }

        // Partial index -> full index.
        $statement = preg_replace('/\)\s*WHERE\s+.*$/is', ')', $probe);

        if (!preg_match(
            '/^\s*CREATE\s+(UNIQUE\s+)?INDEX\s+(?:IF\s+NOT\s+EXISTS\s+)?([A-Za-z0-9_]+)\s+ON\s+([A-Za-z0-9_]+)\s*(\(.*\))\s*$/is',
            $statement,
            $m,
        )) {
            return $statement;
        }

        [, $unique, $index, $table, $columns] = $m;

        if (self::indexExists($table, $index)) {
            return null;
        }

        return trim("CREATE {$unique}INDEX {$index} ON {$table} {$columns}");
    }

    private static function indexExists(string $table, string $index): bool
    {
        if (self::$pdo === null) {
            return false;
        }

        $stmt = self::$pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.statistics
              WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?"
        );
        $stmt->execute([$table, $index]);

        return (int) $stmt->fetchColumn() > 0;
    }

    // ------------------------------------------------------------------ DML

    /**
     * Rewrites SQLite upsert syntax for MySQL. Identity on SQLite.
     *
     * Every ON CONFLICT target in this codebase is its table's only unique key,
     * so MySQL's "any unique key" ON DUPLICATE KEY semantics match one for one.
     * VALUES(col) is used rather than the 8.0.19 row alias because MariaDB has
     * no alias form; it is deprecated on new MySQL but still functional.
     */
    public static function dml(string $sql): string
    {
        if (!self::isMysql()) {
            return $sql;
        }

        if (isset(self::$dmlCache[$sql])) {
            return self::$dmlCache[$sql];
        }

        $out = $sql;

        // `col IS ?` is SQLite's null-safe equality against a bound value.
        // MySQL spells it `col <=> ?` and lets IS take only NULL/TRUE/FALSE,
        // so the SQLite form is a 1064 there rather than a wrong answer. This
        // shipped once already, in AuthController::registerDevice().
        // `IS NOT ?` is deliberately left alone: its MySQL equivalent is
        // NOT (col <=> ?), which needs the left operand, and a rewrite that
        // guessed wrong would invert a condition silently. A 1064 is better.
        $out = preg_replace('/\bIS\s+\?/i', '<=> ?', $out);

        // INSERT OR IGNORE -> INSERT IGNORE
        $out = preg_replace('/\bINSERT\s+OR\s+IGNORE\s+INTO\b/i', 'INSERT IGNORE INTO', $out);

        // ON CONFLICT (...) DO NOTHING -> INSERT IGNORE
        if (preg_match('/\bON\s+CONFLICT\s*\([^)]*\)\s*DO\s+NOTHING\b/i', $out)) {
            $out = preg_replace('/\bON\s+CONFLICT\s*\([^)]*\)\s*DO\s+NOTHING\b/i', '', $out);
            $out = preg_replace('/\bINSERT\s+INTO\b/i', 'INSERT IGNORE INTO', $out, 1);
        }

        // ON CONFLICT (...) DO UPDATE SET x = excluded.x -> ON DUPLICATE KEY UPDATE x = VALUES(x)
        $out = preg_replace(
            '/\bON\s+CONFLICT\s*\([^)]*\)\s*DO\s+UPDATE\s+SET\b/i',
            'ON DUPLICATE KEY UPDATE',
            $out,
        );
        $out = preg_replace('/\bexcluded\.([A-Za-z0-9_]+)/i', 'VALUES($1)', $out);

        // INSERT OR REPLACE -> upsert. REPLACE INTO is wrong here: it deletes
        // the existing row first, which would fire ON DELETE CASCADE.
        $out = preg_replace_callback(
            '/\bINSERT\s+OR\s+REPLACE\s+INTO\s+([A-Za-z0-9_]+)\s*\(([^)]*)\)/i',
            function (array $m) use (&$out): string {
                $columns = array_map('trim', explode(',', $m[2]));
                $sets = implode(', ', array_map(fn(string $c): string => "{$c} = VALUES({$c})", $columns));

                return "INSERT INTO {$m[1]} ({$m[2]}) /*upsert:{$sets}*/";
            },
            $out,
        );

        if (preg_match('#/\*upsert:(.*?)\*/#s', $out, $m)) {
            $out = str_replace($m[0], '', $out);
            $out = rtrim(rtrim(trim($out), ';')) . ' ON DUPLICATE KEY UPDATE ' . $m[1];
        }

        return self::$dmlCache[$sql] = $out;
    }
}
