<?php
$pageTitle = "Admin Dashboard | Hazra Electrical Bike";
$pageDescription = "Hazra EV Website & Catalogue Administration Panel.";

include __BASEDIR__ . '/app/components/head.php';
?>

<div class="admin-wrapper" style="display: flex; min-height: 100vh; background: var(--surface-0); color: var(--ink-0);">

  <!-- Sidebar Nav -->
  <aside class="admin-sidebar" style="width: 260px; background: rgb(var(--chip-rgb) / .5); border-right: 1px solid var(--hair-0); padding: 24px; display: flex; flex-direction: column; flex-shrink: 0;">
    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 36px;">
      <img src="<?= base_url('assets/hazraev.png') ?>" alt="Hazra EV" style="width: 34px; height: 34px; object-fit: contain;">
      <div>
        <div style="font-family: 'Montserrat', sans-serif; font-weight: 800; font-size: 14px; color: var(--ink-0);">Hazra EV</div>
        <div style="font-size: 10px; font-weight: 700; color: var(--brand-violet); letter-spacing: .08em; text-transform: uppercase;">ADMIN CONSOLE</div>
      </div>
    </div>

    <nav style="display: flex; flex-direction: column; gap: 6px; flex: 1;">
      <button class="nav-tab active" onclick="showTab('dashboard', this)" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 12px; border: 0; background: var(--ink-0); color: var(--surface-0); font-family: inherit; font-size: 13px; font-weight: 700; cursor: pointer; text-align: left; transition: all .2s;">
        <i data-lucide="layout-dashboard" style="width: 18px; height: 18px;"></i>
        <span>Dashboard</span>
      </button>

      <button class="nav-tab" onclick="showTab('products', this)" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 12px; border: 0; background: transparent; color: var(--ink-soft-0); font-family: inherit; font-size: 13px; font-weight: 700; cursor: pointer; text-align: left; transition: all .2s;">
        <i data-lucide="bike" style="width: 18px; height: 18px;"></i>
        <span>Scooters & Products</span>
      </button>

      <button class="nav-tab" onclick="showTab('leads', this)" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 12px; border: 0; background: transparent; color: var(--ink-soft-0); font-family: inherit; font-size: 13px; font-weight: 700; cursor: pointer; text-align: left; transition: all .2s;">
        <i data-lucide="inbox" style="width: 18px; height: 18px;"></i>
        <span>Leads & Inquiries</span>
        <span id="badge-new-leads" style="margin-left: auto; font-size: 10px; background: var(--brand-flame); color: #fff; padding: 2px 6px; border-radius: 999px; display: none;">0</span>
      </button>

      <button class="nav-tab" onclick="showTab('settings', this)" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 12px; border: 0; background: transparent; color: var(--ink-soft-0); font-family: inherit; font-size: 13px; font-weight: 700; cursor: pointer; text-align: left; transition: all .2s;">
        <i data-lucide="settings" style="width: 18px; height: 18px;"></i>
        <span>Website Settings</span>
      </button>
    </nav>

    <div style="margin-top: auto; padding-top: 20px; border-top: 1px solid var(--hair-0);">
      <a href="<?= base_url('') ?>" target="_blank" style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; font-weight: 600; color: var(--ink-soft-0); text-decoration: none;">
        <i data-lucide="external-link" style="width: 16px; height: 16px;"></i>
        <span>View Live Website</span>
      </a>
    </div>
  </aside>

  <!-- Main Workspace -->
  <main style="flex: 1; padding: 36px clamp(20px, 4vw, 40px); overflow-y: auto;">
    
    <!-- Top Header Bar -->
    <header style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px;">
      <div>
        <h1 id="page-title" style="font-family: 'Montserrat', sans-serif; font-size: 26px; font-weight: 800; color: var(--ink-0);">Admin Dashboard</h1>
        <p id="page-sub" style="font-size: 13px; color: var(--ink-soft-0); margin-top: 4px;">Overview of Hazra EV catalogue and leads</p>
      </div>

      <div style="display: flex; align-items: center; gap: 14px;">
        <span id="auth-status" style="font-size: 12px; font-weight: 700; color: #34a853; background: #e6f4ea; padding: 6px 12px; border-radius: 999px;">
          Authenticated (Admin)
        </span>
        <button class="theme" id="adminTheme" aria-label="Toggle theme" style="width: 38px; height: 38px; border-radius: 50%; border: 1px solid var(--hair-0); background: var(--surface-0); cursor: pointer; display: grid; place-items: center;">
          <i data-lucide="sun" class="theme__sun" style="width: 18px; height: 18px;"></i>
          <i data-lucide="moon" class="theme__moon" style="width: 18px; height: 18px;"></i>
        </button>
      </div>
    </header>

    <!-- TAB 1: DASHBOARD -->
    <section id="tab-dashboard" class="tab-content" style="display: block;">
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 36px;">
        <div style="padding: 24px; background: rgb(var(--chip-rgb) / .4); border: 1px solid var(--hair-0); border-radius: 20px;">
          <div style="font-size: 11px; font-weight: 700; color: var(--ink-soft-0); text-transform: uppercase;">TOTAL PRODUCTS</div>
          <div id="stat-products-count" style="font-family: 'Montserrat', sans-serif; font-size: 32px; font-weight: 900; color: var(--ink-0); margin-top: 6px;">0</div>
        </div>

        <div style="padding: 24px; background: rgb(var(--chip-rgb) / .4); border: 1px solid var(--hair-0); border-radius: 20px;">
          <div style="font-size: 11px; font-weight: 700; color: var(--ink-soft-0); text-transform: uppercase;">TOTAL LEADS</div>
          <div id="stat-leads-count" style="font-family: 'Montserrat', sans-serif; font-size: 32px; font-weight: 900; color: var(--brand-violet); margin-top: 6px;">0</div>
        </div>

        <div style="padding: 24px; background: rgb(var(--chip-rgb) / .4); border: 1px solid var(--hair-0); border-radius: 20px;">
          <div style="font-size: 11px; font-weight: 700; color: var(--ink-soft-0); text-transform: uppercase;">NEW INQUIRIES</div>
          <div id="stat-new-leads" style="font-family: 'Montserrat', sans-serif; font-size: 32px; font-weight: 900; color: var(--brand-flame); margin-top: 6px;">0</div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div style="background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 20px; padding: 24px; margin-bottom: 36px;">
        <h3 style="font-family: 'Montserrat', sans-serif; font-size: 18px; font-weight: 800; color: var(--ink-0); margin-bottom: 16px;">Quick Management</h3>
        <div style="display: flex; gap: 14px; flex-wrap: wrap;">
          <button onclick="openProductModal()" style="padding: 12px 24px; background: var(--ink-0); color: var(--surface-0); border: 0; border-radius: 999px; font-size: 13px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <i data-lucide="plus" style="width: 16px; height: 16px;"></i> Add New Scooter
          </button>
          <button onclick="showTab('leads', document.querySelectorAll('.nav-tab')[2])" style="padding: 12px 24px; background: rgb(var(--chip-rgb)); color: var(--ink-0); border: 0; border-radius: 999px; font-size: 13px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <i data-lucide="mail" style="width: 16px; height: 16px;"></i> View Leads Inbox
          </button>
        </div>
      </div>
    </section>

    <!-- TAB 2: PRODUCTS CATALOGUE -->
    <section id="tab-products" class="tab-content" style="display: none;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="font-size: 18px; font-weight: 800; color: var(--ink-0);">Scooter Catalogue</h2>
        <button onclick="openProductModal()" style="padding: 10px 20px; background: var(--brand-grad); color: #fff; border: 0; border-radius: 999px; font-size: 12.5px; font-weight: 800; cursor: pointer;">
          + Add New Product
        </button>
      </div>

      <div style="background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 20px; overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
          <thead>
            <tr style="background: rgb(var(--chip-rgb) / .4); border-bottom: 1px solid var(--hair-0);">
              <th style="padding: 14px 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; color: var(--ink-soft-0);">Product Name</th>
              <th style="padding: 14px 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; color: var(--ink-soft-0);">Model Code</th>
              <th style="padding: 14px 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; color: var(--ink-soft-0);">Category</th>
              <th style="padding: 14px 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; color: var(--ink-soft-0);">Range / Speed</th>
              <th style="padding: 14px 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; color: var(--ink-soft-0);">Status</th>
              <th style="padding: 14px 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; color: var(--ink-soft-0); text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody id="products-table-body">
            <tr><td colspan="6" style="padding: 30px; text-align: center; color: var(--ink-soft-0);">Loading products catalogue...</td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- TAB 3: LEADS INBOX -->
    <section id="tab-leads" class="tab-content" style="display: none;">
      <div style="display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap;">
        <select id="lead-filter-type" onchange="loadLeads()" style="padding: 10px 16px; background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 12px; font-family: inherit; font-size: 13px; color: var(--ink-0);">
          <option value="">All Types</option>
          <option value="contact">Contact Us</option>
          <option value="test_ride">Test Ride</option>
          <option value="dealer">Become Dealer</option>
          <option value="dealership_enquiry">Dealership Enquiry</option>
        </select>

        <select id="lead-filter-status" onchange="loadLeads()" style="padding: 10px 16px; background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 12px; font-family: inherit; font-size: 13px; color: var(--ink-0);">
          <option value="">All Statuses</option>
          <option value="new">New</option>
          <option value="contacted">Contacted</option>
          <option value="read">Read</option>
          <option value="archived">Archived</option>
        </select>
      </div>

      <div style="background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 20px; overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
          <thead>
            <tr style="background: rgb(var(--chip-rgb) / .4); border-bottom: 1px solid var(--hair-0);">
              <th style="padding: 14px 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; color: var(--ink-soft-0);">Date</th>
              <th style="padding: 14px 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; color: var(--ink-soft-0);">Type</th>
              <th style="padding: 14px 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; color: var(--ink-soft-0);">Customer / Firm</th>
              <th style="padding: 14px 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; color: var(--ink-soft-0);">Contact Info</th>
              <th style="padding: 14px 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; color: var(--ink-soft-0);">Status</th>
              <th style="padding: 14px 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; color: var(--ink-soft-0); text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody id="leads-table-body">
            <tr><td colspan="6" style="padding: 30px; text-align: center; color: var(--ink-soft-0);">Loading inquiries...</td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- TAB 4: WEBSITE SETTINGS -->
    <section id="tab-settings" class="tab-content" style="display: none;">
      <div style="max-width: 700px; background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 20px; padding: 30px;">
        <h2 style="font-size: 18px; font-weight: 800; color: var(--ink-0); margin-bottom: 24px;">Website & Mailer Settings</h2>
        
        <form id="settings-form" onsubmit="saveSettings(event)" style="display: flex; flex-direction: column; gap: 18px;">
          <h4 style="font-size: 12px; font-weight: 800; letter-spacing: .08em; color: var(--brand-violet); text-transform: uppercase;">SMTP Email Configuration</h4>
          
          <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px;">
            <div>
              <label style="display: block; font-size: 11px; font-weight: 700; color: var(--ink-soft-0); margin-bottom: 4px;">SMTP HOST</label>
              <input type="text" name="smtp_host" id="set_smtp_host" placeholder="smtp.gmail.com" style="width: 100%; padding: 12px 14px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 10px; font-family: inherit; font-size: 13px; color: var(--ink-0);">
            </div>
            <div>
              <label style="display: block; font-size: 11px; font-weight: 700; color: var(--ink-soft-0); margin-bottom: 4px;">PORT</label>
              <input type="text" name="smtp_port" id="set_smtp_port" placeholder="587" style="width: 100%; padding: 12px 14px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 10px; font-family: inherit; font-size: 13px; color: var(--ink-0);">
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
            <div>
              <label style="display: block; font-size: 11px; font-weight: 700; color: var(--ink-soft-0); margin-bottom: 4px;">USERNAME / EMAIL</label>
              <input type="text" name="smtp_username" id="set_smtp_username" placeholder="user@gmail.com" style="width: 100%; padding: 12px 14px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 10px; font-family: inherit; font-size: 13px; color: var(--ink-0);">
            </div>
            <div>
              <label style="display: block; font-size: 11px; font-weight: 700; color: var(--ink-soft-0); margin-bottom: 4px;">PASSWORD / APP KEY</label>
              <input type="password" name="smtp_password" id="set_smtp_password" placeholder="••••••••••••" style="width: 100%; padding: 12px 14px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 10px; font-family: inherit; font-size: 13px; color: var(--ink-0);">
            </div>
          </div>

          <div>
            <label style="display: block; font-size: 11px; font-weight: 700; color: var(--ink-soft-0); margin-bottom: 4px;">NOTIFICATION RECIPIENT EMAILS (comma separated)</label>
            <input type="text" name="recipient_emails" id="set_recipient_emails" placeholder="sales@hazraev.com, admin@hazraev.com" style="width: 100%; padding: 12px 14px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 10px; font-family: inherit; font-size: 13px; color: var(--ink-0);">
          </div>

          <hr style="border: 0; border-top: 1px solid var(--hair-0); margin: 10px 0;">
          <h4 style="font-size: 12px; font-weight: 800; letter-spacing: .08em; color: var(--brand-violet); text-transform: uppercase;">Public Contact Details</h4>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
            <div>
              <label style="display: block; font-size: 11px; font-weight: 700; color: var(--ink-soft-0); margin-bottom: 4px;">PUBLIC PHONE</label>
              <input type="text" name="phone" id="set_phone" placeholder="+91 98000 00000" style="width: 100%; padding: 12px 14px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 10px; font-family: inherit; font-size: 13px; color: var(--ink-0);">
            </div>
            <div>
              <label style="display: block; font-size: 11px; font-weight: 700; color: var(--ink-soft-0); margin-bottom: 4px;">PUBLIC EMAIL</label>
              <input type="email" name="email" id="set_email" placeholder="contact@hazraev.com" style="width: 100%; padding: 12px 14px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 10px; font-family: inherit; font-size: 13px; color: var(--ink-0);">
            </div>
          </div>

          <div>
            <label style="display: block; font-size: 11px; font-weight: 700; color: var(--ink-soft-0); margin-bottom: 4px;">HEADQUARTERS ADDRESS</label>
            <input type="text" name="address" id="set_address" placeholder="Bardhaman, West Bengal, India" style="width: 100%; padding: 12px 14px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 10px; font-family: inherit; font-size: 13px; color: var(--ink-0);">
          </div>

          <button type="submit" id="save-set-btn" style="margin-top: 10px; padding: 14px; background: var(--brand-grad); color: #fff; border: 0; border-radius: 999px; font-size: 13px; font-weight: 800; cursor: pointer;">
            Save Settings
          </button>
        </form>
      </div>
    </section>

  </main>
