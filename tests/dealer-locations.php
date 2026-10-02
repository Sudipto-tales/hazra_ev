<?php
// Run: php tests/dealer-locations.php. Uses a temporary SQLite DB, never the application DB.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/migration.php';
require_once __DIR__ . '/../database/migrations/026_dealer_locations.php';
require_once __DIR__ . '/../api/support/DealerLocation.php';
function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
if (($argv[1] ?? '') === 'endpoint') {
    $pdo = new PDO('sqlite:' . $argv[2], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    function db_query($sql, $params = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($params); return $s; }
    function db_fetch_all($sql, $params = []) { return db_query($sql, $params)->fetchAll(PDO::FETCH_ASSOC); }
    function db_fetch_one($sql, $params = []) { return db_query($sql, $params)->fetch(PDO::FETCH_ASSOC); }
    function db_execute($sql, $params = []) { return db_query($sql, $params)->rowCount(); }
    class ApiRequest { public static function body(): array { global $argv; return json_decode(base64_decode($argv[4]), true); } }
    require_once __DIR__ . '/../core/ApiController.php';
    require_once __DIR__ . '/../api/controllers/DealerLocationsController.php';
    session_start(['save_path'=>sys_get_temp_dir()]); $_SESSION['admin_logged_in'] = true; $_SESSION['user_role'] = 'admin';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = Csrf::token();
    if (($argv[6] ?? '') === 'no-csrf') unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    Ctx::impersonate(['id'=>'admin', 'role'=>($argv[6] ?? '') === 'employee' ? 'employee' : 'admin']);
    $_GET['status'] = 'draft'; // Must never override public publication filtering.
    register_shutdown_function(static function () { fwrite(STDERR, 'STATUS:' . http_response_code()); session_destroy(); });
    $controller = new DealerLocationsController();
    $controller->setRouteParams(['id'=>$argv[5] ?? '']);
    $controller->{$argv[3]}();
}
function rejects(array $body, array $existing = []): void {
    try { DealerLocation::validate($body, $existing); }
    catch (InvalidArgumentException) { return; }
    throw new RuntimeException('Invalid location was accepted');
}
$valid = ['name'=>'Hazra Test', 'state'=>'West Bengal', 'district'=>'Kolkata', 'city'=>'Kolkata',
    'address'=>'Test Road', 'pincode'=>'700001', 'phone'=>'+91 9000000000', 'type'=>'both',
    'lat'=>22.5726, 'lng'=>88.3639, 'status'=>'published', 'hours'=>'10 AM–6 PM'];
check(DealerLocation::validate(['name'=>'Draft'])['lat'] === null, 'Draft may omit coordinates');
check(DealerLocation::validate(array_replace($valid, ['lat'=>0, 'lng'=>0]))['lat'] === 0.0, 'Zero is a valid coordinate');
rejects(array_replace($valid, ['lat'=>91])); rejects(array_replace($valid, ['lng'=>181]));
rejects(array_replace($valid, ['lat'=>'NaN'])); rejects(array_replace($valid, ['lat'=>null]));
rejects(array_replace($valid, ['status'=>'unknown'])); rejects(array_replace($valid, ['pincode'=>'123']));
rejects(array_replace($valid, ['phone'=>'-------']));
rejects(array_replace($valid, ['name'=>['bad']])); rejects(['status'=>'published'], DealerLocation::validate(['name'=>'Draft']));
check(DealerLocation::validate(['hours'=>'Updated'], $valid)['address'] === 'Test Road', 'Partial update preserves existing fields');
$path = tempnam(sys_get_temp_dir(), 'hazra-dealers-');
try {
    $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    Dialect::boot($pdo, 'sqlite');
    $migration = new DealerLocations($pdo); $migration->up(); $migration->up();
    $call = static function ($action, $body = [], $id = '', $mode = '') use ($path) {
        $process = proc_open([PHP_BINARY, __FILE__, 'endpoint', $path, $action, base64_encode(json_encode($body)), $id, $mode], [1=>['pipe','w'], 2=>['pipe','w']], $pipes);
        $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($process);
        check($exit === 0, 'Endpoint failed: ' . $errors);
        try { $payload = json_decode($output, true, 512, JSON_THROW_ON_ERROR); }
        catch (JsonException $e) { throw new RuntimeException('Invalid endpoint output: ' . $output . $errors); }
        return [$payload, $errors];
    };
    [$created, $status] = $call('store', $valid);
    check(str_contains($status, 'STATUS:201'), 'Create returns 201'); $id = $created['data']['id'];
    $call('store', ['name'=>'Hidden draft']);
    [$public] = $call('index'); check(count($public['data']) === 1, 'Public reads exclude drafts even with admin session and draft query');
    check(!isset($public['data'][0]['status']), 'Public response excludes admin visibility field');
    [$admin] = $call('adminIndex'); check(count($admin['data']) === 2, 'Admin can see all records');
    [$updated] = $call('update', ['status'=>'inactive'], $id); check($updated['data']['address'] === 'Test Road', 'Update retains address');
    [$public] = $call('index'); check(count($public['data']) === 0, 'Unpublishing removes location publicly');
    [, $status] = $call('store', $valid, '', 'no-csrf'); check(str_contains($status, 'STATUS:403'), 'Writes reject missing CSRF');
    [, $status] = $call('store', $valid, '', 'employee'); check(str_contains($status, 'STATUS:403'), 'Employees cannot write');
    [, $status] = $call('adminIndex', [], '', 'employee'); check(str_contains($status, 'STATUS:403'), 'Employees cannot read admin locations');
    [, $status] = $call('update', [], 'missing'); check(str_contains($status, 'STATUS:404'), 'Missing updates return 404');
    [, $status] = $call('store', array_replace($valid, ['lat'=>999])); check(str_contains($status, 'STATUS:422'), 'Invalid coordinates are rejected');
    $pdo = null;
} finally { unset($migration); Dialect::boot(new PDO('sqlite::memory:'), 'sqlite'); $pdo = null; if (is_file($path)) unlink($path); }
echo "Dealer location validation, migration, CRUD, publication and authorization checks passed.\n";
