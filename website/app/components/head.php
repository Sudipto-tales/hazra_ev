<?php
$pageTitle = $metaTitle ?? $pageTitle ?? 'Hazra Electrical Bike — Follow Elegant';
$pageDescription = $metaDescription ?? $pageDescription ?? 'Hazra Electrical Bike — electric scooters built for everyday Indian riding. Book a test drive or find a dealership near you.';
$canonicalUrl = $canonicalUrl ?? null;
$ogImage = $ogImage ?? base_url('assets/hazraev.png');
$ogType = $ogType ?? 'website';
$jsonLd = $jsonLd ?? null;
$googleSiteVerification = 'Q4FWGLTexIVX2B-2jie8q39ECbwaq2fqtEXgayv8XW8';
try {
    $vRow = db_fetch_one("SELECT setting_value FROM website_settings WHERE setting_key = 'google_site_verification'");
    if (!empty($vRow['setting_value'])) $googleSiteVerification = $vRow['setting_value'];
} catch (\Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="google-site-verification" content="<?= e($googleSiteVerification) ?>" />
<meta name="app-base" content="<?= e(rtrim(base_url('/'), '/')) ?>/">
<title><?= e($pageTitle) ?></title>
<link rel="icon" href="<?= e(base_url('assets/hazraev.png')) ?>" type="image/png">
<link rel="apple-touch-icon" href="<?= e(base_url('assets/hazraev.png')) ?>">
<meta name="description" content="<?= e($pageDescription) ?>">
<?php if ($canonicalUrl): ?>
<link rel="canonical" href="<?= e($canonicalUrl) ?>">
<?php endif; ?>
<meta property="og:type" content="<?= e($ogType) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($pageDescription) ?>">
<meta property="og:image" content="<?= e((str_starts_with($ogImage, 'http://') || str_starts_with($ogImage, 'https://')) ? $ogImage : base_url($ogImage)) ?>">
<meta property="og:url" content="<?= e($canonicalUrl ?? base_url()) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($pageTitle) ?>">
<meta name="twitter:description" content="<?= e($pageDescription) ?>">
<meta name="twitter:image" content="<?= e((str_starts_with($ogImage, 'http://') || str_starts_with($ogImage, 'https://')) ? $ogImage : base_url($ogImage)) ?>">
<?php if ($jsonLd): ?>
<script type="application/ld+json">
<?= json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
</script>
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@600;700;800;900&display=swap" rel="stylesheet">
<!-- Theme Initialization (Anti-Flash) -->
<script>
(function(){try{var t=localStorage.getItem('theme')||localStorage.getItem('vm-theme');
if(!t)t=window.matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light';
document.documentElement.setAttribute('data-theme',t);}catch(e){}})();
</script>
<?php $cssVer = defined('__BASEDIR__') && file_exists(__BASEDIR__ . '/assets/css/style.css') ? filemtime(__BASEDIR__ . '/assets/css/style.css') : time(); ?>
<link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>?v=<?= $cssVer ?>">
<link rel="stylesheet" href="<?= e(base_url('assets/css/styles/tokens.css')) ?>">
<link rel="stylesheet" href="<?= e(base_url('assets/css/styles/typography.css')) ?>">
<link rel="stylesheet" href="<?= e(base_url('assets/css/styles/global.css')) ?>">
<link rel="stylesheet" href="<?= e(base_url('assets/css/styles/utilities.css')) ?>">
<link rel="stylesheet" href="<?= e(base_url('assets/css/styles/components/page-chrome.css')) ?>">
<?php if (isset($extraCss)): ?>
    <?php foreach ($extraCss as $css): ?>
        <link rel="stylesheet" href="<?= e((str_starts_with($css, 'http://') || str_starts_with($css, 'https://') || str_starts_with($css, '//')) ? $css : base_url($css)) ?>">
    <?php endforeach; ?>
<?php endif; ?>
<script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
