/* Popups — the cookie bar and the first-visit ads card.

   Two unrelated widgets share a screen because they are the only two things
   the site puts in front of a visitor before they have asked for anything, and
   whoever turns one off usually wants to see the state of the other. */
(function () {
    'use strict';

    const { util: U, store, fields: F, form: formLib, layout, toast, media } = window.TMH;

    /* The public site's root, absolute — see core/layout.js. */
    const SITE = window.TMH.api.base;

    const FREQUENCY = [
        { value: 'session', label: 'Once per visit' },
        { value: 'days:1', label: 'Once a day' },
        { value: 'days:7', label: 'Once a week' },
        { value: 'days:30', label: 'Once a month' },
        { value: 'always', label: 'Every page load — testing only' },
    ];

    let doc = null;
    let ctrl = null;

    window.TMH.boot(init);

    async function init() {
        doc = await store.getDoc('settings');
        const p = doc.popups || {};

        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'System' }, { label: 'Popups' }],
            title: 'Popups &',
            accent: 'Cookie Bar',
            sub: 'The two overlays the website shows uninvited. Both can be switched off here without a deploy.',
            actions: `
                <a class="btn btn--ghost" href="${SITE}" target="_blank" rel="noopener">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> View on site</a>`,
        });

        document.getElementById('view').innerHTML = `
            ${expiredBanner(p)}

            <form class="card" id="popupsForm" novalidate>
                ${F.section({
                    title: 'Cookie bar', icon: 'fa-cookie-bite',
                    sub: 'A small card in the corner on a first visit. Consent is remembered in a first-party cookie; nothing is sent anywhere until it is given.',
                    fields: [
                        F.toggle({ name: 'cookieEnabled', label: 'Show the cookie bar' }),
                        F.number({ name: 'cookieRemember', label: 'Remember the choice for', min: 1, max: 730,
                            hint: 'Days. After this the bar comes back.' }),
                        F.textarea({ name: 'cookieMessage', label: 'Message', rows: 3, max: 300,
                            hint: 'Plain language. Say what is actually stored.' }),
                        F.text({ name: 'cookieAcceptLabel', label: 'Accept button' }),
                        F.text({ name: 'cookieDeclineLabel', label: 'Decline button',
                            hint: 'Leave empty to offer no decline button.' }),
                        F.text({ name: 'cookiePolicyUrl', label: 'Policy link', wide: true,
                            placeholder: '/privacy' }),
                    ],
                })}

                ${F.divider()}

                ${F.section({
                    title: 'Ads popup', icon: 'fa-rectangle-ad',
                    sub: 'Shown once when a visitor first opens the site, inside the dates below. Use it for a camp, a new department or a notice — not for every announcement, or people stop reading it.',
                    fields: [
                        F.toggle({ name: 'adsEnabled', label: 'Show the ads popup' }),
                        F.select({ name: 'adsFrequency', label: 'Show it', options: FREQUENCY,
                            hint: 'How often the same visitor sees it again.' }),
                        F.text({ name: 'adsTitle', label: 'Title', wide: true, max: 80,
                            placeholder: 'Free Cardiac Screening Camp' }),
                        F.textarea({ name: 'adsBody', label: 'Body', rows: 3, max: 300 }),
                        F.media({ name: 'adsImage', label: 'Image', wide: true,
                            hint: 'Landscape, at least 900px wide. The card renders without one if left empty.' }),
                        F.text({ name: 'adsLink', label: 'Button link', placeholder: '/contact' }),
                        F.text({ name: 'adsLinkLabel', label: 'Button label', placeholder: 'Book a slot' }),
                        F.date({ name: 'adsStart', label: 'Starts' }),
                        F.date({ name: 'adsEnd', label: 'Ends',
                            hint: 'After this date the popup stops showing on its own.' }),
                        F.toggle({ name: 'adsDismissible', label: 'Let the visitor close it',
                            hint: 'Off means it can only be dismissed by following the link. Use sparingly.' }),
                    ],
                })}

                ${F.divider()}

                ${F.section({
                    title: 'Preview', icon: 'fa-eye',
                    sub: 'Roughly what a first-time visitor sees. Exact type and spacing come from the site’s own stylesheet.',
                    fields: [`<div class="field field--wide"><div id="popupPreview"></div></div>`],
                })}

                ${F.bar({ singleSave: true, saveLabel: 'Save popups' })}
            </form>`;

        ctrl = formLib.create({
            el: '#popupsForm',
            bar: '#formBar',
            onCancel: () => location.reload(),
            onSave: save,
        });

        ctrl.bind(p);
        media.wire(document);
        F.wirePreviews(document);

        paintPreview();
        document.getElementById('popupsForm')
            .addEventListener('input', U.debounce(paintPreview, 200));
        document.getElementById('popupsForm')
            .addEventListener('change', paintPreview);
    }

    /* An ads popup whose end date has passed is not broken — it has simply
       stopped. Saying so beats leaving someone to wonder why the toggle is on
       and nothing appears. */
    function expiredBanner(p) {
        if (!p.adsEnabled || !p.adsEnd) return '';
        const today = U.dateInput(new Date());
        if (p.adsEnd >= today) return '';
        return `
            <div class="banner banner--warn">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span class="grow"><b>The ads popup has expired.</b> It ended on
                    ${U.esc(U.fmtDate(p.adsEnd))} and is no longer shown. Move the end date
                    or switch it off.</span>
            </div>`;
    }

    function paintPreview() {
        const d = ctrl.collect();
        const box = document.getElementById('popupPreview');

        const ads = d.adsEnabled ? `
            <article class="card" style="max-width:340px;overflow:hidden;padding:0">
                ${d.adsImage
                    ? `<img src="${U.esc(d.adsImage)}" alt="" style="width:100%;height:120px;object-fit:cover">`
                    : ''}
                <div style="padding:var(--s4)">
                    <h4 style="font-family:var(--font-head);font-size:1rem">${U.esc(d.adsTitle || 'Untitled popup')}</h4>
                    <p class="text-sm mid mt-2">${U.esc(d.adsBody || '')}</p>
                    <div class="row gap-2 mt-4">
                        <span class="btn btn--primary btn--sm">${U.esc(d.adsLinkLabel || 'Learn more')}</span>
                        ${d.adsDismissible ? '<span class="btn btn--ghost btn--sm">Close</span>' : ''}
                    </div>
                </div>
            </article>`
            : '<p class="text-sm muted">Ads popup is off.</p>';

        const cookie = d.cookieEnabled ? `
            <article class="card" style="max-width:340px">
                <p class="text-sm mid">${U.esc(d.cookieMessage || '')}</p>
                <div class="row gap-2 mt-4">
                    <span class="btn btn--primary btn--sm">${U.esc(d.cookieAcceptLabel || 'Got it')}</span>
                    ${d.cookieDeclineLabel ? `<span class="btn btn--ghost btn--sm">${U.esc(d.cookieDeclineLabel)}</span>` : ''}
                </div>
            </article>`
            : '<p class="text-sm muted">Cookie bar is off.</p>';

        box.innerHTML = `<div class="row wrap gap-4" style="align-items:flex-start">${ads}${cookie}</div>`;
    }

    async function save(data) {
        if (data.adsEnabled && data.adsStart && data.adsEnd && data.adsEnd < data.adsStart) {
            toast.error('The end date is before the start date');
            return;
        }

        doc.popups = Object.assign({}, doc.popups, data);
        await store.setDoc('settings', doc);
        toast.success('Popups saved', {
            body: data.adsEnabled || data.cookieEnabled
                ? 'Live on the next page load.'
                : 'Both overlays are now off.',
        });
    }
}());