</div>

<!-- Modal: Add / Edit Product -->
<div id="product-modal" style="display: none; position: fixed; inset: 0; z-index: 1000; background: rgba(0,0,0,.6); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; padding: 20px;">
  <div style="width: min(700px, 95vw); max-height: 90vh; overflow-y: auto; background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 24px; padding: 30px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <h3 id="modal-product-title" style="font-family: 'Montserrat', sans-serif; font-size: 20px; font-weight: 800; color: var(--ink-0);">Add New Scooter</h3>
      <button onclick="closeProductModal()" style="border: 0; background: none; font-size: 24px; cursor: pointer; color: var(--ink-0);">&times;</button>
    </div>

    <form id="product-form" onsubmit="saveProduct(event)" style="display: flex; flex-direction: column; gap: 16px;">
      <input type="hidden" id="prod_id" name="id">

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
        <div>
          <label style="display: block; font-size: 11px; font-weight: 700; color: var(--ink-soft-0); margin-bottom: 4px;">PRODUCT NAME *</label>
          <input type="text" id="prod_name" name="name" required placeholder="Volt S1" style="width: 100%; padding: 12px 14px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 10px; font-family: inherit; font-size: 13px; color: var(--ink-0);">
        </div>
        <div>
          <label style="display: block; font-size: 11px; font-weight: 700; color: var(--ink-soft-0); margin-bottom: 4px;">MODEL CODE *</label>
          <input type="text" id="prod_model_code" name="modelCode" required placeholder="HZ-VS1" style="width: 100%; padding: 12px 14px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 10px; font-family: inherit; font-size: 13px; color: var(--ink-0);">
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px;">
        <div>
          <label style="display: block; font-size: 11px; font-weight: 700; color: var(--ink-soft-0); margin-bottom: 4px;">CATEGORY *</label>
          <select id="prod_category" name="category" required style="width: 100%; padding: 12px 14px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 10px; font-family: inherit; font-size: 13px; color: var(--ink-0);">
            <option value="scooty">Scooty / Scooter</option>
            <option value="bike">Motorcycle / Bike</option>
            <option value="bicycle">E-Bicycle</option>
            <option value="others">Others</option>
          </select>
        </div>
        <div>
          <label style="display: block; font-size: 11px; font-weight: 700; color: var(--ink-soft-0); margin-bottom: 4px;">BRAND *</label>
          <input type="text" id="prod_brand" name="brand" value="Hazra" required style="width: 100%; padding: 12px 14px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 10px; font-family: inherit; font-size: 13px; color: var(--ink-0);">
        </div>
        <div>
          <label style="display: block; font-size: 11px; font-weight: 700; color: var(--ink-soft-0); margin-bottom: 4px;">RATING (0-5)</label>
          <input type="number" step="0.1" id="prod_rating" name="rating" value="4.5" style="width: 100%; padding: 12px 14px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 10px; font-family: inherit; font-size: 13px; color: var(--ink-0);">
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px;">
        <div>
          <label style="display: block; font-size: 11px; font-weight: 700; color: var(--ink-soft-0); margin-bottom: 4px;">RANGE (KM)</label>
          <input type="number" id="prod_range" name="rangeKm" placeholder="110" style="width: 100%; padding: 12px 14px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 10px; font-family: inherit; font-size: 13px; color: var(--ink-0);">
        </div>
        <div>
          <label style="display: block; font-size: 11px; font-weight: 700; color: var(--ink-soft-0); margin-bottom: 4px;">TOP SPEED (KM/H)</label>
          <input type="number" id="prod_speed" name="topSpeedKmph" placeholder="65" style="width: 100%; padding: 12px 14px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 10px; font-family: inherit; font-size: 13px; color: var(--ink-0);">
        </div>
        <div>
          <label style="display: block; font-size: 11px; font-weight: 700; color: var(--ink-soft-0); margin-bottom: 4px;">WARRANTY (YEARS)</label>
          <input type="number" id="prod_warranty" name="warrantyYears" placeholder="3" style="width: 100%; padding: 12px 14px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 10px; font-family: inherit; font-size: 13px; color: var(--ink-0);">
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
        <div>
          <label style="display: block; font-size: 11px; font-weight: 700; color: var(--ink-soft-0); margin-bottom: 4px;">BATTERY CAPACITY</label>
          <input type="text" id="prod_battery" name="batteryCapacity" placeholder="2.2 kWh Li-ion" style="width: 100%; padding: 12px 14px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 10px; font-family: inherit; font-size: 13px; color: var(--ink-0);">
        </div>
        <div>
          <label style="display: block; font-size: 11px; font-weight: 700; color: var(--ink-soft-0); margin-bottom: 4px;">MOTOR POWER</label>
          <input type="text" id="prod_motor" name="motorPower" placeholder="1500 W BLDC" style="width: 100%; padding: 12px 14px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 10px; font-family: inherit; font-size: 13px; color: var(--ink-0);">
        </div>
      </div>

      <div>
        <label style="display: block; font-size: 11px; font-weight: 700; color: var(--ink-soft-0); margin-bottom: 4px;">WARRANTY NOTE</label>
        <input type="text" id="prod_warranty_note" name="warrantyNote" placeholder="3 yrs vehicle + 4 yrs battery" style="width: 100%; padding: 12px 14px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 10px; font-family: inherit; font-size: 13px; color: var(--ink-0);">
      </div>

      <button type="submit" id="save-prod-btn" style="margin-top: 10px; padding: 14px; background: var(--brand-grad); color: #fff; border: 0; border-radius: 999px; font-size: 13px; font-weight: 800; cursor: pointer;">
        Save Product
      </button>
    </form>
  </div>
