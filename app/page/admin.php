<?php
$pageTitle = "Admin Dashboard | Hazra Electrical Bike";
$pageDescription = "Hazra EV Website & Catalogue Administration Panel.";

include __BASEDIR__ . '/app/components/head.php';
?>

<style>
/* ---- Admin Shell Overrides ---- */
.admin-wrapper { display: flex; min-height: 100vh; background: var(--surface-0); color: var(--ink-0); }
.admin-sidebar  { width: 260px; background: rgb(var(--chip-rgb) / .5); border-right: 1px solid var(--hair-0); padding: 24px 16px; display: flex; flex-direction: column; flex-shrink: 0; overflow-y: auto; }
.admin-main    { flex: 1; padding: 36px clamp(16px,4vw,40px); overflow-y: auto; max-height: 100vh; }

/* Nav items */
.nav-section-label { font-size: 10px; font-weight: 800; letter-spacing: .1em; color: var(--ink-soft-0); text-transform: uppercase; padding: 14px 12px 4px; }
.nav-divider        { border: 0; border-top: 1px solid var(--hair-0); margin: 6px 0; }
.nav-tab {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 14px; border-radius: 10px; border: 0;
  background: transparent; color: var(--ink-soft-0);
  font-family: inherit; font-size: 13px; font-weight: 600;
  cursor: pointer; text-align: left; transition: all .15s; width: 100%;
}
.nav-tab:hover   { background: rgb(var(--chip-rgb)); color: var(--ink-0); }
.nav-tab.active  { background: var(--ink-0); color: var(--surface-0); font-weight: 700; }
.nav-tab svg     { width: 16px; height: 16px; flex-shrink: 0; }

/* Tab panels */
.tab-panel { display: none; }
.tab-panel.active { display: block; }

/* Page header */
.page-hd { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; }
.page-hd h1 { font-family: 'Montserrat', sans-serif; font-size: 24px; font-weight: 800; color: var(--ink-0); }
.page-hd p  { font-size: 13px; color: var(--ink-soft-0); margin-top: 3px; }

/* Stat cards */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(180px,1fr)); gap: 16px; margin-bottom: 28px; }
.stat-card  { padding: 20px 22px; background: rgb(var(--chip-rgb)/.4); border: 1px solid var(--hair-0); border-radius: 18px; }
.stat-card__label { font-size: 10px; font-weight: 800; text-transform: uppercase; color: var(--ink-soft-0); letter-spacing: .07em; }
.stat-card__value { font-family: 'Montserrat',sans-serif; font-size: 30px; font-weight: 900; color: var(--ink-0); margin-top: 4px; }

/* Data table */
.data-card { background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 18px; overflow: hidden; }
.data-table { width: 100%; border-collapse: collapse; text-align: left; }
.data-table thead tr { background: rgb(var(--chip-rgb)/.4); border-bottom: 1px solid var(--hair-0); }
.data-table th { padding: 12px 18px; font-size: 10.5px; font-weight: 800; text-transform: uppercase; color: var(--ink-soft-0); }
.data-table td { padding: 13px 18px; border-bottom: 1px solid var(--hair-0); font-size: 13px; color: var(--ink-0); }
.data-table tr:last-child td { border-bottom: 0; }
.data-table tr:hover td { background: rgb(var(--chip-rgb)/.25); }

