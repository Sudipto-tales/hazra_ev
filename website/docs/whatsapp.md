# Messaging: email, WhatsApp and SMS

Run `php vayu migrate` from `website` before enabling messaging (migrations 028,
029 and 032–035). No live messages are sent during installation or tests.

## Where configuration lives

Everything is managed in **Admin → Settings → Messaging & Webhooks**: provider
credentials, webhook secrets, approved template names, and the per-event
delivery order. Secrets are stored encrypted (AES-256-GCM, `core/Secrets.php`)
in `messaging_credentials`; non-secret fields like template names are stored
beside them in plaintext because an operator has to read them back.

```dotenv
APP_KEY=base64:...   # php vayu key:generate — REQUIRED to save any secret
```

Without `APP_KEY` the panel refuses to save a secret and the application falls
back to the environment, so an un-keyed server behaves exactly as it did before
this layer existed. **Keep a copy of the key**: nothing stored through the panel
can be read without it, and there is no recovery. Add it to `PROD_ENV_FILE` too,
or the next deploy writes an env without it.

Every field resolves **database → the old `website_settings` rows → environment**,
so every variable in `.env.example` still works as a fallback and nothing has to
be migrated out of it. Saving a field in the panel takes precedence from then on.

The generic settings endpoints (`POST /api/v1/website/settings`,
`PATCH /api/v1/settings/{group}`) now refuse any credential key, including
`smtp_password` — which is how that one came to be stored in plaintext.

## Providers

| Provider | Channel | Carries | Needs |
| --- | --- | --- | --- |
| `email.smtp` | email | every event | host, port, username, password, from address. No webhook exists: SMTP has no delivery callback, so email stops at `accepted` and a later bounce is invisible |
| `whatsapp.meta` | whatsapp | every event | access token, phone number ID, Graph version, per-event template names; app secret + verify token for delivery reports. Not needed: WABA ID, App ID, namespace |
| `whatsapp.msg91` | whatsapp | every event | authkey, integrated number, per-event template names, optional namespace, generated header token. Not needed: Meta app secret, verify token, phone number ID |
| `sms.msg91` | sms | OTP only | authkey, OTP template ID, generated header token. Not needed: sender ID or DLT entity ID — both live on the template |
| `sms.twilio` | sms | OTP only | account SID, auth token, from number, status callback URL matching the public one character for character |
| `sms.fast2sms` | sms | OTP only | API key, route (`otp` needs nothing further; `dlt` needs a registered sender ID and message ID). **Send-only** — Fast2SMS documents no delivery-report payload and signs nothing |
| `sms.log` | sms | OTP only | nothing; writes to the error log and is refused outside local/development/testing |

SMS carries verification codes only. These providers expose OTP endpoints rather
than a transactional template per event, and the registry says so in each
provider's `events`, so the router skips an SMS step for any other event instead
of failing it.

## Delivery order (the chain)

Each event — `otp`, `lead_received`, `lead_approved`, `lead_rejected`,
`warranty_received` — has an ordered, admin-editable list of providers in
`messaging_routes`. `MessagingRouter::dispatch()` walks it:

- a step is **skipped**, not failed, when the provider has no credentials, no
  template for that event, no recipient of that kind, or its channel is off;
- `first_success` stops at the first provider that **accepts** the message
  (acceptance, not delivery); `all` runs every usable step — which is what
  "WhatsApp alongside email" has always meant for enquiries, and is seeded from
  the old `wa_lead_mode` value;
- every attempt is its own delivery-log row, sharing one `attempt_group`, so a
  fallback chain reads as one request with several steps;
- one OTP code is used across every step, and the cooldown, hourly and per-IP
  caps stay at the request level — a three-step fallback is still one request.

The seeded defaults reproduce the behaviour that preceded them: email first for
enquiries, then WhatsApp; WhatsApp then SMS for OTP. Email is first in the OTP
chain as well but is skipped whenever no address is on file — and the public
warranty form collects the address at step 2, *after* verification, so in
practice a warranty OTP still goes to the phone unless step 1 is changed to ask
for an email.

Automatic enquiry messages are off by default (`wa_auto_leads`). Sends require
recorded customer opt-in and have a 60-second per-event duplicate guard, which
covers the WhatsApp steps only — it exists so an automatic message and an
admin's manual one cannot both go out, and it has never been meant to suppress
the email. Admin contact, test-drive and dealership lists still offer
Send / Retry & History.

Create these templates in Meta with body parameters in this exact order:

