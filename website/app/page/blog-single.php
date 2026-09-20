<?php
require_once __DIR__ . '/../../core/SafeHtml.php';

$slug = isset($_GET['slug']) ? (string)$_GET['slug'] : (isset($_GET['id']) ? (string)$_GET['id'] : '');

$post = null;
if ($slug) {
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $slug)) {
        $post = db_fetch_one("SELECT * FROM posts WHERE id = ? AND type = 'blog'", [$slug]);
    } else {
        $post = db_fetch_one("SELECT * FROM posts WHERE slug = ? AND type = 'blog'", [$slug]);
    }
}

if (!$post) {
    http_response_code(404);
    require_once __DIR__ . '/../../core/RouteManager.php';
    load_view('resources/views/404.php');
    exit;
}

if ($post['status'] !== 'published' && (empty($_SESSION['admin_logged_in']) || ($_SESSION['user_role'] ?? '') !== 'admin')) {
    http_response_code(404);
    load_view('resources/views/404.php');
    exit;
}

$canonicalUrl = base_url("blog/{$post['slug']}");
$ogImage = img_url($post['cover_image'] ?? null);
$metaTitle = $post['meta_title'] ?: $post['title'] . ' — Hazra EV Blog';
$metaDescription = $post['meta_description'] ?: $post['excerpt'];

$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'BlogPosting',
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

$relatedPosts = db_fetch_all(
    "SELECT * FROM posts WHERE type = 'blog' AND status = 'published' AND id != ? ORDER BY published_at DESC LIMIT 3",
    [$post['id']]
);

$contentHtml = SafeHtml::clean($post['content'] ?? '');

$pageTitle = $metaTitle;
$pageDescription = $metaDescription;

App::render('head', [
    'pageTitle'       => $pageTitle,
    'pageDescription' => $pageDescription,
    'extraCss'        => ['assets/css/styles/pages/blog-single.css'],
    'canonicalUrl'    => $canonicalUrl,
    'ogImage'         => $ogImage,
    'ogType'          => 'article',
    'jsonLd'          => $jsonLd,
]);

App::render('header', ['isStickyOnly' => true]);
?>

<!-- ========== UNIQUE PAGE CONTENT START ========== -->
<section class="hero">
  <p class="eyebrow" style="margin-bottom:12px">Blog · <?= e(ucfirst($post['category'] ?? 'EV Trends')) ?></p>
  <h1 class="hero__title" style="font-size:clamp(28px,5vw,48px)"><?= e($post['title']) ?></h1>
  <p class="hero__lead"><?= e($post['excerpt']) ?></p>
</section>

