<?php
App::render('head', [
    'pageTitle'       => 'Careers — Hazra Electrical Bike',
    'pageDescription' => 'Join Hazra Electrical Bike. Build electric scooters people actually want to ride. Open roles across design, engineering, sales and service.',
    'extraCss'        => ['assets/css/styles/pages/career.css'],
]);

App::render('header', ['isStickyOnly' => true]);
?>

<!-- ========== UNIQUE PAGE CONTENT START ========== -->
<!-- BANNER -->
<section class="car-banner">
  <div class="car-banner__bg" aria-hidden="true"></div>
  <div class="car-banner__veil" aria-hidden="true"></div>
  <div class="car-banner__in">
    <p class="eyebrow"><i class="sq"></i>CAREERS</p>
    <h1 class="car-banner__title">Build with people<br>who build the ride.</h1>
    <p class="car-banner__lead">Designers, engineers, service leads and storytellers — join a team shipping electric scooters for real Indian roads.</p>
  </div>
</section>

<!-- HERO -->
<section class="car-hero">
  <div class="wrap">
    <div class="car-hero__grid">
      <div class="car-hero__art">
        <img src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=1000&q=80" alt="Hazra team illustration style group" loading="eager">
        <div class="car-hero__dots" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></div>
      </div>
      <div>
        <span class="car-hero__kicker">Careers at Hazra</span>
        <h1 class="car-hero__title">HELLO<br><span>PEOPLE!</span></h1>
        <p class="car-hero__lead">
          We build electric scooters for real Indian roads — and we need people who care
          about craft, honesty and riders. Designers, engineers, service leads and storytellers:
          if that sounds like you, come build with us.
        </p>
        <div class="car-hero__actions">
          <a class="btn btn--ink" href="#openings"><span>View openings</span><i data-lucide="arrow-down"></i></a>
          <a class="btn btn--ghost" href="mailto:careers@hazraev.com">careers@hazraev.com</a>
          <div class="car-hero__social">
            <a href="#" aria-label="Instagram"><i data-lucide="instagram"></i></a>
            <a href="#" aria-label="LinkedIn"><i data-lucide="linkedin"></i></a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- WHAT YOU GET — FAQ-style accordion -->
<section class="faq" id="what-you-get">
  <div class="wrap">
    <div class="faq__grid">
      <div class="faq__rail">
        <p class="eyebrow"><i class="sq"></i>WHAT YOU GET</p>
        <h2 class="sec__title reveal-up">Why people stay<br><span class="hl">at Hazra.</span></h2>
        <p class="faq__lead reveal-up">
          Benefits, culture and growth — the practical reasons builders choose to stay.
        </p>
        <a class="link faq__all" href="#openings">See open roles<i data-lucide="arrow-up-right"></i></a>
      </div>

      <ul class="faq__list">
        <li class="faq__item">
          <button class="faq__q" type="button" aria-expanded="false">
            <span>Meaningful work on real products</span>
            <i class="faq__ico" data-lucide="plus"></i>
          </button>
          <div class="faq__panel"><div class="faq__a">
            <p>Ship scooters riders use every day — frames, packs, service and stories — not decks that never leave the room. Your work shows up on the road.</p>
          </div></div>
        </li>
        <li class="faq__item">
          <button class="faq__q" type="button" aria-expanded="false">
            <span>Growth path and ownership</span>
            <i class="faq__ico" data-lucide="plus"></i>
          </button>
          <div class="faq__panel"><div class="faq__a">
            <p>Clear ownership, mentorship and room to stretch across functions as we scale. High performers take on more scope without waiting on a rigid ladder.</p>
          </div></div>
        </li>
        <li class="faq__item">
          <button class="faq__q" type="button" aria-expanded="false">
            <span>Fair pay and health cover</span>
            <i class="faq__ico" data-lucide="plus"></i>
          </button>
          <div class="faq__panel"><div class="faq__a">
            <p>Competitive salary, health cover and performance rewards tied to real outcomes — product quality, rider trust and network strength.</p>
          </div></div>
        </li>
        <li class="faq__item">
          <button class="faq__q" type="button" aria-expanded="false">
            <span>Builder culture</span>
            <i class="faq__ico" data-lucide="plus"></i>
          </button>
          <div class="faq__panel"><div class="faq__a">
            <p>Small teams, fast feedback and respect for craft — whether you design, engineer or run the service floor. Decisions stay close to the work.</p>
          </div></div>
        </li>
        <li class="faq__item">
          <button class="faq__q" type="button" aria-expanded="false">
            <span>Learning and tools</span>
            <i class="faq__ico" data-lucide="plus"></i>
          </button>
          <div class="faq__panel"><div class="faq__a">
            <p>Budget for courses, conferences and tools you need. We prefer people who keep learning over people who wait to be trained.</p>
          </div></div>
        </li>
        <li class="faq__item">
          <button class="faq__q" type="button" aria-expanded="false">
            <span>How hiring works</span>
            <i class="faq__ico" data-lucide="plus"></i>
          </button>
          <div class="faq__panel"><div class="faq__a">
            <p>Apply on a role card, talk to the team, and hear back within 7–10 working days. We value portfolio and proof of craft as much as CV length.</p>
          </div></div>
        </li>
      </ul>
    </div>
  </div>
