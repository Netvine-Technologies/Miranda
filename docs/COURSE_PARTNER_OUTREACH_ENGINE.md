# Course Partner Programme email engine

The command `php artisan course-partners:email` renders the Booking Tree educator partnership template in HTML and plain text, sends it through a dedicated SMTP mailbox, and records the recipient, send status and observed image loads in `course_partner_outreach_emails`. The HTML uses the existing Booking Tree logo, the 20% for 24 months offer, and one link to the [educator programme](https://bookingtree.io/partners/educators). Programme terms come from `config/course_partner_outreach.php` and are saved with each email record.

## Setup before a review send

1. Apply the additive `2026_10_01_000200_create_course_partner_outreach_emails` and `2026_10_02_000000_add_course_partner_unsubscribe_suppression` migrations to the intended database. On production, first verify the effective environment and database identity, then use only `php artisan migrate --force`.
2. Configure the dedicated SMTP mailbox in Miranda's private environment with `COURSE_PARTNER_MAILER=course_partner_smtp`, `COURSE_PARTNER_SMTP_SCHEME`, `COURSE_PARTNER_SMTP_HOST`, `COURSE_PARTNER_SMTP_PORT`, `COURSE_PARTNER_SMTP_USERNAME`, and `COURSE_PARTNER_SMTP_PASSWORD`. Use `smtps` with port 465, or `smtp` with port 587 and STARTTLS. Set `COURSE_PARTNER_FROM_ADDRESS` to an address authorised for that mailbox; the SMTP username may differ. Do not put credentials in source control or a chat message. Keep Booking Tree's transactional Mailgun configuration separate.
3. Set `COURSE_PARTNER_TRACKING_BASE_URL` to a public origin that routes to Miranda. A review copy restricted to Shane's inbox may use the server's HTTP IP address for the first tracked test. Provider emails require HTTPS.
4. Keep `COURSE_PARTNER_OUTREACH_ENABLED=false` until the template, sender, tracking URL, recipient selection, and provider terms have been reviewed.

Send a fictional review copy to Shane with:

```sh
php artisan course-partners:email --review-to=shane.johnson386@gmail.com
```

The review send is tracked but does not change any prospect. Its row appears in `/leads/course-partners/emails` as **Review preview**.
The preview address is restricted to `COURSE_PARTNER_REVIEW_EMAIL` (Shane's inbox by default).

Outinfra's SMTP host, port, and credentials must come from the actual mailbox settings; they are not inferred from the website.

## Sending to a provider after review

Mark the provider **Reviewed** on `/leads/course-partners` after checking the course page and email. Then explicitly enable `COURSE_PARTNER_OUTREACH_ENABLED=true` and run:

```sh
php artisan course-partners:email --prospect=123
```

The command sends to one prospect only. It refuses an unreviewed prospect, missing contact, missing course evidence, a suppressed address, or a second email for the same campaign. A failed or ambiguous send stays recorded for manual inspection before any retry. There is no automatic follow-up or bulk sender.

The email uses the prospect's recorded course focus to describe graduates of nail technician courses, lash technician courses, or both. A prospect without either focus receives generic student wording. The commission offer, programme link, layout, and unsubscribe remain the same.

## Open observations

Each HTML message has a unique, random image URL. Miranda stores only its SHA-256 hash and updates first-open time, last-open time and image-load count when the URL is requested. `/leads/course-partners/emails` shows the recipient and those values; the prospect list shows the latest send and first observed open. The plain-text part has no image.

An image load is **not proof of a human reading the message**. Some mail clients block images; others or their security services load them automatically. No recipient IP address or user agent is stored. The tracking endpoint sends a tiny GIF with no-cache headers.

The email includes a visible unsubscribe link and one-click unsubscribe headers. A GET shows a confirmation page; a POST adds the address to `course_partner_suppressions`. Selecting **Rejected** in Miranda also suppresses the current contact address. Check replies and mark opt-outs rejected promptly. Suppression applies across campaign keys. The system does not yet ingest SMTP bounces or complaints automatically, so keep sends individual and review mailbox feedback.

This implementation does not create referral links, attribute subscriptions, or calculate payouts. Those are separate parts of the Course Partner Programme.
