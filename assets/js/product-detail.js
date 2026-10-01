(() => {
  'use strict';
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const page = document.querySelector('.pd-page');
  if (!page) return;
  window.updateProductColorDetails = color => {
    page.querySelectorAll('[data-feature-color]').forEach(card => {
      card.hidden = Boolean(card.dataset.featureColor && card.dataset.featureColor !== color?.id);
    });
    const empty = document.getElementById('featureEmpty');
    if (empty) empty.hidden = Boolean(page.querySelector('[data-feature-color]:not([hidden])'));
    const track = document.getElementById('featureTrack');
    if (track) track.scrollLeft = 0;
    const form = document.getElementById('productTestRide');
    if (form) {
      form.elements.color_id.value = color?.id || '';
      form.elements.color_name.value = color?.name || '';
    }
    const label = document.getElementById('bookingColorLabel');
    if (label && color) label.textContent = 'Selected color: ' + color.name + (color.in_stock ? '' : ' (Currently out of stock)');
  };
  window.updateProductColorDetails(window.__PRODUCT_COLORS__?.[0]);
  if ('IntersectionObserver' in window) {
    const revealObserver = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.remove('pd-reveal-pending');
          revealObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.08 });
    if (!reducedMotion) page.querySelectorAll('[data-reveal]').forEach(el => {
      el.classList.add('pd-reveal-pending');
      revealObserver.observe(el);
    });
    const sectionObserver = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        page.querySelectorAll('.pd-progress a').forEach(link => {
          const active = link.hash === '#' + entry.target.id;
          link.classList.toggle('is-active', active);
          if (active) link.setAttribute('aria-current', 'location');
          else link.removeAttribute('aria-current');
        });
      });
    }, { rootMargin: '-15% 0px -35% 0px', threshold: 0 });
    ['overview', 'showcase', 'specifications', 'test-ride'].forEach(id => {
      const section = document.getElementById(id);
      if (section) sectionObserver.observe(section);
    });
  }
  page.querySelectorAll('a[href^="#"]').forEach(link => link.addEventListener('click', event => {
    const target = document.getElementById(link.hash.slice(1));
    if (!target) return;
    event.preventDefault();
    target.scrollIntoView({behavior: reducedMotion ? 'instant' : 'smooth', block: 'start'});
    history.replaceState(null, '', link.hash);
  }));
  page.querySelectorAll('[data-feature-step]').forEach(button => button.addEventListener('click', () => {
    const track = document.getElementById('featureTrack');
    const card = track.querySelector('.pd-feature:not([hidden])');
    if (card) track.scrollBy({left: Number(button.dataset.featureStep) * (card.offsetWidth + 18), behavior: reducedMotion ? 'instant' : 'smooth'});
  }));
  const form = document.getElementById('productTestRide');
  const status = document.getElementById('rideStatus');
  let submissionKey = crypto.randomUUID();
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    const button = form.querySelector('button[type="submit"]');
    if (button.disabled) return;
    button.disabled = true;
    status.textContent = 'Sending your request...';
    const abortController = new AbortController();
    const timeout = setTimeout(() => abortController.abort(), 20000);
    try {
      const payload = Object.fromEntries(new FormData(form));
      payload.submission_key = submissionKey;
      const response = await fetch(form.action, {
        method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
        body: JSON.stringify(payload), signal: abortController.signal
      });
      const result = await response.json();
      if (!response.ok || result.ok === false || result.success === false || result.error) throw new Error(result.error?.message || 'We could not send your request. Please try again.');
      status.textContent = 'Thank you! Our team will contact you to arrange your test ride.';
      form.reset();
      submissionKey = crypto.randomUUID();
      const color = window.__PRODUCT_COLORS__?.[typeof currentColorIndex === 'number' ? currentColorIndex : 0];
      window.updateProductColorDetails(color);
    } catch (error) {
      status.textContent = error.name === 'AbortError' ? 'The request took too long. Please try again; your request will not be duplicated.' : error.message;
    } finally { clearTimeout(timeout); button.disabled = false; }
  });
})();
