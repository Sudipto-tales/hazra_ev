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

<?php foreach ($core as $module): 
    $fPath = __BASEDIR__ . "/assets/admin/js/core/{$module}.js";
    $v = file_exists($fPath) ? filemtime($fPath) : '1';
?>
    <script src="<?= e(base_url("assets/admin/js/core/{$module}.js?v={$v}")) ?>"></script>
<?php endforeach; ?>

<?php if (!empty($script)): 
    $pPath = __BASEDIR__ . "/assets/admin/js/pages/{$script}.js";
    $pv = file_exists($pPath) ? filemtime($pPath) : '1';
?>
    <script src="<?= e(base_url("assets/admin/js/pages/{$script}.js?v={$pv}")) ?>"></script>
<?php endif; ?>
</body>

</html>
