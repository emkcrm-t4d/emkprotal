# EMK Membership CRM & Member Portal: Production Deployment Runbook

**Document ID:** RUNBOOK-03-01  
**Phase:** 03-application-foundation-and-deployment-baseline  
**Version:** 1.0  
**Target Environment:** Linux Production / Staging (Ubuntu 22.04 / 24.04 LTS)  
**Governing Architecture:** [PROJECT.md](file:///d:/EMK_Protal/.planning/PROJECT.md), [SECURITY-SPEC.md](file:///d:/EMK_Protal/.planning/phases/02-architecture-data-model-and-security-design/SECURITY-SPEC.md), [OPERATIONAL-TARGETS.md](file:///d:/EMK_Protal/.planning/phases/01-discovery-decisions-and-acceptance-criteria/OPERATIONAL-TARGETS.md)

---

## 1. System Prerequisites

The EMK Membership CRM requires the following host runtime environment:

| Runtime Component | Required Version | Purpose / Notes |
|---|---|---|
| **Operating System** | Ubuntu 22.04 LTS or 24.04 LTS | Standard hosting base |
| **PHP** | 8.3.x (CLI & FPM) | Extensions: `pdo_mysql`, `redis`, `bcmath`, `mbstring`, `xml`, `zip`, `gd`, `pcntl`, `posix`, `curl` |
| **Composer** | 2.7+ | Dependency manager |
| **MySQL Database** | 8.4 LTS | Engine: InnoDB; Charset: `utf8mb4`; Collation: `utf8mb4_unicode_ci`; Triggers enabled |
| **Redis Cache / Queue** | 7.2+ | Memory store for Horizon queues, caching, and rate limiting |
| **Node.js & npm** | Node.js 20.x LTS & npm 10.x | Vite and Tailwind CSS asset compilation |
| **Process Manager** | systemd | Horizon queue worker supervision and auto-restart |
| **Web Server** | Nginx 1.24+ | TLS 1.3 termination and reverse proxy to PHP-FPM 8.3 |

---

## 2. Environment Configuration Checklist & Secret Provisioning

Before initiating deployment, verify that `/var/www/emk-portal/.env` has been provisioned via secure configuration management (e.g., HashiCorp Vault, AWS Secrets Manager, or protected Ansible vault). **Never store credentials or private keys in the Git repository.**

### Critical Environment Variables Checklist

```ini
APP_NAME="EMK Membership CRM"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://hub.emk.org.bd
APP_KEY=base64:... # Cryptographic 32-byte key generated via php artisan key:generate

# Logging (14-day daily rotation + JSON audit trail per DEC-11)
LOG_CHANNEL=daily
LOG_DAILY_DAYS=14
LOG_LEVEL=info

# Database (MySQL 8.4)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=emk_crm_prod
DB_USERNAME=emk_crm_user
DB_PASSWORD="<SECURE_PASSWORD>"
DB_ENGINE=InnoDB
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

# Multi-Guard Session Isolation per DEC-06
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_COOKIE=emk_web_session
SESSION_MEMBER_COOKIE=emk_member_session
SESSION_SECURE_COOKIE=true

# Redis & Horizon Infrastructure per FOUN-02
QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD="<SECURE_REDIS_PASSWORD>"
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_QUEUE_DB=2
HORIZON_PREFIX="emk_horizon:"

# Private File Storage Disk per DEC-09 / ARCH-06
FILESYSTEM_DISK=local
FILESYSTEM_PRIVATE_DISK=private
```

---

## 3. Zero-Downtime Deployment Sequence

Execute this sequential script on the production server (or via automated deployment runner like GitHub Actions / Envoy / Deployer):

```bash
#!/usr/bin/env bash
set -euo pipefail

DEPLOY_DIR="/var/www/emk-portal"
cd "$DEPLOY_DIR"

echo "=== [1/8] Putting application into maintenance mode if needed ==="
# Optional: php artisan down --render="errors::503" --secret="deploy-bypass-token"

echo "=== [2/8] Fetching latest commits from main branch ==="
git fetch origin main
git checkout main
git reset --hard origin/main

echo "=== [3/8] Installing production Composer dependencies ==="
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

echo "=== [4/8] Building production frontend assets ==="
npm ci --ignore-scripts
npm run build

echo "=== [5/8] Running database migrations with triggers ==="
php artisan migrate --force

echo "=== [6/8] Caching configuration, routes, and views ==="
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "=== [7/8] Gracefully restarting Laravel Horizon queue workers ==="
php artisan horizon:terminate

echo "=== [8/8] Verifying health check probe ==="
curl -fsS http://127.0.0.1:8000/healthz | grep '"status":"ok"'

echo "=== DEPLOYMENT COMPLETE ==="
```

---

## 4. Laravel Horizon Queue Supervisor Management

Horizon manages queue workers via Redis. On production servers, systemd ensures that the master `php artisan horizon` process remains active across reboots and restarts.

### 4.1 systemd Unit File: `/etc/systemd/system/horizon.service`

```ini
[Unit]
Description=Laravel Horizon Queue Master Supervisor
After=network.target redis-server.service mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/emk-portal
ExecStart=/usr/bin/php8.3 /var/www/emk-portal/artisan horizon
ExecReload=/usr/bin/php8.3 /var/www/emk-portal/artisan horizon:terminate
Restart=always
RestartSec=3
KillMode=process
TimeoutStopSec=60

[Install]
WantedBy=multi-user.target
```

### 4.2 Managing Horizon Service

```bash
# Reload systemd daemon
sudo systemctl daemon-reload

# Enable Horizon at boot
sudo systemctl enable horizon

# Start Horizon
sudo systemctl start horizon

# Check Horizon process status
sudo systemctl status horizon

# View live Horizon logs
sudo journalctl -u horizon -f
```

---

## 5. Scheduled Maintenance & Cron Configuration

Laravel's task scheduler handles queue pruning, log cleanup, and Horizon snapshots. Install this single line into `www-data` crontab (`crontab -u www-data -e`):

```cron
* * * * * cd /var/www/emk-portal && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled jobs registered in `routes/console.php`:
- `horizon:snapshot` (every 5 minutes): Records throughput and queue backlog metrics.
- `queue:prune-failed --hours=48` (daily): Purges dead-letter records older than 48 hours.
- `queue:prune-batches --hours=48` (daily): Purges job batch state older than 48 hours.

---

## 6. Operational Rollback & Disaster Recovery Runbook

### 6.1 Immediate Code Rollback

In the event of a breaking regression detected post-deployment:

```bash
cd /var/www/emk-portal

# Roll back to previous known-good commit hash
PREV_COMMIT="<PREVIOUS_STABLE_COMMIT>"
git reset --hard "$PREV_COMMIT"

# Reinstall production dependencies
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci --ignore-scripts && npm run build

# Clear and refresh application caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Terminate Horizon to spawn workers running previous code
php artisan horizon:terminate

# Assert health probe returns HTTP 200
curl -I http://127.0.0.1:8000/healthz
```

### 6.2 Database Migration Rollback

If the deployment included a schema migration that failed or requires rollback:

```bash
# Roll back the single last batch of migrations
php artisan migrate:rollback --step=1 --force

# Verify schema integrity
php artisan migrate:status
```

### 6.3 Disaster Recovery (RPO <= 1h, RTO <= 4h) per GAP-P1-06

1. **Database Restore:**
   - Retrieve latest encrypted daily or hourly snapshot from off-site backup storage.
   - Decrypt snapshot with master backup key:
     ```bash
     openssl enc -d -aes-256-cbc -in backup-2026-11-04.sql.enc -out restore.sql -pass file:/root/.backup_key
     ```
   - Import into clean MySQL 8.4 instance:
     ```bash
     mysql -u root -p emk_crm_prod < restore.sql
     ```
2. **Private Document Storage Sync:**
   - Restore encrypted document tree to `/var/www/emk-portal/storage/app/private/workspaces/`.
3. **Application Bring-Up:**
   - Launch application and restart Horizon daemon:
     ```bash
     sudo systemctl restart php8.3-fpm
     sudo systemctl restart horizon
     sudo systemctl restart nginx
     ```
   - Execute verification: `curl -fsS http://127.0.0.1:8000/healthz`
