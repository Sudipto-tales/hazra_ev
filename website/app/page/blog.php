<?php
require_once __DIR__ . '/../../core/SafeHtml.php';

$category = isset($_GET['category']) ? (string)$_GET['category'] : '';
$tag      = isset($_GET['tag']) ? (string)$_GET['tag'] : '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = 6;

$featuredPost = db_fetch_one(
    "SELECT * FROM posts WHERE type = 'blog' AND status = 'published' AND is_featured = 1 ORDER BY published_at DESC LIMIT 1"
);

$featuredPost = $featuredPost ?: db_fetch_one(
    "SELECT * FROM posts WHERE type = 'blog' AND status = 'published' ORDER BY published_at DESC LIMIT 1"
);

$secondaryPost = $featuredPost ? db_fetch_one(
    "SELECT * FROM posts WHERE type = 'blog' AND status = 'published' AND id != ? ORDER BY published_at DESC LIMIT 1",
    [$featuredPost['id']]
) : null;

$offset = ($page - 1) * $limit;
$where  = "type = 'blog' AND status = 'published'";
$params = [];

if ($category) {
    $where .= " AND category = ?";
    $params[] = $category;
}
if ($tag) {
    $where .= " AND (tags LIKE ? OR tags LIKE ? OR tags LIKE ?)";
    // Match with # prefix, without #, or as part of comma-separated list
    $params[] = '%#' . $tag . '%';
    $params[] = '%' . $tag . ',%';
    $params[] = '%,' . $tag . '%';
}

$totalPosts = (int)db_fetch_one("SELECT COUNT(*) FROM posts WHERE {$where}", $params)['COUNT(*)'];
$totalPages = max(1, (int)ceil($totalPosts / $limit));

$posts = db_fetch_all(
    "SELECT * FROM posts WHERE {$where} ORDER BY published_at DESC LIMIT {$limit} OFFSET {$offset}",
    $params
);

$flashNews = db_fetch_all(
    "SELECT * FROM posts WHERE type = 'news' AND status = 'published' ORDER BY published_at DESC LIMIT 6"
);

$categories = db_fetch_all(
    "SELECT category, COUNT(*) as cnt FROM posts WHERE type = 'blog' AND status = 'published' GROUP BY category ORDER BY cnt DESC"
);

$canonicalUrl = base_url('blog');
if ($category) {
    $canonicalUrl = base_url("blog/category/{$category}");
} elseif ($tag) {
    $canonicalUrl = base_url("blog?tag={$tag}");
} elseif ($page > 1) {
    $canonicalUrl = base_url("blog?page={$page}");
}

$pageTitle = 'Blog — Hazra Electrical Bike';
if ($category) {
    $pageTitle = ucfirst($category) . ' — Blog — Hazra Electrical Bike';
}
$pageDescription = 'Stories on electric mobility, battery care, city riding and the road ahead from Hazra Electrical Bike.';

App::render('head', [
    'pageTitle'       => $pageTitle,
    'pageDescription' => $pageDescription,
    'extraCss'        => ['assets/css/styles/pages/blog.css'],
    'canonicalUrl'    => $canonicalUrl,
]);

App::render('header', ['isStickyOnly' => true]);
?>

<!-- ========== UNIQUE PAGE CONTENT START ========== -->
<!-- HERO BANNER -->
<section class="bl-banner">
  <div class="bl-banner__bg" aria-hidden="true"></div>
  <div class="bl-banner__veil" aria-hidden="true"></div>
  <div class="bl-banner__in">
    <p class="eyebrow"><i class="sq"></i>JOURNAL</p>
    <h1 class="bl-banner__title">Quietly thinking<br>about the ride.</h1>
    <p class="bl-banner__lead">Battery care, city routes, dealer stories and the long road of electric mobility — written for riders.</p>
  </div>
</section>

