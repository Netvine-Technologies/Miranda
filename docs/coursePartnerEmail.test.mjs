import assert from 'node:assert/strict';
import test from 'node:test';
import { buildCoursePartnerEmail, COURSE_PARTNER_ENQUIRY_URL, COURSE_PARTNER_LOGO_URL, COURSE_PARTNER_SENDER } from './coursePartnerEmail.mjs';

test('renders nail and lash partnerships from one context and offer configuration', () => {
  for (const courseType of ['nail technician', 'lash technician']) {
    const email = buildCoursePartnerEmail({ recipientFirstName: 'Alex', providerName: 'Glow Beauty Academy', courseType });
    assert.equal(email.subject, 'Partnership idea for Glow Beauty Academy graduates');
    assert.equal(email.previewText, 'A referral partnership for your students, with 20% commission for 12 months.');
    assert.match(email.html, new RegExp(`${courseType} courses`));
    assert.match(email.text, new RegExp(`${courseType} courses`));
    assert.match(email.html, /earn 20% of each referred student’s subscription for their first 12 months/);
    assert.match(email.text, /earn 20% of each referred student’s subscription for their first 12 months/);
    assert.match(email.html, /while they remain a paying customer/);
    assert.match(email.text, /while they remain a paying customer/);
    assert.match(email.html, /Would you be interested in seeing how this could work for Glow Beauty Academy\?/);
    assert.match(email.text, /Would you be interested in seeing how this could work for Glow Beauty Academy\?/);
    assert.match(email.html, new RegExp(`href="${COURSE_PARTNER_ENQUIRY_URL.replaceAll('.', '\\.')}"`));
    assert.match(email.text, new RegExp(COURSE_PARTNER_ENQUIRY_URL.replaceAll('.', '\\.')));
    assert.equal((email.html.match(/<a /g) ?? []).length, 1);
    assert.match(email.html, /If this isn’t relevant, just let me know and I won’t follow up/);
    assert.match(email.text, /If this isn’t relevant, just let me know and I won’t follow up/);
    assert.equal(email.from, `${COURSE_PARTNER_SENDER.name} <${COURSE_PARTNER_SENDER.address}>`);
    assert.equal(email.replyTo, 'support@bookingtree.io');
    assert.match(email.html, new RegExp(COURSE_PARTNER_LOGO_URL.replaceAll('.', '\\.')));
    assert.match(email.html, /alt="Booking Tree logo"/);
    assert.match(email.html, />Booking Tree<\/div>/);
    assert.doesNotMatch(email.html, /Internal preview|Sample subject|Review copy only|Reply to Shane|<button/i);
    assert.doesNotMatch(email.text, /Internal preview|Sample subject|Review copy only|Reply to Shane|£10|£20|£2|£4/i);
  }
});

test('uses the fallback greeting and escapes dynamic HTML', () => {
  const email = buildCoursePartnerEmail({ providerName: 'A & <B> Academy', courseType: 'lash <script>' });
  assert.match(email.html, /Hi there,/);
  assert.match(email.text, /Hi there,/);
  assert.match(email.html, /A &amp; &lt;B&gt; Academy/);
  assert.match(email.html, /lash &lt;script&gt; courses/);
  assert.doesNotMatch(email.html, /<script>/);
  assert.match(email.text, /A & <B> Academy/);
});

test('rejects missing required fields and invalid programme terms', () => {
  assert.throws(() => buildCoursePartnerEmail({ courseType: 'nail technician' }), /providerName is required/);
  assert.throws(() => buildCoursePartnerEmail({ providerName: 'Glow Beauty Academy' }), /courseType is required/);
  assert.throws(() => buildCoursePartnerEmail({ providerName: '{{providerName}}', courseType: 'nail technician' }), /unresolved placeholder/);
  assert.throws(() => buildCoursePartnerEmail({ providerName: 'Glow Beauty Academy', courseType: 'nail technician' }, { commissionPercent: 0, commissionDurationMonths: 12 }), /commissionPercent/);
});

test('uses the same terms in inbox preview, HTML and plain text', () => {
  const email = buildCoursePartnerEmail({ providerName: 'Glow Beauty Academy', courseType: 'nail technician' }, { commissionPercent: 25, commissionDurationMonths: 6 });
  assert.match(email.previewText, /25% commission for 6 months/);
  assert.match(email.html, /earn 25%.*first 6 months/);
  assert.match(email.text, /earn 25%.*first 6 months/);
});