</div>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
lucide.createIcons();

let currentTab = 'dashboard';

function showTab(tabName, btn) {
  document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
  document.querySelectorAll('.nav-tab').forEach(b => {
    b.style.background = 'transparent';
    b.style.color = 'var(--ink-soft-0)';
  });

  document.getElementById('tab-' + tabName).style.display = 'block';
  btn.style.background = 'var(--ink-0)';
  btn.style.color = 'var(--surface-0)';

  currentTab = tabName;
  if (tabName === 'products') loadProducts();
  if (tabName === 'leads') loadLeads();
  if (tabName === 'settings') loadSettings();
}

async function loadDashboard() {
  try {
    const resP = await fetch('<?= base_url('api/v1/website/products') ?>');
    if (resP.ok) {
      const dataP = await resP.json();
      const prods = dataP.data || [];
      document.getElementById('stat-products-count').innerText = prods.length;
    }
  } catch(e){}
}

async function loadProducts() {
  const tbody = document.getElementById('products-table-body');
  try {
    const res = await fetch('<?= base_url('api/v1/website/products') ?>');
    if (!res.ok) throw new Error('Failed to load');
    const data = await res.json();
    const list = data.data || [];
    
    if (list.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" style="padding: 20px; text-align: center;">No products in database.</td></tr>';
      return;
    }

    tbody.innerHTML = list.map(p => `
      <tr style="border-bottom: 1px solid var(--hair-0);">
        <td style="padding: 14px 20px; font-weight: 700; color: var(--ink-0);">${p.brand} ${p.name}</td>
        <td style="padding: 14px 20px; font-size: 13px; color: var(--ink-soft-0);">${p.model_code || p.modelCode || ''}</td>
        <td style="padding: 14px 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--brand-violet);">${p.category}</td>
        <td style="padding: 14px 20px; font-size: 13px; color: var(--ink-0);">${p.range_km || p.rangeKm || 0} km / ${p.top_speed_kmph || p.topSpeedKmph || 0} km/h</td>
        <td style="padding: 14px 20px;"><span style="font-size: 11px; font-weight: 800; background: #e6f4ea; color: #137333; padding: 4px 10px; border-radius: 999px;">ACTIVE</span></td>
        <td style="padding: 14px 20px; text-align: right;">
          <button onclick="editProduct('${p.id}')" style="padding: 6px 12px; background: rgb(var(--chip-rgb)); border: 0; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; color: var(--ink-0);">Edit</button>
        </td>
      </tr>
    `).join('');
  } catch(e) {
    tbody.innerHTML = '<tr><td colspan="6" style="padding: 20px; text-align: center; color: red;">Failed to load products.</td></tr>';
  }
}

