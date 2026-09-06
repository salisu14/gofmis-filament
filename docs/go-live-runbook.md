# GOF MIS — Production Deployment & Go-Live Runbook

This document provides the canonical, step-by-step operational procedure for deploying **GOF MIS** to production and staging environments, executing go-live verification, and performing emergency rollbacks.

---

## 1. Pre-Deployment Readiness & Approvals

Before initiating any deployment window, the deployment operator must verify that all pre-flight conditions are satisfied.

### 1.1 Mandatory Change Window & Approvals
- **Change Window**: Schedule off-peak deployment (recommended Sunday 00:00–02:00 UTC).
- **Stakeholder Notification**: Notify system administrators, finance officers, and zone coordinators 24 hours prior to maintenance.
- **Freeze Active Sessions**: Announce maintenance window so field coordinators complete pending biometrics or intake before the window begins.

### 1.2 Database & Media Backup Verification
Execute a full logical database backup and file storage snapshot immediately prior to deployment:

```bash
# 1. Database Logical Dump (PostgreSQL example)
pg_dump -U gofmis_user -h localhost -F c -b -v -f /backups/gofmis_pre_deploy_$(date +%Y%m%d_%H%M%S).dump gofmis_production

# 2. Private Certificate & Storage Archive
tar -czf /backups/gofmis_storage_$(date +%Y%m%d_%H%M%S).tar.gz /var/www/gofmis/storage/app
```

> [!CAUTION]
> Never proceed with deployment if the database backup verification fails or if free disk space is under 20%.

---

## 2. Environment Pre-Flight Audit

Run the read-only automated health check tool against the target environment:

```bash
php artisan system:health-check --json
```

Verify that:
- Database connectivity is active.
- `APP_ENV` is set to `production`.
- `APP_DEBUG` is `false`.
- Storage paths (`storage/app/private`, `storage/app/public`, `storage/logs`, `bootstrap/cache`) are writable by the web application user (`www-data`).
- All core system roles (`super_admin`, `admin`, `coordinator`, `auditor`, `demo_observer`) are seeded.

---

## 3. Step-by-Step Deployment Execution Sequence

Execute commands in exact sequence from the application root directory (e.g. `/var/www/gofmis`):

### Step 3.1: Code Checkout
```bash
git checkout main
git pull origin main
```

### Step 3.2: Production PHP Dependency Installation
```bash
composer install --no-dev --optimize-autoloader --no-interaction
```

### Step 3.3: Database Migrations
Execute non-destructive database migrations against the production schema:

```bash
php artisan migrate --force
```

> [!IMPORTANT]
> Never run `php artisan migrate:fresh` in production. Only run `migrate --force`.

### Step 3.4: Asset Compilation (If Frontend Assets Built On Server)
```bash
npm ci
npm run build
```

### Step 3.5: Legacy Beneficiary Certificate Migration
Migrate legacy public-storage orphan birth certificates and deceased death certificates to secure private storage:

```bash
# 1. First run dry-run to inspect candidate files
php artisan beneficiaries:migrate-certificates-private

# 2. Execute migration and clean up public disk copies safely
php artisan beneficiaries:migrate-certificates-private --apply --cleanup-public
```

### Step 3.6: Optimization & Configuration Caching
Cache Laravel configuration, routes, events, and views to optimize request latency:

```bash
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache
php artisan icons:cache
```

### Step 3.7: Queue Worker Restart
Gracefully restart Supervisor background queue workers to pick up newly deployed code:

```bash
php artisan queue:restart
sudo supervisorctl restart gofmis-worker:*
```

---

## 4. Post-Deployment Verification & Smoke Tests

### 4.1 Automated Post-Flight Health Audit
```bash
php artisan system:health-check
```

Confirm that overall status is `PASS`.

### 4.2 Read-Only Reconciliation Audits
Run diagnostic reconciliation tools to verify system integrity:

```bash
# 1. Security & RBAC Audit
php artisan security:rbac-audit --details

# 2. Financial Ledger Audit (Read-Only)
php artisan finance:reconcile --details

# 3. Widow Loan Portfolio Audit (Read-Only)
php artisan widow-loans:reconcile

# 4. Inventory Stock Ledger Audit (Read-Only)
php artisan inventory:reconcile

# 5. ID Card Lifecycle Audit (Read-Only)
php artisan id-cards:reconcile --details
```

### 4.3 Manual Functional Smoke Checklist

| Component | Test Action | Expected Result | Pass/Fail |
| :--- | :--- | :--- | :---: |
| **Authentication** | Login as Super Admin | Successfully logs in; MFA challenged if enrolled | [ ] |
| **MFA Verification** | Verify TOTP code | Challenge accepted; redirects to Admin Dashboard | [ ] |
| **Coordinator Access** | Login as Coordinator | Redirects to `/coordinator`; sees only assigned zone data | [ ] |
| **Deceased Records** | View Deceased dossier | Preview death certificate renders securely | [ ] |
| **Orphan Records** | View Orphan dossier | Preview birth certificate renders securely | [ ] |
| **Widow Loans** | Navigate to Widow Loans | Loan list & repayment schedules display properly | [ ] |
| **ID Card Module** | Open ID Card Batches | Active/Revoked single-active status rendered | [ ] |
| **Finance Module** | Open General Ledger | Accounts & Journal Entries display balanced | [ ] |

---

## 5. Rollback & Disaster Recovery Strategy

If a critical failure occurs during deployment (e.g., severe application error, unresolvable DB migration issue, missing media assets):

### 5.1 Application Code Rollback
```bash
# 1. Enable maintenance mode
php artisan down --secret="gofmis-emergency-bypass"

# 2. Checkout previous commit/tag
git checkout <PREVIOUS_RELEASE_TAG_OR_COMMIT>

# 3. Re-install composer dependencies
composer install --no-dev --optimize-autoloader --no-interaction

# 4. Re-build frontend assets
npm ci && npm run build

# 5. Re-cache application configuration
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Restart queue workers
php artisan queue:restart
sudo supervisorctl restart gofmis-worker:*

# 7. Disable maintenance mode
php artisan up
```

### 5.2 Database Disaster Recovery (If Schema Corruption Occurs)
If migrations caused irreversible structural damage or data loss:

1. Enable maintenance mode (`php artisan down`).
2. Terminate active database connections.
3. Restore pre-deployment database dump:
   ```bash
   pg_restore -U gofmis_user -h localhost -d gofmis_production -c /backups/gofmis_pre_deploy_YYYYMMDD_HHMMSS.dump
   ```
4. Restore file storage archive if file operations were disrupted:
   ```bash
   tar -xzf /backups/gofmis_storage_YYYYMMDD_HHMMSS.tar.gz -C /
   ```
5. Clear caches and bring application online (`php artisan config:cache && php artisan up`).

---

## 6. Worker & Cron Supervision Reference

### 6.1 Server Crontab (Required for Scheduled Tasks)
Ensure the following single cron entry is active on the application server for user `www-data`:

```cron
* * * * * cd /var/www/gofmis && php artisan schedule:run >> /dev/null 2>&1
```

### 6.2 Supervisor Configuration (`/etc/supervisor/conf.d/gofmis-worker.conf`)
```ini
[program:gofmis-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/gofmis/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/gofmis/storage/logs/worker.log
```

---

## 7. Operational Sign-Off Matrix

- **Deployment Operator**: ___________________________ Date: ____________
- **Lead System Administrator**: _____________________ Date: ____________
- **QA Sign-Off**: _________________________________ Date: ____________
