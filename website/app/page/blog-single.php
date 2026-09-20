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

// Fallback: If no slug/id provided (e.g. clicking direct /blog-single link), load the latest published blog post
if (!$post && $slug === '') {
    $post = db_fetch_one("SELECT * FROM posts WHERE type = 'blog' AND status = 'published' ORDER BY is_featured DESC, published_at DESC LIMIT 1");
    if (!$post) {
        $post = db_fetch_one("SELECT * FROM posts WHERE status = 'published' ORDER BY published_at DESC LIMIT 1");
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
    $pageTitle = 'Article Not Found — Hazra EV';
    $pageDescription = 'The requested blog article could not be found.';
    App::render('head', [
        'pageTitle'       => $pageTitle,
        'pageDescription' => $pageDescription,
        'extraCss'        => ['assets/css/styles/pages/blog-single.css'],
    ]);
    App::render('header', ['isStickyOnly' => true]);
    ?>
    <section class="hero" style="min-height:55vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:80px var(--gutter);text-align:center;">
      <p class="eyebrow"><i class="sq"></i> 404 NOT FOUND</p>
      <h1 class="hero__title" style="margin-top:12px;">Article Not Found</h1>
      <p class="hero__lead">The blog article you are looking for may have been moved, renamed, or is currently unpublished.</p>
      <div style="margin-top:28px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
        <a class="btn btn--ink" href="<?= e(base_url('blog')) ?>"><span>Browse All Articles</span></a>
        <a class="btn btn--ghost" href="<?= e(base_url()) ?>">Back to Homepage</a>
      </div>
    </section>
    <?php
    App::render('footer');
    exit;
}

$canonicalUrl = base_url("blog/{$post['slug']}");
$coverUrl = img_url($post['cover_image'] ?? null);
$ogImage = $coverUrl;
$metaTitle = $post['meta_title'] ?: $post['title'] . ' — Hazra EV Blog';
$metaDescription = $post['meta_description'] ?: ($post['excerpt'] ?? '');
$authorName = $post['author'] ?: 'Hazra EV Team';
$publishedDate = !empty($post['published_at']) ? strtotime($post['published_at']) : (!empty($post['created_at']) ? strtotime($post['created_at']) : time());

$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'BlogPosting',
    'headline' => $post['title'],
    'description' => $post['excerpt'] ?? '',
    'image' => [$ogImage],
    'datePublished' => date('c', $publishedDate),
    'dateModified' => date('c', strtotime($post['updated_at'] ?? $post['published_at'] ?? 'now')),
    'author' => [
        '@type' => 'Person',
        'name' => $authorName
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

// Auto-inject IDs into h2, h3, h4 headings if missing, so Table of Contents jumps cleanly
$contentHtml = preg_replace_callback('/<(h[2-4])([^>]*)>(.*?)<\/\1>/i', function ($m) {
    $tag = $m[1];
    $attrs = $m[2];
    $title = $m[3];
    if (!preg_match('/\bid\s*=/i', $attrs)) {
        $cleanId = preg_replace('/[^a-z0-9]+/i', '-', trim(strip_tags($title)));
        $cleanId = strtolower(trim($cleanId, '-')) ?: 'section-' . substr(md5($title), 0, 6);
        $attrs .= ' id="' . htmlspecialchars($cleanId) . '"';
    }
    return "<{$tag}{$attrs}>{$title}</{$tag}>";
}, $contentHtml);

if (empty(trim(strip_tags($contentHtml))) && !empty($post['excerpt'])) {
    $contentHtml = '<p style="font-size:1.1em;line-height:1.8;">' . nl2br(e($post['excerpt'])) . '</p>';
}

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
<section class="post">
  <!-- Breadcrumb -->
  <div class="article-breadcrumb">
    <a href="<?= e(base_url()) ?>">Home</a>
    <span class="sep">/</span>
    <a href="<?= e(base_url('blog')) ?>">Blog</a>
    <span class="sep">/</span>
    <span class="curr"><?= e(ucfirst($post['category'] ?? 'EV Trends')) ?></span>
  </div>

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
        preg_match_all('/<h([2-4])[^>]*id="([^"]+)"[^>]*>(.*?)<\/h\1>/i', $contentHtml, $matches);
        if (!empty($matches[2])): ?>
          <?php foreach ($matches[2] as $i => $id): ?>
            <a href="#<?= e($id) ?>" class="<?= $i === 0 ? 'is-active' : '' ?>"><?= e(strip_tags($matches[3][$i])) ?></a>
          <?php endforeach; ?>
        <?php else: ?>
          <a href="#" class="is-active">Article overview</a>
        <?php endif; ?>
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
          <span class="article__cat"><?= e(ucfirst($post['category'] ?? 'EV Trends')) ?></span>
          <span><?= e(date('d M Y', $publishedDate)) ?></span>
          <span>·</span>
          <span><?= e($post['read_minutes'] ?? 4) ?> min read</span>
        </div>
        <h1 class="article__title"><?= e($post['title']) ?></h1>
        <?php if (!empty($post['excerpt'])): ?>
          <p class="article__lead"><?= e($post['excerpt']) ?></p>
        <?php endif; ?>
        <div class="article__author">
          <div class="article__avatar" style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--brand-violet),var(--brand-flame));display:grid;place-items:center;color:#fff;font-weight:700;font-size:15px;flex-shrink:0;">
            <?= e(strtoupper(substr($authorName, 0, 2))) ?>
          </div>
          <div>
            <strong><?= e($authorName) ?></strong>
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
              <img src="<?= e(img_url($rel['cover_image'] ?? null)) ?>" alt="<?= e($rel['title']) ?>" onerror="this.onerror=null;this.src='<?= e(base_url('assets/hazraev.png')) ?>';">
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
      const btn = (typeof event !== 'undefined' && event && event.target) ? event.target.closest('button') : document.querySelector('button[onclick*="copyLink"]');
      if (!btn) return;
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