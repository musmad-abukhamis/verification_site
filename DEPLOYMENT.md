# Deploying to an Ubuntu VPS

A step-by-step guide to host this application (Laravel 12 + Inertia 2 / Vue 3 + Vite,
on **PostgreSQL**) on a fresh **Ubuntu 22.04 / 24.04** VPS, served by **Nginx + PHP-FPM**
with HTTPS, a queue worker, the scheduler, and optional Inertia SSR.

> Replace every placeholder: `your-domain.com`, `deploy` (the OS user), the database
> name/user/password, and all third‑party API keys.

---

## 1. What this app needs

| Component        | Requirement / Notes |
|------------------|---------------------|
| PHP              | **8.2+** (this guide installs 8.3) with FPM |
| PHP extensions   | `pgsql` (pdo_pgsql), `mbstring`, `xml`, `curl`, `zip`, `bcmath`, `gd`, `intl`, `opcache` |
| Composer         | v2 |
| Node.js          | **20 LTS** (Vite 6 needs Node 18+) — only for building front-end assets |
| Database         | **PostgreSQL 14+** (`DB_CONNECTION=pgsql`) |
| Web server       | Nginx (reverse proxy to PHP-FPM) |
| Process manager  | Supervisor — queue worker (**required**: data purchases are fulfilled by queued jobs) + optional SSR |
| Queue / Cache / Session | **database**-backed by default (no Redis required) |
| Outbound HTTPS   | App calls NIN / BVN / VTU / Paystack / Billstack APIs |
| Inbound webhook  | `POST /api/webhooks/billstack` must be publicly reachable over HTTPS |

---

## 2. Initial server setup

SSH in as `root` (or a sudo user) and update the system:

```bash
apt update && apt upgrade -y
apt install -y software-properties-common curl git unzip ufw acl
```

Create a non-root deploy user (run the app as this user, not root):

```bash
adduser deploy
usermod -aG sudo deploy
# copy your SSH key so you can log in as deploy
rsync --archive --chown=deploy:deploy ~/.ssh /home/deploy
```

Configure the firewall:

```bash
ufw allow OpenSSH
ufw allow 'Nginx Full'   # opens 80 and 443
ufw --force enable
```

Set the server timezone (optional but recommended):

```bash
timedatectl set-timezone Africa/Lagos
```

From here on, log in as **`deploy`** unless told otherwise.

---

## 3. Install PHP 8.3 + extensions

```bash
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y \
  php8.3-fpm php8.3-cli php8.3-common \
  php8.3-pgsql php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-zip php8.3-bcmath php8.3-gd php8.3-intl php8.3-opcache
```

Verify:

```bash
php -v
php -m | grep -E 'pdo_pgsql|mbstring|gd|intl|bcmath'
```

Tune PHP-FPM for production (edit `/etc/php/8.3/fpm/php.ini`):

```ini
memory_limit = 256M
upload_max_filesize = 200M
post_max_size = 205M
max_execution_time = 60
expose_php = Off
```

Restart: `sudo systemctl restart php8.3-fpm`

> **The upload limits are load-bearing.** Enrolment record exports
> (Admin → Enrollment Records) run 50–100MB. If `upload_max_filesize` is left at
> PHP's 2M default the upload fails with "The file failed to upload", because
> PHP rejects the file before Laravel ever sees it. `post_max_size` must stay
> *above* `upload_max_filesize` — when a POST exceeds it PHP discards the entire
> request body, so the app cannot report the real reason.
>
> `max_execution_time` does **not** need raising for these imports: the upload
> only stages the file, and the parsing happens in a queued job (which is a CLI
> process and therefore not bound by it). That does mean **the queue worker must
> be running** or uploads sit at "Queued" forever — see the Supervisor section.

Verify what is actually live (not what this file says):

```bash
php -i | grep -E 'upload_max_filesize|post_max_size|memory_limit'
```

---

## 4. Install Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

---

## 5. Install Node.js 20 (for building assets)

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node -v && npm -v
```

> You can build assets on the server (simplest), or build locally / in CI and upload
> the `public/build` and `bootstrap/ssr` directories. This guide builds on the server.

---

## 6. Install & configure PostgreSQL

```bash
sudo apt install -y postgresql postgresql-contrib
sudo systemctl enable --now postgresql
```

Create the database and user:

```bash
sudo -u postgres psql
```

```sql
CREATE DATABASE verification_site;
CREATE USER verification_user WITH ENCRYPTED PASSWORD 'CHANGE_ME_STRONG_PASSWORD';
GRANT ALL PRIVILEGES ON DATABASE verification_site TO verification_user;
-- Postgres 15+: also grant schema rights
\c verification_site
GRANT ALL ON SCHEMA public TO verification_user;
\q
```

The app connects over `127.0.0.1:5432`, which the default `pg_hba.conf` already allows
with password auth (`scram-sha-256`/`md5`). Quick connectivity test:

```bash
psql "postgresql://verification_user:CHANGE_ME_STRONG_PASSWORD@127.0.0.1:5432/verification_site" -c '\conninfo'
```

---

## 7. Get the code

```bash
sudo mkdir -p /var/www
sudo chown deploy:deploy /var/www
cd /var/www
git clone <YOUR_REPO_URL> verification-site
cd verification-site
```

---

## 8. Install dependencies

```bash
# PHP deps (production)
composer install --no-dev --optimize-autoloader

