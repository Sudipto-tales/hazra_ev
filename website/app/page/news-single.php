<?php
require_once __DIR__ . '/../../core/SafeHtml.php';

$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : (isset($_GET['id']) ? trim((string)$_GET['id']) : '');

$post = null;
if ($slug !== '') {
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $slug)) {
        $post = db_fetch_one("SELECT * FROM posts WHERE id = ?", [$slug]);
    } else {
        $post = db_fetch_one("SELECT * FROM posts WHERE slug = ?", [$slug]);
    }
}

// Fallback: If no slug/id provided (e.g. clicking direct /news-single link), load the latest published news article
if (!$post && $slug === '') {
    $post = db_fetch_one("SELECT * FROM posts WHERE type = 'news' AND status = 'published' ORDER BY is_featured DESC, published_at DESC LIMIT 1");
    if (!$post) {
        $post = db_fetch_one("SELECT * FROM posts WHERE type = 'news' AND status = 'published' ORDER BY published_at DESC LIMIT 1");
    }
}

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
$isAdmin = !empty($_SESSION['admin_logged_in']) || ($_SESSION['user_role'] ?? '') === 'admin';

if ($post && $post['status'] !== 'published' && !$isAdmin) {
    $post = null;
}

if (!$post) {
    http_response_code(404);
    $pageTitle = 'News Article Not Found — Hazra EV';
    $pageDescription = 'The requested news article could not be found.';
    App::render('head', [
        'pageTitle'       => $pageTitle,
        'pageDescription' => $pageDescription,
        'extraCss'        => ['assets/css/styles/pages/news-single.css'],
    ]);
    App::render('header', ['isStickyOnly' => true]);
    ?>
    <section class="hero" style="min-height:55vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:80px var(--gutter);text-align:center;">
      <p class="eyebrow"><i class="sq"></i> 404 NOT FOUND</p>
      <h1 class="hero__title" style="margin-top:12px;">News Article Not Found</h1>
      <p class="hero__lead">The news article you are looking for may have been moved, renamed, or is currently unpublished.</p>
      <div style="margin-top:28px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
        <a class="btn btn--ink" href="<?= e(base_url('news')) ?>"><span>Browse All News</span></a>
        <a class="btn btn--ghost" href="<?= e(base_url()) ?>">Back to Homepage</a>
      </div>
    </section>
    <?php
    App::render('footer');
    exit;
}

$canonicalUrl = base_url("news/{$post['slug']}");
$coverUrl = img_url($post['cover_image'] ?? null);
$ogImage = $coverUrl;
$metaTitle = $post['meta_title'] ?: $post['title'] . ' — Hazra EV News';
$metaDescription = $post['meta_description'] ?: ($post['excerpt'] ?? '');
$publishedDate = !empty($post['published_at']) ? strtotime($post['published_at']) : (!empty($post['created_at']) ? strtotime($post['created_at']) : time());

$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'NewsArticle',
    'headline' => $post['title'],
    'description' => $post['excerpt'],
    'image' => [$ogImage],
    'datePublished' => date('c', strtotime($post['published_at'])),
    'dateModified' => date('c', strtotime($post['updated_at'] ?? $post['published_at'])),
    'author' => [
        '@type' => 'Person',
        'name' => $post['author']
    ],
    'publisher' => [
        '@type' => 'Organization',
        'name' => 'Hazra Electrical Bike',
        'logo' => [
            '@type' => 'ImageObject',
            'url' => base_url('assets/hazraev.png')
        ]
    ],
    'mainEntityOfPage' => [
        '@type' => 'WebPage',
        '@id' => $canonicalUrl
    ]
];

$latestNews = db_fetch_all(
    "SELECT * FROM posts WHERE type = 'news' AND status = 'published' AND id != ? ORDER BY published_at DESC LIMIT 3",
    [$post['id']]
);

$contentHtml = SafeHtml::clean($post['content'] ?? '');

if (empty(trim(strip_tags($contentHtml))) && !empty($post['excerpt'])) {
    $contentHtml = '<p style="font-size:1.1em;line-height:1.8;">' . nl2br(e($post['excerpt'])) . '</p>';
}

$pageTitle = $metaTitle;
$pageDescription = $metaDescription;

App::render('head', [
    'pageTitle'       => $pageTitle,
    'pageDescription' => $pageDescription,
    'extraCss'        => ['assets/css/styles/pages/news-single.css'],
    'canonicalUrl'    => $canonicalUrl,
    'ogImage'         => $ogImage,
    'ogType'          => 'article',
    'jsonLd'          => $jsonLd,
]);

App::render('header', ['isStickyOnly' => true]);
?>

