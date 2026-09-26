<?php
require_once __DIR__ . '/../../core/SafeHtml.php';

$slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : (isset($_GET['id']) ? trim((string) $_GET['id']) : '');
$post = null;

if ($slug !== '') {
  $post = db_fetch_one(
    "SELECT * FROM posts WHERE type = 'blog' AND status = 'published' AND (slug = ? OR id = ?) LIMIT 1",
    [$slug, $slug]
  );
}

if (!$post && $slug === '') {
  $post = db_fetch_one(
    "SELECT * FROM posts WHERE type = 'blog' AND status = 'published' ORDER BY is_featured DESC, published_at DESC LIMIT 1"
  );
}

if (!$post) {
  http_response_code(404);
  $pageTitle = 'Blog Article Not Found — Hazra EV';
  $pageDescription = 'The requested blog article could not be found.';
  App::render('head', [
    'pageTitle' => $pageTitle,
    'pageDescription' => $pageDescription,
    'extraCss' => ['assets/css/styles/pages/blog-single.css'],
  ]);
  App::render('header', ['isStickyOnly' => true]);
  ?>
  <section class="hero" style="min-height:55vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:80px var(--gutter);text-align:center;">
    <p class="eyebrow"><i class="sq"></i> 404 NOT FOUND</p>
    <h1 class="hero__title" style="margin-top:12px;">Blog Article Not Found</h1>
    <p class="hero__lead">The blog article you are looking for may have been moved, renamed, or is no longer published.</p>
    <div style="margin-top:28px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
    <a class="btn btn--ink" href="<?= e(base_url('blog')) ?>">Browse Blog</a>
    <a class="btn btn--ghost" href="<?= e(base_url()) ?>">Back to Homepage</a>
    </div>
  </section>
  <?php
  App::render('footer');
  exit;
}

$canonicalUrl = base_url("blog/{$post['slug']}");
$coverUrl = img_url($post['cover_image'] ?? null);
$pageTitle = $post['meta_title'] ?: $post['title'] . ' — Hazra Blog';
$pageDescription = $post['meta_description'] ?: ($post['excerpt'] ?? '');
$publishedDate = !empty($post['published_at'])
  ? strtotime($post['published_at'])
  : (!empty($post['created_at']) ? strtotime($post['created_at']) : time());
$author = trim((string) ($post['author'] ?? 'Hazra Editorial Desk')) ?: 'Hazra Editorial Desk';
$category = trim((string) ($post['category'] ?? 'EV Trends')) ?: 'EV Trends';
$contentHtml = SafeHtml::clean($post['content'] ?? '');
$toc = [];
$headingCounts = [];

$contentHtml = preg_replace_callback('/<h([23])([^>]*)>(.*?)<\/h\1>/is', function ($matches) use (&$toc, &$headingCounts) {
  $level = (int) $matches[1];
  $attributes = $matches[2] ?? '';
  $label = trim(preg_replace('/\s+/', ' ', strip_tags($matches[3])));
  $id = '';

  if (preg_match('/\bid=["\']([^"\']+)["\']/i', $attributes, $idMatch)) {
    $id = $idMatch[1];
  } else {
    $id = trim((string) preg_replace('/[^a-z0-9]+/i', '-', strtolower($label)), '-');
    $id = $id !== '' ? $id : 'section';
    $headingCounts[$id] = ($headingCounts[$id] ?? 0) + 1;
    if ($headingCounts[$id] > 1) {
      $id .= '-' . $headingCounts[$id];
    }
    $attributes .= ' id="' . e($id) . '"';
  }

  if ($label !== '') {
    $toc[] = ['id' => $id, 'label' => $label];
  }

  return '<h' . $level . $attributes . '>' . $matches[3] . '</h' . $level . '>';
}, $contentHtml) ?? $contentHtml;

$relatedPosts = db_fetch_all(
  "SELECT * FROM posts WHERE type = 'blog' AND status = 'published' AND id != ? ORDER BY published_at DESC LIMIT 3",
  [$post['id']]
);
$tags = array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/', (string) ($post['tags'] ?? '')))));
$readMinutes = (int) ($post['read_minutes'] ?? 0);
if ($readMinutes < 1) {
  $readMinutes = SafeHtml::readMinutes($contentHtml);
}

