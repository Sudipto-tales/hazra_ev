<?php

/**
 * Development seed. Run with `php vayu migrate --demo`.
 *
 * Produces one organisation, an admin, four employees, the reference companies
 * visits are detected against, an EV catalogue, and five working days of GPS
 * tracks per employee. The tracks are pushed through the same Engine the API
 * uses, so stops, visits, the timeline, the drawn route and the day rollups are
 * all derived rather than fabricated — seeded data and live data are identical
 * by construction.
 *
 * Idempotent: it does nothing if an organisation already exists.
 */

return function (PDO $pdo): void {
    require_once __DIR__ . '/../../api/support/bootstrap.php';

    if ($pdo->query("SELECT COUNT(*) FROM organizations")->fetchColumn() > 0) {
        echo "  \033[2malready seeded — drop with `php vayu migrate --fresh --demo`\033[0m\n";
        return;
    }

    $now = Wire::now();
    $orgId = Uuid::v4();

    db_execute(
        "INSERT INTO organizations (id, name, timezone, weekend_days, created_at) VALUES (?, ?, ?, ?, ?)",
        [$orgId, 'Hazra EV', 'Asia/Kolkata', '[7]', $now],
    );

    db_execute("INSERT INTO tracking_configs (org_id, updated_at) VALUES (?, ?)", [$orgId, $now]);

    // ------------------------------------------------------------- identity

    $password = password_hash('password123', PASSWORD_BCRYPT);

    $adminId = Uuid::v4();
    db_execute(
        "INSERT INTO users (id, org_id, role, name, email, phone, avatar_url, password_hash, active, created_at, updated_at)
         VALUES (?, ?, 'admin', ?, ?, ?, '', ?, 1, ?, ?)",
        [$adminId, $orgId, 'Rukhsana Hazra', 'admin@hazra-ev.test', '+8801711000001', $password, $now, $now],
    );
    db_execute(
        "INSERT INTO admin_profiles (user_id, admin_code, role_title, region) VALUES (?, ?, ?, ?)",
        [$adminId, 'ADM-001', 'Zonal Manager', 'Bardhaman'],
    );
    db_execute("INSERT INTO user_preferences (user_id) VALUES (?)", [$adminId]);

    // The Engine needs an org and a config; there is no bearer token in the CLI.
    Ctx::impersonate([
        'id' => $adminId, 'org_id' => $orgId, 'role' => 'admin', 'active' => 1,
        'name' => 'Rukhsana Hazra', 'email' => 'admin@hazra-ev.test',
    ]);

    $employeeSeeds = [
        ['Arif Rahman',    'EMP-1042', 'Field Sales Executive', 'Sales',      'Bardhaman North', 'B+',  '2023-04-11'],
        ['Nusrat Jahan',   'EMP-1043', 'Senior Sales Executive', 'Sales',     'Bardhaman South', 'O+',  '2022-09-01'],
        ['Tanvir Hossain', 'EMP-1044', 'Field Sales Executive', 'Distribution', '',          'A+',  '2024-01-15'],
        ['Mitu Akter',     'EMP-1045', 'Territory Officer',     '',           'Memari',      'AB+', '2024-06-03'],
    ];

    $employees = [];

    foreach ($employeeSeeds as $i => [$name, $code, $designation, $department, $region, $blood, $joined]) {
        $id = Uuid::v4();
        $email = strtolower(explode(' ', $name)[0]) . '@hazra-ev.test';

        db_execute(
            "INSERT INTO users (id, org_id, role, name, email, phone, avatar_url, password_hash, active, created_at, updated_at)
             VALUES (?, ?, 'employee', ?, ?, ?, '', ?, 1, ?, ?)",
            [$id, $orgId, $name, $email, '+88017110000' . (10 + $i), $password, $now, $now],
        );

        db_execute(
            "INSERT INTO employee_profiles
                (user_id, employee_code, designation, department, region, banner_url, joined_on,
                 reports_to_id, blood_group, address)
             VALUES (?, ?, ?, ?, ?, '', ?, ?, ?, ?)",
            [$id, $code, $designation, $department, $region, $joined, $adminId, $blood, 'Bardhaman, West Bengal'],
        );

        db_execute("INSERT INTO user_preferences (user_id) VALUES (?)", [$id]);

        $employees[] = ['id' => $id, 'name' => $name];
    }

    echo "  seeded 1 admin, " . count($employees) . " employees\n";

    // ------------------------------------------------------------ customers

    $companySeeds = [
        ['Meghna Motors',     'Distributor', [['Khosbagan Branch', 'Tilak Road, Khosbagan, Bardhaman', 23.2432, 87.8567],
                                              ['Curzon Gate Branch', 'G.T. Road, Curzon Gate, Bardhaman', 23.2370, 87.8635]]],
        ['Padma Auto House',  'Retail',      [['Golapbag Store', 'Golapbag More, Bardhaman', 23.2262, 87.8482]]],
        ['Jamuna EV Traders', 'Corporate',   [['B.C. Road Office', 'Birhata, B.C. Road, Bardhaman', 23.2350, 87.8640]]],
        ['Turag Wheels',      'Retail',      [['Nababhat Showroom', 'Nababhat, G.T. Road, Bardhaman', 23.2565, 87.8772]]],
    ];

    $branchPoints = [];

    foreach ($companySeeds as [$name, $category, $branches]) {
        $companyId = Uuid::v4();

        db_execute(
            "INSERT INTO companies (id, org_id, name, category, updated_at) VALUES (?, ?, ?, ?, ?)",
            [$companyId, $orgId, $name, $category, $now],
        );

        foreach ($branches as [$branchName, $address, $lat, $lng]) {
            $branchId = Uuid::v4();

            db_execute(
                "INSERT INTO branches (id, company_id, name, address, lat, lng) VALUES (?, ?, ?, ?, ?, ?)",
                [$branchId, $companyId, $branchName, $address, $lat, $lng],
            );

            $branchPoints[] = ['lat' => $lat, 'lng' => $lng, 'name' => $name . ' — ' . $branchName];
        }
    }

    echo "  seeded " . count($companySeeds) . " companies, " . count($branchPoints) . " branches\n";

    // ------------------------------------------------------------ catalogue

    $productSeeds = [
        ['scooty', 'Hazra', 'Volt S1',   'HZ-VS1', 4.5, 3, '3 yrs vehicle + 4 yrs battery', 110, 65, '4-5 hrs', '2.2 kWh Li-ion', '1500 W BLDC', 150,
            ['Removable battery', 'Reverse assist', 'LED projector headlamp'],
            [['Midnight Blue', 0xFF1B2A5B], ['Pearl White', 0xFFF2F2F2], ['Sunset Red', 0xFFB3261E]]],
        ['scooty', 'Hazra', 'Volt S2 Pro', 'HZ-VS2', 4.7, 3, '3 yrs vehicle + 5 yrs battery', 140, 75, '3-4 hrs', '3.0 kWh Li-ion', '2000 W BLDC', 160,
            ['Fast charging', 'Digital cluster', 'Regenerative braking'],
            [['Graphite', 0xFF2E2E2E], ['Ocean Teal', 0xFF0E6B6B]]],
        ['bike',   'Hazra', 'Surge R',   'HZ-SR1', 4.3, 2, '2 yrs vehicle + 3 yrs battery', 160, 95, '5-6 hrs', '4.0 kWh Li-ion', '3500 W mid-drive', 180,
            ['Disc brakes front and rear', 'Three ride modes'],
            [['Matte Black', 0xFF111111], ['Racing Orange', 0xFFE8590C]]],
        ['bicycle', 'Hazra', 'Breeze C1', 'HZ-BC1', 4.1, 2, '2 yrs frame + 2 yrs battery', 60, 25, '3 hrs', '0.5 kWh Li-ion', '350 W hub', 110,
            ['Pedal assist', 'Foldable frame'],
            [['Forest Green', 0xFF2F6B3A], ['Silver', 0xFFC9C9C9]]],
        ['others', 'Hazra', 'Haul E-Cart', 'HZ-HC1', 3.9, 2, '2 yrs vehicle + 2 yrs battery', 90, 40, '6 hrs', '5.0 kWh Li-ion', '2500 W BLDC', 450,
            ['500 kg payload tray', 'Hill-hold assist'],
            [['Utility Yellow', 0xFFF2B705]]],
    ];

    foreach ($productSeeds as $p) {
        [$category, $brand, $name, $model, $rating, $warrantyYears, $warrantyNote,
         $rangeKm, $topSpeed, $charging, $battery, $motor, $load, $highlights, $colors] = $p;

        $productId = Uuid::v4();

        db_execute(
            "INSERT INTO products
                (id, org_id, category, brand, name, model_code, rating, warranty_years, warranty_note,
                 range_km, top_speed_kmph, charging_time, battery_capacity, motor_power, load_capacity_kg,
                 highlights, active, listed_at, created_by, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NULL, ?, ?)",
            [
                $productId, $orgId, $category, $brand, $name, $model, $rating, $warrantyYears, $warrantyNote,
                $rangeKm, $topSpeed, $charging, $battery, $motor, $load,
                json_encode($highlights), $adminId, $now,
            ],
        );

        foreach ($colors as $position => [$colorName, $argb]) {
            db_execute(
                "INSERT INTO product_colors (id, product_id, name, argb, in_stock, position) VALUES (?, ?, ?, ?, 1, ?)",
                [Uuid::v4(), $productId, $colorName, $argb, $position],
            );
        }
    }

    $products = db_fetch_all("SELECT id, brand, name, category FROM products WHERE org_id = ?", [$orgId]);
    echo "  seeded " . count($products) . " products\n";

    // A holiday so AttendanceStatus.holiday has a source in the calendar.
    db_execute(
        "INSERT INTO holidays (org_id, date, name) VALUES (?, ?, ?)",
        [$orgId, (new DateTimeImmutable('first day of this month'))->format('Y-m-16'), 'Company foundation day'],
    );

    // ----------------------------------------------------------- workdays

    $timezone = new DateTimeZone('Asia/Kolkata');
    $days = 0;

    for ($back = 6; $back >= 0; $back--) {
        $date = (new DateTimeImmutable("-{$back} days", $timezone));
        $workDate = $date->format('Y-m-d');

        // Weekends stay empty on purpose so the calendar shows a real weekend.
        if (in_array((int) $date->format('N'), [5, 6], true)) {
            continue;
        }

        foreach ($employees as $index => $employee) {
            // One employee is absent on the oldest day, so 'absent' is real too.
            if ($back === 6 && $index === 3) {
                continue;
            }

            seed_workday($employee['id'], $workDate, $index, $branchPoints, $products, $back === 0);
        }

        $days++;
    }

    echo "  seeded {$days} working days of tracks\n";
    echo "  \033[32mlogin\033[0m admin@hazra-ev.test / arif@hazra-ev.test — password123\n";
};

