(() => {
  'use strict';
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const page = document.querySelector('.pd-page');
  if (!page) return;
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
    const card = track.querySelector('.pd-feature');
    if (card) track.scrollBy({left: Number(button.dataset.featureStep) * (card.offsetWidth + 18), behavior: reducedMotion ? 'instant' : 'smooth'});
  }));
  const form = document.getElementById('productTestRide');
  const status = document.getElementById('rideStatus');
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    status.textContent = 'Sending your request...';
    try {
      const response = await fetch(form.action, {
        method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
        body: JSON.stringify(Object.fromEntries(new FormData(form)))
      });
      const result = await response.json();
      if (!response.ok || result.ok === false || result.success === false || result.error) throw new Error('Submission failed');
      status.textContent = 'Thank you! Our team will contact you to arrange your test ride.';
      form.reset();
    } catch (error) {
      status.textContent = 'We could not send your request. Please try again or contact your nearest dealer.';
    } finally { button.disabled = false; }
  });
})();
