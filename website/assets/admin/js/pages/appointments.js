/* Appointment requests — read-only archive.

   The hospital does not take bookings online. A visitor who clicks "Book an
   appointment" on a doctor card lands on the contact page with that doctor
   preselected, and the desk calls back; doctors whose "Appointments available"
   toggle is off show no link at all. So nothing on this screen writes: there
   is no slot to confirm and no patient to notify from here.

   The screen is kept because the records that already exist — and anything a
   future intake form collects — still have to be readable. Two views over the
   same rows: a table for searching, and a day view for "what did Thursday
   morning look like". The day view is not a calendar and does not pretend to
   be one; the hospital's real slots live in the HIS. */
(function () {
    'use strict';

    const { util: U, store, table, layout, modal } = window.TMH;

    const STATUS = [
        { value: 'all', label: 'All' },
        { value: 'pending', label: 'Pending' },
        { value: 'confirmed', label: 'Confirmed' },
        { value: 'completed', label: 'Completed' },
        { value: 'cancelled', label: 'Cancelled' },
    ];

    const TONE = {
        pending: 'warn', confirmed: 'info', completed: 'ok', cancelled: 'off',
    };

    const SLOTS = ['Morning', 'Afternoon', 'Evening'];

    let doctors = [];
    let departments = [];
    let day = U.param('day') || todayIso();

    window.TMH.boot(init);

    async function init() {
        [doctors, departments] = await Promise.all([
            store.all('doctors'),
            store.all('departments'),
        ]);

        const rows = await store.all('appointments');

        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Growth' }, { label: 'Appointments' }],
            title: 'Appointment requests',
            sub: 'A read-only record. The website does not take bookings — it points people at the contact page.',
            actions: `
                <div class="row gap-1">
                    <button type="button" class="btn ${isDayView() ? 'btn--ghost' : 'btn--soft'}" data-view="table">
                        <i class="fa-solid fa-table-list"></i> Table</button>
                    <button type="button" class="btn ${isDayView() ? 'btn--soft' : 'btn--ghost'}" data-view="day">
                        <i class="fa-solid fa-calendar-day"></i> Day</button>
                </div>`,
        });

        document.getElementById('pageHead').querySelectorAll('[data-view]').forEach((b) =>
            b.addEventListener('click', () => {
                U.setParams({ view: b.dataset.view === 'day' ? 'day' : null });
                window.location.reload();
            }));

        document.getElementById('view').innerHTML = `
            <div class="banner banner--info">
                <i class="fa-solid fa-circle-info"></i>
                <span><b>Nothing here can be changed.</b> Appointments are not taken online. A
                    doctor’s card links to the contact page only when
                    <a href="doctors">Appointments available</a> is on for that doctor, and the
                    desk calls the patient back. These rows are kept for reference.</span>
            </div>

            ${U.statStrip([
                ['fa-hourglass-half', 'red', rows.filter((r) => r.status === 'pending').length, 'Pending', 'Never confirmed'],
                ['fa-calendar-check', 'navy', rows.filter((r) => r.status === 'confirmed').length, 'Confirmed', 'A slot was given'],
                ['fa-calendar-day', 'blue', rows.filter((r) => dateOf(r) === todayIso() && r.status !== 'cancelled').length, 'Today', 'Asked or booked for today'],
                ['fa-calendar-xmark', 'magenta', rows.filter((r) => r.status === 'cancelled').length, 'Cancelled', ''],
            ])}
            <div id="body"></div>`;
        U.stagger(document.getElementById('view'));

        if (isDayView()) renderDay();
        else renderTable();
    }

    const isDayView = () => U.param('view') === 'day';

    /* ---------- table view ---------- */

    function renderTable() {
        document.getElementById('body').innerHTML = '<article class="card card--flush" id="listCard"></article>';

        table.create({
            mount: '#listCard',
            entity: 'appointments',
            searchFields: ['patientName', 'phone', 'email', 'reason'],
            searchPlaceholder: 'Search patient, phone or reason',
            statusOptions: STATUS,
            filters: [
                { key: 'departmentId', label: 'Department', options: departments.map((d) => ({ value: d.id, label: d.name })) },
                { key: 'doctorId', label: 'Doctor', options: doctors.map((d) => ({ value: d.id, label: d.name })) },
                { key: 'preferredSlot', label: 'Slot', options: SLOTS.map((s) => ({ value: s, label: s })) },
                {
                    key: 'when',
                    label: 'When',
                    options: [
                        { value: 'today', label: 'Today' },
                        { value: 'upcoming', label: 'Upcoming' },
                        { value: 'past', label: 'Past' },
                    ],
                    match: (r, v) => {
                        const d = dateOf(r);
                        if (v === 'today') return d === todayIso();
                        if (v === 'upcoming') return d > todayIso();
                        return d < todayIso();
                    },
                },
            ],
            sort: 'preferredDate',
            dir: 'asc',
            columns: [
                {
                    label: 'Patient', sort: 'patientName', width: '22%',
                    render: (r, s) => `
                        <div class="cell-media">
                            <span class="avatar" style="display:grid;place-items:center;font-size:11px;font-weight:700;color:var(--text-mid)">${U.esc(U.initials(r.patientName))}</span>
                            <span>
                                <span class="cell-main">${U.mark(r.patientName, s.q)}</span>
                                <span class="cell-sub">${U.esc(r.phone || r.email || 'No contact given')}</span>
                            </span>
                        </div>`,
                },
                {
                    label: 'With', width: '22%',
                    render: (r) => {
                        const doc = doctors.find((d) => d.id === r.doctorId);
                        const dep = departments.find((d) => d.id === r.departmentId);
                        return `
                            <span class="cell-main">${doc ? U.esc(doc.name) : '<span class="muted">No doctor named</span>'}</span>
                            <span class="cell-sub">${dep ? U.esc(dep.name) : 'General'}</span>`;
                    },
                },
                {
                    label: 'Asked for', sort: 'preferredDate', width: '16%',
                    render: (r) => `
                        <span class="cell-main">${U.esc(U.fmtDate(r.preferredDate))}</span>
                        <span class="cell-sub">${U.esc(r.preferredSlot || '—')}</span>`,
                },
                {
                    label: 'Confirmed for', width: '15%',
                    render: (r) => (r.confirmedSlot
                        ? `<span class="cell-main">${U.esc(r.confirmedSlot)}</span>`
                        : '<span class="muted">—</span>'),
                },
                { label: 'Reason', width: '15%', render: (r) => `<span class="clamp-2">${U.esc(r.reason || '—')}</span>` },
                {
                    label: 'Status', sort: 'status', width: '10%',
                    render: (r) => statusTag(r),
                },
            ],
            /* Read and call. No status changes, no delete, no bulk bar. */
            rowActions: (row) => [
                { label: 'Open', icon: 'fa-eye', onClick: () => openDrawer(row) },
                ...(row.phone ? [{ label: 'Call patient', icon: 'fa-phone', onClick: () => call(row) }] : []),
            ],
            onRowClick: openDrawer,
            empty: {
                icon: 'fa-calendar-check', title: 'No appointment requests',
                text: 'Nothing has been recorded. The site sends visitors to the contact page rather than booking them here.',
            },
        });
    }

    /* ---------- day view ---------- */

    function renderDay() {
        const rows = store.allSync('appointments')
            .filter((r) => dateOf(r) === day && r.status !== 'cancelled');

        document.getElementById('body').innerHTML = `
            <article class="card anim-item">
                <div class="card__head">
                    <h3>${U.esc(U.fmtDate(day))}${day === todayIso() ? ' <span class="chip">Today</span>' : ''}</h3>
                    <div class="grow"></div>
                    <div class="row gap-1">
                        <button type="button" class="icon-btn" data-day="-1" aria-label="Previous day"><i class="fa-solid fa-chevron-left"></i></button>
                        <input type="date" id="dayPick" value="${U.esc(day)}"
                               style="height:32px;padding:0 10px;border:1px solid var(--hairline);border-radius:var(--radius-sm);background:var(--surface-2);font-size:var(--fs-sm)">
                        <button type="button" class="icon-btn" data-day="1" aria-label="Next day"><i class="fa-solid fa-chevron-right"></i></button>
                        <button type="button" class="btn btn--ghost btn--sm" data-today>Today</button>
                    </div>
                </div>

                ${rows.length ? `
                <div class="dayview">
                    ${SLOTS.map((slot) => {
                        const inSlot = rows.filter((r) => (r.preferredSlot || 'Morning') === slot);
                        return `
                        <section class="dayview__col">
                            <div class="dayview__head">
                                <span>${U.esc(slot)}</span>
                                <span>${inSlot.length}</span>
                            </div>
                            ${inSlot.length ? inSlot.map((r) => {
                                const doc = doctors.find((d) => d.id === r.doctorId);
                                return `
                                <article class="slot-card slot-card--${U.esc(r.status)}" data-open="${U.esc(r.id)}"
                                         tabindex="0" role="button">
                                    <div class="slot-card__name">${U.esc(r.patientName)}</div>
                                    <div class="slot-card__meta">${doc ? U.esc(doc.name) : 'No doctor named'}</div>
                                    <div class="slot-card__meta">${r.confirmedSlot ? U.esc(r.confirmedSlot.slice(11) || r.confirmedSlot) : U.esc(r.phone || '')}</div>
                                    <div class="mt-2">${statusTag(r)}</div>
                                </article>`;
                            }).join('') : '<p class="text-xs muted">Nothing booked.</p>'}
                        </section>`;
                    }).join('')}
                </div>`
                : `
                <div class="empty">
                    <div class="empty__art"><i class="fa-solid fa-calendar-day"></i></div>
                    <h3>Nothing on this day</h3>
                    <p>No requests asked for ${U.esc(U.fmtDate(day))}.</p>
                </div>`}
            </article>`;

        U.stagger(document.getElementById('body'));

        const body = document.getElementById('body');

        body.querySelectorAll('[data-day]').forEach((b) =>
            b.addEventListener('click', () => setDay(shift(day, Number(b.dataset.day)))));

        body.querySelector('[data-today]').addEventListener('click', () => setDay(todayIso()));

        body.querySelector('#dayPick').addEventListener('change', (e) => {
            if (e.target.value) setDay(e.target.value);
        });

        body.querySelectorAll('[data-open]').forEach((card) => {
            const go = () => {
                const r = store.allSync('appointments').find((x) => x.id === card.dataset.open);
                if (r) openDrawer(r);
            };
            card.addEventListener('click', go);
            card.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    go();
                }
            });
        });
    }

    function setDay(next) {
        day = next;
        U.setParams({ day: next });
        renderDay();
    }

    /* ---------- drawer ---------- */

    /* Detail only. The one action offered is the phone, because calling the
       patient is the only thing that actually moves a request forward. */
    async function openDrawer(row) {
        const doc = doctors.find((d) => d.id === row.doctorId);
        const dep = departments.find((d) => d.id === row.departmentId);

        await modal.drawer({
            title: row.patientName,
            html: `
                <div class="col gap-6">
                    <div>
                        <div class="eyebrow mb-4">Request</div>
                        <dl class="kv">
                            <dt>Status</dt><dd>${statusTag(row)}</dd>
                            <dt>Asked for</dt><dd>${U.esc(U.fmtDate(row.preferredDate))} · ${U.esc(row.preferredSlot || '—')}</dd>
                            ${row.confirmedSlot ? `<dt>Confirmed</dt><dd>${U.esc(row.confirmedSlot)}</dd>` : ''}
                            <dt>Department</dt><dd>${U.esc(dep ? dep.name : 'General')}</dd>
                            <dt>Doctor</dt><dd>${U.esc(doc ? doc.name : 'Not named')}</dd>
                            <dt>Requested</dt><dd>${U.esc(U.ago(row.createdAt))}</dd>
                        </dl>
                    </div>

                    <div>
                        <div class="eyebrow mb-4">Contact</div>
                        <dl class="kv">
                            <dt>Phone</dt>
                            <dd>${row.phone ? `<a href="tel:${U.esc(row.phone.replace(/\s+/g, ''))}">${U.esc(row.phone)}</a>` : '<span class="muted">Not given</span>'}</dd>
                            <dt>Email</dt>
                            <dd>${row.email ? `<a href="mailto:${U.esc(row.email)}">${U.esc(row.email)}</a>` : '<span class="muted">Not given</span>'}</dd>
                        </dl>
                    </div>

                    <div>
                        <div class="eyebrow mb-4">Reason given</div>
                        <p class="text-sm mid">${U.esc(row.reason || 'None given.')}</p>
                    </div>

                    ${row.cancelReason ? `
                    <div>
                        <div class="eyebrow mb-4">Cancelled because</div>
                        <p class="text-sm mid">${U.esc(row.cancelReason)}</p>
                    </div>` : ''}

                    <p class="text-xs muted">This record cannot be edited from the panel.</p>
                </div>`,
            footer: row.phone
                ? `<a class="btn btn--primary grow" href="tel:${U.esc(row.phone.replace(/\s+/g, ''))}">
                       <i class="fa-solid fa-phone"></i> Call ${U.esc(row.patientName)}</a>`
                : null,
        });
    }

    /* ---------- helpers ---------- */

    function statusTag(row) {
        const s = STATUS.find((x) => x.value === row.status);
        return `<span class="tag ${TONE[row.status] || 'off'}">${U.esc(s ? s.label : row.status || 'Unknown')}</span>`;
    }

    function call(row) {
        window.location.href = `tel:${row.phone.replace(/\s+/g, '')}`;
    }

    /* Local dates throughout. toISOString() would report yesterday for the
       first 5.5 hours of every IST day, which is exactly when the morning
       desk is looking at this screen. */
    function todayIso() {
        return U.dateInput(new Date());
    }

    /* The date this row belongs on: what was confirmed if anything was, what
       was asked for otherwise. */
    function dateOf(row) {
        return (row.confirmedSlot || '').slice(0, 10) || row.preferredDate || '';
    }

    function shift(iso, days) {
        const d = new Date(`${iso}T00:00:00`);
        d.setDate(d.getDate() + days);
        return U.dateInput(d);
    }
}());
