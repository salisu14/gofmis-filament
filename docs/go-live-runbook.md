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

### 4.3 Manual Functional Smoke Checklist (24-Point Staging Browser Audit)

For each check, the human operator must record one of: `PASS`, `FAIL`, or `NOT TESTED`. Do NOT pre-mark any result as PASS.

| # | Category | Test Check / Action | Expected Result | Operator Result |
| :---: | :--- | :--- | :--- | :---: |
| **AUTHENTICATION & MFA** | | | | |
| 1 | Authentication & MFA | Super Admin login page loads. | Login form renders cleanly with CSRF token and inputs | [ ] PASS  [ ] FAIL  [ ] NOT TESTED |
| 2 | Authentication & MFA | Super Admin login succeeds. | Valid credentials accepted without server errors | [ ] PASS  [ ] FAIL  [ ] NOT TESTED |
| 3 | Authentication & MFA | MFA challenge appears for enrolled Super Admin. | Prompted for TOTP 6-digit code after primary auth | [ ] PASS  [ ] FAIL  [ ] NOT TESTED |
| 4 | Authentication & MFA | Valid TOTP completes authentication. | Code accepted, session verified, redirected to dashboard | [ ] PASS  [ ] FAIL  [ ] NOT TESTED |
| 5 | Authentication & MFA | Logout terminates authenticated session. | Session invalidated, redirected back to login page | [ ] PASS  [ ] FAIL  [ ] NOT TESTED |
| **SUPER ADMIN CORE ACCESS** | | | | |
| 6 | Super Admin Core Access | Admin dashboard loads without errors. | Key metrics, navigation widgets, and status panels load cleanly | [ ] PASS  [ ] FAIL  [ ] NOT TESTED |
| 7 | Super Admin Core Access | Users/MFA administration page loads. | User list, role badges, and MFA status controls render | [ ] PASS  [ ] FAIL  [ ] NOT TESTED |
| 8 | Super Admin Core Access | Deceased resource/list/view loads. | Deceased records table and individual dossier pages display | [ ] PASS  [ ] FAIL  [ ] NOT TESTED |
| 9 | Super Admin Core Access | Widow resource/list/view loads. | Widow records table and individual dossier pages display | [ ] PASS  [ ] FAIL  [ ] NOT TESTED |
| 10 | Super Admin Core Access | Orphan resource/list/view loads. | Orphan records table and individual dossier pages display | [ ] PASS  [ ] FAIL  [ ] NOT TESTED |
| **COORDINATOR & ZONE ISOLATION** | | | | |
| 11 | Coordinator & Zone Isolation | Coordinator login succeeds. | Field coordinator account logs in cleanly | [ ] PASS  [ ] FAIL  [ ] NOT TESTED |
| 12 | Coordinator & Zone Isolation | Coordinator dashboard loads. | Dashboard restricted to coordinator navigation and zone scope | [ ] PASS  [ ] FAIL  [ ] NOT TESTED |
| 13 | Coordinator & Zone Isolation | Coordinator can access an assigned-zone beneficiary. | Assigned zone records viewable and editable | [ ] PASS  [ ] FAIL  [ ] NOT TESTED |
| 14 | Coordinator & Zone Isolation | Coordinator is denied access to a beneficiary outside assigned zone. | HTTP 403 Forbidden or missing record notification | [ ] PASS  [ ] FAIL  [ ] NOT TESTED |
| **PRIVATE FILES / MEDIA** | | | | |
| 15 | Private Files / Media | Beneficiary photo renders. | Profile picture renders securely without broken image link | [ ] PASS  [ ] FAIL  [ ] NOT TESTED |
| 16 | Private Files / Media | Deceased death certificate preview works. | In-browser PDF/image preview streams cleanly from private storage | [ ] PASS  [ ] FAIL  [ ] NOT TESTED |
| 17 | Private Files / Media | Deceased death certificate download works with sanitized filename. | Download header triggers with sanitized filename | [ ] PASS  [ ] FAIL  [ ] NOT TESTED |
| 18 | Private Files / Media | Orphan birth certificate preview works. | In-browser PDF/image preview streams cleanly from private storage | [ ] PASS  [ ] FAIL  [ ] NOT TESTED |
| 19 | Private Files / Media | Orphan birth certificate download works with sanitized filename. | Download header triggers with sanitized filename | [ ] PASS  [ ] FAIL  [ ] NOT TESTED |
| **BUSINESS WORKFLOWS** | | | | |
| 20 | Business Workflows | Widow Loan resource and representative loan details load. | Loan list, principal amounts, and status tags display | [ ] PASS  [ ] FAIL  [ ] NOT TESTED |
| 21 | Business Workflows | Widow Loan repayment schedule / DPD status renders. | Repayment installments and Days Past Due (DPD) status load | [ ] PASS  [ ] FAIL  [ ] NOT TESTED |
| 22 | Business Workflows | Beneficiary ID-card status/lifecycle view loads correctly. | Single active card rule reflected; status badges active/revoked | [ ] PASS  [ ] FAIL  [ ] NOT TESTED |
| 23 | Business Workflows | Finance General Ledger / Journal view loads without imbalance/UI error. | GL accounts and balanced debits/credits render correctly | [ ] PASS  [ ] FAIL  [ ] NOT TESTED |
| **DOCUMENT / RESPONSIVE** | | | | |
| 24 | Document / Responsive | Representative beneficiary PDF preview renders and representative mobile viewport remains usable. | PDF document generates properly and UI layout remains responsive on mobile screens | [ ] PASS  [ ] FAIL  [ ] NOT TESTED |

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
