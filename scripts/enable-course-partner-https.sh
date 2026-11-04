#!/usr/bin/env bash
set -euo pipefail

# Run as root on the Miranda host after track.bookingtreeoutreach.com resolves
# directly to this server. The site's other hostnames are left untouched.
hostname=track.bookingtreeoutreach.com
server_ip=37.27.95.226
webroot=/home/shane/Miranda/public
site=/etc/nginx/sites-available/miranda-course-partner-tracking

if [[ $(id -u) -ne 0 ]]; then
    echo 'Run this script with sudo.' >&2
    exit 1
fi
if ! getent ahostsv4 "$hostname" | awk '{print $1}' | grep -Fxq "$server_ip"; then
    echo "${hostname} must resolve to ${server_ip} before issuing a certificate." >&2
    exit 1
fi
if [[ ! -f "$webroot/index.php" || ! -S /run/php/php8.4-fpm.sock ]]; then
    echo 'The expected Miranda webroot or PHP-FPM socket is missing.' >&2
    exit 1
fi
if [[ -e "$site" || -e /etc/nginx/sites-enabled/miranda-course-partner-tracking ]]; then
    echo 'The tracking Nginx site already exists; inspect it before changing it.' >&2
    exit 1
fi

apt-get update
DEBIAN_FRONTEND=noninteractive apt-get install -y certbot
certbot certonly --webroot --webroot-path "$webroot" \
    --domain "$hostname" --email shane@bookingtreeoutreach.com \
    --agree-tos --non-interactive --no-eff-email

cat > "$site" <<'NGINX'
server {
    listen 80;
    server_name track.bookingtreeoutreach.com;
    root /home/shane/Miranda/public;

    location ^~ /.well-known/acme-challenge/ {
        try_files $uri =404;
    }
    location / {
        return 301 https://$host$request_uri;
    }
}

server {
    listen 443 ssl;
    server_name track.bookingtreeoutreach.com;
    root /home/shane/Miranda/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/track.bookingtreeoutreach.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/track.bookingtreeoutreach.com/privkey.pem;

    location ^~ /course-partner-email/open/ {
        try_files $uri /index.php?$query_string;
    }
    location ^~ /course-partner-email/unsubscribe/ {
        try_files $uri /index.php?$query_string;
    }
    location / {
        return 404;
    }
    location ~ \.php$ {
        internal;
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }
    location ~ /\.ht {
        deny all;
    }
}
NGINX

ln -s "$site" /etc/nginx/sites-enabled/miranda-course-partner-tracking
if ! nginx -t; then
    rm /etc/nginx/sites-enabled/miranda-course-partner-tracking
    echo 'Nginx validation failed. The existing site was left active.' >&2
    exit 1
fi
systemctl reload nginx
echo "HTTPS enabled for ${hostname}. Verify it before switching Miranda's tracking base URL."
