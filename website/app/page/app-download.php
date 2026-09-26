<?php
/**
 * Public app download page.
 * Displays the latest published Android APK for user download.
 */

// Fetch latest published release
$release = db_fetch_one(
    "SELECT * FROM app_releases WHERE status = 'published' AND platform = 'android' ORDER BY version_code DESC LIMIT 1"
);

// Fetch recent releases for changelog (last 5 published + archived)
$changelog = db_fetch_all(
    "SELECT id, version_name, version_code, release_notes, status, published_at, created_at 
     FROM app_releases 
     WHERE platform = 'android' AND status IN ('published', 'archived') 
     ORDER BY version_code DESC 
     LIMIT 5"
);

$pageTitle = 'Download App';
$pageDescription = 'Download the Hazra EV mobile app for Android. Track your electric vehicle, manage services, and stay connected.';

// SEO override for head component
$seo = [
    'title' => $pageTitle . ' — Hazra EV',
    'description' => $pageDescription,
    'og_title' => $pageTitle . ' — Hazra EV',
    'og_description' => $pageDescription,
];

include __BASEDIR__ . '/app/components/head.php';
include __BASEDIR__ . '/app/components/header.php';
?>

<style>
    .download-hero {
        background: linear-gradient(135deg, var(--hazra-purple, #4b0082) 0%, var(--hazra-purple-dark, #2b1055) 100%);
        color: white;
        padding: 4rem 1rem;
        text-align: center;
        border-radius: 0 0 2rem 2rem;
        margin-bottom: 2rem;
    }
    .download-hero h1 {
        font-family: var(--font-head, sans-serif);
        font-size: 2.5rem;
        margin-bottom: 0.5rem;
        color: white;
    }
    .download-hero .subtitle {
        font-size: 1.25rem;
        opacity: 0.9;
        margin-bottom: 2rem;
    }
    .download-hero .app-icon {
        font-size: 4rem;
        background: white;
        color: var(--hazra-purple, #4b0082);
        width: 100px;
        height: 100px;
        line-height: 100px;
        border-radius: 20px;
        margin: 0 auto 1.5rem auto;
        box-shadow: 0 10px 20px rgba(0,0,0,0.2);
    }
    .btn-download {
        display: inline-block;
        background: var(--hazra-cyan, #00d2ff);
        color: #000;
        padding: 1rem 2.5rem;
        border-radius: 50px;
        font-size: 1.25rem;
        font-weight: bold;
        text-decoration: none;
        box-shadow: 0 5px 15px rgba(0,210,255,0.4);
        transition: transform 0.2s;
    }
    .btn-download:hover {
        transform: translateY(-2px);
        color: #000;
    }
    .btn-download.disabled {
        background: #ccc;
        color: #666;
        box-shadow: none;
        cursor: not-allowed;
    }
    .download-meta {
        margin-top: 1.5rem;
        font-size: 0.9rem;
        opacity: 0.8;
    }
    .download-meta .badge {
        background: var(--hazra-orange, #ff6b00);
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 10px;
        margin-right: 0.5rem;
        font-weight: bold;
    }
    
    .section-title {
        font-family: var(--font-head, sans-serif);
        text-align: center;
        margin-bottom: 2rem;
        font-size: 2rem;
    }

    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        padding: 1rem;
        max-width: 1000px;
        margin: 0 auto 3rem auto;
    }
    .feature-card {
        background: #fff;
        padding: 1.5rem;
        border-radius: 1rem;
        text-align: center;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }
    .feature-card i {
        font-size: 2.5rem;
        color: var(--hazra-purple, #4b0082);
        margin-bottom: 1rem;
    }
    .feature-card h3 {
        font-size: 1.25rem;
        margin-bottom: 0.5rem;
    }

    .guide-section, .changelog-section {
        max-width: 800px;
        margin: 0 auto 3rem auto;
        padding: 0 1rem;
    }
    
    .guide-step {
        display: flex;
        align-items: flex-start;
        margin-bottom: 1.5rem;
        background: #f9f9f9;
        padding: 1rem;
        border-radius: 10px;
    }
    .step-number {
        background: var(--hazra-purple, #4b0082);
        color: white;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        margin-right: 1rem;
        flex-shrink: 0;
    }
    
    .changelog-card {
        background: white;
        border: 1px solid #eee;
        padding: 1.5rem;
        border-radius: 10px;
        margin-bottom: 1rem;
    }
    .changelog-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        border-bottom: 1px solid #eee;
        padding-bottom: 0.5rem;
    }
    .changelog-version {
        font-weight: bold;
        font-size: 1.2rem;
        color: var(--hazra-purple, #4b0082);
    }
    .changelog-date {
        color: #666;
        font-size: 0.9rem;
    }
    </style>

<div class="download-hero">
    <div class="app-icon">
        <i class="fa fa-mobile-alt"></i>
    </div>
    
    <?php if ($release): ?>
        <h1>Hazra EV App</h1>
        <div class="subtitle">Version <?= e($release['version_name']) ?> &bull; <?= date('M j, Y', strtotime($release['published_at'] ?? $release['created_at'])) ?></div>
        
        <button type="button" class="btn-download" id="openDownloadModal">
            <i class="fa fa-download"></i> Download APK
        </button>
        
        <div class="download-meta">
            <?php if (!empty($release['file_size'])): ?>
                <span class="badge"><?= round($release['file_size'] / 1048576, 1) ?> MB</span>
            <?php endif; ?>
            
            <?php if (!empty($release['checksum_sha256'])): ?>
                <span>SHA-256: <?= e(substr($release['checksum_sha256'], 0, 16)) ?>...</span>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <h1>Hazra EV App</h1>
        <div class="subtitle">Coming Soon</div>
        
        <a href="#" class="btn-download disabled" onclick="return false;">
            Coming Soon
        </a>
    <?php endif; ?>
</div>

<?php if ($release): ?>
<div id="dlModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);align-items:center;justify-content:center;padding:1rem;">
  <div style="background:#fff;border-radius:16px;max-width:400px;width:100%;padding:1.5rem;">
    <h3 style="margin:0 0 0.5rem;">Download Hazra EV App</h3>
    <p style="color:#666;font-size:0.9rem;margin:0 0 1rem;">Enter employee code <strong>or</strong> mobile number</p>
    <label style="display:block;margin-bottom:0.25rem;font-size:0.85rem;">Employee code</label>
    <input type="text" id="dlCode" placeholder="EMP-1001" style="width:100%;padding:0.75rem;margin-bottom:0.75rem;border:1px solid #ddd;border-radius:8px;box-sizing:border-box;">
    <label style="display:block;margin-bottom:0.25rem;font-size:0.85rem;">Mobile number</label>
    <input type="tel" id="dlMobile" placeholder="9749167562" inputmode="tel" style="width:100%;padding:0.75rem;margin-bottom:0.75rem;border:1px solid #ddd;border-radius:8px;box-sizing:border-box;">
    <p id="dlError" style="color:#c00;font-size:0.85rem;display:none;margin:0 0 0.75rem;"></p>
    <div style="display:flex;gap:0.5rem;justify-content:flex-end;">
      <button type="button" id="dlCancel" style="padding:0.75rem 1rem;border-radius:8px;border:1px solid #ddd;background:#f5f5f5;">Cancel</button>
      <button type="button" id="dlSubmit" style="padding:0.75rem 1rem;border-radius:8px;border:0;background:#00d2ff;font-weight:bold;">Verify & Download</button>
    </div>
  </div>
</div>
<script>
(function () {
  var RELEASE_ID = <?= json_encode($release['id']) ?>;
  var BASE = <?= json_encode(rtrim(base_url('/'), '/') . '/') ?>;
  var modal = document.getElementById('dlModal');
  var errEl = document.getElementById('dlError');

  document.getElementById('openDownloadModal').addEventListener('click', function () {
    errEl.style.display = 'none';
    modal.style.display = 'flex';
  });
  document.getElementById('dlCancel').addEventListener('click', function () {
    modal.style.display = 'none';
  });
  document.getElementById('dlSubmit').addEventListener('click', async function () {
    var code = document.getElementById('dlCode').value.trim();
    var mobile = document.getElementById('dlMobile').value.trim();
    if (!code && !mobile) {
      errEl.textContent = 'Enter employee code or mobile number';
      errEl.style.display = 'block';
      return;
    }
    var btn = document.getElementById('dlSubmit');
    btn.disabled = true;
    try {
      var res = await fetch(BASE + 'api/v1/app-releases/' + RELEASE_ID + '/request-download', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ employee_code: code, mobile: mobile })
      });
      var json = await res.json();
      if (!res.ok) {
        throw new Error((json.error && json.error.message) || (json.message) || 'Verification failed');
      }
      var url = (json.data && json.data.downloadUrl) || (json.downloadUrl);
      if (!url) throw new Error('No download URL returned');
      window.location.href = url;
    } catch (e) {
      errEl.textContent = e.message || 'Failed';
      errEl.style.display = 'block';
      btn.disabled = false;
    }
  });
})();
</script>
<?php endif; ?>

<h2 class="section-title">Why Use the App?</h2>
<div class="features-grid">
    <div class="feature-card">
        <i class="fa fa-map-marker-alt"></i>
        <h3>Live Tracking</h3>
        <p>Monitor your EV's location and status in real-time.</p>
    </div>
    <div class="feature-card">
        <i class="fa fa-tools"></i>
        <h3>Service History</h3>
        <p>Keep track of all maintenance and service records.</p>
    </div>
    <div class="feature-card">
        <i class="fa fa-battery-full"></i>
        <h3>Battery Health</h3>
        <p>Check battery stats and maximize your driving range.</p>
    </div>
    <div class="feature-card">
        <i class="fa fa-store"></i>
        <h3>Nearby Dealers</h3>
        <p>Locate the nearest authorized Hazra EV dealers and service centers.</p>
    </div>
</div>

<div class="guide-section">
    <h2 class="section-title">Installation Guide</h2>
    
    <div class="guide-step">
        <div class="step-number">1</div>
        <div>
            <strong>Download the APK file</strong>
            <p>Tap the download button above to get the latest app installer on your Android device.</p>
        </div>
    </div>
    
    <div class="guide-step">
        <div class="step-number">2</div>
        <div>
            <strong>Enable "Unknown Sources"</strong>
            <p>Go to your phone's Settings &rarr; Security (or Privacy) and enable "Install from unknown apps" for your browser.</p>
        </div>
    </div>
    
    <div class="guide-step">
        <div class="step-number">3</div>
        <div>
            <strong>Install the App</strong>
            <p>Open the downloaded APK file from your notifications or Downloads folder and tap "Install".</p>
        </div>
    </div>
    
    <div class="guide-step">
        <div class="step-number">4</div>
        <div>
            <strong>Launch and Sign In</strong>
            <p>Open the Hazra EV app, log in with your credentials, and enjoy a seamless connected experience.</p>
            <small style="color:#666;">Note: This app requires Android 6.0 or later.</small>
        </div>
    </div>
</div>

<?php if (!empty($changelog)): ?>
<div class="changelog-section">
    <h2 class="section-title">Release Notes</h2>
    
    <?php foreach ($changelog as $log): ?>
        <div class="changelog-card">
            <div class="changelog-header">
                <span class="changelog-version">v<?= e($log['version_name']) ?> (<?= e($log['version_code']) ?>)</span>
                <span class="changelog-date"><?= date('F j, Y', strtotime($log['published_at'] ?? $log['created_at'])) ?></span>
            </div>
            <div class="changelog-notes"><?= nl2br(e($log['release_notes'])) ?></div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __BASEDIR__ . '/app/components/footer.php'; ?>