# Front-end deps + production build (this also builds the SSR bundle)
npm ci
npm run build
```

`npm run build` runs `vite build && vite build --ssr`, producing:
- `public/build/` — client assets + `manifest.json`
- `bootstrap/ssr/` — the SSR bundle (only used if you enable SSR in step 14)

---

## 9. Configure the environment (`.env`)

Create `.env` from the example and edit it:

```bash
cp .env.example .env
php artisan key:generate
nano .env
```

Set the **core** values:

```dotenv
APP_NAME="Your Brand Name"        # drives the site branding (landing page, auth, titles)
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Use HTTPS-aware URLs
ASSET_URL=https://your-domain.com

# --- Database (PostgreSQL) ---
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=verification_site
DB_USERNAME=verification_user
DB_PASSWORD=CHANGE_ME_STRONG_PASSWORD

# --- Sessions / Cache / Queue (database-backed; no Redis needed) ---
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_DOMAIN=.your-domain.com   # leading dot if you use www + apex
SESSION_SECURE_COOKIE=true        # cookies only over HTTPS
CACHE_STORE=database
QUEUE_CONNECTION=database

LOG_CHANNEL=stack
LOG_LEVEL=error
```

Set the **integration** keys (get these from each provider; do **not** commit them):

```dotenv
# Identity / verification providers
NIN_PROVIDER=
NIN_BASE_URL=
NIN_API_KEY=
BVN_PROVIDER=
BVN_BASE_URL=
BVN_API_KEY=
AREWASMART_VERIFY_BASE_URL=
AREWASMART_VERIFY_TOKEN=

# Airtime / Data (VTU)
VTU_PROVIDER=
VTU_BASE_URL=
VTU_API_KEY=
VTU_SECRET_KEY=
DATA_VENDOR1_URL=
DATA_VENDOR1_KEY=

# Payments / wallet funding
PAYSTACK_PUBLIC_KEY=
PAYSTACK_SECRET_KEY=
BILLSTACK_API_TOKEN=
BILLSTACK_BASE_URL=https://api.billstack.co/v2

# Mail (transactional email)
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS="no-reply@your-domain.com"
MAIL_FROM_NAME="${APP_NAME}"
```

> `VITE_APP_NAME` is read at **build time**. If you change `APP_NAME` and want it
> reflected in any Vite-embedded strings, rebuild (`npm run build`). The page branding
> itself is served from the backend at runtime, so a rebuild is usually unnecessary.

---

## 10. Migrate, seed and link storage

```bash
php artisan migrate --force

# Seed reference/config data (networks, data plans, vendor config, etc.)
php artisan db:seed --force

# Symlink storage/app/public -> public/storage
php artisan storage:link
```

> If you only want specific seeders, run e.g.
> `php artisan db:seed --class=DataPlanSeeder --force`. Re-running seeders may duplicate
> rows — seed once on first deploy.

---

## 11. File permissions

Nginx/PHP-FPM run as `www-data`. The app code is owned by `deploy`, but
`storage/` and `bootstrap/cache/` must be writable by `www-data`:

```bash
cd /var/www/verification-site
sudo chown -R deploy:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 2775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 0664 {} \;

# Keep future files group-writable (ACL)
sudo setfacl -R -d -m g:www-data:rwx storage bootstrap/cache
```

---

## 12. Cache configuration for production

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

> Run these (or `php artisan optimize`) **after** the final `.env` is in place, and
> again on every deploy. To undo while debugging: `php artisan optimize:clear`.

---

## 13. Nginx server block

Create `/etc/nginx/sites-available/verification-site`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.com www.your-domain.com;
    root /var/www/verification-site/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    # Must be >= post_max_size, or nginx 413s the enrolment upload before PHP runs.
    client_max_body_size 205M;

    # A 100MB upload over a slow link takes a while to arrive; the default
    # 60s body timeout cuts it off mid-transfer (UPLOAD_ERR_PARTIAL).
    client_body_timeout 300s;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable it and reload:

```bash
sudo ln -s /etc/nginx/sites-available/verification-site /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

