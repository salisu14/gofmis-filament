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
- **Cron Scheduler**: Add to server crontab (example deployment path `/var/www/gofmis`):
  ```cron
  * * * * * cd /var/www/gofmis && php artisan schedule:run >> /dev/null 2>&1
  ```

  **Scheduled Tasks & Cadence Policy**:
  - `widow-loans:evaluate-delinquency`: Daily at 00:00 (DPD evaluation & status updates)
  - `finance:reconcile`: Daily at 01:00 (Read-only financial ledger diagnostic audit)
  - `inventory:reconcile`: Daily at 01:30 (Read-only stock movement ledger audit)
  - `widow-loans:reconcile`: Daily at 02:00 (Read-only WRL portfolio audit)
  - `id-cards:reconcile`: Weekly on Sunday at 02:30 (Read-only ID card status & expiration audit)
  - `security:rbac-audit`: Weekly on Sunday at 03:00 (Read-only security & permission audit)
  - `zone-coordinators:reconcile`: Monthly on 1st at 04:00 (Read-only coordinator assignment audit)

  **Failure Monitoring & Log Evidence**:
  - Scheduled command executions and errors log directly to standard application logs (`storage/logs/laravel.log`).
  - Failed command executions return non-zero CLI exit codes as a machine-detectable failure signal.
  - OS-level crontab trigger is required in production; actual server crontab installation is managed during host deployment.

  > [!IMPORTANT]
  > **Manual-Only Repair Commands**: Mutating repair commands (`finance:repair-bank-balances`, `zone-coordinators:backfill`, `finance:fix-transaction-morphs`, `biometrics:reencrypt`) MUST NOT be scheduled unattended. They must be executed manually by authorized system administrators after reviewing diagnostic audit logs.

---

## 5. Storage & File Security

- **Public Storage**:
  - Symlink public storage: `php artisan storage:link`
  - Uploaded beneficiary photos and public assets stored on `public` disk (`storage/app/public`).
- **Private Storage**:
  - Beneficiary certificates (Orphan birth certificates & Deceased death certificates), hardship evidence, loan write-off documents, and OOP receipts are stored on `local` (private) disk (`storage/app/private`).
  - Access is restricted via authorized controller endpoints (`BeneficiaryCertificateController`, `/loans/write-off-documents/{writeOff}`).
  - Legacy public certificate files are safely migrated to private storage via `php artisan beneficiaries:migrate-certificates-private --apply --cleanup-public`.

---

## 6. Pre-Flight Deployment Sequence & Runbook

For complete operational steps, maintenance window procedures, and disaster recovery, refer to [`docs/go-live-runbook.md`](file:///home/salsafh/codes/projects/gof/gofmis-atg/docs/go-live-runbook.md).

1. **Automated Environment Pre-Flight Audit**:
   ```bash
   php artisan system:health-check
   ```
2. **Code Checkout**:
   ```bash
   git checkout main && git pull origin main
   ```
3. **Install Production Dependencies**:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
4. **Database Migration**:
   ```bash
   php artisan migrate --force
   ```
5. **Beneficiary Certificate Storage Migration**:
   ```bash
   php artisan beneficiaries:migrate-certificates-private --apply --cleanup-public
   ```
6. **Frontend Assets Compilation**:
   ```bash
   npm ci && npm run build
   ```
7. **Optimization & Caching**:
   ```bash
   php artisan config:cache
   php artisan event:cache
   php artisan route:cache
   php artisan view:cache
   php artisan icons:cache
   ```
8. **Queue & Worker Restart**:
   ```bash
   php artisan queue:restart
   sudo supervisorctl restart gofmis-worker:*
   ```
9. **Diagnostic & Reconciliation Verification**:
   ```bash
   php artisan security:rbac-audit --details
   php artisan finance:reconcile --details
   php artisan widow-loans:reconcile
   php artisan inventory:reconcile
   php artisan id-cards:reconcile --details
   ```