<!-- ========== UNIQUE PAGE CONTENT START ========== -->
<section class="news">
  <!-- Breadcrumb -->
  <div class="news-breadcrumb" style="width:min(1180px,96vw);margin:0 auto 20px;display:flex;align-items:center;gap:8px;font-size:13px;color:var(--ink-soft-0);">
    <a href="<?= e(base_url()) ?>" style="color:inherit;text-decoration:none;">Home</a>
    <span style="opacity:.5;">/</span>
    <a href="<?= e(base_url('news')) ?>" style="color:inherit;text-decoration:none;">News</a>
    <span style="opacity:.5;">/</span>
    <span style="color:var(--ink-0);font-weight:600;"><?= e(ucfirst($post['category'] ?? 'Press Release')) ?></span>
  </div>

  <div class="news__layout">

    <article class="story">
      <div class="story__hero">
        <img src="<?= e($coverUrl) ?>" alt="<?= e($post['title']) ?>" onerror="this.onerror=null;this.src='<?= e(base_url('assets/hazraev.png')) ?>';this.style.objectFit='contain';this.style.padding='40px';">
        <span class="story__badge">Press Release</span>
      </div>
      <div class="story__body">
        <div class="story__meta">
          <time datetime="<?= e(date('c', $publishedDate)) ?>"><?= e(date('j F Y', $publishedDate)) ?></time>
          <?php if ($post['location']): ?>
            <span>·</span>
            <span><?= e($post['location']) ?></span>
          <?php endif; ?>
          <span>·</span>
          <span><?= e($post['read_minutes'] ?? 4) ?> min read</span>
        </div>
        <h1 class="story__title"><?= e($post['title']) ?></h1>
        <p class="story__deck"><?= e($post['excerpt']) ?></p>

        <div class="story__prose">
          <?= $contentHtml ?>
        </div>

        <div class="story__footer">
          <div class="story__share">
            <a href="https://twitter.com/intent/tweet?url=<?= urlencode($canonicalUrl) ?>&text=<?= urlencode($post['title']) ?>" target="_blank" rel="noopener" aria-label="Share on X"><i data-lucide="twitter"></i></a>
            <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($canonicalUrl) ?>" target="_blank" rel="noopener" aria-label="Share on LinkedIn"><i data-lucide="linkedin"></i></a>
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($canonicalUrl) ?>" target="_blank" rel="noopener" aria-label="Share on Facebook"><i data-lucide="facebook"></i></a>
            <a href="https://api.whatsapp.com/send?text=<?= urlencode($post['title'] . ' ' . $canonicalUrl) ?>" target="_blank" rel="noopener" aria-label="Share on WhatsApp"><i data-lucide="message-circle"></i></a>
            <button onclick="copyLink('<?= e($canonicalUrl) ?>')" aria-label="Copy link"><i data-lucide="link"></i></button>
          </div>
          <a href="<?= e(base_url('news')) ?>" style="font-size:13px;font-weight:700;color:var(--brand-violet);display:inline-flex;align-items:center;gap:6px">
            More news & stories <i data-lucide="arrow-right" style="width:14px;height:14px"></i>
          </a>
        </div>
      </div>
    </article>

    <aside class="news-aside">
      <div class="news-aside__card">
        <h4>Latest news</h4>
        <div class="latest">
          <?php foreach ($latestNews as $news): ?>
            <a href="<?= e(base_url("news/{$news['slug']}")) ?>">
              <time><?= e(date('j M Y', strtotime($news['published_at']))) ?></time>
              <strong><?= e($news['title']) ?></strong>
              <span><?= e($news['excerpt'] ?? '') ?></span>
            </a>
          <?php endforeach; ?>
          <?php if (empty($latestNews)): ?>
            <p style="color:var(--ink-soft-0);font-size:13px">No other news items.</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="news-aside__card cta-card">
        <h4>Become a dealer</h4>
        <p>Join the Hazra network and bring electric mobility to your city.</p>
        <a href="<?= e(base_url('become-a-dealer')) ?>">
          Apply now <i data-lucide="arrow-up-right" style="width:14px;height:14px"></i>
        </a>
      </div>
    </aside>

  </div>
</section>

<section class="footer" style="padding: 48px 0; text-align: center;">
  <p style="color: var(--ink-soft-0); margin-bottom: 24px;">
    Stay updated with company news and product launches
  </p>
  <a class="footer__link" href="<?= e(base_url('index')) ?>">
    <span>Back to home</span>
    <i data-lucide="arrow-right" style="width:16px;height:16px"></i>
  </a>
</section>

<script>
document.addEventListener('DOMContentLoaded', async () => {
  if (window.lucide) lucide.createIcons();

  function copyLink(url) {
    navigator.clipboard.writeText(url).then(() => {
      const btn = event.target.closest('button');
      const original = btn.innerHTML;
      btn.innerHTML = '<i data-lucide="check"></i>';
      if (window.lucide) lucide.createIcons();
      setTimeout(() => { btn.innerHTML = original; if (window.lucide) lucide.createIcons(); }, 2000);
    });
  }
  window.copyLink = copyLink;
});
</script>
<!-- ========== UNIQUE PAGE CONTENT END ========== -->

<?php App::render('footer'); ?>