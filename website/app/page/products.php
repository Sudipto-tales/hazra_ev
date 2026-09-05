<?php
$pageTitle = "All Electric Scooters & Bikes | Hazra Electrical Bike";
$pageDescription = "Explore the complete lineup of Hazra EV electric scooters and bikes. Premium performance, long range, and eco-friendly Indian riding.";

// Fetch active products from DB
$products = [];
try {
    if (function_exists('db_fetch_all')) {
        $rows = db_fetch_all("SELECT * FROM products WHERE active = 1 ORDER BY updated_at DESC");
        if ($rows) {
            $ids = array_column($rows, 'id');
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $colors = db_fetch_all("SELECT * FROM product_colors WHERE product_id IN ({$ph}) ORDER BY position, name", $ids);
            
            $colorImages = [];
            if ($colors) {
                $cids = array_column($colors, 'id');
                $cph = implode(',', array_fill(0, count($cids), '?'));
                foreach (db_fetch_all("SELECT * FROM product_color_images WHERE color_id IN ({$cph}) ORDER BY position", $cids) as $img) {
                    $colorImages[$img['color_id']][] = $img['url'];
                }
            }

            $byProd = [];
            foreach ($colors as $c) {
                $c['images'] = $colorImages[$c['id']] ?? [];
                $byProd[$c['product_id']][] = $c;
            }

            foreach ($rows as $r) {
                $r['highlights'] = json_decode($r['highlights'] ?? '[]', true) ?? [];
                $r['colors'] = $byProd[$r['id']] ?? [];
                $products[] = $r;
            }
        }
    }
} catch (Throwable $e) {
    error_log("Error loading products: " . $e->getMessage());
}

include __BASEDIR__ . '/app/components/head.php';
include __BASEDIR__ . '/app/components/header.php';
?>

