<?php

$bundles = [
    'plain' => [],
    'list' => ['table'],
    'media' => ['media'],
    'form' => ['repeater', 'media', 'multiselect', 'fields', 'form'],
    'editor' => ['repeater', 'media', 'multiselect', 'editor', 'fields', 'form'],
    'listform' => ['table', 'repeater', 'media', 'multiselect', 'fields', 'form'],
    'listeditor' => ['table', 'repeater', 'media', 'multiselect', 'editor', 'fields', 'form'],
    'form-static' => ['repeater', 'multiselect', 'media', 'form'],
];

$type = $type ?? 'plain';

$core = array_merge(
    ['util', 'nav', 'toast', 'modal', 'api', 'session'],
    $bundles[$type] ?? [],
    ['layout']
);
?>

<?php foreach ($core as $module): ?>
    <script src="<?= e(base_url("assets/admin/js/core/{$module}.js")) ?>"></script>
<?php endforeach; ?>

<?php if (!empty($script)): ?>
    <script src="<?= e(base_url("assets/admin/js/pages/{$script}.js")) ?>"></script>
<?php endif; ?>
</body>

</html>
