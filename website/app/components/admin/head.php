<?php

/**
 * Hazra EV Admin Document Head
 */
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">

    <script>
        (function () {
            try {
                var stored = localStorage.getItem('hazra-admin-theme');
                document.documentElement.dataset.theme = stored
                    || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            } catch (e) { }
        })();
    </script>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? '') ?> &mdash; Hazra EV Admin</title>

    <meta name="robots" content="noindex,nofollow">

    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <meta name="app-base" content="<?= e(rtrim(base_url('/'), '/')) ?>/">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Baloo+2:wght@600;700;800&family=Noto+Sans+Bengali:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="icon" href="<?= e(base_url('favicon.ico')) ?>" type="image/x-icon">
<?php foreach (['tokens', 'base', 'layout', 'components'] as $sheet): 
    $cPath = __BASEDIR__ . "/assets/admin/css/{$sheet}.css";
    $cv = file_exists($cPath) ? filemtime($cPath) : '1';
?>
    <link rel="stylesheet" href="<?= e(base_url("assets/admin/css/{$sheet}.css?v={$cv}")) ?>">
<?php endforeach; ?>
<?php if (($page ?? '') === 'dealer-locations'): ?>
    <link rel="stylesheet" href="<?= e(base_url('assets/vendor/leaflet/leaflet.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/admin/css/dealer-locations.css?v=' . filemtime(__BASEDIR__ . '/assets/admin/css/dealer-locations.css'))) ?>">
    <script>window.HAZRA_DEALER_MAP = <?= json_encode(require __BASEDIR__ . '/config/dealer-map.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <script defer src="<?= e(base_url('assets/vendor/leaflet/leaflet.js')) ?>"></script>
<?php endif; ?>
</head>

<body data-page="<?= e($page ?? '') ?>">
