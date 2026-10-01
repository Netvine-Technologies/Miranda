# Course Partner Programme email template

The recipient-facing template is [`coursePartnerEmail.mjs`](coursePartnerEmail.mjs). `buildCoursePartnerEmail(context)` returns `subject`, `previewText`, `html`, `text`, `from` and `replyTo`. It does **not** send email or create referral accounts. A future sending workflow must use these outputs and Booking Tree's authorised Mailgun configuration, with the returned Reply-To header.

The terms come from `COURSE_PARTNER_TERMS` in that module. The agreed values are 20% and 12 months. The subject uses the required provider name; the optional verified first name falls back to “Hi there,”. The required course type can be `nail technician` or `lash technician`. Dynamic values are escaped in HTML and validated before rendering.

The [internal preview](COURSE_CAMPAIGN_PREVIEW.html), [HTML example](COURSE_PARTNER_EMAIL_EXAMPLE.html) and [plain-text example](COURSE_CAMPAIGN_REVIEW_EMAIL.txt) are generated from the same renderer with clearly labelled fictional fixtures. The internal notice, fixture metadata, subject and inbox preview sit outside the email frame. Do not use the example fixture as a real recipient record.

Regenerate the files with `node docs/renderCoursePartnerPreview.mjs` from the Miranda directory. Run the focused checks with `node --test docs/coursePartnerEmail.test.mjs`. These commands make no network calls and send no email.

The outbound email has one green-and-white container, Booking Tree's white logo from the live site's static asset, a closing question, and one link to the [educator partner enquiry](https://bookingtree.io/partners/educators#partner-enquiry). The link appears in both HTML and plain text. The brand name remains real text if images are blocked. It contains no review notes, preview panel or reply button. There are no fabricated unsubscribe links or additional incentives. No course provider outreach is enabled by this template change.
