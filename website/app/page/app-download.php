<?php
/**
 * Public app download page for Hazra EV Employees.
 * Displays the latest published Android APK for employee verification & download.
 */

// Fetch latest published release
$release = db_fetch_one(
    "SELECT * FROM app_releases
     WHERE status = 'published' AND platform = 'android'
     ORDER BY version_code DESC LIMIT 1"
);

// Fetch recent releases for changelog (last 10 published + archived)
$history = db_fetch_all(
    "SELECT id, version_name, version_code, release_notes, status,
            file_size, published_at, created_at
     FROM app_releases
     WHERE platform = 'android' AND status IN ('published', 'archived')
     ORDER BY version_code DESC
     LIMIT 10"
);

App::render('head', [
    'pageTitle'       => 'Hazra EV Employee App — Official Download',
    'pageDescription' => 'Official Hazra EV Android App for employees. Manage EV services, track vehicles, monitor battery health, and stay connected.',
]);

App::render('header', ['isStickyOnly' => true]);

$iconUrl = base_url('assets/hazraev.png');
$dlBase  = rtrim(base_url('/'), '/') . '/api/v1/app-releases/';
?>

<!-- Tailwind CSS & Lucide Icons for App Download Page -->
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@0.544.0/dist/umd/lucide.min.js"></script>
<script>
  tailwind.config = {
    darkMode: ['selector', '[data-theme="dark"]'],
    theme: {
      extend: {
        fontFamily: {
          sans: ['"Plus Jakarta Sans"', '"Inter"', 'sans-serif'],
          serif: ['"Playfair Display"', 'serif'],
        },
        colors: {
          forest: {
            50: '#f0fdf4',
            100: '#dcfce7',
            200: '#bbf7d0',
            600: '#16a34a',
            700: '#15803d',
            800: '#166534',
            900: '#14532d',
            950: '#07241c',
            banner: '#0e382b',
            heroDark: '#08251e',
          },
          mint: {
            400: '#34d399',
            500: '#10b981',
            600: '#059669',
          },
          sand: '#F7F9F6',
        },
        boxShadow: {
          'phone': '0 25px 60px -15px rgba(10, 40, 30, 0.28), 0 0 0 1px rgba(0, 0, 0, 0.08)',
          'phone-hover': '0 35px 70px -15px rgba(10, 40, 30, 0.38), 0 0 0 1px rgba(16, 185, 129, 0.3)',
          'glass': '0 8px 32px 0 rgba(0, 0, 0, 0.1)',
          'soft': '0 10px 30px -5px rgba(0, 0, 0, 0.04), 0 5px 15px -5px rgba(0, 0, 0, 0.02)',
        },
        keyframes: {
          floatSlow: {
            '0%, 100%': { transform: 'translateY(0px)' },
            '50%': { transform: 'translateY(-10px)' },
          },
          floatMed: {
            '0%, 100%': { transform: 'translateY(0px)' },
            '50%': { transform: 'translateY(-16px)' },
          },
          pulseSubtle: {
            '0%, 100%': { opacity: '1', transform: 'scale(1)' },
            '50%': { opacity: '0.9', transform: 'scale(1.04)' },
          }
        },
        animation: {
          'float-slow': 'floatSlow 6s ease-in-out infinite',
          'float-med': 'floatMed 4.5s ease-in-out infinite',
          'pulse-subtle': 'pulseSubtle 3s ease-in-out infinite',
        }
      }
    }
  }
</script>

