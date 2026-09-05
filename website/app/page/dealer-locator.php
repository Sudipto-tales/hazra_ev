<?php
$pageTitle = "Locate Dealers | Hazra Electrical Bike";
$pageDescription = "Find an authorized Hazra EV dealership or service center near you.";

$dealers = [];
try {
    if (function_exists('db_fetch_all')) {
        $dealers = db_fetch_all("
            SELECT b.id, b.name AS branch_name, b.address, b.lat, b.lng, c.name AS company_name, c.category
            FROM branches b
            JOIN companies c ON c.id = b.company_id
            ORDER BY c.name, b.name
        ");
    }
} catch (Throwable $e) {}

// Fallback dealers if database yields none
if (empty($dealers)) {
    $dealers = [
        ['company_name' => 'Meghna Motors', 'branch_name' => 'Khosbagan Showroom', 'address' => 'Tilak Road, Khosbagan, Bardhaman', 'category' => 'Distributor'],
        ['company_name' => 'Meghna Motors', 'branch_name' => 'Curzon Gate Branch', 'address' => 'G.T. Road, Curzon Gate, Bardhaman', 'category' => 'Distributor'],
        ['company_name' => 'Padma Auto House', 'branch_name' => 'Golapbag Store', 'address' => 'Golapbag More, Bardhaman', 'category' => 'Retail'],
        ['company_name' => 'Turag Wheels', 'branch_name' => 'Nababhat Showroom', 'address' => 'Nababhat, G.T. Road, Bardhaman', 'category' => 'Retail'],
        ['company_name' => 'Jamuna EV Traders', 'branch_name' => 'B.C. Road Office', 'address' => 'Birhata, B.C. Road, Bardhaman', 'category' => 'Corporate']
    ];
}

include __BASEDIR__ . '/app/components/head.php';
include __BASEDIR__ . '/app/components/header.php';
?>

<main class="page-body">
  <section class="hero" style="text-align: center; padding: clamp(60px, 9vw, 120px) 26px; background: radial-gradient(circle at 50% 0%, rgb(var(--chip-rgb) / .6), transparent 70%);">
    <div style="width: min(800px, 93vw); margin: 0 auto;">
      <span style="font-size: 11px; font-weight: 800; letter-spacing: .2em; color: var(--accent); text-transform: uppercase;">AUTHORIZED NETWORK</span>
      <h1 class="hero__title" style="font-family: 'Montserrat', sans-serif; font-size: clamp(34px, 4.5vw, 64px); font-weight: 900; margin: 12px 0 20px; line-height: 1.1; color: var(--ink-0);">
        Find a Dealer Near You
      </h1>
      <p class="hero__lead" style="font-size: clamp(15px, 1.8vw, 18px); color: var(--ink-soft-0); line-height: 1.6;">
        Locate authorized Hazra EV showrooms and service centers for sales, test drives, and maintenance.
      </p>
    </div>
  </section>

  <section class="search" style="padding: 40px 26px clamp(60px, 9vw, 120px); background: var(--surface-0);">
    <div class="search__in" style="width: min(1200px, 93vw); margin: 0 auto;">
      <!-- Search Filter Box -->
      <div style="display: flex; gap: 16px; margin-bottom: 40px; flex-wrap: wrap; background: rgb(var(--chip-rgb) / .4); padding: 20px; border-radius: 20px; border: 1px solid var(--hair-0);">
        <div style="flex: 1; min-width: 240px;">
          <label style="display: block; font-size: 11px; font-weight: 700; color: var(--ink-soft-0); text-transform: uppercase; margin-bottom: 6px;">Filter by City / Name</label>
          <input type="text" id="dealerSearch" onkeyup="filterDealers()" placeholder="Search location or dealership..." style="width: 100%; padding: 12px 18px; background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 12px; font-family: inherit; color: var(--ink-0); font-size: 14px;">
        </div>
      </div>

      <!-- Dealers Grid -->
      <div class="dealers" id="dealersGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px;">
        <?php foreach ($dealers as $d): ?>
          <div class="dealer-card" style="padding: 28px; background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 22px; transition: transform .3s, box-shadow .3s; display: flex; flex-direction: column;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
              <span style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: var(--brand-violet); background: rgb(var(--chip-rgb)); padding: 4px 10px; border-radius: 999px;">
                <?= htmlspecialchars($d['category'] ?? 'DEALER') ?>
              </span>
              <i data-lucide="shield-check" style="width: 18px; height: 18px; color: #12a5e0;"></i>
            </div>

            <h3 style="font-family: 'Montserrat', sans-serif; font-size: 19px; font-weight: 800; color: var(--ink-0); margin-bottom: 4px;">
              <?= htmlspecialchars($d['company_name']) ?>
            </h3>
            <div style="font-size: 13px; font-weight: 700; color: var(--brand-orange); margin-bottom: 14px;">
              <?= htmlspecialchars($d['branch_name']) ?>
            </div>

            <div style="font-size: 13px; color: var(--ink-soft-0); line-height: 1.6; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 8px;">
              <i data-lucide="map-pin" style="width: 18px; height: 18px; color: var(--brand-flame); flex-shrink: 0; margin-top: 2px;"></i>
              <span><?= htmlspecialchars($d['address']) ?></span>
            </div>

            <div style="margin-top: auto; display: flex; gap: 10px;">
              <a href="<?= base_url('products') ?>" style="flex: 1; text-align: center; padding: 12px; background: var(--ink-0); color: var(--surface-0); text-decoration: none; border-radius: 999px; font-size: 12.5px; font-weight: 700; transition: opacity .25s;">
                Book Test Ride
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</main>

<script>
function filterDealers() {
  const query = document.getElementById('dealerSearch').value.toLowerCase();
  document.querySelectorAll('.dealer-card').forEach(card => {
    const text = card.innerText.toLowerCase();
    card.style.display = text.includes(query) ? 'flex' : 'none';
  });
}
</script>

<?php include __BASEDIR__ . '/app/components/footer.php'; ?>
