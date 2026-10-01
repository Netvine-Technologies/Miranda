# Course Partner Programme email engine

The command `php artisan course-partners:email` renders the Booking Tree educator partnership template in HTML and plain text, sends it through Miranda's Mailgun transport, and records the recipient, send status and observed image loads in `course_partner_outreach_emails`. The HTML uses the existing Booking Tree logo and the 20% for 12 months offer. Programme terms come from `config/course_partner_outreach.php` and are saved with each email record.

## Setup before a review send

1. Apply the additive `2026_10_01_000200_create_course_partner_outreach_emails` migration to the intended database. On production, first verify the effective environment and database identity, then use only `php artisan migrate --force`.
2. Configure `MAILGUN_DOMAIN`, `MAILGUN_API_KEY`, and `MAILGUN_ENDPOINT=api.eu.mailgun.net` in Miranda's private environment, matching the authorised Booking Tree Mailgun account. Never put the API key in source control or a chat message.
3. Set `COURSE_PARTNER_TRACKING_BASE_URL` to a public HTTPS origin that routes to Miranda. The engine refuses to send if this is absent or not HTTPS.
4. Keep `COURSE_PARTNER_OUTREACH_ENABLED=false` until the template, domain, tracking URL and recipient selection have been reviewed.

Send a fictional review copy to Shane with:

```sh
php artisan course-partners:email --review-to=shane.johnson386@gmail.com
```

The review send is tracked but does not change any prospect. Its row appears in `/leads/course-partners/emails` as **Review preview**.
The preview address is restricted to `COURSE_PARTNER_REVIEW_EMAIL` (Shane's inbox by default).

## Sending to a provider after review

Mark the provider **Reviewed** on `/leads/course-partners` after checking the course page and public email. Then explicitly enable `COURSE_PARTNER_OUTREACH_ENABLED=true` and run:

```sh
php artisan course-partners:email --prospect=123
```

The command sends to one prospect only. It refuses an unreviewed prospect, missing contact, missing course evidence, or a second email for the same campaign. A failed or ambiguous send stays recorded for manual inspection before any retry. There is no automatic follow-up or bulk sender.

## Open observations

Each HTML message has a unique, random image URL. Miranda stores only its SHA-256 hash and updates first-open time, last-open time and image-load count when the URL is requested. `/leads/course-partners/emails` shows the recipient and those values; the prospect list shows the latest send and first observed open. The plain-text part has no image.

An image load is **not proof of a human reading the message**. Some mail clients block images; others or their security services load them automatically. No recipient IP address or user agent is stored. The tracking endpoint sends a tiny GIF with no-cache headers.

The email has a visible reply-based opt-out sentence. Mark a provider **Rejected** if they opt out; the command requires **Reviewed** for any first send.

This implementation does not create referral links, attribute subscriptions, or calculate payouts. Those are separate parts of the Course Partner Programme.