<style>
  /* Custom Phone Frame & UI components */
  .iphone-frame {
    border: 8px solid #1e2522;
    border-radius: 46px;
    position: relative;
    background: #fbfdfc;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(6, 38, 28, 0.35), 0 0 0 1.5px rgba(255,255,255,0.4) inset;
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
  }
  html[data-theme="dark"] .iphone-frame {
    border-color: #0b1d16;
    background: #091a14;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7), 0 0 0 1.5px rgba(16, 185, 129, 0.2) inset;
  }

  .iphone-notch {
    width: 86px;
    height: 20px;
    background: #1e2522;
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    border-bottom-left-radius: 12px;
    border-bottom-right-radius: 12px;
    z-index: 50;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
  }
  html[data-theme="dark"] .iphone-notch {
    background: #0b1d16;
  }

  .iphone-camera {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #081110;
  }

  .iphone-sensor {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: #112822;
  }

  .iphone-home-bar {
    position: absolute;
    bottom: 6px;
    left: 50%;
    transform: translateX(-50%);
    width: 80px;
    height: 4px;
    background: #cbd5e1;
    border-radius: 9999px;
    z-index: 40;
  }
  html[data-theme="dark"] .iphone-home-bar {
    background: #1e3a31;
  }

  /* Scroll reveal animations */
  .reveal-on-scroll {
    opacity: 0;
    transform: translateY(35px);
    transition: opacity 0.8s cubic-bezier(0.16, 1, 0.3, 1), transform 0.8s cubic-bezier(0.16, 1, 0.3, 1);
  }
  .reveal-on-scroll.is-visible {
    opacity: 1;
    transform: translateY(0);
  }

  /* Hide scrollbars in mockups */
  .no-scrollbar::-webkit-scrollbar {
    display: none;
  }
  .no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
  }
</style>

