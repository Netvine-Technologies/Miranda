import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildCoursePartnerEmail } from './coursePartnerEmail.mjs';

const directory = path.dirname(fileURLToPath(import.meta.url));
const examples = [
  {
    country: 'United Kingdom', providerName: 'Nail It Academy', courseType: 'nail technician',
    to: 'info@nailit-academy.co.uk',
    evidence: 'https://nailit-academy.co.uk/pages/in-academy-beginners-gel-tech-course',
  },
  {
    country: 'United States', providerName: 'LA Lash Academy', courseType: 'lash technician',
    to: 'info@lalashacademy.com',
    evidence: 'https://www.lalashacademy.com/',
  },
  {
    country: 'Australia', providerName: 'CJ Academy', courseType: 'nail technician',
    to: 'info@cjacademy.com.au',
    evidence: 'https://cjacademy.com.au/accelerated-gel-course/',
  },
];

const escapeHtml = (value) => String(value).replaceAll('&', '&amp;').replaceAll('<', '&lt;')
  .replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#39;');

const panels = examples.map((example, index) => {
  const email = buildCoursePartnerEmail(example);
  return `<section class="card"><h2>${index + 1}. ${escapeHtml(example.providerName)} · ${escapeHtml(example.country)}</h2>
    <p><strong>To:</strong> ${escapeHtml(example.to)}<br><strong>Subject:</strong> ${escapeHtml(email.subject)}<br>
    <strong>Course evidence:</strong> <a href="${escapeHtml(example.evidence)}">${escapeHtml(example.evidence)}</a></p>
    <iframe title="Email preview for ${escapeHtml(example.providerName)}" srcdoc="${escapeHtml(email.html)}"></iframe></section>`;
}).join('\n');

fs.writeFileSync(path.join(directory, 'COURSE_PARTNER_THREE_EMAIL_DRY_RUN.html'), `<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Course Partner Programme · three-email dry run</title>
<style>body{margin:0;background:#edf5ef;color:#1f2937;font:15px/1.5 Arial,sans-serif}.wrap{max-width:1100px;margin:30px auto;padding:0 16px}.card{background:#fff;border:1px solid #d3e2d8;border-radius:12px;padding:20px;margin:20px 0}h1{color:#0b5138}h2{margin-top:0;color:#0b5138}iframe{width:100%;height:690px;border:1px solid #d3e2d8;border-radius:8px}a{color:#0b7a50;overflow-wrap:anywhere}.notice{background:#fff4dc;border:1px solid #e3c778;border-radius:8px;padding:12px}</style>
</head><body><main class="wrap"><h1>Three-email dry run</h1><p class="notice"><strong>PREVIEW ONLY — NO EMAILS SENT.</strong> These are examples from publicly listed course providers. Review each recipient and course page before any campaign launch.</p>${panels}</main></body></html>`);

fs.writeFileSync(path.join(directory, 'COURSE_PARTNER_THREE_EMAIL_DRY_RUN.txt'), examples.map((example, index) => {
  const email = buildCoursePartnerEmail(example);
  return `${index + 1}. ${example.providerName} (${example.country})\nTo: ${example.to}\nSubject: ${email.subject}\nCourse evidence: ${example.evidence}\n\n${email.text}`;
}).join('\n\n' + '='.repeat(72) + '\n\n') + '\n');

console.log('Rendered three dry-run emails. No email was sent.');
