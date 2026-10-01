<?php
/**
 * Apply the extracted storefront dataset on deployments that already ran 007.
 * The shared importer skips existing IDs/addresses and preserves admin edits.
 * Missing verified pins and phone numbers keep these storefronts as drafts.
 */
return static function (PDO $pdo): void {
    $import = require __DIR__ . '/007_dealer_locations_seed.php';
    $import($pdo);
};
