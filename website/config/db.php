<?php
$db_type = env('DB_TYPE', 'sqlite'); // Options: sqlite, mysql, mongo

switch ($db_type) {
    case 'sqlite':
        $sqliteFile = env('DB_DATABASE', __DIR__ . '/../database/database.sqlite');
        $pdo = new PDO("sqlite:" . $sqliteFile);
        break;

    case 'mysql':
        $host = env('DB_HOST', 'localhost');
        $dbname = env('DB_DATABASE', 'your_db');
        $user = env('DB_USERNAME', 'root');
        $pass = env('DB_PASSWORD', '');
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
        break;

    case 'mongo':
        require_once __DIR__ . '/../vendor/autoload.php';
        $mongoHost = env('DB_HOST', 'localhost');
        $mongoPort = env('DB_PORT', '27017');
        $mongoDBName = env('DB_DATABASE', 'your_database');
        $mongoClient = new MongoDB\Client("mongodb://{$mongoHost}:{$mongoPort}");
        $mongoDB = $mongoClient->selectDatabase($mongoDBName);
        break;

    default:
        die("Unsupported database type: $db_type");
}

if (isset($pdo)) {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Rows are JSON-encoded straight onto the wire by the API layer, so the
    // default FETCH_BOTH would emit every column twice — once by name, once by
    // ordinal position.
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    if ($db_type === 'sqlite') {
        // Off by default in SQLite, and the schema leans on ON DELETE CASCADE.
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');
    }
}

// SQL-based reusable functions
function db_query($sql, $params = []) {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_fetch_all($sql, $params = []) {
    return db_query($sql, $params)->fetchAll();
}

function db_fetch_one($sql, $params = []) {
    return db_query($sql, $params)->fetch();
}

function db_execute($sql, $params = []) {
    return db_query($sql, $params)->rowCount();
}

function db_last_insert_id() {
    global $pdo;
    return $pdo->lastInsertId();
}

// MongoDB helper functions (basic)
function mongo_find($collection, $filter = []) {
    global $mongoDB;
    return $mongoDB->$collection->find($filter)->toArray();
}

function mongo_insert($collection, $document) {
    global $mongoDB;
    return $mongoDB->$collection->insertOne($document);
}

function mongo_update($collection, $filter, $update) {
    global $mongoDB;
    return $mongoDB->$collection->updateMany($filter, ['$set' => $update]);
}

function mongo_delete($collection, $filter) {
    global $mongoDB;
    return $mongoDB->$collection->deleteMany($filter);
}
?>
