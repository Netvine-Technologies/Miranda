# Miranda server access

Verified on 2026-11-04 against the live server and the earlier setup conversation.

## Established SSH connection

- Server: `37.27.95.226`
- User: `shane`
- Saved PuTTY session: `Booking Tree - [Miranda]`
- Key location: `C:\Dev\SSH keys\Miranda\private.ppk`
- Application: `/home/shane/Miranda`

Use the saved session so its established server identity and key configuration are reused:

```powershell
& 'C:\Program Files\PuTTY\plink.exe' -batch -load 'Booking Tree - [Miranda]' 'id; sudo -n -l'
```

Never print private keys, passwords, or application environment contents.

## Actual administrative permissions

`shane` can use general sudo with a password. These exact commands also have passwordless permission:

- `/usr/bin/systemctl reload nginx`
- `/usr/bin/systemctl restart php8.3-fpm`
- `/usr/bin/systemctl restart php8.4-fpm`

Check the full output of `sudo -n -l`. A failed `sudo -n true` does not mean there are no passwordless permissions.

The Nginx site/configuration files are owned by root and are not writable by `shane`. Installing packages and editing these files require additional admin authentication.

## Earlier deployment-account discussion

The Codex chat titled **Summarize Mirandacan capabilities** (thread `019ffd3d-ba68-7a92-988e-03eec3862994`) proposed creating `miranda-deploy`. The attempted setup was blocked by automatic approval review; the conversation subsequently adopted restricted sudo permissions for `shane` instead. It also confirmed removal of the temporary unrestricted sudo rule.

The current account database contains neither `deploy` nor `miranda-deploy`. Booking Tree's separate PostgreSQL server does have a `deploy` account; its access documentation does not apply to Miranda.

An earlier chat mentioned a temporary `SERVER_SUDO_PASSWORD` entry, and later reported that it had been removed. The current local/server environment files and available server release copies inspected on 2026-11-04 did not contain this entry. No admin password was recovered.

## Tracking HTTPS setup

`track.bookingtreeoutreach.com` resolves directly to this server. HTTPS was enabled and externally verified on 2026-11-04 after Shane ran the prepared setup script with sudo. The tracking endpoint returns `200 image/gif` with successful certificate validation.

The setup script is `scripts/enable-course-partner-https.sh`. It has already been run; do not rerun it against the existing tracking site:

```bash
sudo bash /home/shane/Miranda/scripts/enable-course-partner-https.sh
```

Production `COURSE_PARTNER_TRACKING_BASE_URL` is now `https://track.bookingtreeoutreach.com`, and Laravel's configuration cache was cleared and its effective setting verified. Prospect sending remains disabled.

Certificate renewal uses the enabled and active `certbot.timer`. Because the webroot renewal method does not itself reload Nginx, Shane's existing crontab now also contains this daily reload at 04:17 server time:

```cron
17 4 * * * /usr/bin/sudo -n /usr/bin/systemctl reload nginx # miranda-tracking-certificate-reload
```

The exact sudo command was verified successfully. This loads renewed certificates within one day; Certbot renews ahead of expiry. The tracking vhost exposes only the open/unsubscribe endpoints and certificate challenges, with other paths returning 404. Existing HTTP links in earlier review emails continue to use the original IP endpoint.
