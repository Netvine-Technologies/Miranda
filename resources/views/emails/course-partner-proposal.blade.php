<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Booking Tree partnership</title></head>
<body style="margin:0;padding:0;background:#ffffff;color:#222222;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="width:100%;table-layout:fixed;border-collapse:collapse;background:#ffffff;"><tr><td align="center" style="padding:24px 20px;">
  <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="width:100%;max-width:600px;table-layout:fixed;border-collapse:collapse;text-align:left;">
    <tr><td style="padding:0 0 28px;">
      <table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;"><tr>
        <td style="width:36px;padding:0 10px 0 0;vertical-align:middle;"><img src="{{ $logoUrl }}" width="32" height="32" alt="" style="display:block;width:32px;height:32px;border:0;border-radius:5px;background:#0b7a50;"></td>
        <td style="vertical-align:middle;color:#334155;font-size:15px;line-height:1.3;">Booking Tree</td>
      </tr></table>
    </td></tr>
    <tr><td style="color:#222222;font-size:16px;line-height:1.6;overflow-wrap:break-word;">
      <p style="margin:0 0 18px;">Hi there,</p>
      <p style="margin:0 0 18px;">I'm Shane from Booking Tree, an online booking system that helps businesses accept bookings, collect payments and send appointment reminders.</p>
      <p style="margin:0 0 18px;">We’d love to partner with {{ $providerName }} so you can recommend Booking Tree to {{ $graduateDescription }} as they start taking on clients.</p>
      <p style="margin:0 0 18px;">In return, you’d earn <strong>{{ $commissionPercent }}% commission on each referred graduate’s paid subscription for their first {{ $commissionMonths }} months</strong>, while they remain a paying customer.</p>
      <p style="margin:0 0 18px;">There’s no cost for your organisation to join. We’d provide a dedicated referral link for you to share with students, so we can track your referrals and credit your commission.</p>
      <p style="margin:0 0 18px;">You can read more about the programme at <a href="{{ $partnerEnquiryUrl }}" style="color:#334155;text-decoration:underline;">bookingtree.io/partners/educators</a>.</p>
      <p style="margin:0 0 24px;">Would you be interested in partnering with us?</p>
      <p style="margin:0 0 28px;">Thanks,<br>Shane Johnson<br>Booking Tree<br><a href="https://bookingtree.io" style="color:#334155;text-decoration:underline;">bookingtree.io</a></p>
      <p style="margin:0;color:#475569;font-size:14px;line-height:1.5;">If this isn’t relevant, just let me know and I won’t follow up.<br><a href="{{ $unsubscribeUrl }}" style="color:#475569;text-decoration:underline;">Unsubscribe</a></p>
    </td></tr>
  </table>
</td></tr></table>
<img src="{{ $trackingUrl }}" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0;">
</body></html>