<main class="page-body">
  <section class="hero-page" style="padding: clamp(60px, 8vw, 100px) 26px 40px; text-align: center; background: radial-gradient(circle at 50% 0%, rgb(var(--chip-rgb) / .6), transparent 70%);">
    <div style="width: min(900px, 93vw); margin: 0 auto;">
      <span style="font-size: 11px; font-weight: 800; letter-spacing: .2em; color: var(--accent); text-transform: uppercase;">HAZRA EV CATALOGUE</span>
      <h1 style="font-family: 'Montserrat', sans-serif; font-size: clamp(32px, 4.5vw, 54px); font-weight: 900; margin: 12px 0 16px; line-height: 1.1; color: var(--ink-0);">Our Electric Lineup</h1>
      <p style="font-size: clamp(14px, 1.6vw, 17px); color: var(--ink-soft-0); max-width: 600px; margin: 0 auto 30px; line-height: 1.6;">
        Built engineered for Indian roads. High speed, long range, zero emissions.
      </p>

      <!-- Category Filter Tabs -->
      <div class="filter-tabs" style="display: inline-flex; gap: 8px; padding: 6px; background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 999px; box-shadow: 0 10px 25px -10px rgba(0,0,0,.1);">
        <button class="tab-btn active" onclick="filterCategory('all', this)" style="padding: 8px 20px; border-radius: 999px; border: 0; font-size: 13px; font-weight: 600; cursor: pointer; background: var(--ink-0); color: var(--surface-0); transition: all .25s;">All Models</button>
        <button class="tab-btn" onclick="filterCategory('scooty', this)" style="padding: 8px 20px; border-radius: 999px; border: 0; font-size: 13px; font-weight: 600; cursor: pointer; background: transparent; color: var(--ink-soft-0); transition: all .25s;">Scooters</button>
        <button class="tab-btn" onclick="filterCategory('bike', this)" style="padding: 8px 20px; border-radius: 999px; border: 0; font-size: 13px; font-weight: 600; cursor: pointer; background: transparent; color: var(--ink-soft-0); transition: all .25s;">Motorcycles</button>
        <button class="tab-btn" onclick="filterCategory('bicycle', this)" style="padding: 8px 20px; border-radius: 999px; border: 0; font-size: 13px; font-weight: 600; cursor: pointer; background: transparent; color: var(--ink-soft-0); transition: all .25s;">E-Bicycles</button>
      </div>
    </div>
  </section>

  <section class="catalog-grid" style="padding: 20px 26px clamp(60px, 9vw, 120px);">
    <div style="width: min(1280px, 93vw); margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 32px;">
      <?php if (empty($products)): ?>
        <div style="grid-column: 1/-1; text-align: center; padding: 60px 20px;">
          <i data-lucide="package-search" style="width: 48px; height: 48px; color: var(--ink-soft-0); margin-bottom: 16px;"></i>
          <h3 style="font-size: 20px; font-weight: 700; color: var(--ink-0);">No models found</h3>
          <p style="color: var(--ink-soft-0);">Check back soon for new EV additions.</p>
        </div>
      <?php endif; ?>

      <?php foreach ($products as $p): ?>
        <?php
          $firstColor = $p['colors'][0] ?? null;
          $firstImg = ($firstColor && !empty($firstColor['images'])) ? $firstColor['images'][0] : base_url('assets/scutie_light.png');
        ?>
        <div class="prod-card" data-category="<?= htmlspecialchars($p['category']) ?>" style="background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 24px; padding: 24px; display: flex; flex-direction: column; transition: transform .35s var(--ease), box-shadow .35s var(--ease); position: relative; overflow: hidden;">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
            <span style="font-size: 11px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--brand-violet); background: rgb(var(--chip-rgb)); padding: 4px 10px; border-radius: 999px;">
              <?= htmlspecialchars(strtoupper($p['category'])) ?>
            </span>
            <span style="font-size: 12px; font-weight: 700; color: var(--ink-soft-0); display: flex; align-items: center; gap: 4px;">
              <i data-lucide="star" style="width: 14px; height: 14px; fill: #f7941d; color: #f7941d;"></i>
              <?= number_format((float)$p['rating'], 1) ?>
            </span>
          </div>

          <h2 style="font-family: 'Montserrat', sans-serif; font-size: 22px; font-weight: 800; color: var(--ink-0); margin-bottom: 4px;">
            <?= htmlspecialchars($p['name']) ?>
          </h2>
          <span style="font-size: 12px; color: var(--ink-soft-0); font-weight: 600; margin-bottom: 16px;">
            Code: <?= htmlspecialchars($p['model_code']) ?>
          </span>

          <!-- Product Image Container -->
          <div style="position: relative; width: 100%; height: 220px; border-radius: 16px; background: rgb(var(--chip-rgb) / .4); display: grid; place-items: center; overflow: hidden; margin-bottom: 20px;">
            <img id="img-<?= $p['id'] ?>" src="<?= htmlspecialchars($firstImg) ?>" alt="<?= htmlspecialchars($p['name']) ?>" style="max-width: 90%; max-height: 85%; object-fit: contain; transition: transform .4s var(--ease);">
          </div>

          <!-- Color Selector Swatches -->
          <?php if (!empty($p['colors'])): ?>
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px;">
              <span style="font-size: 11px; font-weight: 700; color: var(--ink-soft-0); margin-right: 4px;">Colors:</span>
              <?php foreach ($p['colors'] as $idx => $col): ?>
                <?php
                  $hex = '#' . substr(dechex($col['argb'] & 0xFFFFFF), -6);
                  $colImg = !empty($col['images']) ? $col['images'][0] : $firstImg;
                ?>
                <button title="<?= htmlspecialchars($col['name']) ?>" 
                        onclick="switchColor('<?= $p['id'] ?>', '<?= htmlspecialchars($colImg) ?>', this)"
                        style="width: 22px; height: 22px; border-radius: 50%; background: <?= $hex ?>; border: 2px solid <?= $idx === 0 ? 'var(--ink-0)' : 'transparent' ?>; cursor: pointer; transition: transform .2s; padding: 0;">
                </button>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <!-- Specs Pill Bar -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 14px; background: rgb(var(--chip-rgb) / .4); border-radius: 14px; margin-bottom: 24px;">
            <div style="display: flex; align-items: center; gap: 8px;">
              <i data-lucide="zap" style="width: 16px; height: 16px; color: var(--brand-flame);"></i>
              <div>
                <div style="font-size: 10px; font-weight: 700; color: var(--ink-soft-0);">RANGE</div>
                <div style="font-size: 13px; font-weight: 800; color: var(--ink-0);"><?= (int)$p['range_km'] ?> km</div>
              </div>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
              <i data-lucide="gauge" style="width: 16px; height: 16px; color: var(--brand-blue);"></i>
              <div>
                <div style="font-size: 10px; font-weight: 700; color: var(--ink-soft-0);">TOP SPEED</div>
                <div style="font-size: 13px; font-weight: 800; color: var(--ink-0);"><?= (int)$p['top_speed_kmph'] ?> km/h</div>
              </div>
            </div>
          </div>

          <div style="margin-top: auto; display: flex; gap: 10px;">
            <a href="<?= base_url('product-detail?id=' . urlencode($p['id'])) ?>" style="flex: 1; text-align: center; padding: 12px 16px; background: var(--ink-0); color: var(--surface-0); text-decoration: none; border-radius: 999px; font-size: 12.5px; font-weight: 700; transition: opacity .25s;">
              View Details
            </a>
            <a href="<?= base_url('dealership-enquiry') ?>" style="padding: 12px 16px; border: 1px solid var(--hair-0); color: var(--ink-0); text-decoration: none; border-radius: 999px; font-size: 12.5px; font-weight: 700; transition: background .25s;">
              Test Ride
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</main>

<script>
function filterCategory(cat, btn) {
  document.querySelectorAll('.tab-btn').forEach(b => {
    b.style.background = 'transparent';
    b.style.color = 'var(--ink-soft-0)';
  });
  btn.style.background = 'var(--ink-0)';
  btn.style.color = 'var(--surface-0)';

  document.querySelectorAll('.prod-card').forEach(card => {
    if (cat === 'all' || card.dataset.category === cat) {
      card.style.display = 'flex';
    } else {
      card.style.display = 'none';
    }
  });
}

function switchColor(prodId, imgUrl, btn) {
  const imgEl = document.getElementById('img-' + prodId);
  if (imgEl) {
    imgEl.style.opacity = '0';
    setTimeout(() => {
      imgEl.src = imgUrl;
      imgEl.style.opacity = '1';
    }, 200);
  }
  btn.parentElement.querySelectorAll('button').forEach(b => b.style.borderColor = 'transparent');
  btn.style.borderColor = 'var(--ink-0)';
}
</script>

<?php include __BASEDIR__ . '/app/components/footer.php'; ?>