---

## 14. HTTPS with Let's Encrypt

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com -d www.your-domain.com
```

Certbot rewrites the Nginx block to listen on 443 and sets up auto-renewal
(`systemctl status certbot.timer`). After this, confirm in `.env`:

```dotenv
APP_URL=https://your-domain.com
SESSION_SECURE_COOKIE=true
```

then `php artisan config:cache && sudo systemctl reload nginx`.

> **Behind a proxy/load balancer?** Laravel 12 trusts proxies via
> `bootstrap/app.php` → `$middleware->trustProxies(at: '*')`. Add that if your TLS is
> terminated upstream so generated URLs and the `https` scheme are correct.

---

## 15. Queue worker (Supervisor) — **required for data purchases**

The app uses the **database** queue, and the buy-data (VTU) module depends on it:
`DataPurchaseService` debits the user's wallet and dispatches a `ProcessDataPurchase`
job — **only that job calls the vendor API**. With no worker running, customers are
charged and their orders sit at `pending` forever; nothing ever reaches the vendor.
This is not optional background polish — treat a dead worker as a full outage of
data vending.

> For **local development**, `composer run dev` already starts a worker
> (`queue:listen`) and the scheduler alongside the server and Vite. Don't test
> data purchases with only `php artisan serve` + `npm run dev`.

```bash
sudo apt install -y supervisor
```

Create `/etc/supervisor/conf.d/verification-worker.conf`:

```ini
[program:verification-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/verification-site/artisan queue:work --queue=default --sleep=1 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopwaitsecs=3600
user=deploy
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/verification-site/storage/logs/worker.log
stopasgroup=true
killasgroup=true
```

Enrolment spreadsheet imports run on their own `imports` queue, because a
100MB file occupies a worker for minutes and must not stall customer data
purchases on the default queue. Create
`/etc/supervisor/conf.d/verification-imports.conf`:

```ini
[program:verification-imports]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/verification-site/artisan queue:work --queue=imports --sleep=5 --tries=1 --timeout=3600 --max-time=3600
autostart=true
autorestart=true
stopwaitsecs=3600
user=deploy
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/verification-site/storage/logs/imports.log
stopasgroup=true
killasgroup=true
```

`--tries=1` is deliberate: a failed import has already committed its earlier
chunks, so retrying replays the whole file rather than resuming. Re-uploading is
safe (rows upsert on `ticket_id`) and is the intended recovery.

Load them:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

> After each deploy, restart workers so they pick up new code:
> `php artisan queue:restart` (Supervisor relaunches them automatically).

---

## 16. Scheduler (cron) — **required for data-purchase reconciliation**

Run Laravel's scheduler every minute. The buy-data module relies on it:
`ReconcilePendingTransactions` re-queries the vendor for any purchase left in
`processing` (e.g. after a timeout or ambiguous response) and either confirms it
as `success` or refunds the wallet. Without the scheduler, those transactions —
and the customer's money — stay stuck until someone intervenes manually.

```bash
crontab -e -u deploy
```

Add:

```cron
* * * * * cd /var/www/verification-site && php artisan schedule:run >> /dev/null 2>&1
```

---

## 17. (Optional) Inertia SSR

The build produces an SSR bundle. SSR improves first-paint/SEO for public pages
(e.g. the landing page). It is **optional** — without the SSR process running,
Inertia renders on the client and the site works normally.

To enable SSR, run the SSR server under Supervisor.
Create `/etc/supervisor/conf.d/verification-ssr.conf`:

```ini
[program:verification-ssr]
command=php /var/www/verification-site/artisan inertia:start-ssr
autostart=true
autorestart=true
user=deploy
redirect_stderr=true
stdout_logfile=/var/www/verification-site/storage/logs/ssr.log
stopwaitsecs=10
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
```

> The SSR server listens on `127.0.0.1:13714` and is called internally by Laravel —
> it does **not** need a firewall rule. Rebuild (`npm run build`) and restart this
> program (`sudo supervisorctl restart verification-ssr`) on every deploy.
> If you do **not** want SSR, simply don't create this program.

---

## 18. Configure third-party webhooks

The wallet-funding flow receives a signed callback from Billstack. In the Billstack
dashboard, set the webhook URL to:

```
https://your-domain.com/api/webhooks/billstack
```

Notes:
- The endpoint verifies the `x-wiaxy-signature` header — make sure
  `BILLSTACK_API_TOKEN` in `.env` matches the account that sends the webhook.
- It must be reachable over **public HTTPS** (no IP allowlist blocking the provider).
- If you also use Paystack, configure its webhook/redirect URLs in the Paystack
  dashboard to point at your domain.

Test reachability:

```bash
curl -i https://your-domain.com/api/webhooks/billstack   # expect 405/422, not 404/502
```

---

## 19. Post-deploy verification checklist

```bash
# App responds
curl -I https://your-domain.com           # 200

# No stale caches / config errors
php artisan about

# DB reachable & migrated
php artisan migrate:status

# Queue worker running
sudo supervisorctl status

# Logs are clean
tail -n 50 storage/logs/laravel.log
```

Then in a browser:
- Landing page loads with your `APP_NAME` branding.
- `/register` and `/login` work (create a test account).
- Dashboard loads; try a small wallet funding to confirm the webhook credits it.

---

## 20. Redeploying (updates)

Create `deploy.sh` in the project root:

```bash
#!/usr/bin/env bash
set -e
cd /var/www/verification-site

php artisan down || true

git pull origin main
composer install --no-dev --optimize-autoloader
npm ci
npm run build

php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

php artisan queue:restart
sudo supervisorctl restart verification-ssr   # only if SSR is enabled

php artisan up
echo "Deploy complete."
```

```bash
chmod +x deploy.sh
./deploy.sh
```

---

## 21. Security & hardening checklist

- [ ] `APP_DEBUG=false` and `APP_ENV=production` in `.env`.
- [ ] `.env` is **not** in git and is `chmod 640` (readable by `deploy`/`www-data` only).
- [ ] Strong, unique DB password; PostgreSQL only listens on `127.0.0.1`.
- [ ] UFW limited to OpenSSH + Nginx Full; SSH key-only login (disable password auth).
- [ ] HTTPS enforced, `SESSION_SECURE_COOKIE=true`, HSTS via Certbot.
- [ ] `storage/` and `bootstrap/cache/` writable by `www-data`; nothing else world-writable.
- [ ] Provider API keys stored only in `.env`, rotated if ever exposed.
- [ ] Automated PostgreSQL backups (e.g. nightly `pg_dump` to off-server storage):
  ```bash
  pg_dump -U verification_user -h 127.0.0.1 verification_site | gzip > backup-$(date +%F).sql.gz
  ```
- [ ] Enable opcache and keep `php artisan config:cache` in place.

---

## 22. Troubleshooting

| Symptom | Likely cause / fix |
|---------|--------------------|
| **500 on every page** | Check `storage/logs/laravel.log`. Usually permissions on `storage`/`bootstrap/cache`, or a missing `APP_KEY` → `php artisan key:generate`. |
| **419 Page Expired** on login/forms | Session cookie/domain mismatch. Set `SESSION_DOMAIN` to your domain, `APP_URL` to the `https://` URL, `SESSION_SECURE_COOKIE=true`, then `php artisan config:cache`. |
| **"Vite manifest not found"** | Assets weren't built on the server. Run `npm ci && npm run build`; ensure `public/build/manifest.json` exists. |
| **Blank page / 502** | PHP-FPM socket path wrong in Nginx (`/var/run/php/php8.3-fpm.sock`) or PHP-FPM not running: `sudo systemctl status php8.3-fpm`. |
| **DB connection refused** | `php8.3-pgsql` missing, wrong `DB_*` creds, or Postgres not running. Test with the `psql "postgresql://…"` string from step 6. |
| **Config changes ignored** | A cached config is stale: `php artisan optimize:clear` then re-cache. |
| **Queued jobs never run** | Supervisor worker down: `sudo supervisorctl status`; after deploys run `php artisan queue:restart`. |
| **Data purchases stuck at `pending`** (wallet debited, nothing sent) | The queue worker isn't running — see step 15. Check `SELECT COUNT(*) FROM jobs;` for a backlog. Once the worker starts, queued purchases are sent to the vendor **live**, so flush stale test jobs first if they shouldn't go through. |
| **Data purchases stuck at `processing`** | The scheduler isn't running, so `ReconcilePendingTransactions` never requeries/refunds — see step 16. |
| **Webhook returns 404** | Wrong URL — it's `/api/webhooks/billstack`. 419/422/405 means it's reachable (good); 404 means routing/Nginx issue. |
| **SSR errors / hydration warnings** | Stop relying on SSR: don't run the `verification-ssr` program (the app falls back to client rendering). If using SSR, rebuild and restart it after each deploy. |

---

### Quick command reference

```bash
# logs
tail -f storage/logs/laravel.log
sudo tail -f /var/log/nginx/error.log

# services
sudo systemctl restart php8.3-fpm nginx
sudo supervisorctl restart all

# clear + rebuild caches
php artisan optimize:clear && php artisan optimize
```
