<?php
$pageTitle = $pageTitle ?? 'Hazra Electrical Bike — Follow Elegant';
$pageDescription = $pageDescription ?? 'Hazra Electrical Bike — electric scooters built for everyday Indian riding. Book a test drive or find a dealership near you.';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="app-base" content="<?= e(rtrim(base_url('/'), '/')) ?>/">
<title><?= e($pageTitle) ?></title>
<link rel="icon" href="<?= e(base_url('assets/hazraev.png')) ?>" type="image/png">
<link rel="apple-touch-icon" href="<?= e(base_url('assets/hazraev.png')) ?>">
<meta name="description" content="<?= e($pageDescription) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@600;700;800;900&display=swap" rel="stylesheet">
<!-- Theme Initialization (Anti-Flash) -->
<script>
(function(){try{var t=localStorage.getItem('theme')||localStorage.getItem('vm-theme');
if(!t)t=window.matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light';
document.documentElement.setAttribute('data-theme',t);}catch(e){}})();
</script>
<link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(base_url('assets/css/styles/tokens.css')) ?>">
<link rel="stylesheet" href="<?= e(base_url('assets/css/styles/typography.css')) ?>">
<link rel="stylesheet" href="<?= e(base_url('assets/css/styles/global.css')) ?>">
<link rel="stylesheet" href="<?= e(base_url('assets/css/styles/utilities.css')) ?>">
<link rel="stylesheet" href="<?= e(base_url('assets/css/styles/components/page-chrome.css')) ?>">
<?php if (isset($extraCss)): ?>
    <?php foreach ($extraCss as $css): ?>
        <link rel="stylesheet" href="<?= e(base_url($css)) ?>">
    <?php endforeach; ?>
<?php endif; ?>
<script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
