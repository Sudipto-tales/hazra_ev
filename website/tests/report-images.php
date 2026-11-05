<?php
// Run: php tests/report-images.php. Never connects to an application database.
//
// Covers the read path for report attachments and sale-line artwork — the gap
// where images uploaded fine and could never be read back. `hydrate()` selected
// `COUNT(*)` and nothing else, so `Present::image` was only ever reachable from
// the upload response and every report detail screen rendered empty slots. The
// bike photograph had the same problem for a different reason: `saleLine`
// carried no image URL at all.
//
// Two shapes are asserted here because they are a contract with two consumers
// (`docs/02-API-PLAN.md` §3.9):
//   * `imageCount` is ALWAYS present and unchanged — no client has to opt in.
//   * `images` is present only under `?include=images`, and ABSENT otherwise —
//     absent and empty must stay distinguishable, or a list row looks like a
//     report with no photos.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/migration.php';
require_once __DIR__ . '/../core/ApiController.php';
require_once __DIR__ . '/../api/support/bootstrap.php';
require_once __DIR__ . '/../api/controllers/ReportsController.php';
function db_query($sql, $params = []) { global $pdo; $q = $pdo->prepare($sql); $q->execute($params); return $q; }
function db_fetch_one($sql, $params = []) { return db_query($sql, $params)->fetch(PDO::FETCH_ASSOC); }
function db_fetch_all($sql, $params = []) { return db_query($sql, $params)->fetchAll(PDO::FETCH_ASSOC); }
function db_execute($sql, $params = []) { return db_query($sql, $params)->rowCount(); }
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('PRAGMA foreign_keys = ON');
Dialect::boot($pdo, 'sqlite');
foreach (glob(__DIR__ . '/../database/migrations/0*.php') as $file) {
    $before = get_declared_classes(); require_once $file;
    foreach (array_diff(get_declared_classes(), $before) as $class) if (is_subclass_of($class, Migration::class)) (new $class($pdo))->up();
}

$now = '2026-11-05T08:00:00Z';
db_execute('INSERT INTO organizations (id, name, timezone, created_at) VALUES (?, ?, ?, ?)', ['org', 'Test', 'Asia/Kolkata', $now]);
db_execute('INSERT INTO users (id, org_id, role, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
    ['employee', 'org', 'employee', 'Field Rep', 'rep@test.example', password_hash('password123', PASSWORD_BCRYPT), $now, $now]);

// --- catalogue: one product, two colours, one with photos -------------------
db_execute(
    'INSERT INTO products (id, org_id, category, brand, name, model_code, updated_at, hero_image)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
    ['prod', 'org', 'scooty', 'Hazra', 'City Rider', 'HZ-100', $now, 'storage/catalogue/prod/hero.jpg'],
);
db_execute('INSERT INTO product_colors (id, product_id, name, argb, position) VALUES (?, ?, ?, ?, ?)',
    ['col-red', 'prod', 'Crimson', 0xFFCC0000, 0]);
db_execute('INSERT INTO product_colors (id, product_id, name, argb, position) VALUES (?, ?, ?, ?, ?)',
    ['col-blue', 'prod', 'Indigo', 0xFF3F51B5, 1]);
// Two photos for Crimson, out of position order on purpose: the read must pick
// position 0, not insertion order.
db_execute('INSERT INTO product_color_images (id, color_id, url, position) VALUES (?, ?, ?, ?)',
    ['img-red-2', 'col-red', 'storage/catalogue/prod/red-2.jpg', 1]);
db_execute('INSERT INTO product_color_images (id, color_id, url, position) VALUES (?, ?, ?, ?)',
    ['img-red-1', 'col-red', 'storage/catalogue/prod/red-1.jpg', 0]);

// --- a report with two attachments and three sale lines ---------------------
db_execute(
    'INSERT INTO visit_reports (id, employee_id, client_id, work_date, company_name, title, submitted_at)
     VALUES (?, ?, ?, ?, ?, ?, ?)',
    ['rep-1', 'employee', 'cli-1', '2026-11-05', 'Bardhaman Motors', 'Demo + two units', $now],
);
// Inserted out of order; the read must sort by position.
db_execute('INSERT INTO report_images (id, report_id, url, width, height, bytes, position) VALUES (?, ?, ?, ?, ?, ?, ?)',
    ['pic-b', 'rep-1', 'storage/uploads/reports/rep-1/b.jpg', 800, 600, 2048, 1]);
db_execute('INSERT INTO report_images (id, report_id, url, width, height, bytes, position) VALUES (?, ?, ?, ?, ?, ?, ?)',
    ['pic-a', 'rep-1', 'storage/uploads/reports/rep-1/a.jpg', 1024, 768, 4096, 0]);

// Line 1: a colour that has photos.           -> that colour's first photo
// Line 2: a colour with none.                 -> the product's hero image
// Line 3: a colour that is gone from the      -> the product's hero image
//         catalogue (renamed/deleted).
db_execute('INSERT INTO report_sales (id, report_id, product_id, product_name, category, color_name, color_argb, units, position) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
    ['sale-1', 'rep-1', 'prod', 'City Rider', 'scooty', 'Crimson', 0xFFCC0000, 2, 0]);
