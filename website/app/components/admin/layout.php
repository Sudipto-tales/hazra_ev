<?php

$type = $type ?? 'plain';
$script = $script ?? $page;

App::render('admin/head', ['title' => $title ?? '', 'page' => $page ?? '']);
?>
    <div class="app">
<?php App::render('admin/sidebar'); ?>

        <div class="shell">
<?php App::render('admin/topbar'); ?>

            <main class="main">
<?php if (!empty($body)): ?>
<?php App::render($body); ?>
<?php else: ?>
                <div id="pageHead"></div>
                <div id="view"></div>
<?php endif; ?>
            </main>
        </div>
    </div>
<?php App::render('admin/scripts', ['type' => $type, 'script' => $script]); ?>
