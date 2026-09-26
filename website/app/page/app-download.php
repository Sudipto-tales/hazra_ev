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
    /* Download Modal */
    .download-modal-backdrop {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.65);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        box-sizing: border-box;
    }
    .download-modal-backdrop.is-active {
        display: flex;
    }
    .download-modal {
        background: #ffffff;
        width: 100%;
        max-width: 440px;
        border-radius: 1.25rem;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        overflow: hidden;
        position: relative;
        animation: modalSlideUp 0.25s ease-out;
    }
    @keyframes modalSlideUp {
        from {
            opacity: 0;
            transform: translateY(20px) scale(0.98);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
    .download-modal__header {
        background: linear-gradient(135deg, var(--hazra-purple, #4b0082) 0%, var(--hazra-purple-dark, #2b1055) 100%);
        color: #ffffff;
        padding: 1.5rem 1.5rem 1.25rem;
        position: relative;
    }
    .download-modal__title {
        margin: 0 0 0.25rem;
        font-size: 1.35rem;
        font-weight: 700;
        color: #ffffff;
        font-family: var(--font-head, sans-serif);
    }
    .download-modal__subtitle {
        margin: 0;
        font-size: 0.875rem;
        opacity: 0.85;
        color: #ffffff;
    }
    .download-modal__close {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: rgba(255, 255, 255, 0.15);
        border: none;
        color: #ffffff;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        font-size: 1.25rem;
        line-height: 1;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s;
    }
    .download-modal__close:hover {
        background: rgba(255, 255, 255, 0.3);
    }
    .download-modal__body {
        padding: 1.5rem;
    }
    .download-modal__field {
        margin-bottom: 1.25rem;
    }
    .download-modal__label {
        display: block;
        margin-bottom: 0.375rem;
        font-size: 0.875rem;
        font-weight: 600;
        color: #333333;
    }
    .download-modal__input {
        width: 100%;
        padding: 0.8rem 1rem;
        font-size: 1rem;
        border: 1.5px solid #dcdcdc;
        border-radius: 0.5rem;
        box-sizing: border-box;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .download-modal__input:focus {
        outline: none;
        border-color: var(--hazra-purple, #4b0082);
        box-shadow: 0 0 0 3px rgba(75, 0, 130, 0.15);
    }
    .download-modal__input--code {
        text-transform: uppercase;
        font-family: monospace;
        font-weight: 600;
        letter-spacing: 0.5px;
    }
    .download-modal__alert {
        display: none;
        padding: 0.75rem 1rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        margin-bottom: 1rem;
        align-items: center;
        gap: 0.5rem;
    }
    .download-modal__alert--error {
        background: #fff1f0;
        color: #cf1322;
        border: 1px solid #ffa39e;
    }
    .download-modal__alert--success {
        background: #f6ffed;
        color: #389e0d;
        border: 1px solid #b7eb8f;
    }
    .download-modal__actions {
        display: flex;
        gap: 0.75rem;
        margin-top: 0.5rem;
    }
    .download-modal__btn {
        flex: 1;
        padding: 0.875rem 1rem;
        font-size: 1rem;
        font-weight: 600;
        border-radius: 50px;
        border: none;
        cursor: pointer;
        transition: opacity 0.2s, transform 0.1s;
        text-align: center;
    }
    .download-modal__btn--primary {
        background: linear-gradient(135deg, var(--hazra-purple, #4b0082) 0%, #6a11cb 100%);
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(106, 17, 203, 0.35);
    }
    .download-modal__btn--primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
    .download-modal__btn--secondary {
        background: #f0f0f0;
        color: #555555;
    }
    .download-modal__btn--secondary:hover {
        background: #e4e4e4;
    }
    @media (max-width: 480px) {
        .download-modal-backdrop {
            align-items: flex-end;
            padding: 0;
        }
        .download-modal {
            max-width: 100%;
            border-radius: 1.5rem 1.5rem 0 0;
        }
    }
</style>

<div class="download-hero">
    <div class="app-icon">
        <i class="fa fa-mobile-alt"></i>
    </div>
    
    <?php if ($release): ?>
        <h1>Hazra EV App</h1>
        <div class="subtitle">Version <?= e($release['version_name']) ?> &bull; <?= date('M j, Y', strtotime($release['published_at'] ?? $release['created_at'])) ?></div>
        
        <button type="button" class="btn-download" id="openDownloadModalBtn">
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
<!-- Verification Download Modal -->
<div class="download-modal-backdrop" id="downloadModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="download-modal">
        <div class="download-modal__header">
            <h3 class="download-modal__title" id="modalTitle">Employee Verification</h3>
            <p class="download-modal__subtitle">Hazra EV Mobile App &bull; v<?= e($release['version_name']) ?></p>
            <button type="button" class="download-modal__close" id="closeDownloadModalBtn" aria-label="Close">&times;</button>
        </div>
        <div class="download-modal__body">
            <div id="modalAlert" class="download-modal__alert"></div>

            <form id="downloadGateForm" autocomplete="on">
                <div class="download-modal__field">
                    <label class="download-modal__label" for="empCodeInput">Employee ID</label>
                    <input type="text" id="empCodeInput" name="employee_code" class="download-modal__input download-modal__input--code" placeholder="e.g. EMP-1042" required autofocus>
                </div>
                <div class="download-modal__field">
                    <label class="download-modal__label" for="mobileInput">Registered Mobile Number</label>
                    <input type="tel" id="mobileInput" name="mobile" class="download-modal__input" placeholder="e.g. 98XXXXXXXX" inputmode="tel" maxlength="15" required>
                </div>
                <div class="download-modal__actions">
                    <button type="button" class="download-modal__btn download-modal__btn--secondary" id="cancelModalBtn">Cancel</button>
                    <button type="submit" class="download-modal__btn download-modal__btn--primary" id="submitDownloadBtn">
                        <span id="btnText"><i class="fa fa-shield-alt"></i> Verify & Download</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
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

<?php if ($release): ?>
<script>
(function() {
    'use strict';

    const modal = document.getElementById('downloadModal');
    const openBtn = document.getElementById('openDownloadModalBtn');
    const closeBtn = document.getElementById('closeDownloadModalBtn');
    const cancelBtn = document.getElementById('cancelModalBtn');
    const form = document.getElementById('downloadGateForm');
    const empInput = document.getElementById('empCodeInput');
    const mobileInput = document.getElementById('mobileInput');
    const alertBox = document.getElementById('modalAlert');
    const submitBtn = document.getElementById('submitDownloadBtn');
    const btnText = document.getElementById('btnText');
    const requestUrl = <?= json_encode(base_url('/api/v1/app-releases/' . $release['id'] . '/request-download')) ?>;

    function showAlert(msg, isError) {
        if (!alertBox) return;
        alertBox.textContent = msg;
        alertBox.className = 'download-modal__alert ' + (isError ? 'download-modal__alert--error' : 'download-modal__alert--success');
        alertBox.style.display = 'flex';
    }

    function hideAlert() {
        if (!alertBox) return;
        alertBox.style.display = 'none';
        alertBox.textContent = '';
    }

    function openModal() {
        if (!modal) return;
        hideAlert();
        modal.classList.add('is-active');
        document.body.style.overflow = 'hidden';
        setTimeout(() => empInput && empInput.focus(), 100);
    }

    function closeModal() {
        if (!modal) return;
        modal.classList.remove('is-active');
        document.body.style.overflow = '';
        if (form) form.reset();
        hideAlert();
    }

    if (openBtn) openBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal && modal.classList.contains('is-active')) {
            closeModal();
        }
    });

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            hideAlert();

            const code = empInput.value.trim().toUpperCase();
            const mob = mobileInput.value.trim();

            if (!code) {
                showAlert('Please enter your Employee ID (e.g. EMP-1042)', true);
                empInput.focus();
                return;
            }
            if (!mob) {
                showAlert('Please enter your registered mobile number', true);
                mobileInput.focus();
                return;
            }

            submitBtn.disabled = true;
            btnText.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Verifying...';

            try {
                const res = await fetch(requestUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        employee_code: code,
                        mobile: mob,
                    }),
                });

                const json = await res.json().catch(() => null);

                if (!res.ok) {
                    const msg = json?.error?.message || 'Verification failed. Please check your credentials.';
                    showAlert(msg, true);
                    submitBtn.disabled = false;
                    btnText.innerHTML = '<i class="fa fa-shield-alt"></i> Verify & Download';
                    return;
                }

                const empName = json?.data?.employee_name || 'Employee';
                const dlUrl = json?.data?.download_url;

                showAlert('Verified! Welcome, ' + empName + '. Starting download...', false);
                btnText.innerHTML = '<i class="fa fa-check"></i> Downloading...';

                setTimeout(() => {
                    if (dlUrl) {
                        window.location.href = dlUrl;
                    }
                    setTimeout(closeModal, 2500);
                }, 800);
            } catch (err) {
                showAlert('Network error. Please try again.', true);
                submitBtn.disabled = false;
                btnText.innerHTML = '<i class="fa fa-shield-alt"></i> Verify & Download';
            }
        });
    }
})();
</script>
<?php endif; ?>

<?php include __BASEDIR__ . '/app/components/footer.php'; ?>