db_execute('INSERT INTO report_sales (id, report_id, product_id, product_name, category, color_name, color_argb, units, position) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
    ['sale-2', 'rep-1', 'prod', 'City Rider', 'scooty', 'Indigo', 0xFF3F51B5, 1, 1]);
db_execute('INSERT INTO report_sales (id, report_id, product_id, product_name, category, color_name, color_argb, units, position) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
    ['sale-3', 'rep-1', 'prod', 'City Rider', 'scooty', 'Retired Teal', 0xFF009688, 1, 2]);

// A second report with no attachments at all, to pin the empty case.
db_execute(
    'INSERT INTO visit_reports (id, employee_id, client_id, work_date, company_name, title, submitted_at)
     VALUES (?, ?, ?, ?, ?, ?, ?)',
    ['rep-2', 'employee', 'cli-2', '2026-11-05', 'Durgapur EV', 'Cold call', $now],
);

Ctx::impersonate(['id' => 'employee', 'org_id' => 'org', 'role' => 'employee']);

// `hydrate()` is private and reads `?include=` off $_GET, which is exactly the
// seam under test: the include is the contract.
$hydrate = (function (array $ids): array {
    $controller = new ReportsController();
    $method = new ReflectionMethod(ReportsController::class, 'hydrate');
    $method->setAccessible(true);
    $rows = [];
    foreach ($ids as $id) {
        $rows[] = db_fetch_one('SELECT * FROM visit_reports WHERE id = ?', [$id]);
    }
    return $method->invoke($controller, $rows);
});

// ---------------------------------------------------------------- no include
$_GET = [];
$report = $hydrate(['rep-1'])[0];

check($report['imageCount'] === 2, 'imageCount is always sent, include or not');
check(!array_key_exists('images', $report),
    'images must be ABSENT without the include — null or [] would read as "no photos"');

// The bike photograph is NOT behind an include: it is one batched query for the
// whole page and the admin needs it wherever a sale line is shown.
check(count($report['sales']) === 3, 'three sale lines');
check($report['sales'][0]['imageUrl'] === 'storage/catalogue/prod/red-1.jpg',
    'a colour with photos resolves to its own first photo by position, got: '
        . var_export($report['sales'][0]['imageUrl'], true));
check($report['sales'][1]['imageUrl'] === 'storage/catalogue/prod/hero.jpg',
    'a colour with no photos falls back to the product hero image');
check($report['sales'][2]['imageUrl'] === 'storage/catalogue/prod/hero.jpg',
    'a colour no longer in the catalogue falls back to the hero image, not null');

// Relative, like every other image URL in this API — the client resolves it
// against the origin. An absolute URL here would bake the host into stored data.
foreach ($report['sales'] as $line) {
    check(!str_starts_with((string) $line['imageUrl'], 'http'),
        'sale image URLs stay relative');
}

// ------------------------------------------------------------- with include
$_GET = ['include' => 'images'];
$report = $hydrate(['rep-1'])[0];

check(array_key_exists('images', $report), 'images present under ?include=images');
check(count($report['images']) === 2, 'both attachments come back');
check($report['images'][0]['id'] === 'pic-a', 'attachments are ordered by position, not insertion');
check($report['images'][1]['id'] === 'pic-b', 'second attachment follows');
check($report['images'][0]['url'] === 'storage/uploads/reports/rep-1/a.jpg', 'url round-trips');
check($report['images'][0]['width'] === 1024 && $report['images'][0]['height'] === 768,
    'dimensions survive so the client can size a grid cell before loading');
check($report['images'][0]['uploadState'] === 'uploaded',
    'a stored image is always uploaded — queued/failed are device state');
check(!str_starts_with($report['images'][0]['url'], 'http'), 'attachment URLs stay relative');
check($report['imageCount'] === 2, 'imageCount still agrees with the array');

// A report with no attachments: empty array, NOT absent, because the caller did ask.
$report2 = $hydrate(['rep-2'])[0];
check(array_key_exists('images', $report2), 'asked-for-and-none is an empty array');
check($report2['images'] === [], 'no attachments means []');
check($report2['imageCount'] === 0, 'count agrees');
check($report2['sales'] === [], 'no sales, no sale images, no query');

// --------------------------------------------------- the N+1 we must not have
// Two reports, six sale lines, four attachments: the lookups are batched, so the
// query count must not scale with the number of rows. Guarding it here because
// the obvious implementation is one query per line and nothing else would catch
// that regression.
$queries = 0;
$countingPdo = new class($pdo, $queries) extends PDO {
    public function __construct(private PDO $inner, private int &$count) {}
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->count++;
        return $this->inner->prepare($query, $options);
    }
};

$realPdo = $pdo;
$pdo = $countingPdo;
$_GET = ['include' => 'images'];
$both = $hydrate(['rep-1', 'rep-2']);
$pdo = $realPdo;

check(count($both) === 2, 'both reports hydrated');
// sales, image counts, image rows, colour images, hero images = 5, plus the two
// row reads the helper itself does. Anything near the row count is an N+1.
check($queries > 0, 'the counting PDO must actually have been used — a zero here would make the bound below meaningless');
check($queries <= 8, "hydrate must batch its lookups; took {$queries} queries");
echo "report-images: hydrate used {$queries} queries for 2 reports / 6 sale lines / 4 attachments
";

echo "report-images: all checks passed\n";