$jsonLd = [
  '@context' => 'https://schema.org',
  '@type' => 'BlogPosting',
  'headline' => $post['title'],
  'description' => $pageDescription,
  'image' => [$coverUrl],
  'datePublished' => date('c', $publishedDate),
  'dateModified' => date('c', strtotime($post['updated_at'] ?? $post['published_at'] ?? 'now')),
  'author' => ['@type' => 'Person', 'name' => $author],
  'publisher' => [
    '@type' => 'Organization',
    'name' => 'Hazra Electrical Bike',
    'logo' => ['@type' => 'ImageObject', 'url' => base_url('assets/hazraev.png')],
  ],
  'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonicalUrl],
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="google-site-verification" content="Q4FWGLTexIVX2B-2jie8q39ECbwaq2fqtEXgayv8XW8" />
  <script>
  (function(){try{var t=localStorage.getItem('theme')||localStorage.getItem('vm-theme');
  if(!t)t=matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light';
  document.documentElement.setAttribute('data-theme',t);}catch(e){}})();
  </script>
  <title><?= e($pageTitle) ?></title>
  <meta property="og:type" content="article">
  <meta property="og:site_name" content="Hazra Electrical Bike">
  <meta property="og:title" content="<?= e($pageTitle) ?>">
  <meta property="og:description" content="<?= e($pageDescription) ?>">
  <meta property="og:url" content="<?= e(base_url('blog-single')) ?>">
  <meta property="og:image" content="<?= e($coverUrl) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <link rel="canonical" href="<?= e($canonicalUrl) ?>">
  <meta name="description" content="<?= e($pageDescription) ?>">
  <link rel="icon" href="<?= e(base_url('assets/hazraev.png')) ?>" type="image/png">
  <link rel="preload" href="<?= e(base_url('assets/fonts/inter-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="<?= e(base_url('assets/css/styles/tokens.css')) ?>">
  <link rel="stylesheet" href="<?= e(base_url('assets/css/styles/typography.css')) ?>">
  <link rel="stylesheet" href="<?= e(base_url('assets/css/styles/global.css')) ?>">
  <link rel="stylesheet" href="<?= e(base_url('assets/css/styles/utilities.css')) ?>">
  <link rel="stylesheet" href="<?= e(base_url('assets/css/styles/components/page-chrome.css')) ?>">
  <link rel="stylesheet" href="<?= e(base_url('assets/css/styles/pages/blog-single.css')) ?>">
  <script type="application/ld+json">
  <?= json_encode($jsonLd, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>
  </script>
</head>
<body>

<header class="header">
  <div class="header__top">
    <a class="brand" href="<?= e(base_url()) ?>">
      <img class="brand__mark" src="<?= e(base_url('assets/hazraev.png')) ?>" alt="Hazra">
      <span class="brand__txt">Hazra Electrical Bike</span>
    </a>
    <button class="theme" id="theme" aria-label="Toggle theme">
      <i data-lucide="sun-medium" class="theme__sun"></i>
      <i data-lucide="moon" class="theme__moon"></i>
    </button>
  </div>
</header>

<section class="hero">
  <p class="eyebrow" style="margin-bottom:12px">Blog · <?= e($category) ?></p>
  <h1 class="hero__title" style="font-size:clamp(28px,5vw,48px)"><?= e($post['title']) ?></h1>
  <p class="hero__lead">
    <?= e($post['excerpt'] ?? '') ?>
  </p>
</section>

<section class="post">
  <div class="post__layout">

    <!-- LEFT RAIL -->
    <aside class="rail" aria-label="Share and contents">
      <div>
        <div class="rail__label">Share</div>
        <div class="share">
          <a href="https://www.instagram.com/" target="_blank" rel="noopener noreferrer" class="share__btn"><i data-lucide="instagram"></i> Instagram</a>
          <a href="https://www.facebook.com/sharer/sharer.php?u=<?= e(urlencode($canonicalUrl)) ?>" target="_blank" rel="noopener noreferrer" class="share__btn"><i data-lucide="facebook"></i> Facebook</a>
          <button type="button" class="share__btn" data-copy-link data-copy-url="<?= e($canonicalUrl) ?>"><i data-lucide="link"></i> Copy link</button>
        </div>
      </div>
      <div class="toc">
        <div class="rail__label">On this page</div>
        <?php foreach ($toc as $index => $heading): ?>
          <a href="#<?= e($heading['id']) ?>" class="<?= $index === 0 ? 'is-active' : '' ?>"><?= e($heading['label']) ?></a>
        <?php endforeach; ?>
      </div>
    </aside>

    <!-- MAIN ARTICLE -->
    <article class="article">
      <div class="article__hero">
        <img src="<?= e($coverUrl) ?>" alt="<?= e($post['title']) ?>" onerror="this.onerror=null;this.src='<?= e(base_url('assets/hazraev.png')) ?>';this.style.objectFit='contain';this.style.padding='40px';">
        <div class="article__hero-shade"></div>
      </div>
      <div class="article__body">
        <div class="article__meta">
          <span class="article__cat"><?= e($category) ?></span>
          <time datetime="<?= e(date('c', $publishedDate)) ?>"><?= e(date('j M Y', $publishedDate)) ?></time>
          <span>Â·</span>
          <span><?= e($readMinutes) ?> min read</span>
        </div>
        <h1 class="article__title"><?= e($post['title']) ?></h1>
        <p class="article__lead">
          <?= e($post['excerpt'] ?? '') ?>
        </p>
        <div class="article__author">
          <img src="https://i.pravatar.cc/96?u=<?= e(urlencode($author)) ?>" alt="<?= e($author) ?>">
          <div>
            <strong><?= e($author) ?></strong>
            <span><?= e($category) ?> · Hazra Insights</span>
          </div>
        </div>

        <div class="prose">
          <?= $contentHtml ?>
        </div>

        <?php if ($tags): ?>
          <div class="article__tags">
            <?php foreach ($tags as $tag): ?>
              <a href="<?= e(base_url('blog?tag=' . urlencode(ltrim($tag, '#')))) ?>" class="article__tag"><?= e(str_starts_with($tag, '#') ? $tag : '#' . $tag) ?></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </article>

    <!-- RIGHT ASIDE -->
    <aside class="aside" aria-label="Related and subscribe">
      <div class="aside__card">
        <h4>Related posts</h4>
        <div class="related">
          <?php foreach ($relatedPosts as $related): ?>
            <a href="<?= e(base_url('blog/' . $related['slug'])) ?>" class="related__item">
              <img src="<?= e(img_url($related['cover_image'] ?? null)) ?>" alt="<?= e($related['title']) ?>">
              <div>
                <strong><?= e($related['title']) ?></strong>
                <span><?= e(ucwords(str_replace('-', ' ', $related['category'] ?? 'EV Trends'))) ?> · <?= e($related['read_minutes'] ?? 4) ?> min</span>
              </div>
            </a>
          <?php endforeach; ?>
          <?php if (!$relatedPosts): ?>
            <p style="color:var(--ink-soft-0);font-size:13px">No related posts yet.</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="aside__card newsletter">
        <h4>Stay in the loop</h4>
        <p>Weekly insights on electric mobility, rider stories, and product updates. No spam.</p>
        <input type="email" placeholder="Your email" aria-label="Email for newsletter">
        <button type="button">Subscribe</button>
      </div>
    </aside>

  </div>
</section>

<section class="footer">
  <p style="color: var(--ink-soft-0); margin-bottom: 24px;">
    More stories from the Hazra community
  </p>
  <a class="footer__link" href="<?= e(base_url('blog')) ?>">
    <span>Back to Blog</span>
    <i data-lucide="arrow-right"></i>
  </a>
</section>

<script src="https://unpkg.com/lucide@0.544.0/dist/umd/lucide.min.js"></script>
<script>
  lucide.createIcons();

  const copyLinkButton = document.querySelector('[data-copy-link]');
  copyLinkButton.addEventListener('click', async () => {
    const url = copyLinkButton.dataset.copyUrl;
    const originalLabel = copyLinkButton.innerHTML;

    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(url);
      } else {
        const textarea = document.createElement('textarea');
        textarea.value = url;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        textarea.remove();
      }

      copyLinkButton.innerHTML = '<i data-lucide="check"></i> Copied';
      lucide.createIcons();
      window.setTimeout(() => {
        copyLinkButton.innerHTML = originalLabel;
        lucide.createIcons();
      }, 1600);
    } catch (error) {
      copyLinkButton.innerHTML = '<i data-lucide="circle-alert"></i> Copy failed';
      lucide.createIcons();
      window.setTimeout(() => {
        copyLinkButton.innerHTML = originalLabel;
        lucide.createIcons();
      }, 1600);
    }
  });
</script>
<script src="<?= e(base_url('assets/js/script.js')) ?>"></script>
</body>
</html>
