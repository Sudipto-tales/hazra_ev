<?php
// Run: php tests/dealer-locations-seed.php. No application DB is modified.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/migration.php';
require_once __DIR__ . '/../database/migrations/026_dealer_locations.php';
function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
Dialect::boot($pdo, 'sqlite');
(new DealerLocations($pdo))->up();
$seed = require __DIR__ . '/../database/seeds/007_dealer_locations_seed.php';
ob_start();
try {
    $seed($pdo); $seed($pdo);
    $rows = $pdo->query('SELECT * FROM dealer_locations ORDER BY id')->fetchAll();
    check(count($rows) === 2, 'Rerunning must not duplicate locations');
    foreach ($rows as $row) {
        check($row['status'] === 'draft', 'Unverified pins must not be published');
        check($row['lat'] === null && $row['lng'] === null && $row['phone'] === '', 'Seeder must not invent coordinates or phone numbers');
        check($row['district'] === 'Purba Bardhaman' && $row['pincode'] === '713103', 'Addresses must use the supplied region and PIN');
    }
    check(str_contains($rows[0]['address'], 'Below LIC Division Office'), 'Main showroom landmark must be preserved');
    check(str_contains($rows[1]['address'], 'Gopalnagar Ave'), 'Ichlabad address must be preserved');
    $update = $pdo->prepare('UPDATE dealer_locations SET name = ?, address = ?, status = ?, phone = ?, lat = ?, lng = ? WHERE id = ?');
    $update->execute(['Admin-edited showroom', 'Corrected address', 'published', '+91 9000000000', 23.2, 87.8, $rows[0]['id']]);
    $seed($pdo);
    $edited = $pdo->query('SELECT * FROM dealer_locations ORDER BY id')->fetchAll();
    check(count($edited) === 2 && $edited[0]['name'] === 'Admin-edited showroom' && $edited[0]['status'] === 'published', 'Reruns must preserve admin corrections and publication status');
    check($edited[0]['address'] === 'Corrected address' && (float)$edited[0]['lat'] === 23.2, 'Pin and address edits must survive reruns');
    $pdo->exec('DELETE FROM dealer_locations');
    $existing = $rows[0]; $existing['id'] = 'existing-admin-location';
    $columns = array_keys($existing);
    $pdo->prepare('INSERT INTO dealer_locations (' . implode(',', $columns) . ') VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')')->execute(array_values($existing));
    $seed($pdo);
    check((int)$pdo->query('SELECT COUNT(*) FROM dealer_locations')->fetchColumn() === 2, 'An existing exact address must not be duplicated under a new ID');
} finally { ob_end_clean(); }
echo "Dealer address seeder, rerun safety and admin-edit preservation checks passed.\n";