| Setting | Category | Body parameters |
| --- | --- | --- |
| wa_template_otp | Authentication, copy-code button | OTP (also passed to button index 0) |
| wa_template_lead_received | Utility, subject to Meta approval | Customer name, enquiry type |
| wa_template_lead_approved | Utility, subject to Meta approval | Customer name, enquiry type, appointment time |
| wa_template_lead_rejected | Utility, subject to Meta approval | Customer name, enquiry type |
| wa_template_warranty_received | Utility, subject to Meta approval | Customer name, registration reference |

Use an authentication template with the copy-code button for browser OTPs.
Expose `/api/v1/whatsapp/webhook` over HTTPS and subscribe to `messages` in Meta.
GET verifies the subscription; POST validates the raw-body HMAC with the app
secret. History tracks accepted, sent, delivered, read, and failed states;
accepted is not a delivery confirmation. Duplicate/out-of-order delivery
events cannot move a delivered or read message back to sent or failed.

Warranty OTPs use WhatsApp first when enabled, with immediate SMS fallback if
the API rejects or cannot accept the request. Customers can choose SMS for a
subsequent request after the usual cooldown if WhatsApp hasn't arrived.
A subsequent request expires earlier unverified codes. Automatic fallback in
the original request uses the same code. Late delivery failure is visible in
history; no background worker attempts to recover plaintext OTPs.

Public forms record optional WhatsApp consent for enquiry and warranty updates.
Enquiry consent is saved with the lead details; warranty consent records a UTC
timestamp in `warranty_registrations.whatsapp_consent_at`.
Existing records without consent cannot receive these notifications. OTP
delivery is requested separately by the warranty form's Send OTP action.
Tokens and OTP bodies are never stored in message history.

Check locally with `php tests/whatsapp.php`, `php tests/messaging-config.php`,
`php tests/webhook-events.php` and `php tests/otp-reveal.php`. Then test using Meta test recipients:
OTP verification, rejected sends with SMS fallback, delayed failure statuses,
customer consent, admin retry, and duplicate webhook delivery. Real provider
delivery requires your configured accounts and approved templates.

## Message Delivery page

Open **System → Message Delivery** (`/admin/message-delivery`). The admin-only
page combines email, WhatsApp and SMS sends. Filter by channel, status, UTC date
range, phone, purpose, reference, provider message ID, and whether to include
panel test sends (hidden by default, so they never inflate real figures). The
page shows errors and refreshes every 30 seconds while visible. Clicking a
reference shows every attempt for the same enquiry, registration or challenge —
which is how a fallback chain reads: one reference, several steps, each labelled
with its position in the delivery order.

`accepted` means provider API acceptance, `sent` means provider dispatch, and
`delivered`/`read` require callback confirmation. `logged` means development
logging only; it is never a delivery confirmation. Email has no callback at all,
so an email never moves past `accepted`. SMS sends made before migration 029 and
email sends made before migration 034 cannot be reconstructed; recording begins
with each of those changes.

Only failed messages offer retry. Enquiry retries require consent and the
current enquiry event; warranty retries require recorded warranty consent.
Verified, consumed, stale, or superseded OTP challenges cannot be retried.
An OTP with an accepted fallback also cannot be retried from this page. Other
eligible failed OTP retries issue a fresh code through the original channel
(WhatsApp can fall back to SMS), preserving cooldown and hourly limits and
invalidating the previous code. A retry re-sends through **the provider the
message was sent with**, not the top of the chain, so an operator running
WhatsApp through MSG91 is not silently retried through Meta.

### Revealing a live OTP

Off by default. Switched on in Admin → Settings → Messaging & Webhooks, the
delivery page grows an eye on OTP rows whose challenge is still live.

- The code is kept as AES-256-GCM ciphertext beside the existing bcrypt hash —
  never instead of it, so `password_verify` remains the only verification path
  and an unreadable cipher cannot weaken the check.
- It is wiped the moment the challenge is verified, consumed, expired or
  superseded, and `code_cipher_expires_at` is a second independent deadline.
  `php vayu otp:prune` and every send sweep strays.
- The code is never in the listing payload. The eye calls
  `POST /api/v1/admin/message-delivery/{channel}/{id}/code`, which writes a row
  in `otp_reveal_audit` naming the admin, their IP and the challenge **every
  time**, and is capped at 30 reveals per admin per hour. The reveal is hidden
  again after 30 seconds.
- A code sent while the setting was off was never stored and cannot be shown.

What this buys, and what it costs: support can read a live code back to a
customer who says it never arrived, and in exchange any admin session — or
anyone who steals one — can complete a customer's warranty verification while
the code is live. Live-only, off-by-default and audited is what makes that
trade defensible; retaining codes after use would not be.

