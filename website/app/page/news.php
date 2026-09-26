<?php
require_once __DIR__ . '/../../core/SafeHtml.php';

$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = 10;

$offset = ($page - 1) * $limit;
$totalPosts = (int)db_fetch_one("SELECT COUNT(*) FROM posts WHERE type = 'news' AND status = 'published'")['COUNT(*)'];
$totalPages = max(1, (int)ceil($totalPosts / $limit));

$posts = db_fetch_all(
    "SELECT * FROM posts WHERE type = 'news' AND status = 'published' ORDER BY published_at DESC LIMIT {$limit} OFFSET {$offset}"
);

$canonicalUrl = base_url('news');
if ($page > 1) {
    $canonicalUrl = base_url("news?page={$page}");
}

$pageTitle = 'News & Press Releases — Hazra Electrical Bike';
$pageDescription = 'Official news, press releases, and announcements from Hazra Electrical Bike. Stay updated on dealership expansions, product launches, and service initiatives.';

App::render('head', [
    'pageTitle'       => $pageTitle,
    'pageDescription' => $pageDescription,
    'extraCss'        => ['assets/css/styles/pages/news.css'],
    'canonicalUrl'    => $canonicalUrl,
    'ogType'          => 'website',
]);

App::render('header', ['isStickyOnly' => true]);
?>

<!-- ========== UNIQUE PAGE CONTENT START ========== -->
<section class="hero" style="padding: 60px 0 40px;">
  <div class="wrap">
    <p class="eyebrow"><i class="sq"></i>NEWSROOM</p>
    <h1 class="hero__title" style="font-size:clamp(28px,5vw,48px);margin-bottom:12px">News & Press Releases</h1>
    <p class="hero__lead" style="max-width: 700px;">Official announcements, dealership expansions, product updates, and community initiatives from Hazra Electrical Bike.</p>
  </div>
</section>

<section class="news-archive">
  <div class="wrap">
    <div class="news-grid">
      <?php if (empty($posts)): ?>
        <div class="bl-empty" style="grid-column: 1 / -1; text-align:center;padding:60px 20px">
          <i data-lucide="newspaper" style="width:48px;height:48px;color:var(--ink-soft-0);margin-bottom:16px"></i>
          <h3>No news items yet</h3>
          <p>Check back soon for the latest announcements.</p>
        </div>
      <?php else: ?>
        <?php foreach ($posts as $post): ?>
          <article class="news-card">
            <div class="news-card__img">
              <img src="<?= e(img_url($post['cover_image'] ?? null)) ?>" alt="" loading="lazy">
              <?php if ($post['location']): ?>
                <span class="news-card__location"><?= e($post['location']) ?></span>
              <?php endif; ?>
            </div>
            <div class="news-card__body">
              <div class="news-card__meta">
                <time datetime="<?= e(date('c', strtotime($post['published_at']))) ?>"><?= e(date('j M Y', strtotime($post['published_at']))) ?></time>
                <span>·</span>
                <span><?= e($post['read_minutes'] ?? 4) ?> min read</span>
              </div>
              <span class="news-card__cat"><?= e(ucfirst($post['category'] ?? 'Company')) ?></span>
              <h3><a href="<?= e(base_url("news/{$post['slug']}")) ?>"><?= e($post['title']) ?></a></h3>
              <p><?= e($post['excerpt']) ?></p>
              <a href="<?= e(base_url("news/{$post['slug']}")) ?>" class="news-card__link">Read more <i data-lucide="arrow-right" style="width:14px;height:14px"></i></a>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="bl-pagination" aria-label="News pagination" style="margin-top: 40px; display:flex;justify-content:center;align-items:center;gap:16px">
      <?php if ($page > 1): ?>
        <a class="btn btn--ghost" href="<?= e(base_url("news?page=" . ($page - 1))) ?>">
          <i data-lucide="chevron-left"></i> Previous
        </a>
      <?php endif; ?>
      <span class="bl-page-info">Page <?= $page ?> of <?= $totalPages ?></span>
      <?php if ($page < $totalPages): ?>
        <a class="btn btn--ink" href="<?= e(base_url("news?page=" . ($page + 1))) ?>">
          Next <i data-lucide="chevron-right"></i>
        </a>
      <?php endif; ?>
    </nav>
    <?php endif; ?>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();
});
</script>
<!-- ========== UNIQUE PAGE CONTENT END ========== -->

<?php App::render('footer'); ?>