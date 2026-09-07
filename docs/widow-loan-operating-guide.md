# GOF MIS — Widow Revolving Loan (WRL) Operating Guide

## 1. Overview & Program Governance

The **Widow Revolving Loan (WRL)** program provides interest-free micro-finance revolving loans to eligible widows registered with the Garko Orphans Foundation. The program empowers widows to establish or expand small-scale commercial enterprises to achieve financial self-sufficiency.

### Core Operating Principles
- **Interest-Free Capital**: Zero interest or administrative markup charged on principal.
- **Revolving Pool Structure**: Repayments flow directly into the *WRL Repayment Account* to fund subsequent loan disbursements for new widow cohorts.
- **Strict Invariant (Plan vs. Reality)**: Loan schedule (`WidowLoanSchedule`) tracks planned installments; repayments (`WidowLoanRepayment`) match the oldest unpaid installment.

---

## 2. Loan Application, Approval Flow & Schedule Generation

### 2.1 Eligibility Criteria
1. Active registered Widow in GOF MIS.
2. Verified business proposal or commercial trade.
3. No active defaulted WRL loan. Remarried widows with outstanding loans remain responsible for repayment but are ineligible for new disbursements.

### 2.2 Approval & Disbursement Sequence
1. **Application Submission**: Admin or Finance Custodian creates loan application specifying requested principal, installment frequency (weekly/monthly), and tenure.
2. **Approval Flow**: Application routes through approval engine (`ApprovalFlow`). Super Admin or designated Admin approves loan.
3. **Disbursement**: System debits *WRL Disbursement Account*, transfers funds to widow's bank/cash voucher, and generates immutable repayment schedule (`WidowLoanSchedule`).

---

## 3. Loan Repayments & Installment Matching

### Repayment Execution
1. Widow makes repayment via bank transfer or cash deposit.
2. Finance Custodian logs repayment in `/admin/widow-loan-repayments`.
3. System automatically resolves destination *WRL Repayment Account* (sub-account under Main Operating Account).
4. System credits repayment against the oldest outstanding installment in `WidowLoanSchedule`.
5. Official receipt (e.g. `RCP-00028`) is generated for the payer.

---

## 4. Delinquency & Days Past Due (DPD) Evaluation

### Automated Daily Delinquency Evaluation (`widow-loans:evaluate-delinquency`)
Scheduled to execute daily at 00:00:
```bash
php artisan widow-loans:evaluate-delinquency
```

### Delinquency Status Thresholds
| Status | DPD Threshold | Action / Impact |
| :--- | :--- | :--- |
| **Performing / Active** | DPD = 0 | Normal installment schedule. |
| **Overdue** | 1 <= DPD <= 30 | Gentle reminder issued to borrower. |
| **Delinquent** | 31 <= DPD <= 90 | Coordinator field visit & contact activity logged. |
| **Default** | DPD > 90 | Escalated for formal administrative review & write-off evaluation. |

---

## 5. Hardship Relief Windows & Promise to Pay

### 5.1 Hardship Relief Window (`WidowLoanReliefPeriod`)
When a borrower experiences documented medical, family, or economic hardship:
1. Coordinator or Admin submits Hardship Request.
2. Super Admin approves relief window (e.g. 30 to 90 days).
3. System pauses delinquency DPD calculations during the active relief period without altering loan principal.

### 5.2 Promise to Pay (PTP) Workflow
1. Field Coordinator logs contact activity and registers a formal Promise to Pay date.
2. System monitors PTP fulfillment on scheduled date.
3. Fulfilled promises reset contact reminders; broken promises escalate delinquency status.

---

## 6. Counter-Funding Ledger Treatment

When third-party donors or philanthropic partners provide relief funds to cover delinquent loan balances:
- **Counter Funding Ledger (`WidowLoanCounterFunding`)**: Donor funds credit the repayment ledger directly.
- **Accounting Rule**: Counter-funding reduces outstanding borrower balance without modifying original principal schedule, ensuring complete audit transparency.

---

## 7. Administrative Write-Off Policies & Procedures

Loan write-off is an accounting procedure for uncollectible debt after all recovery efforts fail:
1. **Recommendation**: Coordinator or Admin submits Write-Off Recommendation with field report.
2. **Super Admin Verification**: Super Admin reviews recommendation and uploaded supporting document.
3. **Sensitive Action Execution**:
   - Super Admin enters password and types exact confirmation phrase `WRITE OFF LOAN`.
   - System updates loan status to `Written Off`, removes balance from active asset ledger, and logs immutable audit trail.
   - *Note*: Write-off is internal accounting relief; it does not forgive legal debt obligations.