<section class="post">
  <div class="post__layout">

    <!-- LEFT RAIL -->
    <aside class="rail" aria-label="Share and contents">
      <div>
        <div class="rail__label">Share</div>
        <div class="share">
          <a class="share__btn" href="https://twitter.com/intent/tweet?url=<?= urlencode($canonicalUrl) ?>&text=<?= urlencode($post['title']) ?>" target="_blank" rel="noopener"><i data-lucide="twitter"></i> Twitter / X</a>
          <a class="share__btn" href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($canonicalUrl) ?>" target="_blank" rel="noopener"><i data-lucide="linkedin"></i> LinkedIn</a>
          <a class="share__btn" href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($canonicalUrl) ?>" target="_blank" rel="noopener"><i data-lucide="facebook"></i> Facebook</a>
          <a class="share__btn" href="https://api.whatsapp.com/send?text=<?= urlencode($post['title'] . ' ' . $canonicalUrl) ?>" target="_blank" rel="noopener"><i data-lucide="message-circle"></i> WhatsApp</a>
          <button class="share__btn" onclick="copyLink('<?= e($canonicalUrl) ?>')"><i data-lucide="link"></i> Copy link</button>
        </div>
      </div>
      <div class="toc">
        <div class="rail__label">On this page</div>
        <?php
        preg_match_all('/<h[2-4][^>]*id="([^"]+)"[^>]*>([^<]+)<\/h[2-4]>/i', $contentHtml, $matches);
        if (!empty($matches[1])): ?>
          <?php foreach ($matches[1] as $i => $id): ?>
            <a href="#<?= e($id) ?>" class="<?= $i === 0 ? 'is-active' : '' ?>"><?= e($matches[2][$i]) ?></a>
          <?php endforeach; ?>
        <?php else: ?>
          <a href="#" class="is-active">Article content</a>
        <?php endif; ?>
      </div>
    </aside>

    <!-- MAIN ARTICLE -->
    <article class="article">
      <div class="article__hero">
        <img src="<?= e(img_url($post['cover_image'] ?? null)) ?>" alt="<?= e($post['title']) ?>">
        <div class="article__hero-shade"></div>
      </div>
      <div class="article__body">
        <div class="article__meta">
          <span class="article__cat"><?= e(ucfirst($post['category'] ?? 'EV Trends')) ?></span>
          <span><?= e(date('d M Y', strtotime($post['published_at']))) ?></span>
          <span>·</span>
          <span><?= e($post['read_minutes'] ?? 4) ?> min read</span>
        </div>
        <h1 class="article__title"><?= e($post['title']) ?></h1>
        <p class="article__lead"><?= e($post['excerpt']) ?></p>
        <div class="article__author">
          <img src="<?= e(img_url($post['cover_image'] ?? null)) ?>" alt="<?= e($post['author']) ?>">
          <div>
            <strong><?= e($post['author']) ?></strong>
            <span>Hazra EV Journal</span>
          </div>
        </div>

        <div class="prose">
          <?= $contentHtml ?>
        </div>

        <?php
        $tags = array_filter(array_map('trim', explode(',', str_replace('#', '', $post['tags'] ?? ''))));
        if (!empty($tags)):
        ?>
        <div class="article__tags">
          <?php foreach ($tags as $tag): ?>
            <a href="<?= e(base_url("blog?tag=" . urlencode(ltrim($tag, '#')))) ?>" class="article__tag">#<?= e(ltrim($tag, '#')) ?></a>
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
          <?php foreach ($relatedPosts as $rel): ?>
            <a href="<?= e(base_url("blog/{$rel['slug']}")) ?>" class="related__item">
              <img src="<?= e(img_url($rel['cover_image'] ?? null)) ?>" alt="">
              <div>
                <strong><?= e($rel['title']) ?></strong>
                <span><?= e(ucfirst($rel['category'] ?? 'EV Trends')) ?> · <?= e($rel['read_minutes'] ?? 4) ?> min</span>
              </div>
            </a>
          <?php endforeach; ?>
          <?php if (empty($relatedPosts)): ?>
            <p style="color:var(--ink-soft-0);font-size:13px">No related posts yet.</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="aside__card newsletter">
        <h4>Stay in the loop</h4>
        <p>Weekly insights on electric mobility, rider stories, and product updates. No spam.</p>
        <form id="newsletterForm" style="display:flex;gap:8px;flex-direction:column">
          <input type="email" name="email" placeholder="Your email" required style="padding:10px 12px;border:1px solid var(--line);border-radius:8px;font:inherit">
          <button type="submit" class="btn btn--ink" style="width:100%"><span>Subscribe</span></button>
        </form>
        <p id="newsletterMsg" style="font-size:12px;margin-top:8px;display:none"></p>
      </div>
    </aside>

  </div>
</section>

<section class="footer" style="padding: 48px 0; text-align: center;">
  <p style="color: var(--ink-soft-0); margin-bottom: 24px;">
    More stories from the Hazra community
  </p>
  <a class="footer__link" href="<?= e(base_url('blog')) ?>">
    <span>Back to Blog</span>
    <i data-lucide="arrow-right" style="width:16px;height:16px"></i>
  </a>
</section>

<script>
document.addEventListener('DOMContentLoaded', async () => {
  if (window.lucide) lucide.createIcons();

  // Table of contents highlight
  const tocLinks = document.querySelectorAll('.toc a');
  const headings = document.querySelectorAll('.prose h2[id], .prose h3[id], .prose h4[id]');
  if (tocLinks.length && headings.length) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          tocLinks.forEach(l => l.classList.remove('is-active'));
          const active = document.querySelector('.toc a[href="#' + entry.target.id + '"]');
          if (active) active.classList.add('is-active');
        }
      });
    }, { rootMargin: '-100px 0px -66%' });
    headings.forEach(h => observer.observe(h));
  }

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
          body: JSON.stringify({ email, type: 'newsletter', source: 'blog-single' })
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

  function copyLink(url) {
    navigator.clipboard.writeText(url).then(() => {
      const btn = event.target.closest('button');
      const original = btn.innerHTML;
      btn.innerHTML = '<i data-lucide="check"></i> Copied!';
      if (window.lucide) lucide.createIcons();
      setTimeout(() => { btn.innerHTML = original; if (window.lucide) lucide.createIcons(); }, 2000);
    });
  }
  window.copyLink = copyLink;
});
</script>
<!-- ========== UNIQUE PAGE CONTENT END ========== -->

<?php App::render('footer'); ?>