<div class="bg-[#F8FAF8] dark:bg-[#071913] text-slate-800 dark:text-slate-100 font-sans antialiased overflow-x-hidden selection:bg-emerald-600 selection:text-white pt-24 sm:pt-28 transition-colors duration-300">

  <!-- HERO SECTION -->
  <section id="hero" class="relative pt-8 pb-16 sm:pb-24 overflow-hidden">
    <!-- Ambient background gradient -->
    <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[700px] h-[500px] bg-gradient-to-tr from-emerald-100/60 to-emerald-200/30 dark:from-emerald-900/30 dark:to-emerald-950/20 blur-3xl pointer-events-none -z-10 rounded-full"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center reveal-on-scroll">

      <!-- Employee Access Eyebrow Badge -->
      <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-100/90 dark:bg-emerald-950/80 text-emerald-900 dark:text-emerald-300 text-xs font-bold uppercase tracking-wider mb-6 border border-emerald-300/60 dark:border-emerald-800/80 shadow-xs">
        <span class="w-2.5 h-2.5 rounded-full bg-emerald-600 dark:bg-emerald-400 animate-pulse"></span>
        HAZRA EV EMPLOYEE PORTAL APP
      </div>

      <!-- App Icon Showcase -->
      <div class="w-24 h-24 sm:w-28 sm:h-28 mx-auto mb-6 rounded-3xl bg-white dark:bg-forest-900 p-2.5 shadow-xl border border-emerald-100 dark:border-emerald-800 flex items-center justify-center transform hover:scale-105 transition-transform duration-300">
        <img src="<?= e($iconUrl) ?>" alt="Hazra EV App Icon" class="w-full h-full object-contain rounded-2xl">
      </div>

      <!-- Main Heading -->
      <h1 class="text-4xl sm:text-5xl md:text-6xl font-extrabold tracking-tight text-forest-950 dark:text-emerald-50 max-w-3xl mx-auto leading-[1.15]">
        Hazra EV Employee App <br class="hidden sm:inline" />
        <span class="text-emerald-700 dark:text-emerald-400 font-bold">Official Android Release</span>
      </h1>

      <p class="mt-4 text-slate-600 dark:text-slate-300 text-sm sm:text-base max-w-xl mx-auto leading-relaxed">
        Exclusive mobile application for authorized Hazra EV staff & field employees. Track vehicle telemetry, manage customer services, and log battery diagnostics.
      </p>

      <!-- Download Button & Meta Details -->
      <div class="mt-8 mb-12 flex flex-col items-center gap-4">
        <?php if ($release): ?>
          <button type="button" id="openDownloadModal" class="inline-flex items-center gap-3 px-8 py-4 bg-forest-900 hover:bg-forest-800 dark:bg-emerald-600 dark:hover:bg-emerald-500 active:scale-95 text-white font-bold text-base rounded-2xl shadow-xl shadow-forest-950/20 transition-all duration-200 cursor-pointer">
            <i data-lucide="download" class="w-5 h-5"></i>
            <span>Download Official APK</span>
            <span class="text-xs bg-emerald-500/30 text-emerald-200 px-2.5 py-0.5 rounded-full font-medium">v<?= e($release['version_name']) ?></span>
          </button>

          <div class="flex flex-wrap items-center justify-center gap-3 text-xs text-slate-500 dark:text-slate-400 font-medium">
            <span class="bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border border-emerald-300/40 dark:border-emerald-800/60 px-2.5 py-1 rounded-lg font-bold">
              Release: <?= date('M j, Y', strtotime($release['published_at'] ?? $release['created_at'])) ?>
            </span>
            <?php if (!empty($release['file_size'])): ?>
              <span class="bg-slate-200/80 dark:bg-forest-900/90 text-slate-700 dark:text-slate-300 border border-slate-300/40 dark:border-emerald-900/60 px-2.5 py-1 rounded-lg">
                Size: <?= round($release['file_size'] / 1048576, 1) ?> MB
              </span>
            <?php endif; ?>
            <?php if (!empty($release['checksum_sha256'])): ?>
              <span class="bg-slate-200/80 dark:bg-forest-900/90 text-slate-700 dark:text-slate-300 border border-slate-300/40 dark:border-emerald-900/60 px-2.5 py-1 rounded-lg font-mono">
                SHA-256: <?= e(substr($release['checksum_sha256'], 0, 12)) ?>...
              </span>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <button type="button" disabled class="inline-flex items-center gap-3 px-8 py-4 bg-slate-300 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-bold text-base rounded-2xl cursor-not-allowed">
            <i data-lucide="clock" class="w-5 h-5"></i>
            <span>App Release Coming Soon</span>
          </button>
        <?php endif; ?>
      </div>

      <!-- Triple Phone Mockup Showcase -->
      <div class="relative max-w-5xl mx-auto flex items-end justify-center pt-4 pb-8">

        <!-- LEFT PHONE: Service & Inspection Mockup -->
        <div class="hidden lg:block w-[265px] h-[520px] iphone-frame translate-x-12 translate-y-8 -rotate-6 opacity-90 shadow-2xl hover:rotate-0 hover:z-30 hover:opacity-100 hover:scale-105 transition-all duration-500">
          <div class="iphone-notch"><div class="iphone-camera"></div><div class="iphone-sensor"></div></div>
          <div class="p-4 pt-8 text-left text-xs bg-slate-50 h-full flex flex-col">
            <div class="flex justify-between items-center pb-3 border-b border-slate-200">
              <span class="font-bold text-slate-800 text-sm">Service Jobs</span>
              <i data-lucide="wrench" class="w-4 h-4 text-emerald-600"></i>
            </div>

            <!-- Job 1 -->
            <div class="mt-4 p-3 bg-white rounded-2xl shadow-sm border border-slate-100 space-y-2">
              <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold text-xs">HZ</div>
                <div>
                  <h4 class="font-bold text-slate-900 text-xs">Striker EV Inspection</h4>
                  <p class="text-[10px] text-slate-500">Ticket #78291 • Bardhaman Branch</p>
                </div>
              </div>
              <div class="flex justify-between items-center text-[10px] text-emerald-800 bg-emerald-50 px-2 py-1 rounded-lg font-semibold">
                <span>Status: In Progress</span>
                <span class="text-emerald-700">View Log</span>
              </div>
            </div>

            <!-- Job 2 -->
            <div class="mt-3 p-3 bg-white rounded-2xl shadow-sm border border-slate-100 space-y-2">
              <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold text-xs">DP</div>
                <div>
                  <h4 class="font-bold text-slate-900 text-xs">Battery Cell Diagnostics</h4>
                  <p class="text-[10px] text-slate-500">60V 30Ah Pack • Passed</p>
                </div>
              </div>
              <div class="text-[10px] text-slate-600 bg-slate-100 px-2 py-1 rounded-lg flex justify-between">
                <span>Assigned Tech: EMP-104</span>
                <span class="text-emerald-600 font-bold">Done</span>
              </div>
            </div>

            <!-- Daily Target Bar -->
            <div class="mt-auto bg-forest-900 text-white rounded-2xl p-3 shadow-md">
              <p class="text-[10px] text-emerald-200">Today's Inspection Target</p>
              <p class="text-sm font-bold">14 / 16 Vehicles Verified</p>
              <div class="w-full bg-emerald-800/80 rounded-full h-1.5 mt-2">
                <div class="bg-emerald-400 h-1.5 rounded-full w-[88%]"></div>
              </div>
            </div>
          </div>
          <div class="iphone-home-bar"></div>
        </div>

        <!-- CENTER PHONE: Main Hazra EV Dashboard -->
        <div class="w-[300px] sm:w-[320px] h-[590px] iphone-frame z-20 shadow-phone -translate-y-2 hover:scale-[1.02] transition-transform duration-300">
          <div class="iphone-notch"><div class="iphone-camera"></div><div class="iphone-sensor"></div></div>

          <div class="pt-8 px-4 pb-6 bg-[#f7faf8] h-full flex flex-col justify-between overflow-y-auto no-scrollbar">
            <div>
              <!-- Top Header inside Phone -->
              <div class="flex items-center justify-between py-2 text-xs">
                <div class="flex items-center gap-1.5 font-bold text-forest-950">
                  <img src="<?= e($iconUrl) ?>" class="w-5 h-5 object-contain">
                  Hazra EV Staff
                </div>
                <div class="flex items-center gap-2 text-slate-500">
                  <i data-lucide="bell" class="w-4 h-4"></i>
                  <span class="w-6 h-6 rounded-full bg-emerald-700 text-white font-bold text-[10px] flex items-center justify-center">EMP</span>
                </div>
              </div>

              <!-- Telemetry Search Banner -->
              <div class="relative mt-2">
                <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-2.5 text-slate-400"></i>
                <input type="text" placeholder="Search chassis, customer ID..." class="w-full bg-white text-xs pl-8 pr-3 py-2 rounded-xl border border-slate-200 focus:outline-none" readonly>
              </div>

              <!-- Main Banner Inside App -->
              <div class="mt-3 relative rounded-2xl overflow-hidden shadow-sm h-32 group">
                <img src="<?= e(base_url('assets/scooters/hazra_broucher_6_scooter_9.png')) ?>" class="w-full h-full object-cover bg-forest-950">
                <div class="absolute inset-0 bg-gradient-to-t from-forest-950 via-forest-950/40 to-transparent p-3 flex flex-col justify-end text-left text-white">
                  <span class="text-[9px] uppercase tracking-wider text-emerald-300 font-semibold">Live Telemetry</span>
                  <h3 class="text-xs font-bold leading-tight">Hazra Striker Pro &bull; Realtime Monitor</h3>
                </div>
              </div>

              <!-- Quick Actions Grid -->
              <div class="grid grid-cols-4 gap-2 mt-4 text-center">
                <div class="p-2 bg-white rounded-xl shadow-xs border border-slate-100 flex flex-col items-center">
                  <div class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center mb-1">
                    <i data-lucide="map-pin" class="w-3.5 h-3.5"></i>
                  </div>
                  <span class="text-[9px] font-medium text-slate-700">Track</span>
                </div>
                <div class="p-2 bg-white rounded-xl shadow-xs border border-slate-100 flex flex-col items-center">
                  <div class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center mb-1">
                    <i data-lucide="battery-charging" class="w-3.5 h-3.5"></i>
                  </div>
                  <span class="text-[9px] font-medium text-slate-700">Battery</span>
                </div>
                <div class="p-2 bg-white rounded-xl shadow-xs border border-slate-100 flex flex-col items-center">
                  <div class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center mb-1">
                    <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
                  </div>
                  <span class="text-[9px] font-medium text-slate-700">Warranty</span>
                </div>
                <div class="p-2 bg-white rounded-xl shadow-xs border border-slate-100 flex flex-col items-center">
                  <div class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center mb-1">
                    <i data-lucide="store" class="w-3.5 h-3.5"></i>
                  </div>
                  <span class="text-[9px] font-medium text-slate-700">Dealer</span>
                </div>
              </div>

              <!-- Dealer / Service Status -->
              <div class="mt-4 text-left">
                <div class="flex justify-between items-center text-xs mb-2">
                  <span class="font-bold text-slate-900">Active Service Request</span>
                  <span class="text-[10px] text-emerald-600 font-semibold">View</span>
                </div>
                <div class="bg-white p-2.5 rounded-xl border border-slate-100 flex items-center gap-3">
                  <div class="w-10 h-10 rounded-xl bg-emerald-900 text-emerald-400 flex items-center justify-center">
                    <i data-lucide="zap" class="w-5 h-5"></i>
                  </div>
                  <div class="flex-1">
                    <h4 class="text-xs font-bold text-slate-900">Speed Control ECU Update</h4>
                    <p class="text-[10px] text-slate-500">Bardhaman Main Service Hub</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Phone Bottom Navigation -->
            <div class="pt-2 border-t border-slate-200/80 flex justify-around text-slate-400">
              <i data-lucide="home" class="w-4 h-4 text-emerald-600"></i>
              <i data-lucide="layers" class="w-4 h-4"></i>
              <i data-lucide="wrench" class="w-4 h-4"></i>
              <i data-lucide="user" class="w-4 h-4"></i>
            </div>
          </div>
          <div class="iphone-home-bar"></div>
        </div>

        <!-- RIGHT PHONE: Dealer & Telemetry Status -->
        <div class="hidden lg:block w-[265px] h-[520px] iphone-frame -translate-x-12 translate-y-8 rotate-6 opacity-90 shadow-2xl hover:rotate-0 hover:z-30 hover:opacity-100 hover:scale-105 transition-all duration-500">
          <div class="iphone-notch"><div class="iphone-camera"></div><div class="iphone-sensor"></div></div>
          <div class="p-3 pt-8 text-left text-xs bg-slate-50 h-full flex flex-col justify-between">

            <div class="flex items-center gap-2 pb-2.5 border-b border-slate-200">
              <i data-lucide="activity" class="w-5 h-5 text-emerald-600"></i>
              <div>
                <div class="text-[11px] font-bold text-slate-800">Live Telemetry Data</div>
                <div class="text-[9px] text-emerald-600 flex items-center gap-1">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                  Connected GPS & BMS
                </div>
              </div>
            </div>

            <div class="space-y-2.5 my-auto text-[11px]">
              <div class="bg-emerald-100 text-emerald-950 p-2.5 rounded-2xl rounded-tl-sm shadow-xs">
                <span class="font-bold block">Vehicle Location:</span>
                Ghordourchati, Bardhaman Hub (Active)
              </div>
              <div class="bg-white border border-slate-200 text-slate-800 p-2.5 rounded-2xl rounded-tr-sm shadow-xs">
                <span class="font-bold block">Battery Temperature:</span>
                28°C &bull; Normal Operating Range
              </div>
              <div class="bg-emerald-100 text-emerald-950 p-2.5 rounded-2xl rounded-tl-sm shadow-xs">
                <span class="font-bold block">Odometer:</span>
                1,420 km &bull; Next Service in 580 km
              </div>
            </div>

            <div class="bg-forest-900 text-white p-2 rounded-xl text-center font-semibold text-[10px]">
              Sync Complete
            </div>
          </div>
          <div class="iphone-home-bar"></div>
        </div>

      </div>
    </div>
  </section>

  <!-- FEATURES SECTION -->
  <section id="features" class="bg-forest-950 text-white py-20 sm:py-28 relative">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

      <div class="text-center max-w-2xl mx-auto mb-14 reveal-on-scroll">
        <span class="text-emerald-400 font-bold tracking-wider text-xs uppercase">Why Use the Employee App?</span>
        <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight mt-2 text-white">
          Powerful EV Fleet & Service Management
        </h2>
        <p class="text-slate-300 text-sm mt-3">
          Designed specifically for Hazra EV engineers, field staff, service technicians, and dealer managers.
        </p>
      </div>

      <!-- 4 Feature Cards Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 reveal-on-scroll">

        <!-- Card 1 -->
        <div class="bg-forest-900/60 p-6 rounded-3xl border border-emerald-800/60 hover:border-emerald-500/80 transition-all duration-300 flex flex-col justify-between">
          <div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center mb-5">
              <i data-lucide="map-pin" class="w-6 h-6"></i>
            </div>
            <h3 class="text-xl font-bold text-white mb-2">Live Tracking</h3>
            <p class="text-xs text-slate-300 leading-relaxed">
              Monitor electric vehicle location, speed, route history, and geofence alerts in real-time.
            </p>
          </div>
        </div>

        <!-- Card 2 -->
        <div class="bg-forest-900/60 p-6 rounded-3xl border border-emerald-800/60 hover:border-emerald-500/80 transition-all duration-300 flex flex-col justify-between">
          <div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center mb-5">
              <i data-lucide="wrench" class="w-6 h-6"></i>
            </div>
            <h3 class="text-xl font-bold text-white mb-2">Service History</h3>
            <p class="text-xs text-slate-300 leading-relaxed">
              Keep detailed maintenance records, customer service tickets, and component replacements.
            </p>
          </div>
        </div>

        <!-- Card 3 -->
        <div class="bg-forest-900/60 p-6 rounded-3xl border border-emerald-800/60 hover:border-emerald-500/80 transition-all duration-300 flex flex-col justify-between">
          <div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center mb-5">
              <i data-lucide="battery-charging" class="w-6 h-6"></i>
            </div>
            <h3 class="text-xl font-bold text-white mb-2">Battery Health</h3>
            <p class="text-xs text-slate-300 leading-relaxed">
              Check Li-ion & Lead-Graphene battery cell state, voltage levels, and thermal performance stats.
            </p>
          </div>
        </div>

        <!-- Card 4 -->
        <div class="bg-forest-900/60 p-6 rounded-3xl border border-emerald-800/60 hover:border-emerald-500/80 transition-all duration-300 flex flex-col justify-between">
          <div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center mb-5">
              <i data-lucide="store" class="w-6 h-6"></i>
            </div>
            <h3 class="text-xl font-bold text-white mb-2">Dealer Portal</h3>
            <p class="text-xs text-slate-300 leading-relaxed">
              Locate authorized Hazra EV dealership hubs, manage inventory allocations, and update stock.
            </p>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- INSTALLATION GUIDE SECTION -->
  <section id="guide" class="py-24 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="max-w-3xl mx-auto reveal-on-scroll">

      <div class="text-center mb-14">
        <span class="text-emerald-700 dark:text-emerald-400 text-xs font-bold uppercase tracking-wider">Simple Setup</span>
        <h2 class="text-3xl sm:text-4xl font-extrabold text-forest-950 dark:text-emerald-50 tracking-tight mt-1">
          Android Installation Guide
        </h2>
        <p class="text-slate-600 dark:text-slate-300 text-sm mt-2">Follow these 4 simple steps to install the app on your mobile device.</p>
      </div>

      <div class="space-y-4">

        <!-- Step 1 -->
        <div class="bg-white dark:bg-[#0c2019] p-6 rounded-2xl border border-slate-200 dark:border-emerald-900/60 shadow-soft flex items-start gap-5 transition-colors">
          <div class="w-10 h-10 rounded-full bg-forest-900 dark:bg-emerald-600 text-emerald-400 dark:text-white font-bold flex items-center justify-center shrink-0">
            1
          </div>
          <div>
            <h3 class="font-bold text-forest-950 dark:text-white text-base">Verify Employee Identity & Download APK</h3>
            <p class="text-xs text-slate-500 dark:text-slate-300 mt-1 leading-relaxed">
              Click the "Download Official APK" button above. Enter your registered Employee Code (e.g. EMP-1001) or registered mobile number to request the secure download link.
            </p>
          </div>
        </div>

        <!-- Step 2 -->
        <div class="bg-white dark:bg-[#0c2019] p-6 rounded-2xl border border-slate-200 dark:border-emerald-900/60 shadow-soft flex items-start gap-5 transition-colors">
          <div class="w-10 h-10 rounded-full bg-forest-900 dark:bg-emerald-600 text-emerald-400 dark:text-white font-bold flex items-center justify-center shrink-0">
            2
          </div>
          <div>
            <h3 class="font-bold text-forest-950 dark:text-white text-base">Enable "Install Unknown Apps"</h3>
            <p class="text-xs text-slate-500 dark:text-slate-300 mt-1 leading-relaxed">
              Go to your Android phone's <strong>Settings &rarr; Security &amp; Privacy</strong> and enable permission for Chrome/Browser to install apps from unknown sources.
            </p>
          </div>
        </div>

        <!-- Step 3 -->
        <div class="bg-white dark:bg-[#0c2019] p-6 rounded-2xl border border-slate-200 dark:border-emerald-900/60 shadow-soft flex items-start gap-5 transition-colors">
          <div class="w-10 h-10 rounded-full bg-forest-900 dark:bg-emerald-600 text-emerald-400 dark:text-white font-bold flex items-center justify-center shrink-0">
            3
          </div>
          <div>
            <h3 class="font-bold text-forest-950 dark:text-white text-base">Install the Package</h3>
            <p class="text-xs text-slate-500 dark:text-slate-300 mt-1 leading-relaxed">
              Open the downloaded APK file from your notification shade or Downloads folder and tap <strong>Install</strong>.
            </p>
          </div>
        </div>

        <!-- Step 4 -->
        <div class="bg-white dark:bg-[#0c2019] p-6 rounded-2xl border border-slate-200 dark:border-emerald-900/60 shadow-soft flex items-start gap-5 transition-colors">
          <div class="w-10 h-10 rounded-full bg-forest-900 dark:bg-emerald-600 text-emerald-400 dark:text-white font-bold flex items-center justify-center shrink-0">
            4
          </div>
          <div>
            <h3 class="font-bold text-forest-950 dark:text-white text-base">Launch &amp; Log In</h3>
            <p class="text-xs text-slate-500 dark:text-slate-300 mt-1 leading-relaxed">
              Open the Hazra EV app, log in using your staff credentials, and begin managing operations seamlessly.
              <span class="block text-[11px] text-slate-400 dark:text-slate-400 mt-1 font-medium">* Requires Android 6.0 (Marshmallow) or higher.</span>
            </p>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- DYNAMIC RELEASE NOTES / CHANGELOG SECTION -->
  <?php if (!empty($history)): ?>
  <section id="changelog" class="py-20 bg-[#F4F7F4] dark:bg-[#05140f] border-t border-slate-200 dark:border-emerald-950">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 reveal-on-scroll">

      <div class="text-center mb-12">
        <span class="text-emerald-700 dark:text-emerald-400 text-xs font-bold uppercase tracking-wider">Version History</span>
        <h2 class="text-3xl font-extrabold text-forest-950 dark:text-emerald-50 tracking-tight mt-1">
          Release Notes &amp; Updates
        </h2>
      </div>

      <div class="space-y-4">
        <?php foreach ($history as $log): ?>
          <div class="bg-white dark:bg-[#0c2019] p-6 rounded-2xl border border-slate-200 dark:border-emerald-900/60 shadow-xs transition-colors">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-3 border-b border-slate-100 dark:border-emerald-900/40">
              <div class="flex items-center gap-2">
                <span class="text-base font-bold text-forest-950 dark:text-white">v<?= e($log['version_name']) ?></span>
                <span class="text-xs bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 font-semibold px-2.5 py-0.5 rounded-full border border-emerald-200/50 dark:border-emerald-800/60">Code: <?= e($log['version_code']) ?></span>
                <?php if ($log['status'] === 'published'): ?>
                  <span class="text-[10px] bg-forest-900 dark:bg-emerald-600 text-white font-bold px-2 py-0.5 rounded-md">LATEST</span>
                <?php endif; ?>
              </div>
              <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                <?= date('F j, Y', strtotime($log['published_at'] ?? $log['created_at'])) ?>
              </span>
            </div>
            <div class="mt-3 text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
              <?= nl2br(e($log['release_notes'])) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

    </div>
  </section>
  <?php endif; ?>

