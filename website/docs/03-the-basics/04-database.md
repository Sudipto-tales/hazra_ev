# Database

Vayu supports SQLite, MySQL and MongoDB, chosen by `DB_TYPE`.

SQLite is the development default and needs no server. MySQL (or MariaDB) is
what production runs. **The same migrations and seeders run on both** — see
[Portable schema](#portable-schema) for how, and why migrations do not name
column types directly.

## Configuration

In your `.env`:

```env
# SQLite — the default. Nothing else needed; the file is database/database.sqlite
DB_TYPE=sqlite
DB_DATABASE=

# MySQL / MariaDB
DB_TYPE=mysql
DB_HOST=127.0.0.1        # localhost when the app runs on the database server
DB_PORT=3306
DB_DATABASE=my_database
DB_USERNAME=root
DB_PASSWORD=secret
DB_CHARSET=utf8mb4       # optional, this is the default
DB_COLLATION=utf8mb4_unicode_ci

# MongoDB
DB_TYPE=mongo
DB_HOST=127.0.0.1
DB_PORT=27017
DB_DATABASE=my_database
```

The MySQL database itself is not created for you. Either create it once:

```sql
CREATE DATABASE my_database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

…or run `php vayu migrate --create-database` with a user that holds the `CREATE`
privilege.

## Keeping a database in sync

`db:sync` is the command to run after every deploy, and the only one that should
ever run against live data. It applies whatever migrations and seeders the
checkout has that the database does not, in order, and does nothing else. It
never drops a table and never rewrites a row, so running it twice is a no-op.

```bash
php vayu db:sync            # applies what's pending. never drops. safe twice
php vayu db:sync --status   # what's applied, what's pending
php vayu db:sync --dry      # list, change nothing
```

Add `--create-database` on the first run if the MySQL database does not exist
yet.

Typical deploy:

```bash
git pull
php vayu db:sync --status   # read it before you change anything
php vayu db:sync
```

If a migration fails halfway, the ones that already ran stay recorded. Fix the
failing migration and run `db:sync` again — it resumes at the one that broke and
does not repeat the others.

### The destructive commands

`migrate` keeps the shortcuts that are only ever appropriate in development:

```bash
php vayu migrate                    # apply pending migrations
php vayu migrate --seed             # …then run pending seeders
php vayu migrate --fresh --seed     # DROP EVERYTHING, rebuild, seed
php vayu migrate --fresh --demo     # …with the full demo dataset
php vayu migrate --create-database  # create the MySQL database if missing
```

`--fresh` drops every table the migrations declare. `--demo` refuses to run when
`APP_ENV=production`.

### When nothing can reach MySQL

Some shared hosts keep remote MySQL closed, leaving the control panel's import
form as the only way in. For that case:

```bash
php vayu db:export                  # schema + bootstrap admin, as .sql
php vayu db:export --schema-only    # schema alone, no data
```

The file lands in `database/exports/` and imports through phpMyAdmin
(**Import → Choose File → Go**). It needs no database connection to generate,
and it fills the ledger tables as part of the import, so a later `db:sync`
against that database correctly reports nothing to do.

## Migrations

Migration files live in `database/migrations/`, named with a numeric prefix that
orders them. The prefix is stripped to derive the class name, so
`004_session_location_tables.php` must declare `class SessionLocationTables`.

Each migration extends `Migration` and passes its SQL to `$this->exec()`, which
expands the type tokens for the connected driver, splits the body into single
statements, and rewrites what the target driver cannot take verbatim.

```php
// database/migrations/009_posts_table.php
class PostsTable extends Migration
{
    public function up()
    {
        $this->exec("
            CREATE TABLE IF NOT EXISTS posts (
                id         {uuid} PRIMARY KEY,
                author_id  {uuid} NOT NULL,
                title      {str} NOT NULL,
                body       {text} NOT NULL {default ''},
                published  {bool} NOT NULL DEFAULT 0,
                created_at {ts} NOT NULL,
                FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_posts_author ON posts (author_id, created_at DESC);
        ");
    }

    public function down()
    {
        $this->drop(['posts']);
    }
}
```

### Portable schema

`config/dialect.php` is the only file that knows a driver name. Migrations use
tokens instead of concrete column types:

| Token | SQLite | MySQL |
| --- | --- | --- |
| `{uuid}` | `TEXT` | `CHAR(36)` |
| `{str}` | `TEXT` | `VARCHAR(255)` |
| `{str:N}` | `TEXT` | `VARCHAR(N)` |
| `{email}` | `TEXT COLLATE NOCASE` | `VARCHAR(191)` |
| `{ts}` | `TEXT` | `VARCHAR(30)` — ISO-8601 UTC, sorts chronologically on both |
| `{date}` | `TEXT` | `VARCHAR(10)` |
| `{text}` | `TEXT` | `TEXT` |
| `{json}` | `TEXT` | `LONGTEXT` — a JSON blob outgrows MySQL's 64 KB `TEXT` |
| `{int}` | `INTEGER` | `INT` |
| `{bool}` | `INTEGER` | `TINYINT(1)` |
| `{float}` | `REAL` | `DOUBLE` |
| `{autoid}` | `INTEGER PRIMARY KEY AUTOINCREMENT` | `BIGINT AUTO_INCREMENT PRIMARY KEY` |
| `{default 'x'}` | `DEFAULT 'x'` | version-appropriate form — see below |
| `{opts}` | *(nothing)* | `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 …` |

Four rules are worth knowing before writing a migration:

- **Declare foreign keys table-level**, as in the example above. MySQL parses an
  inline `REFERENCES` clause on a column and then silently ignores it, so an
  inline `ON DELETE CASCADE` would quietly stop cascading.
- **Use `{default '…'}` for a default on a `{text}` or `{json}` column.** MySQL
  forbade defaults on TEXT columns until 8.0.13 and then allowed only the
  parenthesised form, while MariaDB takes the plain one. Dialect reads the
  server version and picks; a literal `DEFAULT '[]'` on a TEXT column will fail
  on MySQL.
- **Quote identifiers that are reserved words**, with backticks — SQLite accepts
  them too. `audit_log` has a `` `before` `` column for exactly this reason.
- **Partial indexes flatten on MySQL.** `CREATE INDEX … WHERE x IS NULL` keeps
  its `WHERE` on SQLite and loses it on MySQL, which has no partial indexes. The
  index is still correct there, just less selective. `IF NOT EXISTS` on an index
  is emulated against `information_schema`.

### Portable queries

Application SQL is written in SQLite's dialect and translated on the way out —
`db_query()` routes every statement through `Dialect::dml()`, so no controller
has to know which database it is talking to.

| Written as | Becomes on MySQL |
| --- | --- |
| `ON CONFLICT (k) DO UPDATE SET a = excluded.a` | `ON DUPLICATE KEY UPDATE a = VALUES(a)` |
| `ON CONFLICT (k) DO NOTHING` | `INSERT IGNORE` |
| `INSERT OR IGNORE INTO` | `INSERT IGNORE INTO` |
| `INSERT OR REPLACE INTO` | an upsert — *not* `REPLACE INTO`, which deletes the row first and would fire cascades |

One caveat on upserts: MySQL's `ON DUPLICATE KEY` fires on *any* unique key, not
just the one named in `ON CONFLICT`. Where a table has more than one unique
index, check that this is what you want.

## Seeders

Seeders live in `database/seeds/`, numbered like migrations, and are recorded in
a `seeders` ledger once they run. A seeder returns a closure:

```php
// database/seeds/002_default_categories.php
return function (PDO $pdo): void {
    if (db_fetch_one("SELECT id FROM categories LIMIT 1")) {
        return;   // the ledger is the guard; this is the belt
    }

    db_execute("INSERT INTO categories (id, name) VALUES (?, ?)", [Uuid::v4(), 'General']);
};
```

Adding seed data later means dropping a new numbered file in the folder. The
next `db:sync` applies it once, on every database, and never again.

Seeders run on production, so they should contain only data the application
genuinely needs — reference rows, a first administrator. Development fixtures
belong in `database/demo/`, behind `php vayu migrate --demo`.

## SQL Helpers (SQLite / MySQL)

All helpers use PDO prepared statements — safe from SQL injection by default —
and all of them pass through the dialect translation described above.

### Fetch Multiple Rows

```php
$users = db_fetch_all("SELECT * FROM users WHERE active = ?", [1]);
```

### Fetch Single Row

```php
$user = db_fetch_one("SELECT * FROM users WHERE id = ?", [$id]);
```

### Execute (INSERT / UPDATE / DELETE)

```php
db_execute("INSERT INTO posts (id, title) VALUES (?, ?)", [$id, $title]);
db_execute("UPDATE posts SET title = ? WHERE id = ?", [$newTitle, $id]);
db_execute("DELETE FROM posts WHERE id = ?", [$id]);
```

### Get Last Insert ID

```php
$id = db_last_insert_id();
```

Only meaningful for a table with an `{autoid}` column. Most tables here carry an
application-generated UUID instead, so that the client can name a row it created
offline.

## MongoDB Helpers

Mongo is outside the migration and dialect system — there is no schema to
migrate and no SQL to translate.

```php
$docs = mongo_find('users', ['status' => 1]);
mongo_insert('users', ['name' => 'John', 'email' => 'john@example.com']);
mongo_update('users', ['id' => 1], ['name' => 'Updated']);
mongo_delete('users', ['id' => 1]);
```
