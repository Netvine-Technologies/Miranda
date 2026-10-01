# Course Partner Programme: nail and lash educators

## Purpose

Recruit course providers and educators who can recommend Booking Tree to students finishing nail technician or lash technician training. The offer is a referral partnership, not customer research. Miranda discovers and qualifies potential partners; Booking Tree must attribute referred customers, track subscription payments, calculate commission, and record payouts before the programme can go live.

## Proposed commercial rules

- Monthly Booking Tree plans in scope: Pro at **£10/month** and Advanced at **£20/month**. These match `Booking-Tree/Backend/services/subscriptions/planRegistry.js` as inspected on 1 October 2026.
- A partner earns **20% recurring commission for 12 months** for each attributed student who becomes a paying customer. At unchanged monthly prices and 12 successful monthly payments, that is £2/month and up to £24 for Pro, or £4/month and up to £48 for Advanced. These are illustrations, not guaranteed earnings.
- The 12-month window starts at the customer's **first successful paid subscription charge**, not at referral, registration, or trial start. Store that timestamp and the resulting expiry timestamp for each referred customer. A cancellation or payment failure does not extend or restart the window.
- Accrue commission only for successful paid subscription charges made while the customer is actively subscribed and within that window. Stop accrual on cancellation; do not accrue on unpaid invoices. If the customer resumes within the original window, eligible successful charges may accrue until the original expiry. Never restart the 12-month clock.
- Calculate each monthly commission from the eligible subscription amount actually charged, using the percentage captured for that referral; a plan change can therefore change future commission. Reverse or adjust commission if its underlying charge is refunded or disputed, before payout where possible or through an auditable adjustment after payout.
- Give each partner a unique referral code and link. Capture attribution when the student signs up, preserve it through trial and first payment, and prevent the same customer being credited to multiple partners. Define an attribution window and conflict policy before launch.
- Store the commission percentage and duration as programme terms, with a snapshot on each attributed referral. Do not permanently hard-code 20% or 12 months in calculation logic; later changes should not silently alter existing partner agreements.
- Booking Tree also lists annual plans. Their commission and payout treatment needs an explicit rule before annual subscriptions are eligible; the monthly examples above must not be applied to an annual charge by assumption.

## Tracking and records

| Record | Required fields |
| --- | --- |
| Partner | Partner/educator name, academy/business name, contact details, unique referral code, unique referral link, programme terms version, status. |
| Referred customer | Partner ID, referred customer ID, referral code/link used, attribution timestamp, customer plan, subscription start date (first successful paid charge), monthly subscription amount, commission percentage snapshot, commission expiry date, active/cancelled status. |
| Commission ledger | Referred customer ID, source subscription payment/invoice ID, charge date, plan and amount charged, commission percentage, commission amount, status (accrued, payable, paid, reversed), payout ID/date, adjustment reason. Use the payment/invoice ID to prevent duplicate commission on webhook retries. |
| Partner summary | Number of attributed customers, active customers, accrued commission, unpaid commission, **total commission paid**. Derive paid totals from the ledger rather than editing them independently. |

Keep monetary values in GBP minor units (pence) and timestamps in UTC. Preserve the original attribution, rate, duration, and commission entries for audit even if a partner, plan, or programme setting later changes. A payout process needs a payment destination, payout cadence, minimum amount if any, and a reconciliation path; those are not yet defined.

## Outreach message

Lead with: **“Recommend Booking Tree to your students when they finish their course. If they become a paying customer, you earn 20% of their subscription for their first 12 months.”** Keep the first email concise and low-pressure. Do not ask educators how they currently manage bookings. Do not lead with the £2/£4 examples; reserve those for partner information or follow-up material. See [`COURSE_CAMPAIGN_EMAIL_TEMPLATE.md`](COURSE_CAMPAIGN_EMAIL_TEMPLATE.md) for the review copy.

## Partner discovery

The first manually checked UK prospects are in [`COURSE_PARTNER_SHORTLIST.md`](COURSE_PARTNER_SHORTLIST.md), with provider course and contact evidence. They are a review list, not an outreach queue.

Miranda's Lead Discovery page has **Nail technician courses** and **Lash technician courses** campaign choices. Start a separate scan for each focus and country. The supported target countries are the UK, US, Canada, Australia, and New Zealand. Choose one location, or use **Four starter markets** to queue four city scans for the selected country through Web Search. The city lists are configurable in `config/leads.php`. A broad country scan is also possible by leaving the location blank. A four-market batch can consume up to four times the search and crawl quota of a single scan.

Each scan uses the existing Google Maps or Web Search provider, then crawls business websites. Google Maps uses the selected country as its region. Web Search uses the result page as the first crawl page, which helps it find course pages. The crawler records a course evidence URL only when that page contains both the relevant subject and course or training language. It also records public email addresses and their source pages using the existing email filters.

Miranda saves course matches in the separate `course_partner_prospects` table after a crawl finds evidence for the selected focus. The unique country and website domain prevent duplicate prospect rows across scans; a scan-run link keeps the discovery history. Nail and lash evidence and public contact emails accumulate across scans. A rescan preserves the review status and notes. The selected country is a **search target**, not independently verified proof that the provider operates there; check the course page and location before outreach.

Use **Course Partner Prospects** to filter by country, focus, review status, or whether a public email was found. Review evidence and mark each prospect reviewed or rejected. Its CSV export includes the selected filters and one row per contact email; prospects without an email also appear for manual research. The per-run export in Recent Runs reads the same separate table. These are potential partners, not attributed referral partners yet. This feature gathers contacts; it does not send campaign messages or track commission.

The additive migrations `2026_10_01_000000_add_course_campaign_evidence.php` and `2026_10_01_000100_create_course_partner_prospects.php` must be applied before course scans become available. Existing general lead scans remain available while they are pending. A lead discovery queue worker must be running to process scans. Existing course evidence in general leads is not automatically backfilled into the new prospect table; rescan to populate it.

## Launch gate

The referral link/code, attribution, paid-subscription event processing, commission ledger, payout workflow, and partner terms are specifications only. They are not implemented by Miranda's lead discovery changes. Agree annual-plan treatment, attribution conflicts/window, refunds, payout cadence, and partner payment details before enabling external outreach that promises commission. Review the email with Shane first; no business contacts should be emailed by the discovery feature.