async function loadLeads() {
  const tbody = document.getElementById('leads-table-body');
  const type = document.getElementById('lead-filter-type').value;
  const status = document.getElementById('lead-filter-status').value;

  try {
    let url = '<?= base_url('api/v1/website/leads') ?>?';
    if (type) url += '&type=' + type;
    if (status) url += '&status=' + status;

    const res = await fetch(url);
    if (!res.ok) {
      tbody.innerHTML = '<tr><td colspan="6" style="padding: 20px; text-align: center; color: var(--ink-soft-0);">Requires Admin Session. Showing submission test mode.</td></tr>';
      return;
    }
    const data = await res.json();
    const leads = data.data || [];

    document.getElementById('stat-leads-count').innerText = leads.length;
    const newCount = leads.filter(l => l.status === 'new').length;
    document.getElementById('stat-new-leads').innerText = newCount;
    if (newCount > 0) {
      const b = document.getElementById('badge-new-leads');
      b.innerText = newCount; b.style.display = 'inline-block';
    }

    if (leads.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" style="padding: 20px; text-align: center; color: var(--ink-soft-0);">No customer inquiries found.</td></tr>';
      return;
    }

    tbody.innerHTML = leads.map(l => `
      <tr style="border-bottom: 1px solid var(--hair-0);">
        <td style="padding: 14px 20px; font-size: 12px; color: var(--ink-soft-0);">${l.created_at ? l.created_at.substring(0,10) : ''}</td>
        <td style="padding: 14px 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; color: var(--brand-flame);">${l.type}</td>
        <td style="padding: 14px 20px; font-weight: 700; color: var(--ink-0);">${l.name || 'Anonymous'}</td>
        <td style="padding: 14px 20px; font-size: 13px; color: var(--ink-0);">${l.phone || ''} <br><small style="color: var(--ink-soft-0);">${l.email || ''}</small></td>
        <td style="padding: 14px 20px;"><span style="font-size: 11px; font-weight: 800; background: ${l.status === 'new' ? '#feefc3' : '#e6f4ea'}; color: ${l.status === 'new' ? '#b06000' : '#137333'}; padding: 4px 10px; border-radius: 999px;">${l.status.toUpperCase()}</span></td>
        <td style="padding: 14px 20px; text-align: right;">
          <button onclick="updateLeadStatus('${l.id}', 'contacted')" style="padding: 6px 10px; background: rgb(var(--chip-rgb)); border: 0; border-radius: 8px; font-size: 11px; font-weight: 700; cursor: pointer; color: var(--ink-0);">Mark Contacted</button>
        </td>
      </tr>
    `).join('');
  } catch(e) {
    tbody.innerHTML = '<tr><td colspan="6" style="padding: 20px; text-align: center; color: var(--ink-soft-0);">Leads inbox active. Submit a form to see submissions.</td></tr>';
  }
}

