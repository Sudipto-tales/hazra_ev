# WhatsApp messaging

Run `php vayu migrate` from `website` before enabling WhatsApp (migrations 028 and 029).
No live messages are sent during installation or tests.

Configure server environment variables:

```dotenv
WHATSAPP_ACCESS_TOKEN=your-system-user-token
WHATSAPP_PHONE_NUMBER_ID=your-meta-phone-number-id
WHATSAPP_GRAPH_VERSION=vXX.0
WHATSAPP_APP_SECRET=your-meta-app-secret
WHATSAPP_VERIFY_TOKEN=your-random-webhook-verification-token
SMS_PROVIDER=msg91
MSG91_AUTH_KEY=your-key
MSG91_TEMPLATE_ID=your-approved-sms-template
APP_ENV=production
```

Replace `vXX.0` with a supported Graph API version for your Meta app.
Existing Twilio SMS configuration is also supported. Missing production SMS
credentials cause an explicit failure; log delivery is development-only.

In Admin → Settings, enable WhatsApp and enter language and approved template
names. Credentials stay exclusively in the environment. Automatic enquiry
messages are off by default. Select alongside-email delivery or fallback when customer email is missing, disabled, or rejected by SMTP. SMTP acceptance cannot detect later email bounces.
Admin contact, test-drive, and dealership lists offer Send / Retry & History.
Sends require recorded customer opt-in and have a 60-second event cooldown.

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

Check locally with `php tests/whatsapp.php`. Then test using Meta test recipients:
OTP verification, rejected sends with SMS fallback, delayed failure statuses,
customer consent, admin retry, and duplicate webhook delivery. Real provider
delivery requires your configured accounts and approved templates.

## Message Delivery page

Open **System → Message Delivery** (`/admin/message-delivery`). The admin-only
page combines existing WhatsApp history with new SMS sends. Filter by channel,
status, UTC date range, phone, purpose, reference, or provider message ID. The
page shows errors and refreshes every 30 seconds while visible. Clicking a
reference shows the attempts for the same enquiry, registration, or challenge,
including a WhatsApp OTP attempt and its SMS fallback.

`accepted` means provider API acceptance, `sent` means provider dispatch, and
`delivered`/`read` require callback confirmation. `logged` means development
logging only; it is never a delivery confirmation. SMS sends made before
migration 029 cannot be reconstructed; recording begins with this change.

Only failed messages offer retry. Enquiry retries require consent and the
current enquiry event; warranty retries require recorded warranty consent.
Verified, consumed, stale, or superseded OTP challenges cannot be retried.
An OTP with an accepted fallback also cannot be retried from this page. Other
eligible failed OTP retries issue a fresh code through the original channel
(WhatsApp can fall back to SMS), preserving cooldown and hourly limits and
invalidating the previous code. Admin responses never include OTP plaintext.

### SMS callbacks

For Twilio, configure the exact public HTTPS endpoint:

```dotenv
SMS_TWILIO_CALLBACK_URL=https://your-domain/api/v1/sms/webhook/twilio
```

The sender adds the per-message correlation query parameter automatically.
Callbacks use the existing `TWILIO_TOKEN` to validate `X-Twilio-Signature`
against the configured URL and all form parameters. Keep the URL identical
to the externally visible URL, including any deployment subdirectory.
[Twilio signature documentation](https://www.twilio.com/docs/usage/security)
and [status callbacks](https://www.twilio.com/docs/messaging/guides/track-outbound-message-status).

For MSG91, configure a random server environment secret:

```dotenv
SMS_MSG91_WEBHOOK_TOKEN=your-random-shared-secret
```

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

Run `php tests/message-delivery.php` and `node tests/message-delivery-ui.js`
for isolated tracking, filtering, signature, authorization, retry, and UI
checks. These do not contact messaging providers or use the application DB.