</div>

<!-- EMPLOYEE VERIFICATION DOWNLOAD MODAL -->
<?php if ($release): ?>
<div id="dlModal" class="fixed inset-0 z-50 bg-black/60 dark:bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
  <div class="bg-white dark:bg-[#0b1f18] text-slate-800 dark:text-slate-100 border border-transparent dark:border-emerald-800/80 rounded-3xl max-w-md w-full p-6 shadow-2xl relative animate-in fade-in zoom-in-95 duration-200">
    <button type="button" id="dlCancel" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
      <i data-lucide="x" class="w-5 h-5"></i>
    </button>

    <div class="flex items-center gap-3 mb-4">
      <div class="w-12 h-12 rounded-2xl bg-emerald-100 dark:bg-emerald-950 text-forest-900 dark:text-emerald-300 flex items-center justify-center border border-emerald-200/50 dark:border-emerald-800/60">
        <i data-lucide="shield-check" class="w-6 h-6 text-emerald-800 dark:text-emerald-400"></i>
      </div>
      <div>
        <h3 class="text-lg font-bold text-forest-950 dark:text-white">Hazra EV Employee Download</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">App version <?= e($release['version_name']) ?> (Android)</p>
      </div>
    </div>

    <p class="text-xs text-slate-600 dark:text-slate-300 mb-4 bg-slate-50 dark:bg-[#061510] p-3 rounded-xl border border-slate-200 dark:border-emerald-900/60">
      To download the app, please verify your identity using your assigned <strong>Employee Code</strong> or registered <strong>Mobile Number</strong>.
    </p>

    <div class="space-y-3 text-xs">
      <div>
        <label class="block text-slate-700 dark:text-slate-300 font-bold mb-1">Employee Code</label>
        <input type="text" id="dlCode" placeholder="e.g. EMP-1001" class="w-full bg-slate-50 dark:bg-[#061510] border border-slate-300 dark:border-emerald-800 rounded-xl p-3 text-xs text-slate-800 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-emerald-600 dark:focus:border-emerald-400 focus:ring-1 focus:ring-emerald-600">
      </div>

      <div>
        <label class="block text-slate-700 dark:text-slate-300 font-bold mb-1">Or Mobile Number</label>
        <input type="tel" id="dlMobile" placeholder="e.g. 9749167562" inputmode="tel" class="w-full bg-slate-50 dark:bg-[#061510] border border-slate-300 dark:border-emerald-800 rounded-xl p-3 text-xs text-slate-800 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-emerald-600 dark:focus:border-emerald-400 focus:ring-1 focus:ring-emerald-600">
      </div>

      <div id="dlError" class="text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800 rounded-xl p-2.5 text-xs font-semibold hidden"></div>
    </div>

    <div class="mt-6 flex gap-3">
      <button type="button" id="dlCloseBtn" class="w-1/3 py-3 rounded-xl border border-slate-200 dark:border-emerald-800 text-slate-700 dark:text-slate-300 font-semibold text-xs hover:bg-slate-50 dark:hover:bg-emerald-950/60">Cancel</button>
      <button type="button" id="dlSubmit" class="w-2/3 py-3 rounded-xl bg-forest-900 hover:bg-forest-800 dark:bg-emerald-600 dark:hover:bg-emerald-500 text-white font-bold text-xs shadow-md transition flex items-center justify-center gap-2">
        <i data-lucide="download" class="w-4 h-4"></i>
        <span>Verify &amp; Download</span>
      </button>
    </div>
  </div>