</section>

<!-- OPEN ROLES -->
<section class="car-jobs" id="openings">
  <div class="wrap">
    <header class="sec__head">
      <p class="eyebrow"><i class="sq"></i>OPEN ROLES</p>
      <h2 class="sec__title">Find your next<br><span class="hl">chapter.</span></h2>
    </header>

    <div class="car-jobs__filters" role="tablist">
      <button class="car-filter is-on" type="button" data-filter="all">All</button>
      <button class="car-filter" type="button" data-filter="Engineering">Engineering</button>
      <button class="car-filter" type="button" data-filter="Design">Design</button>
      <button class="car-filter" type="button" data-filter="Sales">Sales</button>
      <button class="car-filter" type="button" data-filter="Service">Service</button>
    </div>

    <div class="car-jobs__grid" id="jobGrid">
      <button class="car-job" type="button" style="--i:0" data-dept="Engineering" data-job="me">
        <div class="car-job__top">
          <span class="car-job__dept">Engineering</span>
          <span class="car-job__type">Full-time</span>
        </div>
        <h3 class="car-job__title">Mechanical Engineer</h3>
        <div class="car-job__meta">
          <span><i data-lucide="map-pin"></i> Ghaziabad</span>
          <span><i data-lucide="briefcase"></i> 3–6 yrs</span>
        </div>
        <p class="car-job__excerpt">Own frame, suspension and packaging for the next CHALO platforms.</p>
        <span class="car-job__go">View role <i data-lucide="arrow-right"></i></span>
      </button>

      <button class="car-job" type="button" style="--i:1" data-dept="Engineering" data-job="ee">
        <div class="car-job__top">
          <span class="car-job__dept">Engineering</span>
          <span class="car-job__type">Full-time</span>
        </div>
        <h3 class="car-job__title">Electrical / Battery Engineer</h3>
        <div class="car-job__meta">
          <span><i data-lucide="map-pin"></i> Ghaziabad</span>
          <span><i data-lucide="briefcase"></i> 2–5 yrs</span>
        </div>
        <p class="car-job__excerpt">Battery packs, BMS integration and charging behaviour on real routes.</p>
        <span class="car-job__go">View role <i data-lucide="arrow-right"></i></span>
      </button>

      <button class="car-job" type="button" style="--i:2" data-dept="Design" data-job="id">
        <div class="car-job__top">
          <span class="car-job__dept">Design</span>
          <span class="car-job__type">Full-time</span>
        </div>
        <h3 class="car-job__title">Industrial Designer</h3>
        <div class="car-job__meta">
          <span><i data-lucide="map-pin"></i> Noida / Hybrid</span>
          <span><i data-lucide="briefcase"></i> 2–4 yrs</span>
        </div>
        <p class="car-job__excerpt">Form language, colour &amp; trim, and rider touchpoints across the line-up.</p>
        <span class="car-job__go">View role <i data-lucide="arrow-right"></i></span>
      </button>

      <button class="car-job" type="button" style="--i:3" data-dept="Sales" data-job="rs">
        <div class="car-job__top">
          <span class="car-job__dept">Sales</span>
          <span class="car-job__type">Full-time</span>
        </div>
        <h3 class="car-job__title">Regional Sales Manager</h3>
        <div class="car-job__meta">
          <span><i data-lucide="map-pin"></i> North India</span>
          <span><i data-lucide="briefcase"></i> 5+ yrs</span>
        </div>
        <p class="car-job__excerpt">Grow dealer network, targets and on-ground brand presence in your region.</p>
        <span class="car-job__go">View role <i data-lucide="arrow-right"></i></span>
      </button>

      <button class="car-job" type="button" style="--i:4" data-dept="Service" data-job="sm">
        <div class="car-job__top">
          <span class="car-job__dept">Service</span>
          <span class="car-job__type">Full-time</span>
        </div>
        <h3 class="car-job__title">Service Network Lead</h3>
        <div class="car-job__meta">
          <span><i data-lucide="map-pin"></i> Pan-India</span>
          <span><i data-lucide="briefcase"></i> 4–8 yrs</span>
        </div>
        <p class="car-job__excerpt">Train dealers, set SLA standards and keep riders moving after the sale.</p>
        <span class="car-job__go">View role <i data-lucide="arrow-right"></i></span>
      </button>

      <button class="car-job" type="button" style="--i:5" data-dept="Design" data-job="ux">
        <div class="car-job__top">
          <span class="car-job__dept">Design</span>
          <span class="car-job__type">Contract</span>
        </div>
        <h3 class="car-job__title">UI / Brand Designer</h3>
        <div class="car-job__meta">
          <span><i data-lucide="map-pin"></i> Remote / Noida</span>
          <span><i data-lucide="briefcase"></i> 1–3 yrs</span>
        </div>
        <p class="car-job__excerpt">Website, app surfaces and campaign systems aligned to the Hazra mark.</p>
        <span class="car-job__go">View role <i data-lucide="arrow-right"></i></span>
      </button>
    </div>
  </div>
