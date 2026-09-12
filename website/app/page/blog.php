<?php
App::render('head', [
    'pageTitle'       => 'Blog — Hazra Electrical Bike',
    'pageDescription' => 'Stories on electric mobility, battery care, city riding and the road ahead from Hazra Electrical Bike.',
    'extraCss'        => ['assets/css/styles/pages/blog.css'],
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
        <div class="bl-feat">
          <article class="bl-feat__main">
            <div class="bl-feat__copy">
              <span class="bl-cat">Lifestyle</span>
              <h3><a href="<?= e(base_url('blog-single')) ?>">Returning to the unnamed wild beyond the maps</a></h3>
              <p>There are places that refuse to be mapped — not because they’re far, but because they still belong to the rider and the road.</p>
              <div class="bl-meta">
                <img src="https://i.pravatar.cc/56?u=lora" alt="">
                <span>Lora</span>
                <span>· Jun 13, 2026</span>
              </div>
              <div class="bl-tags">
                <span>silence</span><span>noise</span><span>solitude</span><span>landscape</span>
              </div>
            </div>
            <div class="bl-feat__media">
              <img src="https://images.unsplash.com/photo-1474519811234-466cb63bf5d2?auto=format&fit=crop&w=800&q=80" alt="Featured" loading="lazy">
            </div>
          </article>
          <div class="bl-feat__side">
            <article class="bl-side-card">
              <img src="https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=600&q=80" alt="" loading="lazy">
              <div class="bl-side-card__body">
                <h4><a href="<?= e(base_url('blog-single')) ?>">When machines begin to dream of trees</a></h4>
                <p>A calm space for slow thoughts. No noise — just reflection.</p>
                <div class="bl-meta"><span>Jun 13, 2026</span></div>
              </div>
            </article>
          </div>
        </div>

        <!-- Breaking news -->
        <div class="bl-break">
          <div class="bl-break__head"><i data-lucide="hash"></i> Breaking News</div>

          <article class="bl-post">
            <div class="bl-post__img"><img src="https://images.unsplash.com/photo-1506905925346-21bda4d32df4?auto=format&fit=crop&w=400&q=80" alt="" loading="lazy"></div>
            <div class="bl-post__body">
              <span class="bl-cat">World</span>
              <h3><a href="<?= e(base_url('blog-single')) ?>">When nature stops waiting for us to return</a></h3>
              <p>There comes a time when the wild no longer looks for us. Forests learn to grow without footsteps…</p>
              <div class="bl-meta"><img src="https://i.pravatar.cc/48?u=tair" alt=""><span>Tair</span><span>· Jun 13, 2026</span></div>
            </div>
          </article>

          <article class="bl-post">
            <div class="bl-post__img"><img src="https://images.unsplash.com/photo-1493238792000-8113da705763?auto=format&fit=crop&w=400&q=80" alt="" loading="lazy"></div>
            <div class="bl-post__body">
              <span class="bl-cat">Entertainment</span>
              <h3><a href="<?= e(base_url('blog-single')) ?>">Beneath the soil, seeds whisper about another day</a></h3>
              <p>Even in stillness, life speaks softly. Hidden under the quiet earth, small voices promise a future.</p>
              <div class="bl-meta"><img src="https://i.pravatar.cc/48?u=sera" alt=""><span>Sera</span><span>· Jun 13, 2026</span></div>
            </div>
          </article>

          <article class="bl-post">
            <div class="bl-post__img"><img src="https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=400&q=80" alt="" loading="lazy"></div>
            <div class="bl-post__body">
              <span class="bl-cat">Fashion</span>
              <h3><a href="<?= e(base_url('blog-single')) ?>">Stories the stones keep to themselves</a></h3>
              <p>The earth remembers everything — our footsteps, our fires, our silence.</p>
              <div class="bl-meta"><img src="https://i.pravatar.cc/48?u=nira" alt=""><span>Nira</span><span>· Jun 13, 2026</span></div>
            </div>
          </article>

          <article class="bl-post">
            <div class="bl-post__img"><img src="https://images.unsplash.com/photo-1483728642387-6c3bdddeba27?auto=format&fit=crop&w=400&q=80" alt="" loading="lazy"></div>
            <div class="bl-post__body">
              <span class="bl-cat">Solitude</span>
              <h3><a href="<?= e(base_url('blog-single')) ?>">When the mountains breathe at first dawn</a></h3>
              <p>Morning finds its way through mist and memory. The mountains inhale light.</p>
              <div class="bl-meta"><img src="https://i.pravatar.cc/48?u=liar" alt=""><span>Liar</span><span>· Jun 13, 2026</span></div>
            </div>
          </article>

          <article class="bl-post">
            <div class="bl-post__img"><img src="https://images.unsplash.com/photo-1506126613408-eca07ce68773?auto=format&fit=crop&w=400&q=80" alt="" loading="lazy"></div>
            <div class="bl-post__body">
              <span class="bl-cat">Reflection</span>
              <h3><a href="<?= e(base_url('blog-single')) ?>">Listening to the earth between storms</a></h3>
              <p>There’s a calm voice in the pause before thunder. Sometimes, silence is the loudest form of understanding.</p>
              <div class="bl-meta"><img src="https://i.pravatar.cc/48?u=arin" alt=""><span>Arin</span><span>· Jun 13, 2026</span></div>
            </div>
          </article>

          <article class="bl-post">
            <div class="bl-post__img"><img src="https://images.unsplash.com/photo-1476480862126-209bfaa8edc8?auto=format&fit=crop&w=400&q=80" alt="" loading="lazy"></div>
            <div class="bl-post__body">
              <span class="bl-cat">Renewal</span>
              <h3><a href="<?= e(base_url('blog-single')) ?>">Beneath the soil, seeds whisper about another day</a></h3>
              <p>In the quiet dark, life hums beneath our forgetting. Tiny seeds dream of sunlight.</p>
              <div class="bl-meta"><img src="https://i.pravatar.cc/48?u=keen" alt=""><span>Keen</span><span>· Jun 13, 2026</span></div>
            </div>
          </article>
        </div>

        <div class="bl-more"><a href="#">Show more posts <i data-lucide="arrow-down"></i></a></div>
      </div>

      <!-- SIDEBAR -->
      <aside class="bl-side">
        <div class="bl-widget">
          <h3 class="bl-widget__title">Flash news</h3>
          <div class="bl-flash">
            <a class="bl-flash__row" href="<?= e(base_url('news-single')) ?>">
              <div>
                <h4>Time slowly fades where moss grows</h4>
                <time>Jun 13, 2026</time>
              </div>
              <img src="https://images.unsplash.com/photo-1506905925346-21bda4d32df4?auto=format&fit=crop&w=120&q=80" alt="">
            </a>
            <a class="bl-flash__row" href="<?= e(base_url('news-single')) ?>">
              <div>
                <h4>The weight of light in endless fields</h4>
                <time>Jun 13, 2026</time>
              </div>
              <img src="https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=120&q=80" alt="">
            </a>
            <a class="bl-flash__row" href="<?= e(base_url('news-single')) ?>">
              <div>
                <h4>What the fire gently leaves behind</h4>
                <time>Jun 13, 2026</time>
              </div>
              <img src="https://images.unsplash.com/photo-1558981403-c5f9899a28bc?auto=format&fit=crop&w=120&q=80" alt="">
            </a>
            <a class="bl-flash__row" href="<?= e(base_url('news-single')) ?>">
              <div>
                <h4>A meadow quietly built from memory</h4>
                <time>Jun 13, 2026</time>
              </div>
              <img src="https://images.unsplash.com/photo-1493238792000-8113da705763?auto=format&fit=crop&w=120&q=80" alt="">
            </a>
          </div>
        </div>

        <div class="bl-widget">
          <h3 class="bl-widget__title">Top authors</h3>
          <div class="bl-authors">
            <div class="bl-author">
              <span class="bl-author__n">1</span>
              <div style="display:flex;gap:10px;align-items:center">
                <img src="https://i.pravatar.cc/72?u=a1" alt="">
                <div><b>Laura Bennett</b><span>Elite author</span></div>
              </div>
              <button type="button">Follow</button>
            </div>
            <div class="bl-author">
              <span class="bl-author__n">2</span>
              <div style="display:flex;gap:10px;align-items:center">
                <img src="https://i.pravatar.cc/72?u=a2" alt="">
                <div><b>Robert Edition</b><span>Exclusive author</span></div>
              </div>
              <button type="button">Follow</button>
            </div>
            <div class="bl-author">
              <span class="bl-author__n">3</span>
              <div style="display:flex;gap:10px;align-items:center">
                <img src="https://i.pravatar.cc/72?u=a3" alt="">
                <div><b>Daniel Cross</b><span>Author favorite</span></div>
              </div>
              <button type="button">Follow</button>
            </div>
            <div class="bl-author">
              <span class="bl-author__n">4</span>
              <div style="display:flex;gap:10px;align-items:center">
                <img src="https://i.pravatar.cc/72?u=a4" alt="">
                <div><b>Sophia Turner</b><span>Author favorite</span></div>
              </div>
              <button type="button">Follow</button>
            </div>
          </div>
        </div>

        <div class="bl-widget">
          <h3 class="bl-widget__title">Follow us</h3>
          <div class="bl-socials">
            <a href="#"><i data-lucide="facebook"></i> Facebook <em>12k</em></a>
            <a href="#"><i data-lucide="twitter"></i> Twitter <em>12k</em></a>
            <a href="#"><i data-lucide="instagram"></i> Instagram <em>12k</em></a>
            <a href="#"><i data-lucide="youtube"></i> Youtube <em>12k</em></a>
          </div>
        </div>

        <div class="bl-widget">
          <h3 class="bl-widget__title">Trending topics</h3>
          <div class="bl-topics">
            <a class="bl-topic" href="#">
              <img src="https://images.unsplash.com/photo-1558981403-c5f9899a28bc?auto=format&fit=crop&w=120&q=80" alt="">
              <div><b>Range &amp; battery</b><span>24 articles</span></div>
            </a>
            <a class="bl-topic" href="#">
              <img src="https://images.unsplash.com/photo-1493238792000-8113da705763?auto=format&fit=crop&w=120&q=80" alt="">
              <div><b>City riding</b><span>18 articles</span></div>
            </a>
            <a class="bl-topic" href="#">
              <img src="https://images.unsplash.com/photo-1486262715619-67b85e0b08d3?auto=format&fit=crop&w=120&q=80" alt="">
              <div><b>Dealer stories</b><span>11 articles</span></div>
            </a>
          </div>
        </div>

        <div class="bl-ad">
          <span>Your banner here<br>310 × 220</span>
          <p>Partner with Hazra on the journal.</p>
          <a class="btn btn--ink" href="<?= e(base_url('contact')) ?>"><span>Contact</span><i data-lucide="arrow-right"></i></a>
        </div>
      </aside>
    </div>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', async () => {
  if (window.lucide) lucide.createIcons();
  try {
    const apiPath = <?= json_encode(base_url('api/v1/posts?status=published')) ?>;
    const res = await fetch(apiPath);
    if (!res.ok) return;
    const data = await res.json();
    const posts = data.data || [];
    const blogs = posts.filter(p => p.type === 'blog');
    const news = posts.filter(p => p.type === 'news');
    if (blogs.length > 0) {
      const feat = blogs[0];
      const mainTitle = document.querySelector('.bl-feat__main h3 a');
      const mainExcerpt = document.querySelector('.bl-feat__main p');
      const mainImg = document.querySelector('.bl-feat__media img');
      if (mainTitle && feat.title) {
        mainTitle.textContent = feat.title;
        mainTitle.href = <?= json_encode(base_url('blog-single?id=')) ?> + feat.id;
      }
      if (mainExcerpt && (feat.excerpt || feat.content)) mainExcerpt.textContent = feat.excerpt || feat.content.substring(0, 120) + '...';
      if (mainImg && feat.cover_image) mainImg.src = feat.cover_image;
    }
    if (news.length > 0) {
      const flashContainer = document.querySelector('.bl-flash');
      if (flashContainer) {
        flashContainer.innerHTML = news.map(n => `
          <a class="bl-flash__row" href="<?= e(base_url('news-single?id=')) ?>${n.id}">
            <div>
              <h4>${n.title}</h4>
              <time>${n.created_at ? n.created_at.substring(0,10) : ''}</time>
            </div>
            ${n.cover_image ? `<img src="${n.cover_image}" alt="">` : ''}
          </a>
        `).join('');
      }
    }
  } catch (e) {}
});
</script>
<!-- ========== UNIQUE PAGE CONTENT END ========== -->

<?php App::render('footer'); ?>