</div>

<script>
(function () {
  var RELEASE_ID = <?= json_encode($release['id']) ?>;
  var BASE = <?= json_encode(rtrim(base_url('/'), '/') . '/') ?>;
  var modal = document.getElementById('dlModal');
  var errEl = document.getElementById('dlError');

  var openBtn = document.getElementById('openDownloadModal');
  if (openBtn) {
    openBtn.addEventListener('click', function () {
      errEl.style.display = 'none';
      errEl.classList.add('hidden');
      modal.classList.remove('hidden');
    });
  }

  function hideModal() {
    modal.classList.add('hidden');
  }

  document.getElementById('dlCancel')?.addEventListener('click', hideModal);
  document.getElementById('dlCloseBtn')?.addEventListener('click', hideModal);

  document.getElementById('dlSubmit')?.addEventListener('click', async function () {
    var code = document.getElementById('dlCode').value.trim();
    var mobile = document.getElementById('dlMobile').value.trim();

    if (!code && !mobile) {
      errEl.textContent = 'Please enter your Employee Code or Mobile Number.';
      errEl.style.display = 'block';
      errEl.classList.remove('hidden');
      return;
    }

    var btn = document.getElementById('dlSubmit');
    btn.disabled = true;
    btn.style.opacity = '0.7';

    try {
      var res = await fetch(BASE + 'api/v1/app-releases/' + RELEASE_ID + '/request-download', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ employee_code: code, mobile: mobile })
      });
      var json = await res.json();
      if (!res.ok) {
        throw new Error((json.error && json.error.message) || (json.message) || 'Verification failed. Please check your employee details.');
      }
      var url = (json.data && json.data.downloadUrl) || (json.downloadUrl);
      if (!url) throw new Error('No download URL returned from server.');
      window.location.href = url;
    } catch (e) {
      errEl.textContent = e.message || 'Verification failed';
      errEl.style.display = 'block';
      errEl.classList.remove('hidden');
      btn.disabled = false;
      btn.style.opacity = '1';
    }
  });
})();
</script>
<?php endif; ?>

<script>
  // Initialize Lucide Icons
  if (window.lucide && typeof window.lucide.createIcons === 'function') {
    window.lucide.createIcons();
  }

  // IntersectionObserver for scroll reveals
  const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
      }
    });
  }, { threshold: 0.1 });

  document.querySelectorAll('.reveal-on-scroll').forEach(el => {
    revealObserver.observe(el);
  });
</script>

<?php App::render('footer'); ?>
