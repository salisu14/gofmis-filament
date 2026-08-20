# GOF MIS — Manual Functional QA Checklist

This document provides a structured manual testing checklist for user acceptance testing (UAT) and functional QA verification across all role interfaces in GOF MIS.

---

## Manual QA Status Summary

| Status | Definition | Count |
| :--- | :--- | :---: |
| **PASS** | Automated test suite and manual code execution verified working | **28** |
| **FAIL** | Defects identified during testing | **0** |
| **BLOCKED** | Execution blocked by external dependency | **0** |
| **NOT TESTED** | Manual end-to-end browser walkthrough required prior to live release | **6** |
| **N/A** | Feature not applicable in current release scope | **0** |

---

## Functional Test Cases Matrix

| Module | Workflow | Expected Result | Role | Status | Notes |
| :--- | :--- | :--- | :--- | :---: | :--- |
| **Auth & Security** | Login + MFA Challenge | User completes MFA totp/recovery verification before accessing admin | `super_admin`, `admin`, `coordinator` | **PASS** | Covered in SecurityHardeningTest |
| **Auth & Security** | User Status Enforcement | Deactivated/Suspended user logged out immediately | All Roles | **PASS** | Verified via middleware |
| **Auth & Security** | Sensitive Action Re-auth | Deleting user or writing off loan requires password & confirmation phrase | `super_admin` | **PASS** | Verified in SecurityHardeningTest |
| **Beneficiaries** | Create Deceased | Deceased record persists cleanly with auto reg_no | `admin`, `coordinator` | **PASS** | Verified in FilamentResourceSmokeTest |
| **Beneficiaries** | Relation Manager Add Orphan | Inline modal creates orphan linked to deceased | `admin`, `coordinator` | **PASS** | Verified in FilamentRelationManagerSmokeTest |
| **Beneficiaries** | Relation Manager Add Widow | Inline modal creates widow linked to deceased | `admin`, `coordinator` | **PASS** | Verified in FilamentRelationManagerSmokeTest |
| **Beneficiaries** | Coordinator Zone Isolation | Coordinator cannot view/edit beneficiary from another zone | `coordinator` | **PASS** | Verified in CoordinatorZoneIsolationTest |
| **Medical** | Prescription Form Diagnosis Select | Illness option label displays safely without null crash | `admin`, `coordinator` | **PASS** | Verified in FilamentRelationManagerSmokeTest |
| **Medical** | Relation Manager Prescriptions | Medical record entry syncs diagnosis and medications | `admin`, `coordinator` | **PASS** | Verified in FilamentRelationManagerSmokeTest |
| **ID Cards** | ID Card Generation & Print | Single and batch ID cards generate unique card_number without duplicate | `admin` | **PASS** | Verified in IdCardWorkflowTest |
| **ID Cards** | ID Card Download Security | Cross-zone coordinator denied PDF download (403) | `coordinator` | **PASS** | Verified in CoordinatorZoneIsolationTest |
| **Education** | Record Invoice Payment | Payment debits child education bank account and updates balance | `admin` | **PASS** | Verified in FilamentRelationManagerSmokeTest |
| **Financial** | Bank Balance Reconciliation | Stored balance matches calculated transaction sum | `admin`, `auditor` | **PASS** | Verified via `finance:reconcile` |
| **Financial** | Bank Balance Repair Command | Dry run reports zero changes, apply updates inside DB transaction | `super_admin` | **PASS** | Verified in RepairBankBalancesTest |
| **Widow Loans** | Disburse Loan & Generate Schedule | Disbursing loan generates versioned repayment schedule | `admin` | **PASS** | Verified in WidowLoanDelinquencyAndHardshipTest |
| **Widow Loans** | Evaluate Delinquency | Daily command updates DPD and flags overdue/delinquent loans | `system` | **PASS** | Verified via `widow-loans:evaluate-delinquency` (Scheduled daily in `routes/console.php`) |
| **Widow Loans** | Report & Approve Hardship | Coordinator reports hardship; Admin verifies; Super Admin approves relief window | `coordinator`, `admin`, `super_admin` | **PASS** | Verified in `WidowLoanUiTest` & `WidowLoanDelinquencyAndHardshipTest` |
| **Widow Loans** | Recovery Activity & Promise to Pay | Log contact activities and register/fulfill/break promise to pay | `coordinator`, `admin` | **PASS** | Verified in `WidowLoanUiTest` |
| **Widow Loans** | Write-Off Recommendation | Coordinator/Admin recommends write-off for administrative review | `coordinator`, `admin` | **PASS** | Verified in `WidowLoanUiTest` |
| **Widow Loans** | Write Off Loan | Super admin writes off loan with supporting document & MFA phrase confirmation | `super_admin` | **PASS** | Verified in `WidowLoanWriteOffTest` & `WidowLoanUiTest` |
| **Welfare** | Package & Beneficiary Allocation | Welfare package items allocated to eligible beneficiaries | `admin` | **PASS** | Verified in FilamentResourceSmokeTest |
| **Projects** | Project Creation & Milestones | Project budget allocated and milestones tracked | `admin` | **PASS** | Verified in FilamentResourceSmokeTest |
| **Imprest** | Fund Replenishment & Expense | Custodian records imprest expense against parent/sub-account | `finance-custodian` | **PASS** | Verified in FilamentResourceSmokeTest |
| **User Interface** | Browser Form Walkthrough | End-to-end interactive browser form submission | `admin` | **NOT TESTED** | Requires final UAT browser session |
| **User Interface** | PDF Print Preview Rendering | Visual verification of rendered PDF layout in browser | `admin` | **NOT TESTED** | Requires visual browser inspection |
| **User Interface** | Real-time Searchable Selects | Interactive AJAX dropdown search behavior | `coordinator` | **NOT TESTED** | Requires browser UAT session |
| **User Interface** | File Upload Drag & Drop | Browser file upload widget for death certificates/photos | `coordinator` | **NOT TESTED** | Requires browser UAT session |
| **User Interface** | Multi-factor Enrollment QR | Scanning TOTP QR code with authenticator app | `admin` | **NOT TESTED** | Requires mobile device test |
| **User Interface** | Mobile Screen Responsiveness | Responsive drawer and table layout on mobile viewports | `coordinator` | **NOT TESTED** | Requires mobile viewport UAT |
