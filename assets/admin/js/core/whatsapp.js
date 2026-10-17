(function () {
    'use strict';
    const { api, modal, toast, util: U } = window.HAZRA;
    window.HAZRA.whatsapp = {
        async open(row) {
            const url = `api/v1/website/leads/${encodeURIComponent(row.id)}/whatsapp`;
            try {
                const response = await api.get(url);
                const items = response.data || [];
                await modal.open({
                    title: 'WhatsApp notifications',
                    subtitle: `${row.name || 'Customer'} · ${row.phone || 'No phone number'}`,
                    html: `<p>Sends the configured acknowledgement, approval, or rejection template for this enquiry's current status. Customer opt-in is required.</p>
                        ${items.length ? `<table><thead><tr><th>Event</th><th>Status</th><th>Time</th><th>Error</th></tr></thead><tbody>${items.map(item => `<tr><td>${U.esc(item.event_name)}</td><td>${U.esc(item.status)}</td><td>${U.esc(item.updated_at)}</td><td>${U.esc(item.error_code || '')}</td></tr>`).join('')}</tbody></table>` : '<p>No messages sent yet.</p>'}`,
                    footer: '<button type="button" class="btn btn--ghost" data-close>Close</button><button type="button" class="btn btn--primary" data-send>Send / Retry on WhatsApp</button>',
                    onMount(panel, close) {
                        panel.querySelector('[data-close]').addEventListener('click', () => close());
                        panel.querySelector('[data-send]').addEventListener('click', async event => {
                            event.currentTarget.disabled = true;
                            try {
                                const result = await api.post(url, {});
                                toast.success(result.data.message);
                                close();
                                window.HAZRA.whatsapp.open(row);
                            } catch (error) {
                                toast.error(error.message || 'Sending failed');
                                panel.querySelector('[data-send]').disabled = false;
                            }
                        });
                    },
                });
            } catch (error) { toast.error(error.message || 'Could not load WhatsApp history'); }
        },
    };
}());