</section>

<section class="car-cta">
  <div class="wrap">
    <h2 class="sec__title">Don’t see your role?</h2>
    <p>Send a short note and portfolio — we hire for attitude and craft year-round.</p>
    <a class="btn btn--ink" href="mailto:careers@hazraev.com"><span>careers@hazraev.com</span><i data-lucide="mail"></i></a>
  </div>
</section>

<!-- JOB MODAL -->
<div class="car-modal" id="jobModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle" hidden>
  <div class="car-modal__backdrop" data-close></div>
  <div class="car-modal__panel">
    <button class="car-modal__close" type="button" data-close aria-label="Close"><i data-lucide="x"></i></button>
    <div class="car-modal__body">
      <p class="car-modal__dept" id="modalDept">Engineering</p>
      <h2 class="car-modal__title" id="modalTitle">Role title</h2>
      <div class="car-modal__meta" id="modalMeta"></div>

      <div class="car-modal__block">
        <h4>About the role</h4>
        <p id="modalAbout"></p>
      </div>
      <div class="car-modal__block">
        <h4>What you’ll do</h4>
        <ul id="modalDo"></ul>
      </div>
      <div class="car-modal__block">
        <h4>What you bring</h4>
        <ul id="modalNeed"></ul>
      </div>

      <div class="car-modal__block">
        <h4>Reference contact</h4>
        <div class="car-modal__contact">
          <a href="mailto:careers@hazraev.com"><i data-lucide="mail"></i> careers@hazraev.com</a>
          <a href="tel:+919829016542"><i data-lucide="phone"></i> +91 98290 16542</a>
          <span><i data-lucide="map-pin"></i> Sahibabad Industrial Area, Ghaziabad, UP 201010</span>
        </div>
      </div>

      <div class="car-modal__block">
        <h4>Apply for this role</h4>
        <form class="car-form" id="applyForm">
          <input type="hidden" name="role" id="formRole">
          <div class="car-form__row">
            <label>Full name<input type="text" name="name" required placeholder="Your name"></label>
            <label>Email<input type="email" name="email" required placeholder="you@email.com"></label>
          </div>
          <div class="car-form__row">
            <label>Phone<input type="tel" name="phone" required placeholder="+91"></label>
            <label>Experience (years)<input type="number" name="exp" min="0" step="1" placeholder="3"></label>
          </div>
          <label>Portfolio / LinkedIn<input type="url" name="link" placeholder="https://"></label>
          <label>Cover note<textarea name="note" placeholder="A few lines on why this role fits you"></textarea></label>
          <label class="car-form__file">
            <input type="file" name="resume" accept=".pdf,.doc,.docx">
            <span id="fileLabel">Upload resume (PDF / DOC)</span>
          </label>
          <div class="car-form__actions">
            <button class="btn btn--ink" type="submit"><span>Submit application</span><i data-lucide="send"></i></button>
            <button class="btn btn--ghost" type="button" data-close>Cancel</button>
          </div>
          <p class="car-form__note">We reply within 7–10 working days. Your data is used only for hiring.</p>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();

  const io = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.classList.add('is-in');
        io.unobserve(e.target);
      }
    });
  }, { threshold: 0.12 });
  document.querySelectorAll('.car-job').forEach(el => io.observe(el));

  /* FAQ accordion */
  document.querySelectorAll('#what-you-get .faq__q').forEach(btn => {
    btn.addEventListener('click', () => {
      const item = btn.closest('.faq__item');
      const open = item.classList.contains('is-open');
      document.querySelectorAll('#what-you-get .faq__item.is-open').forEach(i => {
        i.classList.remove('is-open');
        i.querySelector('.faq__q')?.setAttribute('aria-expanded', 'false');
      });
      if (!open) {
        item.classList.add('is-open');
        btn.setAttribute('aria-expanded', 'true');
      }
    });
  });

  /* Filters */
  document.querySelectorAll('.car-filter').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.car-filter').forEach(b => b.classList.remove('is-on'));
      btn.classList.add('is-on');
      const f = btn.dataset.filter;
      document.querySelectorAll('.car-job').forEach(card => {
        const show = f === 'all' || card.dataset.dept === f;
        card.style.display = show ? '' : 'none';
      });
    });
  });

  /* Job data for modal */
  const JOBS = {
    me: {
      dept: 'Engineering', title: 'Mechanical Engineer',
      meta: [['map-pin', 'Ghaziabad'], ['briefcase', '3–6 yrs'], ['clock', 'Full-time']],
      about: 'Lead mechanical design for frame, suspension and body packaging on current and next-gen CHALO platforms. Work closely with electrical and industrial design.',
      do: ['Own CAD models and drawings for structural parts', 'Run DFM reviews with production partners', 'Validate prototypes on Indian road conditions', 'Document BOMs and engineering changes'],
      need: ['B.E. / B.Tech Mechanical or related', 'Hands-on CAD (SolidWorks / CATIA)', 'Experience with two-wheelers or automotive preferred', 'Comfortable on the shop floor']
    },
    ee: {
      dept: 'Engineering', title: 'Electrical / Battery Engineer',
      meta: [['map-pin', 'Ghaziabad'], ['briefcase', '2–5 yrs'], ['clock', 'Full-time']],
      about: 'Design and validate battery packs, BMS integration and charging behaviour so published range matches real rider routes.',
      do: ['Specify cells, pack layout and thermal strategy', 'Integrate BMS and charger protocols', 'Plan field tests and analyse telemetry', 'Support service with diagnostics playbooks'],
      need: ['Degree in Electrical / Electronics / related', 'Battery pack or EV powertrain experience', 'Strong safety and compliance mindset']
    },
    id: {
      dept: 'Design', title: 'Industrial Designer',
      meta: [['map-pin', 'Noida / Hybrid'], ['briefcase', '2–4 yrs'], ['clock', 'Full-time']],
      about: 'Shape the look and feel of Hazra scooters — proportion, surfaces, colour & trim, and everyday touchpoints.',
      do: ['Sketch and 3D concepts for new models', 'Build CMF directions with brand guidelines', 'Collaborate with engineering on feasibility', 'Present to leadership and partners'],
      need: ['Degree in Industrial / Product Design', 'Strong portfolio of physical products', 'Rhino / Alias / similar tools']
    },
    rs: {
      dept: 'Sales', title: 'Regional Sales Manager',
      meta: [['map-pin', 'North India'], ['briefcase', '5+ yrs'], ['clock', 'Full-time']],
      about: 'Grow revenue and dealer quality across your region — targets, relationships and on-ground brand presence.',
      do: ['Hit regional volume and mix targets', 'Recruit and coach dealers', 'Run local campaigns with marketing', 'Report pipeline and market feedback'],
      need: ['Proven two-wheeler or auto sales leadership', 'Willingness to travel extensively', 'Data-driven and people-first']
    },
    sm: {
      dept: 'Service', title: 'Service Network Lead',
      meta: [['map-pin', 'Pan-India'], ['briefcase', '4–8 yrs'], ['clock', 'Full-time']],
      about: 'Build a service network riders can trust — training, SLAs, spares flow and continuous improvement.',
      do: ['Define service standards and audits', 'Train dealer technicians', 'Improve first-time-fix rates', 'Feed product teams with field issues'],
      need: ['Service operations experience in auto / EV', 'Strong process and training skills', 'Customer empathy']
    },
    ux: {
      dept: 'Design', title: 'UI / Brand Designer',
      meta: [['map-pin', 'Remote / Noida'], ['briefcase', '1–3 yrs'], ['clock', 'Contract']],
      about: 'Craft digital and campaign surfaces that feel as considered as the scooters — web, app and brand systems.',
      do: ['Design website and product pages', 'Extend brand system for campaigns', 'Work with engineering on front-end polish', 'Maintain component library'],
      need: ['Strong UI portfolio (Figma)', 'Motion and illustration a plus', 'Interest in EV / mobility']
    }
  };

  const modal = document.getElementById('jobModal');
  const openModal = id => {
    const j = JOBS[id]; if (!j) return;
    document.getElementById('modalDept').textContent = j.dept;
    document.getElementById('modalTitle').textContent = j.title;
    document.getElementById('formRole').value = j.title;
    document.getElementById('modalAbout').textContent = j.about;
    document.getElementById('modalMeta').innerHTML = j.meta.map(([ic, t]) => `<span><i data-lucide="${ic}"></i>${t}</span>`).join('');
    document.getElementById('modalDo').innerHTML = j.do.map(t => `<li>${t}</li>`).join('');
    document.getElementById('modalNeed').innerHTML = j.need.map(t => `<li>${t}</li>`).join('');
    modal.hidden = false;
    requestAnimationFrame(() => modal.classList.add('is-open'));
    document.body.style.overflow = 'hidden';
    if (window.lucide) lucide.createIcons({ nodes: [modal] });
  };
  const closeModal = () => {
    modal.classList.remove('is-open');
    document.body.style.overflow = '';
    setTimeout(() => { modal.hidden = true; }, 300);
  };

  document.querySelectorAll('.car-job').forEach(card => {
    card.addEventListener('click', () => openModal(card.dataset.job));
  });
  modal.querySelectorAll('[data-close]').forEach(el => el.addEventListener('click', closeModal));
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

  document.querySelector('.car-form__file input')?.addEventListener('change', e => {
    const f = e.target.files?.[0];
    document.getElementById('fileLabel').textContent = f ? f.name : 'Upload resume (PDF / DOC)';
  });

  document.getElementById('applyForm')?.addEventListener('submit', e => {
    e.preventDefault();
    alert('Application received. We will get back within 7–10 working days.');
    closeModal();
    e.target.reset();
    document.getElementById('fileLabel').textContent = 'Upload resume (PDF / DOC)';
  });
});
</script>
<!-- ========== UNIQUE PAGE CONTENT END ========== -->

<?php App::render('footer'); ?>
