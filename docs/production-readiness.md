# GOF MIS — Production Readiness Guide & Checklist

This document provides deployment guidelines, system configuration settings, security hardening procedures, and operational checklists for deploying GOF MIS to production environments.

---

## 1. Environment & Configuration Settings (`.env`)

> [!IMPORTANT]
> Never commit actual passwords, secrets, API tokens, or private keys to source control.

| Setting | Value / Instruction |
| :--- | :--- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://gofmis.org` (replace with actual domain) |
| `APP_KEY` | Generate with `php artisan key:generate` |
| `LOG_CHANNEL` | `daily` or `single` |
| `LOG_LEVEL` | `info` or `warning` |

---

## 2. Session & Cookie Hardening

| Setting | Value | Description |
| :--- | :--- | :--- |
| `SESSION_DRIVER` | `database` / `redis` | Prevents session tampering |
| `SESSION_LIFETIME` | `120` | Expiration in minutes |
| `SESSION_SECURE_COOKIE` | `true` | Enforces HTTPS-only cookies |
| `SESSION_HTTP_ONLY` | `true` | Prevents JavaScript access to cookies |
| `SESSION_SAME_SITE` | `lax` / `strict` | Mitigates CSRF vulnerabilities |

---

## 3. Database & Connection Pooling

- **Database Engine**: PostgreSQL 14+ recommended (SQLite used in local/testing).
- **Charset & Collation**: UTF-8 (`utf8mb4`).
- **Connection Supervision**: Configure PgBouncer if transaction volume requires connection pooling.
- **Backup Policy**: Automated daily full logical backups (`pg_dump`) with point-in-time WAL archiving.

---

## 4. Cache, Queue & Scheduler

- **Cache Driver**: `redis` or `database`.
- **Queue Driver**: `redis` or `database`.
- **Queue Supervision**: Supervisor configuration to keep queue workers running:
  ```ini
  [program:gofmis-worker]
  process_name=%(program_name)s_%(process_num)02d
  command=php /var/www/gofmis/artisan queue:work --sleep=3 --tries=3 --max-time=3600
  autostart=true
  autorestart=true
  stopasgroup=true
  killasgroup=true
  numprocs=2
  redirect_stderr=true
  stdout_logfile=/var/www/gofmis/storage/logs/worker.log
  ```
- **Cron Scheduler**: Add to server crontab:
  ```cron
  * * * * * cd /var/www/gofmis && php artisan schedule:run >> /dev/null 2>&1
  ```

---

## 5. Storage & File Security

- **Public Storage**:
  - Symlink public storage: `php artisan storage:link`
  - Uploaded photos and certificates stored on `public` disk.
- **Private Storage**:
  - Hardship evidence and write-off documents stored on `local` (private) disk.
  - Access restricted via authorized controller endpoints (`/loans/write-off-documents/{writeOff}`).

---

## 6. Pre-Flight Deployment Sequence

1. **Code Checkout**:
   ```bash
   git checkout main && git pull origin main
   ```
2. **Install Production Dependencies**:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
3. **Database Migration**:
   ```bash
   php artisan migrate --force
   ```
4. **Optimization & Caching**:
   ```bash
   php artisan config:cache
   php artisan event:cache
   php artisan route:cache
   php artisan view:cache
   php artisan icons:cache
   ```
5. **Queue & Worker Restart**:
   ```bash
   php artisan queue:restart
   ```
6. **Diagnostic Verification**:
   ```bash
   php artisan security:rbac-audit --details
   php artisan finance:reconcile --details
   php artisan finance:repair-bank-balances
   php artisan id-cards:reconcile --details
   php artisan widow-loans:evaluate-delinquency
   php artisan widow-loans:reconcile
   ```