## Webhooks page

Open **System → Webhooks** (`/admin/webhooks`). Every inbound provider callback
is recorded in `webhook_events`, **including the ones this server refuses** —
which is the point: a webhook that "does not work" is almost always arriving and
being rejected for a wrong secret or a wrong header name, and before this table
a refusal returned 403 and left nothing to look at. Meta's GET verification
handshake is recorded the same way.

Three outcomes, which are also the filters:

- **Refused** — authentication failed. The provider is talking to us and we are
  saying no; fix the secret.
- **Matched nothing** — authenticated, but no recorded send matches the provider
  ID in the call. Replayable.
- **Applied** — authenticated, and it moved a message's status.

Nothing secret is stored: header values are dropped except for a safe
allow-list, an authentication header is reduced to `present, N characters`
(enough to tell a missing header from a truncated paste), and payload keys that
look like credentials are replaced before the body is written. Retention is
admin-set, default 30 days, applied by `php vayu webhooks:prune`.

**Replay** re-applies a verified call that matched nothing — typically one that
arrived before its own message row was committed. It is refused for a call that
failed authentication (replaying it would apply an unverified payload) and for
one already applied. It is safe to repeat: every handler keeps its status-rank
guard, so a replay can only move a message forward, never regress a `delivered`
or `read` one.

### SMS callbacks

For Twilio, set the status callback URL in the panel to the exact public HTTPS
endpoint (the panel shows it, derived from `APP_URL`):

```
https://your-domain/api/v1/sms/webhook/twilio
```

The sender adds the per-message correlation query parameter automatically.
Callbacks use the Twilio auth token to validate `X-Twilio-Signature`
against the configured URL and all form parameters. Keep the URL identical
to the externally visible URL, including any deployment subdirectory.
[Twilio signature documentation](https://www.twilio.com/docs/usage/security)
and [status callbacks](https://www.twilio.com/docs/messaging/guides/track-outbound-message-status).

For MSG91, generate the shared secret in the panel (Generate, beside the webhook
header token) and paste it into MSG91. It is shown once.

In MSG91 **OTP → Webhook**, choose **On Report Received**, use
`https://your-domain/api/v1/sms/webhook/msg91`, and set the custom header
`X-SMS-Webhook-Token` to that same secret. Configure this JSON body:

```json
{
  "CRQID": "{{CRQID}}",
  "requestId": "{{requestId}}",
  "telNum": "{{telNum}}",
  "status": "{{status}}",
  "failureReason": "{{failureReason}}"
}
```

The sender passes a message-specific CRQID. The callback also checks the
provider ID and recipient before changing a record. MSG91 OTP report status
`1` means delivered and `2` means failed.
[MSG91 webhook configuration](https://msg91.com/help/webhook-new/how-to-receive-otp-delivery-reports-via-webhook).

Without configured callbacks, real SMS sends remain accepted; the page does
not assume delivery. Unknown, unauthenticated, duplicate, or out-of-order
callbacks cannot invent messages or regress confirmed delivery.

### WhatsApp through MSG91

Delivery reports for the indirect route arrive on their own endpoint,
`POST /api/v1/whatsapp/webhook/msg91`, authenticated by a generated
`X-WA-Webhook-Token` header — a shared secret rather than an HMAC, because that
is all MSG91 offers. In MSG91 choose **Webhook (New) → Outbound Report
Received**; "Outbound Request Received" only logs `Sent` and carries no delivery
result. Bulk sends produce one call per recipient, which is what this page
expects. MSG91 gives the endpoint five seconds and never retries, so the handler
does one indexed update and returns.

Correlation is the request ID MSG91 returned when it accepted the send; a report
with no request ID falls back to the most recent in-flight send to that number.
Meta's endpoint and MSG91's cannot touch each other's rows: both are scoped by
`whatsapp_messages.provider`.

The outbound template request follows MSG91's documented bulk template API. If
an account's panel shows a different payload shape,
`core/messaging/transports/WhatsAppMsg91Transport.php` is the only file that has
to change.

## Tests

```
php tests/whatsapp.php             providers, chains, OTP limits, signatures
php tests/message-delivery.php     the unified log, filters, retries, callbacks
php tests/messaging-config.php     the credential store, registry, chain routing
php tests/webhook-events.php       webhook logging, redaction, replay
php tests/otp-reveal.php           reveal gating, wiping, auditing
node tests/message-delivery-ui.js  delivery page, the eye, test-send labelling
```

None of these contacts a messaging provider or uses the application database.
