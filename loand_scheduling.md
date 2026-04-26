Widow Loan Management Workflow

This guide explains how the WidowLoanSchedule and WidowLoanRepayment systems interact to provide accurate financial tracking.

1. The Strategy: "Plan vs. Reality"

The system separates the expected repayment path from the actual cash flow:

WidowLoanSchedule (The Plan): Generated once the loan is approved. It creates a ledger of individual installments (e.g., 80 weeks of ₦500).

WidowLoanRepayment (The Reality): Records every time the widow brings in cash.

2. Math & Generation Logic

Using the example of a ₦40,000 loan with ₦500 weekly payments:

Logic: $Total Payable \div Duration = Installment$.

Workflow:

Admin creates the loan application.

Once APPROVED, the admin clicks "Generate Ledger" on the table.

The system runs a loop 80 times (based on the duration_months units and weekly frequency).

80 rows are added to widow_loan_schedules, each with a unique installment_number and a due_date incremented by 7 days.

3. Automated Repayment Matching

When a user records a new Repayment:

The user enters the amount (e.g., ₦500).

The RepaymentsRelationManager runs a query on the schedules table:
WHERE is_paid = false ORDER BY installment_number ASC LIMIT 1

The system finds the oldest unpaid installment (e.g., Week #1) and marks it as is_paid = true.

This creates a "perfect match" between the cash received and the expected schedule.

4. Dynamic Reporting (The Infolist)

The total_paid and outstanding_balance in the Infolist are not static database values. They are calculated in real-time:

total_paid = SUM(repayments.amount)

outstanding_balance = total_payable - total_paid

This approach ensures that even if a database update fails, the financial summary shown to the staff is always based on the actual repayment records.

5. Remarriage Policy

If a widow's status changes to is_married = true:

Her historical data remains in the system for audit purposes.

She remains responsible for any outstanding_balance.

The WidowLoanForm will display a warning and prevent the selection of this widow for new loan applications.