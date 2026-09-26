<?php

/**
 * The only seed that ships to the server.
 *
 * Creates the organisation, its tracking rulebook, and one admin account —
 * nothing else. There are deliberately no employees, companies or products:
 * the roster is built through the admin UI against the real business, and a
 * placeholder user in a production database is a liability, not a convenience.
 *
 * Idempotent twice over: the seeder ledger stops it running again, and it
 * checks for the admin's email before inserting anyway, so it is also safe to
 * run against a database that was seeded by hand.
 *
 * The password is a first-login credential, not a secret. Change it in the app
 * before the first employee is added.
 */

return function (PDO $pdo): void {
    require_once __DIR__ . '/../../api/support/Uuid.php';
    require_once __DIR__ . '/../../api/support/Wire.php';

    $email = 'admin@hazraev.com';

    $now = Wire::now();

    // An org may already exist — `--demo` runs before the seeders in dev, and
    // the server may be re-running this after a manual edit. Reuse it.
    $org = db_fetch_one("SELECT id FROM organizations LIMIT 1");

    if ($org === false || $org === null) {
        $orgId = Uuid::v4();
        db_execute(
            "INSERT INTO organizations (id, name, timezone, weekend_days, created_at) VALUES (?, ?, ?, ?, ?)",
            [$orgId, 'Hazra EV', 'Asia/Kolkata', '[7]', $now],
        );
        echo "  organisation Hazra EV created\n";
    } else {
        $orgId = $org['id'];
    }

    $hasConfig = db_fetch_one("SELECT org_id FROM tracking_configs WHERE org_id = ?", [$orgId]);
    if ($hasConfig === false || $hasConfig === null) {
        db_execute("INSERT INTO tracking_configs (org_id, updated_at) VALUES (?, ?)", [$orgId, $now]);
    }

    $existing = db_fetch_one("SELECT id FROM users WHERE email = ?", [$email]);
    if ($existing !== false && $existing !== null) {
        echo "  \033[2m{$email} already exists — nothing to do\033[0m\n";
        return;
    }

    $adminId = Uuid::v4();

    db_execute(
        "INSERT INTO users (id, org_id, role, name, email, phone, avatar_url, password_hash, active, created_at, updated_at)
         VALUES (?, ?, 'admin', ?, ?, '', '', ?, 1, ?, ?)",
        [$adminId, $orgId, 'Admin', $email, password_hash('admin123', PASSWORD_BCRYPT), $now, $now],
    );

    // admin_code is unique and the dev demo data claims ADM-001, so take the
    // first code that is free rather than colliding.
    $code = 'ADM-001';
    for ($n = 1; $n < 1000; $n++) {
        $candidate = sprintf('ADM-%03d', $n);
        $taken = db_fetch_one("SELECT admin_code FROM admin_profiles WHERE admin_code = ?", [$candidate]);
        if ($taken === false || $taken === null) {
            $code = $candidate;
            break;
        }
    }

    db_execute(
        "INSERT INTO admin_profiles (user_id, admin_code, role_title, region) VALUES (?, ?, ?, ?)",
        [$adminId, $code, 'Administrator', ''],
    );

    db_execute("INSERT INTO user_preferences (user_id) VALUES (?)", [$adminId]);

    echo "  admin {$email} created ({$code})\n";
};
