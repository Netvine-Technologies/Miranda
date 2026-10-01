export const COURSE_PARTNER_TERMS = Object.freeze({
  commissionPercent: 20,
  commissionDurationMonths: 12,
});

export const COURSE_PARTNER_SENDER = Object.freeze({
  name: 'Shane at Booking Tree',
  address: 'postmaster@noreply.bookingtree.io',
  replyTo: 'support@bookingtree.io',
});

export const COURSE_PARTNER_LOGO_URL = 'https://bookingtree.io/booking-tree-logo-white.png';
export const COURSE_PARTNER_ENQUIRY_URL = 'https://bookingtree.io/partners/educators#partner-enquiry';

const escapeHtml = (value) => String(value)
  .replaceAll('&', '&amp;')
  .replaceAll('<', '&lt;')
  .replaceAll('>', '&gt;')
  .replaceAll('"', '&quot;')
  .replaceAll("'", '&#39;');

function cleanField(value, field, { required = false } = {}) {
  const cleaned = String(value ?? '').replace(/[\u0000-\u001f\u007f]+/g, ' ').replace(/\s+/g, ' ').trim();
  if (required && !cleaned) throw new Error(`${field} is required`);
  if (/\{\{[^}]+\}\}/.test(cleaned)) throw new Error(`${field} contains an unresolved placeholder`);
  if (cleaned.length > 120) throw new Error(`${field} is too long`);
  return cleaned;
}

function positiveInteger(value, field) {
  if (!Number.isInteger(value) || value <= 0) throw new Error(`${field} must be a positive integer`);
  return value;
}

export function buildCoursePartnerEmail({
  recipientFirstName = '',
  providerName,
  courseType,
} = {}, terms = COURSE_PARTNER_TERMS) {
  const name = cleanField(recipientFirstName, 'recipientFirstName');
  const provider = cleanField(providerName, 'providerName', { required: true });
  const course = cleanField(courseType, 'courseType', { required: true });
  const percent = positiveInteger(terms.commissionPercent, 'commissionPercent');
  const months = positiveInteger(terms.commissionDurationMonths, 'commissionDurationMonths');
  const greeting = name ? `Hi ${name},` : 'Hi there,';
  const subject = `Partnership idea for ${provider} graduates`;
  const previewText = `A referral partnership for your students, with ${percent}% commission for ${months} months.`;
  const intro = `I’m Shane from Booking Tree, and I wanted to share a partnership idea for students completing your ${course} courses.`;
  const value = 'As your graduates start taking on clients, Booking Tree can help them accept online bookings, collect payments and send appointment reminders.';
  const offerStart = 'The idea is simple: recommend Booking Tree to your students when they finish their course, and ';
  const offerBold = `earn ${percent}% of each referred student’s subscription for their first ${months} months`;
  const offerEnd = ', while they remain a paying customer.';
  const attribution = 'There’s no cost to take part, and we would provide a dedicated referral link so you receive credit for the students you introduce.';
  const question = `Would you be interested in seeing how this could work for ${provider}?`;
  const enquiryLabel = 'View the programme and enquire';
  const optOut = 'If this isn’t relevant, just let me know and I won’t follow up.';

  const text = [
    'A partnership for your graduates',
    '', greeting,
    '', intro,
    '', value,
    '', `${offerStart}${offerBold}${offerEnd}`,
    '', attribution,
    '', question,
    '', `${enquiryLabel}: ${COURSE_PARTNER_ENQUIRY_URL}`,
    '', 'Thanks,', 'Shane', 'Booking Tree',
    '', optOut,
  ].join('\n');

  const html = `<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Booking Tree educator partnership</title>
<style>@media only screen and (max-width:540px){.email-padding{padding:20px !important}.outer-padding{padding:12px !important}}</style></head>
<body style="margin:0;padding:0;background:#f2f8f4;color:#1e293b;font-family:Arial,Helvetica,sans-serif;">
<div style="display:none;font-size:1px;line-height:1px;color:#f2f8f4;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">${escapeHtml(previewText)}</div>
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;background:#f2f8f4;"><tr><td class="outer-padding" align="center" style="padding:24px 12px;">
  <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="width:100%;max-width:520px;border-collapse:separate;border-spacing:0;background:#ffffff;border:1px solid #dce9df;border-radius:12px;overflow:hidden;">
    <tr><td class="email-padding" style="padding:20px 24px;background:#0b7a50;color:#ffffff;">
      <table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;"><tr>
        <td style="width:58px;padding:0 10px 0 0;vertical-align:middle;"><img src="${COURSE_PARTNER_LOGO_URL}" width="50" height="50" alt="Booking Tree logo" style="display:block;width:50px;height:50px;border:0;"></td>
        <td style="vertical-align:middle;color:#ffffff;">
          <div style="font-size:20px;font-weight:700;line-height:1.3;">Booking Tree</div>
          <div style="margin-top:4px;font-size:11px;letter-spacing:1.3px;line-height:1.4;color:#d8f4e5;">EDUCATOR PARTNERSHIPS</div>
        </td>
      </tr></table>
    </td></tr>
    <tr><td class="email-padding" style="padding:24px;color:#334155;font-size:15px;line-height:1.6;overflow-wrap:break-word;">
      <h1 style="margin:0 0 18px;color:#123b2c;font-size:22px;line-height:1.3;font-weight:700;">A partnership for your graduates</h1>
      <p style="margin:0 0 15px;">${escapeHtml(greeting)}</p>
      <p style="margin:0 0 15px;">${escapeHtml(intro)}</p>
      <p style="margin:0 0 15px;">${escapeHtml(value)}</p>
      <p style="margin:0 0 15px;">${escapeHtml(offerStart)}<strong>${escapeHtml(offerBold)}</strong>${escapeHtml(offerEnd)}</p>
      <p style="margin:0 0 15px;">${escapeHtml(attribution)}</p>
      <p style="margin:0 0 10px;">${escapeHtml(question)}</p>
      <p style="margin:0 0 18px;"><a href="${COURSE_PARTNER_ENQUIRY_URL}" style="color:#0b7a50;font-weight:700;text-decoration:underline;">${escapeHtml(enquiryLabel)}</a></p>
      <p style="margin:0 0 18px;">Thanks,<br><strong>Shane</strong><br>Booking Tree</p>
      <p style="margin:0;color:#64748b;font-size:12px;line-height:1.5;">${escapeHtml(optOut)}</p>
    </td></tr>
  </table>
</td></tr></table>
</body></html>`;

  return {
    subject,
    previewText,
    html,
    text,
    from: `${COURSE_PARTNER_SENDER.name} <${COURSE_PARTNER_SENDER.address}>`,
    replyTo: COURSE_PARTNER_SENDER.replyTo,
  };
}
