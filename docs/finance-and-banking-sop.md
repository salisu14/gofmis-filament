# GOF MIS — Finance & Banking Standard Operating Procedure

## 1. Overview of Financial Architecture & General Ledger

The **GOF MIS Financial Module** enforces strict double-entry accounting principles to manage foundation treasury assets, operational expenses, widow revolving loan disbursements, education support payments, and out-of-pocket reimbursements.

### Architectural Invariants
- **Double-Entry General Ledger**: Every financial transaction generates balanced `JournalEntry` and `JournalLine` records (`Debit Sum = Credit Sum`).
- **Main Operating Treasury Account**: System permits **exactly ONE central Main Operating Account** (`usage = general`, `parent_bank_account_id = null`).
- **Dedicated Operational Sub-Accounts**: All operational bank accounts branch as child accounts under the Main Operating Account.

---

## 2. Bank Account Hierarchy & Fund Reservation

### Account Structure Matrix
```
[Main Treasury Operating Account] (Parent: NULL)
  ├── [WRL Loan Disbursement Account] (Sub-account)
  ├── [WRL Loan Repayment Account] (Sub-account)
  ├── [Child Education Support Account] (Sub-account)
  └── [Out-Of-Pocket Reimbursement Account] (Sub-account)
```

### Ledger Balance vs Reserved Balance
- **Ledger Balance**: Actual verified ledger funds stored in database.
- **Reserved Balance**: Funds locked for approved pending disbursements (e.g., approved loan or fee invoice awaiting bank transfer execution).
- **Available Balance Calculation**:
  $$\text{Available Balance} = \text{Ledger Balance} - \text{Reserved Balance}$$
- Disbursements attempting to exceed Available Balance are automatically blocked with `InsufficientBankBalanceException`.

---

## 3. Out-Of-Pocket (OOP) Expense Processing

Field staff and administrators incurring authorized operational expenses out-of-pocket submit reimbursement claims:
1. **Submission**: Staff submits OOP claim via `/admin/out-of-pocket-expenditures` with expense category, amount, description, and receipt image/PDF attachment.
2. **Verification & Approval**: Finance Custodian or Admin verifies receipt proof and approves claim.
3. **Reimbursement Execution**: System debits *Out-Of-Pocket Reimbursement Account*, credits recipient, records double-entry journal, and updates claim status to `Reimbursed`.

---

## 4. Education & Welfare Support Disbursements

### Education Support Payments
1. Verified education fee invoices generate payment vouchers.
2. Payment execution debits the *Child Education Support Account*.
3. System logs `transaction_type = education_payment` with exact school reference.

### Welfare Intervention Financing
1. Approved welfare package procurement charges the Main Treasury Account.
2. Stock receipts log inventory asset additions in accounting ledger.

---

## 5. Double-Entry Journal Lines & Accounting Verification

Every transaction records structured debit and credit journal lines:

| Transaction Type | Debit Account | Credit Account |
| :--- | :--- | :--- |
| **WRL Loan Disbursement** | WRL Disbursement Sub-Account | Borrower Loan Asset Ledger |
| **WRL Loan Repayment** | WRL Repayment Sub-Account | Borrower Loan Asset Ledger |
| **OOP Reimbursement** | Operational Expense Account | OOP Reimbursement Sub-Account |
| **Bank Transfer** | Destination Bank Sub-Account | Source Bank Sub-Account |

---

## 6. Financial Audit & Repair Commands

### 6.1 Read-Only Ledger Audit (`finance:reconcile`)
Runs daily at 01:00 via cron scheduler:
```bash
php artisan finance:reconcile --details
```
Audits every registered bank account by calculating the exact sum of all double-entry journal lines and comparing against the stored `current_balance`. Returns zero exit code if balanced; outputs detailed discrepancy breakdown if imbalance detected.

### 6.2 Manual Bank Balance Repair (`finance:repair-bank-balances`)
Used exclusively by Super Admins after auditing discrepancies:
```bash
# Step 1: Run dry-run to inspect proposed adjustments
php artisan finance:repair-bank-balances

# Step 2: Execute repair inside atomic database transaction
php artisan finance:repair-bank-balances --apply
```
Recalculates and updates bank account stored balances inside a single database transaction. *Must never be scheduled unattended*.
