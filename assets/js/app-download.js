(() => {
  'use strict';
  const config = JSON.parse(document.getElementById('releaseConfig').textContent);
  const modal = document.getElementById('dlModal');
  const error = document.getElementById('dlError');
  const submit = document.getElementById('dlSubmit');
  const code = document.getElementById('dlCode');
  const mobile = document.getElementById('dlMobile');
  let selected, opener, request, previousOverflow, background = [];
  const showError = message => { error.textContent = message; error.classList.remove('hidden'); };
  function close() {
    request?.abort(); request = null;
    modal.classList.add('hidden');
    document.body.style.overflow = previousOverflow || '';
    background.forEach(([element, inert]) => { element.inert = inert; }); background = [];
    submit.disabled = false; submit.querySelector('span').textContent = 'Verify & Download';
    opener?.focus(); selected = null;
  }
  document.addEventListener('click', event => {
    const button = event.target.closest('button[data-release-id]');
    if (!button || button.disabled) return;
    opener = button;
    selected = button.dataset.releaseId;
    document.getElementById('dlVersion').textContent = `App version ${button.dataset.versionName}`;
    code.value = ''; mobile.value = ''; error.classList.add('hidden');
    previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    // Isolate background controls while retaining the dialog's ancestors.
    let current = modal;
    while (current.parentElement && current.parentElement !== document.documentElement) {
      Array.from(current.parentElement.children).forEach(element => {
        if (element !== current && !['SCRIPT', 'STYLE', 'LINK'].includes(element.tagName)) {
          background.push([element, element.inert]); element.inert = true;
        }
      });
      current = current.parentElement;
    }
    modal.classList.remove('hidden'); code.focus();
  });
  document.getElementById('dlCancel').addEventListener('click', close);
  document.getElementById('dlCloseBtn').addEventListener('click', close);
  modal.addEventListener('click', event => { if (event.target === modal) close(); });
  modal.addEventListener('keydown', event => {
    if (event.key === 'Escape') { event.preventDefault(); close(); }
    if (event.key === 'Enter' && event.target.matches('input')) { event.preventDefault(); submit.click(); }
    if (event.key === 'Tab') {
      const controls = Array.from(modal.querySelectorAll('button:not(:disabled),input'));
      const first = controls[0], last = controls[controls.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
  });
  submit.addEventListener('click', async () => {
    if (!selected || submit.disabled) return;
    const employeeCode = code.value.trim(), number = mobile.value.replace(/\D/g, '');
    if (!employeeCode && !number) { showError('Please enter your Employee Code or Mobile Number.'); code.focus(); return; }
    if (number && !/^[0-9]{10,15}$/.test(number)) { showError('Enter a valid mobile number with 10 to 15 digits.'); mobile.focus(); return; }
    error.classList.add('hidden'); submit.disabled = true;
    submit.querySelector('span').textContent = 'Verifying...';
    const controller = new AbortController(); request = controller;
    const timeout = setTimeout(() => controller.abort(), 20000);
    try {
      const response = await fetch(`${config.base}api/v1/app-releases/${encodeURIComponent(selected)}/request-download`, {
        method: 'POST', credentials: 'same-origin', signal: controller.signal,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ employee_code: employeeCode, mobile: number })
      });
      const result = await response.json().catch(() => { throw new Error('The download service is temporarily unavailable. Please try again later.'); });
      if (request !== controller || controller.signal.aborted) return;
      if (!response.ok) throw new Error(result.error?.message || 'Verification failed. Check your employee details.');
      const url = new URL(result.data?.downloadUrl, location.href);
      if (url.origin !== location.origin || !url.searchParams.has('token')) throw new Error('Could not prepare the download. Please try again.');
      // An attachment navigation keeps the page available for another version.
      window.location.assign(url.href);
      close();
      document.getElementById('releaseLoadStatus').textContent = `Your download of v${result.data.versionName} is starting.`;
    } catch (failure) {
      if (request === controller) showError(failure.name === 'AbortError' ? 'Verification timed out. Please try again.' : (failure instanceof TypeError ? 'Unable to connect. Check your internet connection and try again.' : failure.message));
    } finally {
      clearTimeout(timeout);
      if (request === controller) { request = null; submit.disabled = false; submit.querySelector('span').textContent = 'Verify & Download'; }
    }
  });
  const list = document.getElementById('releaseList'), more = document.getElementById('releaseMore');
  const status = document.getElementById('releaseLoadStatus');
  let cursor = config.nextCursor;
  const element = (tag, className, text) => {
    const node = document.createElement(tag); node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  };
  function card(release) {
    const node = element('article', 'release-card'); node.id = `release-${release.id}`;
    const top = element('div', 'release-card__top');
    top.append(element('span', 'release-badge', 'PREVIOUS RELEASE'));
    const rawDate = (release.publishedAt || release.createdAt).replace(' ', 'T');
    const date = new Date(/[Zz]|[+-]\d\d:\d\d$/.test(rawDate) ? rawDate : rawDate + 'Z');
    top.append(element('time', '', Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })));
    const meta = element('div', 'release-card__meta');
    [`${(release.fileSize / 1048576).toFixed(1)} MB`, release.androidRequirement, `Build ${release.versionCode}`, release.channel].forEach(text => meta.append(element('span', '', text)));
    const notes = element('details', ''); notes.append(element('summary', '', 'Release notes'), element('p', '', release.releaseNotes || 'No release notes were provided for this version.'));
    const button = element('button', 'release-download', release.available ? 'Download this version ↓' : 'APK unavailable');
    button.type = 'button'; button.disabled = !release.available; button.dataset.releaseId = release.id; button.dataset.versionName = release.versionName;
    node.append(top, element('h3', '', `v${release.versionName}`), meta, notes, button);
    return node;
  }
  more.addEventListener('click', async () => {
    if (!cursor || more.disabled) return;
    more.disabled = true; more.textContent = 'Loading...'; status.textContent = '';
    try {
      const query = new URLSearchParams({ limit: '6', exclude: config.latestId, cursor });
      const response = await fetch(`${config.base}api/v1/app-releases?${query}`, { headers: { Accept: 'application/json' } });
      const result = await response.json().catch(() => { throw new Error('The download service is temporarily unavailable. Please try again later.'); });
      if (!response.ok) throw new Error(result.error?.message || 'Could not load more versions. Please try again.');
      result.data.forEach(release => { if (!document.getElementById(`release-${release.id}`)) list.append(card(release)); });
      cursor = result.meta?.nextCursor; more.hidden = !cursor;
      status.textContent = cursor ? 'More versions loaded.' : 'All previous versions are shown.';
    } catch (failure) { status.textContent = failure.message; }
    finally { more.disabled = false; more.textContent = 'Load more versions ↓'; }
  });
  const target = new URLSearchParams(location.search).get('release');
  async function selectLinkedRelease() {
    if (!target || !/^[a-f0-9-]{36}$/i.test(target)) return;
    let button = document.querySelector(`button[data-release-id="${target}"]`);
    while (!button && cursor) {
      const previous = cursor;
      more.click();
      while (more.disabled) await new Promise(resolve => setTimeout(resolve, 100));
      if (previous === cursor) break;
      button = document.querySelector(`button[data-release-id="${target}"]`);
    }
    if (button) { button.closest('article')?.classList.add('is-selected'); button.scrollIntoView({ block: 'center' }); if (!button.disabled) button.click(); }
    else status.textContent = 'This version is no longer available. Please choose another release.';
  }
  selectLinkedRelease();
  window.lucide?.createIcons();
  const reveals = document.querySelectorAll('.reveal-on-scroll');
  if (!('IntersectionObserver' in window) || matchMedia('(prefers-reduced-motion: reduce)').matches) reveals.forEach(node => node.classList.add('is-visible'));
  else {
    const observer = new IntersectionObserver(entries => entries.forEach(entry => { if (entry.isIntersecting) { entry.target.classList.add('is-visible'); observer.unobserve(entry.target); } }), { threshold: .1 });
    reveals.forEach(node => observer.observe(node));
  }
})();
