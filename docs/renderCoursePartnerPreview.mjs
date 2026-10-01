import { writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { buildCoursePartnerEmail } from './coursePartnerEmail.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const fixtures = [
  { label: 'Desktop · nail academy', width: 560, context: { recipientFirstName: 'Alex', providerName: 'Glow Beauty Academy', courseType: 'nail technician' } },
  { label: 'Mobile · lash educator', width: 360, context: { recipientFirstName: 'Sam', providerName: 'Sam’s Lash Training', courseType: 'lash technician' } },
  { label: 'Mobile · unknown first name', width: 360, context: { providerName: 'Independent Beauty Courses', courseType: 'nail technician' } },
];

const escapeHtml = (value) => String(value)
  .replaceAll('&', '&amp;')
  .replaceAll('<', '&lt;')
  .replaceAll('>', '&gt;')
  .replaceAll('"', '&quot;')
  .replaceAll("'", '&#39;');

function panel(fixture) {
  const email = buildCoursePartnerEmail(fixture.context);
  return `<section class="panel">
    <h2>${escapeHtml(fixture.label)}</h2>
    <p><strong>Fixture:</strong> ${escapeHtml(JSON.stringify(fixture.context))}</p>
    <p><strong>Subject:</strong> ${escapeHtml(email.subject)}<br><strong>Inbox preview:</strong> ${escapeHtml(email.previewText)}<br><strong>From:</strong> ${escapeHtml(email.from)}<br><strong>Reply-To:</strong> ${escapeHtml(email.replyTo)}</p>
    <iframe title="${escapeHtml(fixture.label)} email rendering" width="${fixture.width}" height="${fixture.width < 400 ? 1050 : 740}" srcdoc="${escapeHtml(email.html)}"></iframe>
  </section>`;
}

const preview = `<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Course Partner Programme · internal preview</title>
<style>body{margin:0;background:#eef4f0;color:#173a2a;font:15px/1.5 Arial,Helvetica,sans-serif}.shell{max-width:1150px;margin:auto;padding:24px}.notice{background:#0b7a50;color:white;padding:18px 20px;border-radius:10px}.notice h1{font-size:22px;margin:0 0 6px}.notice p{margin:0}.grid{display:flex;flex-wrap:wrap;align-items:flex-start;gap:20px;margin-top:20px}.panel{box-sizing:border-box;max-width:100%;background:white;border:1px solid #dce9df;border-radius:10px;padding:16px}.panel h2{margin:0 0 10px;font-size:17px}.panel p{max-width:560px;overflow-wrap:anywhere}.panel iframe{display:block;max-width:100%;border:1px solid #dce9df;background:#fff}.notes{margin-top:20px;color:#475569}</style></head>
<body><main class="shell"><div class="notice"><h1>Internal preview — not sent</h1><p>Example names and providers appear only in these fixtures. Each frame uses the exact HTML returned by the sending template.</p></div>
<div class="grid">${fixtures.map(panel).join('\n')}</div>
<p class="notes">The actual email has one container and no preview notes, subject panel or button. The first name falls back to “Hi there,” when unverified. No email is sent by this preview generator.</p>
</main></body></html>`;

await writeFile(path.join(here, 'COURSE_CAMPAIGN_PREVIEW.html'), preview, 'utf8');
await writeFile(path.join(here, 'COURSE_PARTNER_EMAIL_EXAMPLE.html'), buildCoursePartnerEmail(fixtures[0].context).html + '\n', 'utf8');
await writeFile(path.join(here, 'COURSE_CAMPAIGN_REVIEW_EMAIL.txt'), buildCoursePartnerEmail(fixtures[0].context).text + '\n', 'utf8');
console.log('Rendered internal preview and HTML/plain-text examples. No email sent.');