/* Badges */
.badge { display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px; font-size: 10.5px; font-weight: 800; }
.badge--green  { background: #e6f4ea; color: #137333; }
.badge--amber  { background: #feefc3; color: #b06000; }
.badge--red    { background: #fce8e6; color: #c5221f; }
.badge--violet { background: #f3e8fd; color: #7b2ff7; }
.badge--blue   { background: #e8f0fe; color: #1a73e8; }
.badge--grey   { background: rgb(var(--chip-rgb)); color: var(--ink-soft-0); }

/* Form */
.form-card { max-width: 700px; background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 18px; padding: 28px; }
.form-row  { display: flex; flex-direction: column; gap: 4px; }
.form-row label { font-size: 10.5px; font-weight: 800; text-transform: uppercase; color: var(--ink-soft-0); letter-spacing: .06em; }
.form-input {
  width: 100%; padding: 11px 14px;
  background: rgb(var(--chip-rgb)/.35); border: 1px solid var(--hair-0);
  border-radius: 10px; font-family: inherit; font-size: 13px; color: var(--ink-0);
  transition: border-color .15s;
}
.form-input:focus { outline: none; border-color: var(--brand-violet); }
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; }
.section-sub { font-size: 11px; font-weight: 800; letter-spacing: .08em; color: var(--brand-violet); text-transform: uppercase; margin: 6px 0 2px; }

/* Buttons */
.btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border: 0; border-radius: 999px; font-family: inherit; font-size: 13px; font-weight: 700; cursor: pointer; transition: opacity .15s; }
.btn:hover { opacity: .85; }
.btn--primary  { background: var(--brand-grad); color: #fff; }
.btn--ghost    { background: rgb(var(--chip-rgb)); color: var(--ink-0); }
.btn--sm       { padding: 6px 13px; font-size: 12px; }
.btn--danger   { background: #fce8e6; color: #c5221f; }

/* Filters row */
.filters-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }
.filter-select { padding: 9px 14px; background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 10px; font-family: inherit; font-size: 13px; color: var(--ink-0); cursor: pointer; }

/* Modal backdrop */
.modal-overlay {
  display: none; position: fixed; inset: 0; z-index: 1000;
  background: rgba(0,0,0,.55); backdrop-filter: blur(6px);
  align-items: center; justify-content: center; padding: 20px;
}
.modal-overlay.open { display: flex; }
.modal-box {
  width: min(680px,95vw); max-height: 90vh; overflow-y: auto;
  background: var(--surface-0); border: 1px solid var(--hair-0);
  border-radius: 22px; padding: 28px;
}
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.modal-header h3 { font-family: 'Montserrat',sans-serif; font-size: 19px; font-weight: 800; color: var(--ink-0); }
.modal-close { border: 0; background: none; font-size: 22px; cursor: pointer; color: var(--ink-soft-0); line-height: 1; }
</style>

<div class="admin-wrapper">

  <!-- ===== SIDEBAR ===== -->
  <aside class="admin-sidebar">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:28px;">
      <img src="<?= base_url('assets/hazraev.png') ?>" alt="Hazra EV" style="width:32px;height:32px;object-fit:contain;">
      <div>
        <div style="font-family:'Montserrat',sans-serif;font-weight:800;font-size:13px;color:var(--ink-0);">Hazra EV</div>
        <div style="font-size:9.5px;font-weight:700;color:var(--brand-violet);letter-spacing:.08em;text-transform:uppercase;">Admin Console</div>
      </div>
    </div>

    <nav style="display:flex;flex-direction:column;gap:3px;flex:1;">

      <button class="nav-tab active" data-tab="dashboard" id="nav-dashboard">
        <i data-lucide="layout-dashboard"></i><span>Dashboard</span>
      </button>

      <hr class="nav-divider">
      <div class="nav-section-label">Catalogue</div>

      <button class="nav-tab" data-tab="products" id="nav-products">
        <i data-lucide="bike"></i><span>Products</span>
      </button>
      <button class="nav-tab" data-tab="blogs" id="nav-blogs">
        <i data-lucide="file-text"></i><span>Blogs</span>
      </button>
      <button class="nav-tab" data-tab="news" id="nav-news">
        <i data-lucide="newspaper"></i><span>News</span>
      </button>
      <button class="nav-tab" data-tab="gallery" id="nav-gallery">
        <i data-lucide="image"></i><span>Gallery</span>
      </button>

      <hr class="nav-divider">

      <button class="nav-tab" data-tab="jobs" id="nav-jobs">
        <i data-lucide="briefcase"></i><span>Jobs</span>
      </button>

      <hr class="nav-divider">

      <button class="nav-tab" data-tab="test-drive" id="nav-test-drive">
        <i data-lucide="zap"></i><span>Test Drive</span>
        <span id="badge-td" style="margin-left:auto;font-size:10px;background:var(--brand-flame);color:#fff;padding:2px 6px;border-radius:999px;display:none;">0</span>
      </button>
      <button class="nav-tab" data-tab="dealership" id="nav-dealership">
        <i data-lucide="store"></i><span>Dealership</span>
      </button>
      <button class="nav-tab" data-tab="contact" id="nav-contact">
        <i data-lucide="mail"></i><span>Contact</span>
      </button>
      <button class="nav-tab" data-tab="career-apps" id="nav-career-apps">
        <i data-lucide="users"></i><span>Career Apps</span>
        <span id="badge-apps" style="margin-left:auto;font-size:10px;background:var(--brand-violet);color:#fff;padding:2px 6px;border-radius:999px;display:none;">0</span>
      </button>

      <hr class="nav-divider">

      <button class="nav-tab" data-tab="settings" id="nav-settings">
        <i data-lucide="settings"></i><span>Settings</span>
      </button>

    </nav>

    <div style="padding-top:16px;border-top:1px solid var(--hair-0);margin-top:auto;">
      <a href="<?= base_url('') ?>" target="_blank" style="display:flex;align-items:center;gap:8px;font-size:12px;font-weight:600;color:var(--ink-soft-0);text-decoration:none;">
        <i data-lucide="external-link" style="width:15px;height:15px;"></i>
        View Live Site
      </a>
    </div>
  </aside>

  <!-- ===== MAIN ===== -->
  <main class="admin-main">

    <!-- Top Bar -->
    <header style="display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;">
      <div>
        <h1 id="page-title" style="font-family:'Montserrat',sans-serif;font-size:24px;font-weight:800;color:var(--ink-0);">Dashboard</h1>
        <p id="page-sub" style="font-size:13px;color:var(--ink-soft-0);margin-top:3px;">Welcome to Hazra EV Admin Console</p>
      </div>
      <div style="display:flex;align-items:center;gap:12px;">
        <span style="font-size:11.5px;font-weight:700;color:#34a853;background:#e6f4ea;padding:5px 12px;border-radius:999px;">✓ Admin</span>
        <button class="theme" id="adminTheme" aria-label="Toggle theme" style="width:36px;height:36px;border-radius:50%;border:1px solid var(--hair-0);background:var(--surface-0);cursor:pointer;display:grid;place-items:center;">
          <i data-lucide="sun" class="theme__sun" style="width:17px;height:17px;"></i>
          <i data-lucide="moon" class="theme__moon" style="width:17px;height:17px;"></i>
        </button>
      </div>
    </header>

    <!-- ───── TAB: DASHBOARD ───── -->
    <section id="tab-dashboard" class="tab-panel active">
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card__label">Products</div><div class="stat-card__value" id="stat-products">–</div></div>
        <div class="stat-card"><div class="stat-card__label">Posts</div><div class="stat-card__value" id="stat-posts">–</div></div>
        <div class="stat-card"><div class="stat-card__label">Gallery</div><div class="stat-card__value" id="stat-gallery">–</div></div>
        <div class="stat-card"><div class="stat-card__label">Open Jobs</div><div class="stat-card__value" id="stat-jobs">–</div></div>
        <div class="stat-card"><div class="stat-card__label">New Leads</div><div class="stat-card__value" id="stat-leads" style="color:var(--brand-flame);">–</div></div>
        <div class="stat-card"><div class="stat-card__label">Applications</div><div class="stat-card__value" id="stat-apps" style="color:var(--brand-violet);">–</div></div>
      </div>

      <div class="data-card" style="padding:24px;margin-bottom:24px;">
        <h3 style="font-family:'Montserrat',sans-serif;font-size:17px;font-weight:800;color:var(--ink-0);margin-bottom:14px;">Quick Actions</h3>
        <div style="display:flex;gap:12px;flex-wrap:wrap;">
          <button class="btn btn--primary" onclick="switchTab('products');openModal('product')">
            <i data-lucide="plus" style="width:15px;height:15px;"></i> Add Product
          </button>
          <button class="btn btn--ghost" onclick="switchTab('blogs');openModal('post','blog')">
            <i data-lucide="file-text" style="width:15px;height:15px;"></i> New Blog Post
          </button>
          <button class="btn btn--ghost" onclick="switchTab('news');openModal('post','news')">
            <i data-lucide="newspaper" style="width:15px;height:15px;"></i> Add News
          </button>
          <button class="btn btn--ghost" onclick="switchTab('jobs');openModal('job')">
            <i data-lucide="briefcase" style="width:15px;height:15px;"></i> Post a Job
          </button>
          <button class="btn btn--ghost" onclick="switchTab('test-drive')">
            <i data-lucide="inbox" style="width:15px;height:15px;"></i> View Leads
          </button>
        </div>
      </div>
    </section>

    <!-- ───── TAB: PRODUCTS ───── -->
    <section id="tab-products" class="tab-panel">
      <div class="page-hd">
        <div><h1>Products</h1><p>Manage your EV catalogue</p></div>
        <button class="btn btn--primary" onclick="openModal('product')"><i data-lucide="plus" style="width:15px;height:15px;"></i> Add Product</button>
      </div>
      <div class="data-card">
        <table class="data-table">
          <thead><tr>
            <th>Brand / Name</th><th>Model Code</th><th>Category</th><th>Range / Speed</th><th>Status</th><th style="text-align:right;">Actions</th>
          </tr></thead>
          <tbody id="products-tbody"><tr><td colspan="6" style="padding:30px;text-align:center;color:var(--ink-soft-0);">Loading…</td></tr></tbody>
        </table>
      </div>
    </section>

    <!-- ───── TAB: BLOGS ───── -->
    <section id="tab-blogs" class="tab-panel">
      <div class="page-hd">
        <div><h1>Blogs</h1><p>Manage blog posts</p></div>
        <button class="btn btn--primary" onclick="openModal('post','blog')"><i data-lucide="plus" style="width:15px;height:15px;"></i> New Blog</button>
      </div>
      <div class="data-card">
        <table class="data-table">
          <thead><tr><th>Title</th><th>Status</th><th>Published</th><th style="text-align:right;">Actions</th></tr></thead>
          <tbody id="blogs-tbody"><tr><td colspan="4" style="padding:30px;text-align:center;color:var(--ink-soft-0);">Loading…</td></tr></tbody>
        </table>
      </div>
    </section>

    <!-- ───── TAB: NEWS ───── -->
    <section id="tab-news" class="tab-panel">
      <div class="page-hd">
        <div><h1>News</h1><p>Manage news items</p></div>
        <button class="btn btn--primary" onclick="openModal('post','news')"><i data-lucide="plus" style="width:15px;height:15px;"></i> Add News</button>
      </div>
      <div class="data-card">
        <table class="data-table">
          <thead><tr><th>Title</th><th>Status</th><th>Published</th><th style="text-align:right;">Actions</th></tr></thead>
          <tbody id="news-tbody"><tr><td colspan="4" style="padding:30px;text-align:center;color:var(--ink-soft-0);">Loading…</td></tr></tbody>
        </table>
      </div>
    </section>

    <!-- ───── TAB: GALLERY ───── -->
    <section id="tab-gallery" class="tab-panel">
      <div class="page-hd">
        <div><h1>Gallery</h1><p>Manage site gallery images</p></div>
        <button class="btn btn--primary" onclick="openModal('gallery')"><i data-lucide="plus" style="width:15px;height:15px;"></i> Add Image</button>
      </div>
      <div class="data-card">
        <table class="data-table">
          <thead><tr><th>Title</th><th>Image URL</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
          <tbody id="gallery-tbody"><tr><td colspan="4" style="padding:30px;text-align:center;color:var(--ink-soft-0);">Loading…</td></tr></tbody>
        </table>
      </div>
    </section>

    <!-- ───── TAB: JOBS ───── -->
    <section id="tab-jobs" class="tab-panel">
      <div class="page-hd">
        <div><h1>Jobs</h1><p>Career openings you publish</p></div>
        <button class="btn btn--primary" onclick="openModal('job')"><i data-lucide="plus" style="width:15px;height:15px;"></i> Post Job</button>
      </div>
      <div class="data-card">
        <table class="data-table">
          <thead><tr><th>Title</th><th>Location</th><th>Expires</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
          <tbody id="jobs-tbody"><tr><td colspan="5" style="padding:30px;text-align:center;color:var(--ink-soft-0);">Loading…</td></tr></tbody>
        </table>
      </div>
    </section>

    <!-- ───── TAB: CAREER APPS ───── -->
    <section id="tab-career-apps" class="tab-panel">
      <div class="page-hd">
        <div><h1>Career Applications</h1><p>Job applications from candidates</p></div>
      </div>
      <div class="filters-row">
        <select class="filter-select" id="apps-job-filter" onchange="loadApplications()">
          <option value="">All Jobs</option>
        </select>
        <select class="filter-select" id="apps-status-filter" onchange="loadApplications()">
          <option value="">All Statuses</option>
          <option value="new">New</option>
          <option value="shortlisted">Shortlisted</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>
      <div class="data-card">
        <table class="data-table">
          <thead><tr><th>Applicant</th><th>Email / Phone</th><th>Job</th><th>Applied</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
          <tbody id="apps-tbody"><tr><td colspan="6" style="padding:30px;text-align:center;color:var(--ink-soft-0);">Loading…</td></tr></tbody>
        </table>
      </div>
    </section>

    <!-- ───── TAB: TEST DRIVE ───── -->
    <section id="tab-test-drive" class="tab-panel">
      <div class="page-hd">
        <div><h1>Test Drive Requests</h1><p>Incoming test ride enquiries</p></div>
      </div>
      <?php echo leadsTable('test_ride', 'td'); ?>
    </section>

    <!-- ───── TAB: DEALERSHIP ───── -->
    <section id="tab-dealership" class="tab-panel">
      <div class="page-hd">
        <div><h1>Dealership Enquiries</h1><p>Dealer / franchise applications</p></div>
      </div>
      <?php echo leadsTable('dealer', 'dl'); ?>
    </section>

    <!-- ───── TAB: CONTACT ───── -->
    <section id="tab-contact" class="tab-panel">
      <div class="page-hd">
        <div><h1>Contact Messages</h1><p>General enquiries from public</p></div>
      </div>
      <?php echo leadsTable('contact', 'ct'); ?>
    </section>

    <!-- ───── TAB: SETTINGS ───── -->
    <section id="tab-settings" class="tab-panel">
      <div class="page-hd"><div><h1>Settings</h1><p>Website configuration & mailer</p></div></div>
      <div class="form-card">
        <form id="settings-form" onsubmit="saveSettings(event)" style="display:flex;flex-direction:column;gap:16px;">
          <p class="section-sub">SMTP Configuration</p>
          <div class="form-grid-2">
            <div class="form-row"><label>SMTP Host</label><input class="form-input" type="text" name="smtp_host" id="set_smtp_host" placeholder="smtp.gmail.com"></div>
            <div class="form-row"><label>Port</label><input class="form-input" type="text" name="smtp_port" id="set_smtp_port" placeholder="587"></div>
          </div>
          <div class="form-grid-2">
            <div class="form-row"><label>Username / Email</label><input class="form-input" type="text" name="smtp_username" id="set_smtp_username" placeholder="user@gmail.com"></div>
            <div class="form-row"><label>Password / App Key</label><input class="form-input" type="password" name="smtp_password" id="set_smtp_password" placeholder="••••••••"></div>
          </div>
          <div class="form-row"><label>Notification Recipients (comma-separated)</label><input class="form-input" type="text" name="recipient_emails" id="set_recipient_emails" placeholder="sales@hazraev.com, admin@hazraev.com"></div>

          <hr style="border:0;border-top:1px solid var(--hair-0);">
          <p class="section-sub">Public Contact Details</p>
          <div class="form-grid-2">
            <div class="form-row"><label>Phone</label><input class="form-input" type="text" name="phone" id="set_phone" placeholder="+91 98000 00000"></div>
            <div class="form-row"><label>Email</label><input class="form-input" type="email" name="email" id="set_email" placeholder="contact@hazraev.com"></div>
          </div>
          <div class="form-row"><label>Address</label><input class="form-input" type="text" name="address" id="set_address" placeholder="Bardhaman, West Bengal, India"></div>
          <div class="form-row"><label>WhatsApp Number</label><input class="form-input" type="text" name="whatsapp" id="set_whatsapp" placeholder="+91 98000 00000"></div>

          <button type="submit" class="btn btn--primary" style="width:fit-content;margin-top:6px;">Save Settings</button>
        </form>
      </div>
    </section>

  </main>
</div>

<!-- ===== MODALS ===== -->

<!-- Product Modal -->
<div id="modal-product" class="modal-overlay">
  <div class="modal-box">
    <div class="modal-header">
      <h3 id="product-modal-title">Add Product</h3>
      <button class="modal-close" onclick="closeModal('product')">&times;</button>
    </div>
    <form id="product-form" onsubmit="saveProduct(event)" style="display:flex;flex-direction:column;gap:14px;">
      <input type="hidden" id="prod_id" name="id">
      <div class="form-grid-2">
        <div class="form-row"><label>Name *</label><input class="form-input" type="text" id="prod_name" name="name" required placeholder="Volt S1"></div>
        <div class="form-row"><label>Model Code *</label><input class="form-input" type="text" id="prod_model_code" name="modelCode" required placeholder="HZ-VS1"></div>
      </div>
      <div class="form-grid-3">
        <div class="form-row"><label>Brand *</label><input class="form-input" type="text" id="prod_brand" name="brand" value="Hazra" required></div>
        <div class="form-row"><label>Category *</label>
          <select class="form-input" id="prod_category" name="category" required>
            <option value="scooty">Scooty</option><option value="bike">Bike</option>
            <option value="bicycle">E-Bicycle</option><option value="others">Others</option>
          </select>
        </div>
        <div class="form-row"><label>Rating (0-5)</label><input class="form-input" type="number" step="0.1" id="prod_rating" name="rating" value="4.5"></div>
      </div>
      <div class="form-grid-3">
        <div class="form-row"><label>Range (km)</label><input class="form-input" type="number" id="prod_range" name="rangeKm" placeholder="110"></div>
        <div class="form-row"><label>Top Speed (km/h)</label><input class="form-input" type="number" id="prod_speed" name="topSpeedKmph" placeholder="65"></div>
        <div class="form-row"><label>Warranty (years)</label><input class="form-input" type="number" id="prod_warranty" name="warrantyYears" placeholder="3"></div>
      </div>
      <div class="form-grid-2">
        <div class="form-row"><label>Battery Capacity</label><input class="form-input" type="text" id="prod_battery" name="batteryCapacity" placeholder="2.2 kWh Li-ion"></div>
        <div class="form-row"><label>Motor Power</label><input class="form-input" type="text" id="prod_motor" name="motorPower" placeholder="1500W BLDC"></div>
      </div>
      <div class="form-row"><label>Warranty Note</label><input class="form-input" type="text" id="prod_warranty_note" name="warrantyNote" placeholder="3 yrs vehicle + 4 yrs battery"></div>
      <div class="form-grid-2">
        <div class="form-row"><label>Hero Image URL</label><input class="form-input" type="text" id="prod_hero_image" name="hero_image" placeholder="https://..."></div>
        <div class="form-row"><label>Slug</label><input class="form-input" type="text" id="prod_slug" name="slug" placeholder="volt-s1"></div>
      </div>
      <div class="form-grid-2" style="align-items:center;">
        <div class="form-row" style="flex-direction:row;gap:10px;align-items:center;margin-top:6px;">
          <input type="checkbox" id="prod_is_featured" name="is_featured" value="1" style="width:18px;height:18px;cursor:pointer;">
          <label for="prod_is_featured" style="cursor:pointer;font-size:12px;font-weight:700;">Show on homepage Feature</label>
        </div>
        <div class="form-row"><label>Featured Order</label><input class="form-input" type="number" id="prod_featured_order" name="featured_order" value="0"></div>
      </div>
      <button type="submit" class="btn btn--primary">Save Product</button>
    </form>
  </div>
</div>

<!-- Post Modal (Blog / News) -->
<div id="modal-post" class="modal-overlay">
  <div class="modal-box">
    <div class="modal-header">
      <h3 id="post-modal-title">New Post</h3>
      <button class="modal-close" onclick="closeModal('post')">&times;</button>
    </div>
    <form id="post-form" onsubmit="savePost(event)" style="display:flex;flex-direction:column;gap:14px;">
      <input type="hidden" id="post_id" name="id">
      <input type="hidden" id="post_type" name="type" value="blog">
      <div class="form-row"><label>Title *</label><input class="form-input" type="text" id="post_title" name="title" required placeholder="Post title"></div>
      <div class="form-grid-2">
        <div class="form-row"><label>Slug</label><input class="form-input" type="text" id="post_slug" name="slug" placeholder="my-post-slug"></div>
        <div class="form-row"><label>Status</label>
          <select class="form-input" id="post_status" name="status">
            <option value="draft">Draft</option><option value="published">Published</option>
          </select>
        </div>
      </div>
      <div class="form-row"><label>Excerpt</label><input class="form-input" type="text" id="post_excerpt" name="excerpt" placeholder="Short summary"></div>
      <div class="form-row"><label>Cover Image URL</label><input class="form-input" type="text" id="post_cover" name="cover_image" placeholder="https://..."></div>
      <div class="form-row"><label>Content *</label><textarea class="form-input" id="post_content" name="content" rows="6" required placeholder="Write your content here…" style="resize:vertical;"></textarea></div>
      <div class="form-row"><label>Author</label><input class="form-input" type="text" id="post_author" name="author" placeholder="Hazra EV Team"></div>
      <button type="submit" class="btn btn--primary">Save Post</button>
    </form>
  </div>
</div>

<!-- Gallery Modal -->
<div id="modal-gallery" class="modal-overlay">
  <div class="modal-box">
    <div class="modal-header">
      <h3>Add Gallery Image</h3>
      <button class="modal-close" onclick="closeModal('gallery')">&times;</button>
    </div>
    <form id="gallery-form" onsubmit="saveGallery(event)" style="display:flex;flex-direction:column;gap:14px;">
      <input type="hidden" id="gallery_id" name="id">
      <div class="form-row"><label>Title *</label><input class="form-input" type="text" id="gallery_title" name="title" required placeholder="Gallery image title"></div>
      <div class="form-row"><label>Image URL / Path *</label><input class="form-input" type="text" id="gallery_image" name="image_path" required placeholder="https://... or /assets/..."></div>
      <div class="form-row"><label>Caption</label><input class="form-input" type="text" id="gallery_caption" name="caption" placeholder="Optional caption"></div>
      <div class="form-row"><label>Status</label>
        <select class="form-input" id="gallery_status" name="status">
          <option value="active">Active</option><option value="inactive">Inactive</option>
        </select>
      </div>
      <button type="submit" class="btn btn--primary">Save Image</button>
    </form>
  </div>
</div>

<!-- Job Modal -->
<div id="modal-job" class="modal-overlay">
  <div class="modal-box">
    <div class="modal-header">
      <h3 id="job-modal-title">Post a Job</h3>
      <button class="modal-close" onclick="closeModal('job')">&times;</button>
    </div>
    <form id="job-form" onsubmit="saveJob(event)" style="display:flex;flex-direction:column;gap:14px;">
      <input type="hidden" id="job_id" name="id">
      <div class="form-row"><label>Job Title *</label><input class="form-input" type="text" id="job_title" name="title" required placeholder="e.g. EV Sales Executive"></div>
      <div class="form-grid-2">
        <div class="form-row"><label>Location</label><input class="form-input" type="text" id="job_location" name="location" placeholder="Bardhaman, WB"></div>
        <div class="form-row"><label>Type</label>
          <select class="form-input" id="job_type" name="type">
            <option value="full-time">Full-Time</option><option value="part-time">Part-Time</option>
            <option value="internship">Internship</option><option value="contract">Contract</option>
          </select>
        </div>
      </div>
      <div class="form-grid-2">
        <div class="form-row"><label>Openings</label><input class="form-input" type="number" id="job_positions" name="positions" placeholder="1"></div>
        <div class="form-row"><label>Expires On</label><input class="form-input" type="date" id="job_expires" name="expires_at"></div>
      </div>
      <div class="form-row"><label>Apply Email</label><input class="form-input" type="email" id="job_email" name="apply_email" placeholder="hr@hazraev.com"></div>
      <div class="form-row"><label>Description *</label><textarea class="form-input" id="job_desc" name="description" rows="4" required placeholder="Job description…" style="resize:vertical;"></textarea></div>
      <div class="form-row"><label>Requirements</label><textarea class="form-input" id="job_req" name="requirements" rows="3" placeholder="Skills, experience…" style="resize:vertical;"></textarea></div>
      <button type="submit" class="btn btn--primary">Post Job</button>
    </form>
  </div>
</div>

<?php
/* Helper: render a leads table section for a given type */
function leadsTable(string $type, string $prefix): string {
    $filterOptions = '<option value="">All Statuses</option><option value="new">New</option><option value="contacted">Contacted</option><option value="archived">Archived</option>';
    return <<<HTML
    <div class="filters-row">
      <select class="filter-select" id="{$prefix}-status-filter" onchange="loadLeads('{$type}','{$prefix}')">
        {$filterOptions}
      </select>
    </div>
    <div class="data-card">
      <table class="data-table">
        <thead><tr><th>Date</th><th>Name</th><th>Phone / Email</th><th>Message</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody id="{$prefix}-leads-tbody"><tr><td colspan="6" style="padding:30px;text-align:center;color:var(--ink-soft-0);">Loading…</td></tr></tbody>
      </table>
    </div>
HTML;
}
?>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
lucide.createIcons();

// ======= TAB ROUTING =======
const tabMeta = {
  'dashboard':   { title: 'Dashboard',             sub: 'Overview of Hazra EV' },
  'products':    { title: 'Products',               sub: 'Manage EV catalogue' },
  'blogs':       { title: 'Blogs',                  sub: 'Manage blog posts' },
  'news':        { title: 'News',                   sub: 'Manage news items' },
  'gallery':     { title: 'Gallery',                sub: 'Manage site gallery' },
  'jobs':        { title: 'Jobs',                   sub: 'Career openings' },
  'career-apps': { title: 'Career Applications',    sub: 'Job applications from candidates' },
  'test-drive':  { title: 'Test Drive Requests',    sub: 'Incoming test ride enquiries' },
  'dealership':  { title: 'Dealership Enquiries',   sub: 'Dealer / franchise applications' },
  'contact':     { title: 'Contact Messages',       sub: 'General enquiries from public' },
  'settings':    { title: 'Settings',               sub: 'Website configuration & mailer' },
};

function switchTab(tabId) {
  document.querySelectorAll('.nav-tab').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));

  const btn = document.getElementById('nav-' + tabId);
  if (btn) btn.classList.add('active');

  const panel = document.getElementById('tab-' + tabId);
  if (panel) panel.classList.add('active');

  const meta = tabMeta[tabId] || {};
  document.getElementById('page-title').textContent = meta.title || '';
  document.getElementById('page-sub').textContent   = meta.sub   || '';

  if (tabId === 'dashboard')   loadDashboard();
  if (tabId === 'products')    loadProducts();
  if (tabId === 'blogs')       loadPosts('blog');
  if (tabId === 'news')        loadPosts('news');
  if (tabId === 'gallery')     loadGallery();
  if (tabId === 'jobs')        loadJobs();
  if (tabId === 'career-apps') loadApplications();
  if (tabId === 'test-drive')  loadLeads('test_ride', 'td');
  if (tabId === 'dealership')  loadLeads('dealer', 'dl');
  if (tabId === 'contact')     loadLeads('contact', 'ct');
  if (tabId === 'settings')    loadSettings();
}

document.querySelectorAll('.nav-tab[data-tab]').forEach(btn => {
  btn.addEventListener('click', () => switchTab(btn.dataset.tab));
});

// ======= MODALS =======
function openModal(which, extra) {
  if (which === 'product')  { document.getElementById('product-form').reset(); document.getElementById('prod_id').value = ''; document.getElementById('product-modal-title').textContent = 'Add Product'; }
  if (which === 'post')     { document.getElementById('post-form').reset(); document.getElementById('post_id').value = ''; document.getElementById('post_type').value = extra || 'blog'; document.getElementById('post-modal-title').textContent = extra === 'news' ? 'Add News Item' : 'New Blog Post'; }
  if (which === 'gallery')  { document.getElementById('gallery-form').reset(); document.getElementById('gallery_id').value = ''; }
  if (which === 'job')      { document.getElementById('job-form').reset(); document.getElementById('job_id').value = ''; document.getElementById('job-modal-title').textContent = 'Post a Job'; }
  document.getElementById('modal-' + which).classList.add('open');
}
function closeModal(which) {
  document.getElementById('modal-' + which).classList.remove('open');
}
// Close on backdrop click
document.querySelectorAll('.modal-overlay').forEach(el => {
  el.addEventListener('click', e => { if (e.target === el) el.classList.remove('open'); });
});

// ======= HELPERS =======
const API = '<?= base_url('api/v1') ?>';

async function apiFetch(path, opts = {}) {
  const res = await fetch(API + path, { headers: { 'Content-Type': 'application/json', ...(opts.headers||{}) }, ...opts });
  if (!res.ok) throw new Error(res.status);
  return res.json();
}

function statusBadge(s) {
  const map = { new:'amber', draft:'grey', published:'green', open:'green', closed:'red', contacted:'blue', archived:'grey', active:'green', inactive:'grey', shortlisted:'violet', rejected:'red' };
  return `<span class="badge badge--${map[s]||'grey'}">${(s||'').toUpperCase()}</span>`;
}

function dateStr(d) { return d ? d.substring(0,10) : '—'; }

// ======= DASHBOARD =======
async function loadDashboard() {
  try {
    const [prods, posts, gallery, jobs, apps] = await Promise.allSettled([
      apiFetch('/website/products'),
      apiFetch('/posts'),
      apiFetch('/gallery'),
      apiFetch('/jobs'),
      apiFetch('/job-applications'),
    ]);
    const v = r => r.status === 'fulfilled' ? (r.value?.data?.length ?? r.value?.data?.total ?? (Array.isArray(r.value?.data) ? r.value.data.length : '–')) : '–';
    document.getElementById('stat-products').textContent = v(prods);
    document.getElementById('stat-posts').textContent    = v(posts);
    document.getElementById('stat-gallery').textContent  = v(gallery);
    document.getElementById('stat-jobs').textContent     = v(jobs);
    document.getElementById('stat-apps').textContent     = v(apps);
  } catch(e) {}
}

// ======= PRODUCTS =======
async function loadProducts() {
  const tbody = document.getElementById('products-tbody');
  tbody.innerHTML = '<tr><td colspan="6" style="padding:28px;text-align:center;color:var(--ink-soft-0);">Loading…</td></tr>';
  try {
    const data = await apiFetch('/website/products');
    const list = data.data || [];
    if (!list.length) { tbody.innerHTML = '<tr><td colspan="6" style="padding:24px;text-align:center;color:var(--ink-soft-0);">No products found.</td></tr>'; return; }
    tbody.innerHTML = list.map(p => `
      <tr>
        <td><strong>${p.brand || ''} ${p.name || ''}</strong></td>
        <td style="color:var(--ink-soft-0);">${p.model_code || p.modelCode || ''}</td>
        <td><span class="badge badge--violet">${(p.category||'').toUpperCase()}</span></td>
        <td>${p.range_km || p.rangeKm || 0}km / ${p.top_speed_kmph || p.topSpeedKmph || 0}km/h</td>
        <td>${statusBadge(p.active == 1 ? 'active' : 'inactive')}</td>
        <td style="text-align:right;"><button class="btn btn--ghost btn--sm" onclick="editProduct('${p.id}')">Edit</button></td>
      </tr>`).join('');
  } catch(e) {
    tbody.innerHTML = `<tr><td colspan="6" style="padding:24px;text-align:center;color:red;">Failed to load products.</td></tr>`;
  }
}

async function editProduct(id) {
  try {
    const data = await apiFetch('/products/' + id);
    const p = data.data || data;
    document.getElementById('prod_id').value             = p.id || '';
    document.getElementById('prod_name').value           = p.name || '';
    document.getElementById('prod_brand').value          = p.brand || 'Hazra';
    document.getElementById('prod_model_code').value     = p.model_code || p.modelCode || '';
    document.getElementById('prod_category').value       = p.category || 'scooty';
    document.getElementById('prod_rating').value         = p.rating || 4.5;
    document.getElementById('prod_range').value          = p.range_km || p.rangeKm || '';
    document.getElementById('prod_speed').value          = p.top_speed_kmph || p.topSpeedKmph || '';
    document.getElementById('prod_warranty').value       = p.warranty_years || p.warrantyYears || '';
    document.getElementById('prod_battery').value        = p.battery_capacity || p.batteryCapacity || '';
    document.getElementById('prod_motor').value          = p.motor_power || p.motorPower || '';
    document.getElementById('prod_warranty_note').value  = p.warranty_note || p.warrantyNote || '';
    document.getElementById('prod_is_featured').checked  = (p.is_featured == 1 || p.isFeatured == 1);
    document.getElementById('prod_featured_order').value = p.featured_order || p.featuredOrder || 0;
    document.getElementById('prod_hero_image').value     = p.hero_image || p.heroImage || '';
    document.getElementById('prod_slug').value           = p.slug || '';
    document.getElementById('product-modal-title').textContent = 'Edit Product';
    openModal('product');
  } catch(e) { alert('Could not load product details.'); }
}

async function saveProduct(e) {
  e.preventDefault();
  const f = new FormData(e.target);
  const body = {
    brand: f.get('brand'), name: f.get('name'), modelCode: f.get('modelCode'),
    category: f.get('category'), rating: parseFloat(f.get('rating')||4.5),
    rangeKm: parseInt(f.get('rangeKm')||0), topSpeedKmph: parseInt(f.get('topSpeedKmph')||0),
    warrantyYears: parseInt(f.get('warrantyYears')||0),
    batteryCapacity: f.get('batteryCapacity'), motorPower: f.get('motorPower'),
    warrantyNote: f.get('warrantyNote'),
    is_featured: f.get('is_featured') === '1' ? 1 : 0,
    featured_order: parseInt(f.get('featured_order')||0),
    hero_image: f.get('hero_image'),
    slug: f.get('slug'),
    colors: [{ name: 'Default', argb: 0, inStock: true, imageUrls: [] }]
  };
  const id = f.get('id');
  try {
    if (id) await apiFetch('/products/' + id, { method: 'PATCH', body: JSON.stringify(body) });
    else    await apiFetch('/products', { method: 'POST', body: JSON.stringify(body) });
    closeModal('product'); loadProducts();
  } catch(err) { alert('Failed to save product. ' + err.message); }
}

// ======= POSTS (Blog / News) =======
async function loadPosts(type) {
  const tbodyId = type === 'blog' ? 'blogs-tbody' : 'news-tbody';
  const tbody = document.getElementById(tbodyId);
  tbody.innerHTML = '<tr><td colspan="4" style="padding:28px;text-align:center;color:var(--ink-soft-0);">Loading…</td></tr>';
  try {
    const data = await apiFetch('/posts?type=' + type);
    const list = data.data || [];
    if (!list.length) { tbody.innerHTML = '<tr><td colspan="4" style="padding:24px;text-align:center;color:var(--ink-soft-0);">No posts yet.</td></tr>'; return; }
    tbody.innerHTML = list.map(p => `
      <tr>
        <td><strong>${p.title || ''}</strong></td>
        <td>${statusBadge(p.status)}</td>
        <td style="color:var(--ink-soft-0);">${dateStr(p.published_at)}</td>
        <td style="text-align:right;">
          <button class="btn btn--ghost btn--sm" onclick="editPost('${p.id}','${type}')">Edit</button>
          <button class="btn btn--danger btn--sm" style="margin-left:6px;" onclick="deletePost('${p.id}','${type}')">Del</button>
        </td>
      </tr>`).join('');
  } catch(e) { tbody.innerHTML = '<tr><td colspan="4" style="padding:24px;text-align:center;color:red;">Failed to load.</td></tr>'; }
}

async function editPost(id, type) {
  try {
    const data = await apiFetch('/posts/' + id);
    const p = data.data || data;
    document.getElementById('post_id').value      = p.id || '';
    document.getElementById('post_type').value    = type;
    document.getElementById('post_title').value   = p.title || '';
    document.getElementById('post_slug').value    = p.slug || '';
    document.getElementById('post_status').value  = p.status || 'draft';
    document.getElementById('post_excerpt').value = p.excerpt || '';
    document.getElementById('post_cover').value   = p.cover_image || '';
    document.getElementById('post_content').value = p.content || '';
    document.getElementById('post_author').value  = p.author || '';
    document.getElementById('post-modal-title').textContent = type === 'news' ? 'Edit News Item' : 'Edit Blog Post';
    document.getElementById('modal-post').classList.add('open');
  } catch(e) { alert('Could not load post.'); }
}

async function savePost(e) {
  e.preventDefault();
  const f = new FormData(e.target);
  const body = { type: f.get('type'), title: f.get('title'), slug: f.get('slug'), status: f.get('status'), excerpt: f.get('excerpt'), cover_image: f.get('cover_image'), content: f.get('content'), author: f.get('author') };
  const id = f.get('id');
  const type = f.get('type');
  try {
    if (id) await apiFetch('/posts/' + id, { method: 'PATCH', body: JSON.stringify(body) });
    else    await apiFetch('/posts', { method: 'POST', body: JSON.stringify(body) });
    closeModal('post'); loadPosts(type);
  } catch(err) { alert('Failed to save post. ' + err.message); }
}

async function deletePost(id, type) {
  if (!confirm('Delete this post?')) return;
  try {
    await apiFetch('/posts/' + id, { method: 'DELETE' });
    loadPosts(type);
  } catch(e) { alert('Failed to delete.'); }
}

// ======= GALLERY =======
async function loadGallery() {
  const tbody = document.getElementById('gallery-tbody');
  tbody.innerHTML = '<tr><td colspan="4" style="padding:28px;text-align:center;color:var(--ink-soft-0);">Loading…</td></tr>';
  try {
    const data = await apiFetch('/gallery');
    const list = data.data || [];
    if (!list.length) { tbody.innerHTML = '<tr><td colspan="4" style="padding:24px;text-align:center;color:var(--ink-soft-0);">No gallery items yet.</td></tr>'; return; }
    tbody.innerHTML = list.map(g => `
      <tr>
        <td><strong>${g.title || ''}</strong>${g.caption ? '<br><small style="color:var(--ink-soft-0);">'+g.caption+'</small>' : ''}</td>
        <td style="font-size:12px;color:var(--ink-soft-0);max-width:180px;overflow:hidden;text-overflow:ellipsis;">${g.image_path || ''}</td>
        <td>${statusBadge(g.status)}</td>
        <td style="text-align:right;">
          <button class="btn btn--ghost btn--sm" onclick="editGallery('${g.id}')">Edit</button>
          <button class="btn btn--danger btn--sm" style="margin-left:6px;" onclick="deleteGallery('${g.id}')">Del</button>
        </td>
      </tr>`).join('');
  } catch(e) { tbody.innerHTML = '<tr><td colspan="4" style="padding:24px;text-align:center;color:red;">Failed to load.</td></tr>'; }
}

async function editGallery(id) {
  try {
    const data = await apiFetch('/gallery/' + id);
    const g = data.data || data;
    document.getElementById('gallery_id').value      = g.id || '';
    document.getElementById('gallery_title').value   = g.title || '';
    document.getElementById('gallery_image').value   = g.image_path || '';
    document.getElementById('gallery_caption').value = g.caption || '';
    document.getElementById('gallery_status').value  = g.status || 'active';
    document.getElementById('modal-gallery').classList.add('open');
  } catch(e) { alert('Could not load gallery item.'); }
}

async function saveGallery(e) {
  e.preventDefault();
  const f = new FormData(e.target);
  const body = { title: f.get('title'), image_path: f.get('image_path'), caption: f.get('caption'), status: f.get('status') };
  const id = f.get('id');
  try {
    if (id) await apiFetch('/gallery/' + id, { method: 'PATCH', body: JSON.stringify(body) });
    else    await apiFetch('/gallery', { method: 'POST', body: JSON.stringify(body) });
    closeModal('gallery'); loadGallery();
  } catch(err) { alert('Failed to save gallery item.'); }
}

async function deleteGallery(id) {
  if (!confirm('Delete this gallery item?')) return;
  try { await apiFetch('/gallery/' + id, { method: 'DELETE' }); loadGallery(); }
  catch(e) { alert('Failed to delete.'); }
}

// ======= JOBS =======
async function loadJobs() {
  const tbody = document.getElementById('jobs-tbody');
  tbody.innerHTML = '<tr><td colspan="5" style="padding:28px;text-align:center;color:var(--ink-soft-0);">Loading…</td></tr>';
  try {
    const data = await apiFetch('/jobs');
    const list = data.data || [];
    if (!list.length) { tbody.innerHTML = '<tr><td colspan="5" style="padding:24px;text-align:center;color:var(--ink-soft-0);">No open jobs.</td></tr>'; return; }
    tbody.innerHTML = list.map(j => `
      <tr>
        <td><strong>${j.title || ''}</strong><br><small style="color:var(--ink-soft-0);">${j.type||''}</small></td>
        <td style="color:var(--ink-soft-0);">${j.location || '—'}</td>
        <td style="color:var(--ink-soft-0);">${dateStr(j.expires_at)}</td>
        <td>${statusBadge(j.status)}</td>
        <td style="text-align:right;">
          <button class="btn btn--ghost btn--sm" onclick="editJob('${j.id}')">Edit</button>
          <button class="btn btn--danger btn--sm" style="margin-left:6px;" onclick="deleteJob('${j.id}')">Del</button>
        </td>
      </tr>`).join('');
    // populate job filter in career apps
    const sel = document.getElementById('apps-job-filter');
    const existing = [...sel.options].map(o=>o.value);
    list.forEach(j => { if (!existing.includes(j.id)) { const o = new Option(j.title, j.id); sel.add(o); }});
  } catch(e) { tbody.innerHTML = '<tr><td colspan="5" style="padding:24px;text-align:center;color:red;">Failed to load.</td></tr>'; }
}

async function editJob(id) {
  try {
    const data = await apiFetch('/jobs/' + id);
    const j = data.data || data;
    document.getElementById('job_id').value        = j.id || '';
    document.getElementById('job_title').value     = j.title || '';
    document.getElementById('job_location').value  = j.location || '';
    document.getElementById('job_type').value      = j.type || 'full-time';
    document.getElementById('job_positions').value = j.positions || '';
    document.getElementById('job_expires').value   = (j.expires_at||'').substring(0,10);
    document.getElementById('job_email').value     = j.apply_email || '';
    document.getElementById('job_desc').value      = j.description || '';
    document.getElementById('job_req').value       = j.requirements || '';
    document.getElementById('job-modal-title').textContent = 'Edit Job';
    document.getElementById('modal-job').classList.add('open');
  } catch(e) { alert('Could not load job.'); }
}

async function saveJob(e) {
  e.preventDefault();
  const f = new FormData(e.target);
  const body = { title: f.get('title'), location: f.get('location'), type: f.get('type'), positions: parseInt(f.get('positions')||1), expires_at: f.get('expires_at'), apply_email: f.get('apply_email'), description: f.get('description'), requirements: f.get('requirements') };
  const id = f.get('id');
  try {
    if (id) await apiFetch('/jobs/' + id, { method: 'PATCH', body: JSON.stringify(body) });
    else    await apiFetch('/jobs', { method: 'POST', body: JSON.stringify(body) });
    closeModal('job'); loadJobs();
  } catch(err) { alert('Failed to save job. ' + err.message); }
}

async function deleteJob(id) {
  if (!confirm('Delete this job posting?')) return;
  try { await apiFetch('/jobs/' + id, { method: 'DELETE' }); loadJobs(); }
  catch(e) { alert('Failed to delete.'); }
}

// ======= CAREER APPLICATIONS =======
async function loadApplications() {
  const tbody = document.getElementById('apps-tbody');
  const jobId = document.getElementById('apps-job-filter').value;
  const status = document.getElementById('apps-status-filter').value;
  tbody.innerHTML = '<tr><td colspan="6" style="padding:28px;text-align:center;color:var(--ink-soft-0);">Loading…</td></tr>';
  try {
    let url = '/job-applications';
    const params = [];
    if (jobId) params.push('job_id=' + jobId);
    if (status) params.push('status=' + status);
    if (params.length) url += '?' + params.join('&');
    const data = await apiFetch(url);
    const list = data.data || [];
    const badge = document.getElementById('badge-apps');
    const newCount = list.filter(a => a.status === 'new').length;
    if (newCount) { badge.textContent = newCount; badge.style.display = 'inline-block'; } else badge.style.display = 'none';
    if (!list.length) { tbody.innerHTML = '<tr><td colspan="6" style="padding:24px;text-align:center;color:var(--ink-soft-0);">No applications yet.</td></tr>'; return; }
    tbody.innerHTML = list.map(a => `
      <tr>
        <td><strong>${a.applicant_name || ''}</strong></td>
        <td style="font-size:12px;">${a.email||''}<br><span style="color:var(--ink-soft-0);">${a.phone||''}</span></td>
        <td style="color:var(--ink-soft-0);">${a.job_id || '—'}</td>
        <td style="color:var(--ink-soft-0);">${dateStr(a.applied_at)}</td>
        <td>${statusBadge(a.status)}</td>
        <td style="text-align:right;">
          <button class="btn btn--ghost btn--sm" onclick="updateAppStatus('${a.id}','shortlisted')">Shortlist</button>
          <button class="btn btn--danger btn--sm" style="margin-left:4px;" onclick="updateAppStatus('${a.id}','rejected')">Reject</button>
        </td>
      </tr>`).join('');
  } catch(e) { tbody.innerHTML = '<tr><td colspan="6" style="padding:24px;text-align:center;color:red;">Failed to load applications.</td></tr>'; }
}

async function updateAppStatus(id, status) {
  try {
    await apiFetch('/job-applications/' + id, { method: 'PATCH', body: JSON.stringify({ status }) });
    loadApplications();
  } catch(e) { alert('Failed to update status.'); }
}

// ======= LEADS (split by type) =======
async function loadLeads(type, prefix) {
  const status = (document.getElementById(prefix + '-status-filter') || {}).value || '';
  const tbody = document.getElementById(prefix + '-leads-tbody');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="6" style="padding:28px;text-align:center;color:var(--ink-soft-0);">Loading…</td></tr>';
  try {
    let url = '/website/leads?type=' + type;
    if (status) url += '&status=' + status;
    const data = await apiFetch(url);
    const leads = data.data || [];
    // update test-drive badge
    if (type === 'test_ride') {
      const badge = document.getElementById('badge-td');
      const newCount = leads.filter(l => l.status === 'new').length;
      if (newCount) { badge.textContent = newCount; badge.style.display = 'inline-block'; } else badge.style.display = 'none';
    }
    if (!leads.length) { tbody.innerHTML = '<tr><td colspan="6" style="padding:24px;text-align:center;color:var(--ink-soft-0);">No enquiries found.</td></tr>'; return; }
    tbody.innerHTML = leads.map(l => `
      <tr>
        <td style="color:var(--ink-soft-0);">${dateStr(l.created_at)}</td>
        <td><strong>${l.name || 'Anonymous'}</strong></td>
        <td style="font-size:12px;">${l.phone||''}<br><span style="color:var(--ink-soft-0);">${l.email||''}</span></td>
        <td style="font-size:12px;color:var(--ink-soft-0);max-width:200px;">${(l.message||'').substring(0,80)}${(l.message||'').length>80?'…':''}</td>
        <td>${statusBadge(l.status)}</td>
        <td style="text-align:right;">
          <button class="btn btn--ghost btn--sm" onclick="updateLeadStatus('${l.id}','contacted','${type}','${prefix}')">Mark Contacted</button>
          <button class="btn btn--ghost btn--sm" style="margin-left:4px;" onclick="updateLeadStatus('${l.id}','archived','${type}','${prefix}')">Archive</button>
        </td>
      </tr>`).join('');
  } catch(e) {
    tbody.innerHTML = '<tr><td colspan="6" style="padding:24px;text-align:center;color:var(--ink-soft-0);">Requires admin session to view leads.</td></tr>';
  }
}

async function updateLeadStatus(id, status, type, prefix) {
  try {
    await apiFetch('/website/leads/' + id, { method: 'PATCH', body: JSON.stringify({ status }) });
    loadLeads(type, prefix);
  } catch(e) { alert('Failed to update lead status.'); }
}

// ======= SETTINGS =======
async function loadSettings() {
  try {
    const data = await apiFetch('/website/settings');
    const s = data.data || {};
    for (const [k, v] of Object.entries(s)) {
      const el = document.getElementById('set_' + k);
      if (el) el.value = v;
    }
  } catch(e) {}
}

async function saveSettings(e) {
  e.preventDefault();
  const f = new FormData(e.target);
  const body = {};
  f.forEach((v, k) => body[k] = v);
  try {
    await apiFetch('/website/settings', { method: 'POST', body: JSON.stringify(body) });
    alert('Settings saved!');
  } catch(err) { alert('Error saving settings: ' + err.message); }
}

// ======= BOOT =======
document.addEventListener('DOMContentLoaded', () => {
  loadDashboard();
});
</script>

</body>
</html>
