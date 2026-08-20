# Database

Vayu supports SQLite, MySQL, and MongoDB. The database type is controlled by the `DB_TYPE` environment variable.

## Configuration

In your `.env` file:

```env
# For SQLite (default — no server needed)
DB_TYPE=sqlite

# For MySQL
DB_TYPE=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=my_database
DB_USERNAME=root
DB_PASSWORD=secret

# For MongoDB
DB_TYPE=mongo
DB_HOST=127.0.0.1
DB_PORT=27017
DB_DATABASE=my_database
```

## SQL Helpers (SQLite / MySQL)

### Fetch Multiple Rows

```php
$users = db_fetch_all("SELECT * FROM users_tbl WHERE status = ?", [1]);
```

### Fetch Single Row

```php
$user = db_fetch_one("SELECT * FROM users_tbl WHERE id = ?", [$id]);
```

### Execute (INSERT / UPDATE / DELETE)

```php
// Insert
db_execute("INSERT INTO users_tbl (name, email) VALUES (?, ?)", [$name, $email]);
$id = db_last_insert_id();

// Update
db_execute("UPDATE users_tbl SET name = ? WHERE id = ?", [$newName, $id]);

// Delete
db_execute("DELETE FROM users_tbl WHERE id = ?", [$id]);
```

### Get Last Insert ID

```php
$id = db_last_insert_id();
```

All SQL helpers use PDO prepared statements — safe from SQL injection by default.

## MongoDB Helpers

```php
// Find documents
$docs = mongo_find('users', ['status' => 1]);

// Insert a document
mongo_insert('users', ['name' => 'John', 'email' => 'john@example.com']);

// Update documents
mongo_update('users', ['id' => 1], ['name' => 'Updated']);

// Delete documents
mongo_delete('users', ['id' => 1]);
```

## Migrations

Migration files live in `database/migrations/`. Each migration extends the `Migration` base class.

### Creating a Migration

```php
// database/migrations/PostsTable.php
class PostsTable extends Migration
{
    public function up()
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(200) NOT NULL,
            body TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function down()
    {
        $this->pdo->exec("DROP TABLE IF EXISTS posts");
    }

    public function seed()
    {
        $this->pdo->exec("INSERT INTO posts (title, body) VALUES
            ('First Post', 'Hello World')");
    }
}
```

### Existing Migration

The framework ships with `UsersTable` migration that creates the `users_tbl` table with fields for authentication (name, email, password, verification token, remember token, etc.).