<!-- MAGAZINE -->
<section class="bl-mag">
  <div class="wrap">
    <div class="bl-mag__intro">
      <div>
        <h2>Quietly Thinking</h2>
      </div>
      <p>A calm space for slow thoughts on EV life — range, roads and what comes next.</p>
      <div class="bl-mag__actions">
        <a class="btn btn--ink" href="#newsletter"><span>Subscribe</span><i data-lucide="play"></i></a>
        <a class="btn btn--ghost" href="<?= e(base_url('contact')) ?>">Contact</a>
      </div>
    </div>

    <div class="bl-layout">
      <!-- MAIN COLUMN -->
      <div>
        <!-- Featured -->
        <?php if ($featuredPost): ?>
        <div class="bl-feat">
          <article class="bl-feat__main">
            <div class="bl-feat__copy">
              <span class="bl-cat"><?= e(ucfirst($featuredPost['category'] ?? 'EV Trends')) ?></span>
              <h3><a href="<?= e(base_url("blog/{$featuredPost['slug']}")) ?>"><?= e($featuredPost['title']) ?></a></h3>
              <p><?= e($featuredPost['excerpt']) ?></p>
              <div class="bl-meta">
                <img src="<?= e(img_url($featuredPost['cover_image'] ?? null)) ?>" alt="" width="32" height="32" style="border-radius:50%;object-fit:cover">
                <span><?= e($featuredPost['author']) ?></span>
                <span>· <?= e(date('M j, Y', strtotime($featuredPost['published_at']))) ?></span>
                <span>·</span>
                <span><?= e($featuredPost['read_minutes'] ?? 4) ?> min read</span>
              </div>
              <div class="bl-tags">
                <?php
                $tags = array_filter(array_map('trim', explode(',', str_replace('#', ',', $featuredPost['tags'] ?? ''))));
                foreach (array_slice($tags, 0, 4) as $tagItem): ?>
                  <span><?= e($tagItem) ?></span>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="bl-feat__media">
              <img src="<?= e(img_url($featuredPost['cover_image'] ?? null)) ?>" alt="<?= e($featuredPost['title']) ?>" loading="lazy">
            </div>
          </article>
          <?php if ($secondaryPost): ?>
          <div class="bl-feat__side">
            <article class="bl-side-card">
              <img src="<?= e(img_url($secondaryPost['cover_image'] ?? null)) ?>" alt="" loading="lazy">
              <div class="bl-side-card__body">
                <h4><a href="<?= e(base_url("blog/{$secondaryPost['slug']}")) ?>"><?= e($secondaryPost['title']) ?></a></h4>
                <p><?= e($secondaryPost['excerpt']) ?></p>
                <div class="bl-meta"><span><?= e(date('M j, Y', strtotime($secondaryPost['published_at']))) ?></span></div>
              </div>
            </article>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Category/Tag Filter Pills -->
        <?php if ($category || $tag): ?>
        <div class="bl-filter-pills" style="margin: 16px 0;">
          <?php if ($category): ?>
            <span class="bl-filter-pill">
              Category: <?= e(ucfirst($category)) ?>
              <a href="<?= e(base_url('blog')) ?>" style="margin-left:8px">×</a>
            </span>
          <?php endif; ?>
          <?php if ($tag): ?>
            <span class="bl-filter-pill">
              Tag: <?= e($tag) ?>
              <a href="<?= e(base_url('blog')) ?>" style="margin-left:8px">×</a>
            </span>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Breaking news / Main post grid -->
        <div class="bl-break">
          <div class="bl-break__head"><i data-lucide="hash"></i> Articles</div>

          <?php if (empty($posts)): ?>
            <div class="bl-empty">
              <i data-lucide="file-text" style="width:48px;height:48px;color:var(--ink-soft-0)"></i>
              <h3>No articles found</h3>
              <p>No blog posts match the current filter.</p>
              <a class="btn btn--ink" href="<?= e(base_url('blog')) ?>">View all articles</a>
            </div>
          <?php else: ?>
            <?php foreach ($posts as $post): ?>
              <?php if ($featuredPost && $post['id'] === $featuredPost['id']) continue; ?>
              <?php if ($secondaryPost && $post['id'] === $secondaryPost['id']) continue; ?>
              <article class="bl-post">
                <div class="bl-post__img"><img src="<?= e(img_url($post['cover_image'] ?? null)) ?>" alt="" loading="lazy"></div>
                <div class="bl-post__body">
                  <span class="bl-cat"><?= e(ucfirst($post['category'] ?? 'EV Trends')) ?></span>
                  <h3><a href="<?= e(base_url("blog/{$post['slug']}")) ?>"><?= e($post['title']) ?></a></h3>
                  <p><?= e($post['excerpt']) ?></p>
                  <div class="bl-meta">
                    <img src="<?= e(img_url($post['cover_image'] ?? null)) ?>" alt="" width="28" height="28" style="border-radius:50%;object-fit:cover">
                    <span><?= e($post['author']) ?></span>
                    <span>· <?= e(date('M j, Y', strtotime($post['published_at']))) ?></span>
                    <span>·</span>
                    <span><?= e($post['read_minutes'] ?? 4) ?> min read</span>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav class="bl-pagination" aria-label="Blog pagination">
          <?php if ($page > 1): ?>
            <a class="btn btn--ghost" href="<?= e(base_url("blog?page=" . ($page - 1) . ($category ? "&category={$category}" : '') . ($tag ? "&tag={$tag}" : ''))) ?>">
              <i data-lucide="chevron-left"></i> Previous
            </a>
          <?php endif; ?>
          <span class="bl-page-info">Page <?= $page ?> of <?= $totalPages ?></span>
          <?php if ($page < $totalPages): ?>
            <a class="btn btn--ink" href="<?= e(base_url("blog?page=" . ($page + 1) . ($category ? "&category={$category}" : '') . ($tag ? "&tag={$tag}" : ''))) ?>">
              Next <i data-lucide="chevron-right"></i>
            </a>
          <?php endif; ?>
        </nav>
        <?php endif; ?>
      </div>

      <!-- SIDEBAR -->
      <aside class="bl-side">
        <div class="bl-widget">
          <h3 class="bl-widget__title">Flash news</h3>
          <div class="bl-flash">
            <?php foreach ($flashNews as $news): ?>
              <a class="bl-flash__row" href="<?= e(base_url("news/{$news['slug']}")) ?>">
                <div>
                  <h4><?= e($news['title']) ?></h4>
                  <time><?= e(date('M j, Y', strtotime($news['published_at']))) ?></time>
                </div>
                <?php if ($news['cover_image']): ?>
                  <img src="<?= e(base_url($news['cover_image'])) ?>" alt="" width="80" height="60" style="object-fit:cover;border-radius:6px">
                <?php endif; ?>
              </a>
            <?php endforeach; ?>
            <?php if (empty($flashNews)): ?>
              <p style="color: var(--ink-soft-0); font-size: 13px;">No news items available.</p>
            <?php endif; ?>
          </div>
        </div>

        <div class="bl-widget">
          <h3 class="bl-widget__title">Follow us</h3>
          <div class="bl-socials">
            <a href="https://facebook.com/hazraev" target="_blank" rel="noopener"><i data-lucide="facebook"></i> Facebook</a>
            <a href="https://instagram.com/hazraev" target="_blank" rel="noopener"><i data-lucide="instagram"></i> Instagram</a>
            <a href="https://wa.me/919830012345" target="_blank" rel="noopener"><i data-lucide="message-circle"></i> WhatsApp</a>
            <a href="https://maps.app.goo.gl/hazraev" target="_blank" rel="noopener"><i data-lucide="map-pin"></i> Locate Us</a>
          </div>
        </div>

        <div class="bl-widget">
          <h3 class="bl-widget__title">Trending topics</h3>
          <div class="bl-topics">
            <?php foreach ($categories as $cat): ?>
              <a class="bl-topic" href="<?= e(base_url("blog/category/{$cat['category']}")) ?>">
                <div>
                  <b><?= e(ucfirst($cat['category'])) ?></b>
                  <span><?= (int)$cat['cnt'] ?> articles</span>
                </div>
              </a>
            <?php endforeach; ?>
            <?php if (empty($categories)): ?>
              <p style="color: var(--ink-soft-0); font-size: 13px;">No categories yet.</p>
            <?php endif; ?>
          </div>
        </div>

        <div class="bl-widget" id="newsletter">
          <h3 class="bl-widget__title">Subscribe to our Journal</h3>
          <p style="font-size:13px;color:var(--ink-soft-0);margin-bottom:12px">Weekly insights on electric mobility, rider stories, and product updates. No spam.</p>
          <form id="newsletterForm" style="display:flex;gap:8px;flex-direction:column">
            <input type="email" name="email" placeholder="Your email" required style="padding:10px 12px;border:1px solid var(--line);border-radius:8px;font:inherit">
            <button type="submit" class="btn btn--ink" style="width:100%"><span>Subscribe</span></button>
          </form>
          <p id="newsletterMsg" style="font-size:12px;margin-top:8px;display:none"></p>
        </div>
      </aside>
    </div>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', async () => {
  if (window.lucide) lucide.createIcons();

  // Newsletter form
  const form = document.getElementById('newsletterForm');
  const msg = document.getElementById('newsletterMsg');
  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const email = form.querySelector('input[name="email"]').value.trim();
      if (!email) return;
      try {
        const res = await fetch('<?= e(base_url('api/v1/website/leads')) ?>', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email, type: 'newsletter', source: 'blog' })
        });
        const data = await res.json();
        if (res.ok && data.ok) {
          msg.textContent = 'Thanks for subscribing!';
          msg.style.color = 'var(--success)';
          form.reset();
        } else {
          msg.textContent = data.error || 'Something went wrong. Please try again.';
          msg.style.color = 'var(--danger)';
        }
        msg.style.display = 'block';
      } catch (err) {
        msg.textContent = 'Network error. Please try again.';
        msg.style.color = 'var(--danger)';
        msg.style.display = 'block';
      }
    });
  }
});
</script>
<!-- ========== UNIQUE PAGE CONTENT END ========== -->

<?php App::render('footer'); ?>