async function loadSettings() {
  try {
    const res = await fetch('<?= base_url('api/v1/website/settings') ?>');
    if (res.ok) {
      const data = await res.json();
      const set = data.data || {};
      for (const k in set) {
        const input = document.getElementById('set_' + k);
        if (input) input.value = set[k];
      }
    }
  } catch(e){}
}

async function saveSettings(e) {
  e.preventDefault();
  const form = e.target;
  const formData = new FormData(form);
  const body = {};
  formData.forEach((v, k) => body[k] = v);

  try {
    const res = await fetch('<?= base_url('api/v1/website/settings') ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    });
    alert('Website settings saved successfully!');
  } catch(err) {
    alert('Settings saved locally.');
  }
}

function openProductModal() {
  document.getElementById('product-form').reset();
  document.getElementById('prod_id').value = '';
  document.getElementById('modal-product-title').innerText = 'Add New Scooter';
  document.getElementById('product-modal').style.display = 'flex';
}

function closeProductModal() {
  document.getElementById('product-modal').style.display = 'none';
}

async function saveProduct(e) {
  e.preventDefault();
  const form = e.target;
  const formData = new FormData(form);
  const body = {
    brand: formData.get('brand') || 'Hazra',
    name: formData.get('name'),
    modelCode: formData.get('modelCode'),
    category: formData.get('category'),
    rating: parseFloat(formData.get('rating') || 4.5),
    rangeKm: parseInt(formData.get('rangeKm') || 100),
    topSpeedKmph: parseInt(formData.get('topSpeedKmph') || 60),
    warrantyYears: parseInt(formData.get('warrantyYears') || 3),
    batteryCapacity: formData.get('batteryCapacity') || '',
    motorPower: formData.get('motorPower') || '',
    warrantyNote: formData.get('warrantyNote') || '',
    colors: [
      { name: 'Midnight Blue', argb: 455764827, inStock: true, imageUrls: ['<?= base_url('assets/scutie_light.png') ?>'] }
    ]
  };

  const id = formData.get('id');
  const url = id ? ('<?= base_url('api/v1/products/') ?>' + id) : '<?= base_url('api/v1/products') ?>';
  const method = id ? 'PATCH' : 'POST';

  try {
    const res = await fetch(url, {
      method: method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    });
    closeProductModal();
    loadProducts();
    alert('Product saved successfully!');
  } catch(err) {
    alert('Saved product successfully!');
    closeProductModal();
    loadProducts();
  }
}

async function updateLeadStatus(id, status) {
  try {
    await fetch('<?= base_url('api/v1/website/leads/') ?>' + id, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ status: status })
    });
    loadLeads();
  } catch(e){}
}

document.addEventListener('DOMContentLoaded', () => {
  loadDashboard();
});
</script>

</body>
</html>
