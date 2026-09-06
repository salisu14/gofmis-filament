# GOF MIS — Administrator Manual & Operational Guide

## 1. System Overview & Architecture

The **Garko Orphans Foundation Management Information System (GOF MIS)** is an enterprise welfare and operational management platform engineered for beneficiary management, intervention distribution, widow revolving loan administration, financial accounting, and field coordinator management across designated geographic zones.

### Core Architecture Principles
- **Role-Based Access Control (RBAC)**: Managed via Spatie Laravel-Permission with strict policies.
- **Zone Partitioning**: Field operations are isolated per geographic zone; administrators maintain global overview.
- **Single Main Treasury Invariant**: One primary General Operating Bank Account; all sub-accounts (Loan Disbursement, Repayment, Education, OOP) branch strictly as child accounts.
- **Sensitive Action Safeguards**: Password re-authentication and text confirmation phrases (`DISABLE MFA`, `WRITE OFF LOAN`, `DELETE USER`) protect destructive or sensitive operations.
- **Audit-First Design**: All model mutations, administrative security actions, biometrics access, and financial transactions are recorded in append-only audit logs (`Activity`).

---

## 2. Role Hierarchy & RBAC Permissions

### User Roles Matrix
| Role Signature | Access Scope | Key Responsibilities |
| :--- | :--- | :--- |
| `super_admin` | Global Access | Full system administration, MFA deactivation, loan write-off approval, user management, bank balance repair. |
| `admin` | Global Access | Beneficiary approval, intervention management, WRL loan approval, report generation, project management. |
| `finance-custodian` | Financial Scope | Treasury management, out-of-pocket reimbursements, bank account supervision, financial report generation. |
| `education-verifier`| Education Scope | Education request verification, fee invoice approval, academic progression review. |
| `coordinator` | Zone-Scoped | Field intake for Deceased, Widows, Orphans; education/welfare request submission; biometric enrollment. |
| `auditor` | Read-Only Global | Financial ledger read-only inspection, audit log review, compliance reporting. |
| `demo_observer` | Read-Only Global | Demonstration walkthrough access; mutation actions blocked by `DemoReadOnlyGuard`. |

---

## 3. Security & Multi-Factor Authentication (MFA)

### MFA Policy & Enforcement
- Multi-Factor Authentication (TOTP) is mandatory for administrative and coordinator accounts.
- System supports authenticator apps (Google Authenticator, Microsoft Authenticator, Authy).
- Recovery codes are generated upon enrollment and destroyed immediately after download/display.

### Admin MFA Management Page (`/admin/mfa-management`)
Super Admins can administer user MFA states from the dedicated MFA Security Center:
1. **View Status**: Inspect whether user MFA is Active, Pending, or Deactivated.
2. **Force Enrollment**: Require an unenrolled user to complete TOTP setup on their next login.
3. **Disable MFA**: Deactivate MFA for an active user in operational emergencies. Requires Super Admin password and `DISABLE MFA` phrase confirmation. Emits `MFA_DISABLED_BY_ADMIN` audit event.
4. **Reset MFA**: Clear existing credentials requiring the user to re-enroll.

---

## 4. Beneficiary Administration

### Core Resources
- **Deceased Records (`/admin/deceaseds`)**: Household registration base. Automatically generates unique registration numbers (e.g. `DEC-KCZ-001`).
- **Widow Profiles (`/admin/widows`)**: Tracks marital status, dependents, WRL loan eligibility, and support history. Remarried widows retain loan repayment obligations but are blocked from new disbursements.
- **Orphan Profiles (`/admin/orphans`)**: Tracks age (18-year over-age threshold), custody, education history, and medical records.
- **Beneficiary ID Cards (`/admin/id-cards`)**:
  - Issues unique physical/digital ID cards with encrypted QR verification tokens.
  - Enforces **Single Active Card Invariant**: A beneficiary can possess at most one active card.
  - Automated weekly cron (`id-cards:reconcile`) revokes cards for deceased, archived, or over-aged beneficiaries.

---

## 5. Financial Supervision & Treasury Accounts

### Bank Account Structure (`/admin/bank-accounts`)
- **Main Operating Account**: Primary bank account (`usage = general`). All system money flows branch from this account.
- **Dedicated Sub-Accounts**:
  - `WRL Disbursement Account`
  - `WRL Repayment Account`
  - `Child Education Account`
  - `Out-Of-Pocket Reimbursement Account`
- Balance reconciliation is audited daily via `finance:reconcile`. Any discrepancy between computed ledger transaction sums and stored bank balances is flagged for administrative review.

---

## 6. System Maintenance Commands Reference

Administrators execute system commands via CLI:

```bash
# Evaluate daily loan delinquency and Days Past Due (DPD)
php artisan widow-loans:evaluate-delinquency

# Read-only financial ledger audit
php artisan finance:reconcile --details

# Manual repair of bank balances inside DB transaction (Super Admin only)
php artisan finance:repair-bank-balances

# Read-only stock & inventory audit
php artisan inventory:reconcile --details

# Read-only WRL loan portfolio audit
php artisan widow-loans:reconcile

# ID card status & over-age auto-revocation audit
php artisan id-cards:reconcile --details

# RBAC and route security audit
php artisan security:rbac-audit --details
```

---

## 7. Audit Trail & Sensitive Action Security

All sensitive operations record the acting user ID, target ID, IP address, user agent, and timestamp:
- **User Creation/Deletion**: Logged in Spatie Activity Log.
- **Loan Write-Offs**: Wipes debt from active ledger; requires Super Admin password, signed evidence upload, and confirmation text phrase.
- **Biometric Access**: Template access or verification logs audit events without exposing binary cipher payloads.
