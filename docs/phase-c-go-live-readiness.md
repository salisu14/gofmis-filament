# GOF MIS — Phase C Go-Live Readiness Roadmap

## Phase C Objective
Transition the fully implemented GOF MIS application to production readiness through security hardening, automated reconciliation scheduling, manual browser UAT sign-off, user documentation delivery, and staging dry-run execution.

---

## Phase C Work Packages & Status

### WP-C01: Security & Permission Hardening
- **Status**: `CLOSED / COMMITTED` (Commit `f312e81`)
- **Summary**: Hardened sponsorship authorization, seeded missing permissions, and added environment verification safeguards.

### WP-C02: Scheduled Reconciliation & Monitoring
- **Status**: `CLOSED / COMMITTED` (Commit `0052455`)
- **Summary**: Configured background reconciliation cron schedules in `routes/console.php` for financial, inventory, loan, ID card, security RBAC, and coordinator assignment audit commands.

### WP-C03: Manual Browser UAT Sign-Off
- **Status**: `CLOSED / COMMITTED / MANUAL UAT PASS` (Commits `6435720`, `2f84c77`, `2c5c5ef`)
- **Summary**: Resolved single-active ID card invariant, private beneficiary certificate access, and MFA admin action visibility. Completed 100% human browser UAT across all 8 documented acceptance criteria.

### WP-C04: System SOP & User Manual Documentation
- **Status**: `CLOSED / COMMITTED` (Commit `04e65dd`)
- **Summary**: Authored comprehensive operational manuals (`administrator-manual.md`, `coordinator-field-sop.md`, `finance-and-banking-sop.md`, `widow-loan-operating-guide.md`) and added automated completeness test (`SystemDocumentationCompletenessTest.php`).

### WP-C05: Staging & Production Deployment Dry-Run / Go-Live Hardening
- **Status**: `IMPLEMENTED / READY FOR STAGING VALIDATION`
- **Summary**: Built automated system pre-flight health audit (`php artisan system:health-check`), safe dry-run certificate storage migration CLI (`php artisan beneficiaries:migrate-certificates-private`), comprehensive Go-Live & Deployment Runbook (`docs/go-live-runbook.md`), and completed production readiness audits. Repository code & tooling readiness is 100% complete; remote target host validation remains pending controlled staging deployment.

---

## Overall Phase C Readiness Classification
- **Repository Code & Tooling**: `PHASE C IMPLEMENTATION COMPLETE`
- **Staging / Infrastructure Validation**: `READY FOR STAGING VALIDATION`

---

## WP-C05 Completed Hardening Actions

### 1. Legacy Beneficiary Certificate Private Storage Migration Tooling
- **Command**: `php artisan beneficiaries:migrate-certificates-private [--apply] [--cleanup-public] [--json]`
- **Features**: Defaults to safe, read-only dry-run mode. Audits Orphan birth certificates and Deceased death certificates, verifies sha256 hashes, copies files from `public` to `local` (private) disk, and reports unreferenced public files. Covered by automated unit tests in `BeneficiaryCertificateMigrationTest.php`.

### 2. Automated Production Pre-Flight Audit Tooling
- **Command**: `php artisan system:health-check [--json] [--strict]`
- **Features**: Performs automated read-only pre-flight audit of DB connectivity, pending migrations, environment config (`APP_ENV`, `APP_DEBUG`, `SESSION_DRIVER`), biometric encryption keys, storage directory permissions, core RBAC role seeds, and queue/cache drivers. Covered by automated unit tests in `SystemHealthCheckCommandTest.php`.

### 3. Go-Live Runbook & Deployment Guide
- **Document**: [`docs/go-live-runbook.md`](file:///home/salsafh/codes/projects/gof/gofmis-atg/docs/go-live-runbook.md)
- **Features**: Step-by-step deployment sequence, off-peak change window protocols, PostgreSQL database backup/restore procedures, Supervisor worker configuration, crontab setup, post-deployment manual smoke checklist, and disaster recovery application/database rollback instructions.

---

## Go-Live Readiness Gate Classification Matrix

| Gate # | Gate Description | Readiness Classification | Evidence / Requirement |
| :---: | :--- | :---: | :--- |
| 1 | Codebase & Test Suite Health | **PASS** | 1346 passed, 1 skipped / 4806 assertions |
| 2 | Database Migration Safety | **PASS** | Additive migrations verified; `migrate --force` safe |
| 3 | Repository Configuration Readiness | **PASS** | `.env.example` hardened with session & encryption keys |
| 4 | Target Host Environment Config | **ENVIRONMENT VERIFICATION REQUIRED** | Target server `.env` validation during deployment |
| 5 | Security & MFA Code Hardening | **PASS** | RBAC, MFA, zone isolation, signed URLs active |
| 6 | Queue Subsystem Architecture | **PASS** | Supervisor config created & `queue:restart` tested |
| 7 | Target Host Queue Worker Daemon | **ENVIRONMENT VERIFICATION REQUIRED** | Supervisor daemon active on target host |
| 8 | Scheduler Code Architecture | **PASS** | 7 background tasks registered in `routes/console.php` |
| 9 | Target Host Cron Installation | **ENVIRONMENT VERIFICATION REQUIRED** | Crontab entry active on target host |
| 10 | Filesystem Security Architecture | **PASS** | Private vs public storage disk separation enforced |
| 11 | Target Host Directory Permissions | **ENVIRONMENT VERIFICATION REQUIRED** | Target host permissions (`www-data` writability) |
| 12 | Private Certificate Migration CLI | **PASS** | `beneficiaries:migrate-certificates-private` verified |
| 13 | Automated Pre-Flight Health Check CLI | **PASS** | `system:health-check` verified |
| 14 | Deployment Runbook Document | **PASS** | `docs/go-live-runbook.md` authored |
| 15 | Backup & Disaster Recovery Design | **PASS** | `pg_dump` & `pg_restore` steps documented |
| 16 | Target Host Backup / Restore Test | **ENVIRONMENT VERIFICATION REQUIRED** | Execution of backup/restore on staging host |
| 17 | SMTP Mail Delivery Test | **ENVIRONMENT VERIFICATION REQUIRED** | Real SMTP server test on staging host |
| 18 | Manual Post-Deployment Smoke Test | **MANUAL VERIFICATION REQUIRED** | Human operator verification post-deployment |
| 19 | Remote Staging Host Dry-Run | **ENVIRONMENT VERIFICATION REQUIRED** | Target staging deployment dry-run |