/**
 * Builds one employee-day: a session, an interpolated GPS track through two
 * branches with a dwell at each, and a report filed at the first stop.
 *
 * `$live` leaves the last session open so the dashboard has something working.
 */
function seed_workday(
    string $employeeId,
    string $workDate,
    int $index,
    array $branches,
    array $products,
    bool $live,
): void {
    $timezone = new DateTimeZone('Asia/Kolkata');

    $start = new DateTimeImmutable($workDate . ' 09:' . str_pad((string) (5 + $index * 7), 2, '0', STR_PAD_LEFT) . ':00', $timezone);

    $first  = $branches[$index % count($branches)];
    $second = $branches[($index + 1) % count($branches)];

    // Home -> branch A -> dwell -> branch B -> dwell -> home.
    $origin = ['lat' => 23.2280 + $index * 0.006, 'lng' => 87.8500 + $index * 0.006];

    $legs = [
        ['from' => $origin, 'to' => $first,  'minutes' => 28, 'dwell' => 42],
        ['from' => $first,  'to' => $second, 'minutes' => 34, 'dwell' => 31],
        ['from' => $second, 'to' => $origin, 'minutes' => 30, 'dwell' => 0],
    ];

    $intervalSeconds = 120;
    $cursor = $start;
    $fixes = [];

    foreach ($legs as $leg) {
        $steps = max(1, (int) round($leg['minutes'] * 60 / $intervalSeconds));

        for ($s = 1; $s <= $steps; $s++) {
            $t = $s / $steps;
            $fixes[] = [
                'id'         => Uuid::v4(),
                'latitude'   => $leg['from']['lat'] + ($leg['to']['lat'] - $leg['from']['lat']) * $t,
                'longitude'  => $leg['from']['lng'] + ($leg['to']['lng'] - $leg['from']['lng']) * $t,
                'accuracy'   => 8 + ($s % 5),
                'speedKmh'   => 18 + ($s % 7),
                'recordedAt' => $cursor->setTimezone(new DateTimeZone('UTC'))->format(Wire::TS),
            ];
            $cursor = $cursor->modify("+{$intervalSeconds} seconds");
        }

        // The dwell: the same point repeated, which is exactly what a parked
        // phone produces and what stop detection is written against.
        $dwellSteps = (int) round($leg['dwell'] * 60 / $intervalSeconds);

        for ($s = 0; $s < $dwellSteps; $s++) {
            $fixes[] = [
                'id'         => Uuid::v4(),
                'latitude'   => $leg['to']['lat'] + (($s % 3) - 1) * 0.00012,
                'longitude'  => $leg['to']['lng'] + (($s % 2) - 0.5) * 0.00012,
                'accuracy'   => 9 + ($s % 4),
                'speedKmh'   => 0.0,
                'recordedAt' => $cursor->setTimezone(new DateTimeZone('UTC'))->format(Wire::TS),
            ];
            $cursor = $cursor->modify("+{$intervalSeconds} seconds");
        }
    }

    $sessionId = Uuid::v4();

    db_execute(
        "INSERT INTO work_sessions (id, employee_id, client_id, work_date, seq, started_at, start_lat, start_lng)
         VALUES (?, ?, ?, ?, 1, ?, ?, ?)",
        [
            $sessionId, $employeeId, Uuid::v4(), $workDate,
            $fixes[0]['recordedAt'], $fixes[0]['latitude'], $fixes[0]['longitude'],
        ],
    );

    $session = db_fetch_one("SELECT * FROM work_sessions WHERE id = ?", [$sessionId]);

    // Straight through the real ingest path, so seeded fixes obey the same
    // accuracy and jump rules as live ones.
    Engine::ingest($session, $fixes);

    if (!$live) {
        $endedAt = $fixes[count($fixes) - 1]['recordedAt'];

        db_execute("UPDATE work_sessions SET ended_at = ? WHERE id = ?", [$endedAt, $sessionId]);
        db_execute("UPDATE stop_records SET departure = ? WHERE session_id = ? AND departure IS NULL", [$endedAt, $sessionId]);
        db_execute(
            "UPDATE company_visits SET departure = ?, status = 'completed' WHERE session_id = ? AND departure IS NULL",
            [$endedAt, $sessionId],
        );
    }

    // One report per day, filed against the first detected visit when there is
    // one — which is what pins it to a company on the admin map.
    $visit = db_fetch_one(
        "SELECT v.*, c.name AS company_name, b.name AS branch_name
           FROM company_visits v
           JOIN companies c ON c.id = v.company_id
           LEFT JOIN branches b ON b.id = v.branch_id
          WHERE v.session_id = ? ORDER BY v.arrival LIMIT 1",
        [$sessionId],
    );

    $reportId = Uuid::v4();
    $submittedAt = $fixes[min(count($fixes) - 1, 30)]['recordedAt'];
    $product = $products[$index % count($products)];

    db_execute(
        "INSERT INTO visit_reports
            (id, employee_id, client_id, session_id, visit_id, work_date, company_name, branch_name,
             company_id, branch_id, title, body, lat, lng, status, deal_value, payment_received,
             follow_up_on, submitted_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted', ?, ?, ?, ?)",
        [
            $reportId, $employeeId, Uuid::v4(), $sessionId,
            $visit['id'] ?? null, $workDate,
            $visit['company_name'] ?? 'Walk-in shop',
            $visit['branch_name'] ?? null,
            $visit['company_id'] ?? null,
            $visit['branch_id'] ?? null,
            'Stock discussion and demo',
            'Walked through the new range with the counter staff. They want a demo unit next week and asked about the extended battery warranty.',
            $visit['lat'] ?? $fixes[0]['latitude'],
            $visit['lng'] ?? $fixes[0]['longitude'],
            // Free text as the seller typed it — never coerced to a number.
            ['approx 2.4L', '1,80,000 tk', 'TBD after demo', '95k'][$index % 4],
            ['50% advance', '25,000 tk', null, 'nil'][$index % 4],
            null,
            $submittedAt,
        ],
    );

    db_execute("INSERT INTO report_reviews (report_id, decision) VALUES (?, 'pending')", [$reportId]);

    db_execute(
        "INSERT INTO report_sales (id, report_id, product_id, product_name, category, color_name, color_argb, units, position)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)",
        [
            Uuid::v4(), $reportId, $product['id'],
            $product['brand'] . ' ' . $product['name'], $product['category'],
            'Midnight Blue', 0xFF1B2A5B, 1 + ($index % 3),
        ],
    );

    Engine::recompute($employeeId, $workDate);
}
