<?php

/**
 * User-provided Hazra Electrical Bike storefront addresses.
 * Exact showroom pins and branch phone numbers were not provided, so these
 * start as drafts. Do not substitute city centres or another business's pin.
 * Other similarly named Kolkata shops are not confirmed Hazra dealers.
 * Stable IDs and address checks make reruns safe and preserve admin edits.
 */
return static function (PDO $pdo): void {
    require_once __DIR__ . '/../../api/support/DealerLocation.php';
    require_once __DIR__ . '/../../api/support/Wire.php';

    $locations = json_decode(file_get_contents(__DIR__ . '/../data/dealer-locations.json'), true, 512, JSON_THROW_ON_ERROR);
    $prepared = [];
    foreach ($locations as $location) {
        $id = $location['id'];
        unset($location['id']);
        $prepared[] = ['id' => $id] + DealerLocation::validate($location);
    }

    $exists = $pdo->prepare('SELECT id FROM dealer_locations WHERE id = ? OR (state = ? AND city = ? AND address = ? AND pincode = ?) LIMIT 1');
    $now = Wire::now();
    $inserted = 0;
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) $pdo->beginTransaction();
    try {
        foreach ($prepared as $location) {
            $exists->execute([$location['id'], $location['state'], $location['city'], $location['address'], $location['pincode']]);
            if ($exists->fetchColumn() !== false) { $exists->closeCursor(); continue; }
            $exists->closeCursor();
            $location['created_at'] = $location['updated_at'] = $now;
            $columns = array_keys($location);
            $insert = $pdo->prepare('INSERT INTO dealer_locations (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')');
            $insert->execute(array_values($location));
            $inserted++;
        }
        if ($ownTransaction) $pdo->commit();
    } catch (Throwable $error) {
        if ($ownTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    echo "  Added {$inserted} Hazra dealer location drafts. Confirm pins and phone numbers in Admin > Dealer Locations before publishing.\n";
};
