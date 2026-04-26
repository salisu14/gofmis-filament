# Manual Widow Loan Approval System - Implementation Guide

## Overview

A complete, manual approval workflow system has been built for the widow loan management system. This includes Filament resources, actions, forms, tables, widgets, and supporting infrastructure.

## ✅ Components Created

### 1. Database Models & Migrations
- **ApprovalFlow** - Tracks the overall approval workflow
- **ApprovalStep** - Tracks individual approval steps
- **Migrations** - Two new tables created with foreign keys and indexes

### 2. Filament Resources
- **WidowLoanResource** - Full CRUD for widow loans with approval integration
- **ApprovalFlowResource** - View and manage approval workflows

### 3. Actions (Reusable Buttons)
- **SubmitForApprovalAction** - Submit a draft loan for approval
- **ApproveWidowLoanAction** - Approve at current step
- **RejectWidowLoanAction** - Reject at current step

### 4. Forms & Tables
- **WidowLoanForm** - Organized form with sections (Loan Info, Status & Amounts, Disbursement & Approval)
- **WidowLoansTable** - Table with status badges, filtering, and inline actions
- **ApprovalFlowsTable** - View all approval workflows with progress indicators

### 5. Pages
- **ViewWidowLoan** - Enhanced with approval actions
- **ListWidowLoans** - List all loans with approval/rejection actions
- Plus auto-generated Create, Edit, and List pages

### 6. Widgets
- **PendingApprovalsWidget** - Dashboard widget showing pending approvals

### 7. Views
- **approval-flow-info.blade.php** - Component showing approval workflow state

### 8. Seeders
- **WidowLoanWithApprovalsSeeder** - Sample data for testing

## 🚀 Setup Instructions

### Step 1: Run Migrations
```bash
php artisan migrate
```

### Step 2: (Optional) Seed Sample Data
```bash
php artisan db:seed --class=WidowLoanWithApprovalsSeeder
```

### Step 3: Clear Cache
```bash
php artisan filament:cache-components
php artisan view:clear
php artisan cache:clear
```

### Step 4: Check Navigation
Go to `/admin` and you should see "Widow Services" group with:
- Widow Loans
- Loan Repayments (if resource created)
- Approval Flows

## 📝 Usage Workflow

### Creating a Loan
1. Navigate to **Widow Services → Widow Loans**
2. Click **Create**
3. Fill in loan details:
   - Select widow
   - Enter principal amount
   - Set duration in months
   - Add purpose
   - Add loan agreement URL (optional)
4. Click **Create**

Status will be set to **DRAFT** by default.

### Submitting for Approval
1. View or edit the loan
2. Click **Submit for Approval** button
3. (Optional) Add submission notes
4. Confirm

This will:
- Create an ApprovalFlow with 3 steps (Loan Officer → Finance Manager → Director)
- Change status to **PENDING**
- Set current_step to 1

### Approving a Loan (Step 1 - Loan Officer)
1. View the pending loan
2. Click **Approve Loan** button
3. Review approval flow info
4. Add approval comments
5. Confirm

This will:
- Mark Step 1 as approved
- Move to Step 2
- display "Step 2 of 3"

### Approving a Loan (Step 2 - Finance Manager)
1. View the loan (should show Step 2 of 3)
2. Click **Approve Loan** button
3. Add comments
4. Confirm

This will:
- Mark Step 2 as approved
- Move to Step 3

### Final Approval (Step 3 - Director)
1. View the loan (should show Step 3 of 3)
2. Click **Approve Loan** button
3. Add comments
4. Confirm

This will:
- Mark all steps as approved
- Change ApprovalFlow status to **APPROVED**
- Loan status remains **PENDING** (ready for disbursement)

### Rejecting a Loan
At any step, you can reject:
1. Click **Reject Loan** button
2. Enter rejection reason (required)
3. Add optional comments
4. Confirm

This will:
- Mark step as rejected
- Change ApprovalFlow status to **REJECTED**
- Loan status remains **PENDING** (can be resubmitted)

## 📊 Approval Flow States

### ApprovalFlow Status
- **pending** - Awaiting approval at current step
- **approved** - All steps completed successfully
- **rejected** - Rejected at some step

### ApprovalStep Status
- **pending** - Currently awaiting approval
- **approved** - Already approved
- **rejected** - Was rejected
- **waiting** - Waiting for previous step to complete

### Loan Status Progression
```
DRAFT 
  ↓ (Submit for Approval)
PENDING (awaiting approval workflow)
  ↓ (All approval steps pass)
APPROVED (ready to disburse)
  ↓ (Disburse funds)
DISBURSED (funds transferred)
  ↓ (Record repayments)
COMPLETED (fully repaid)
```

## 🎯 Key Features

### 1. Multi-Step Workflow
- Configurable number of approval steps
- Each step can require a specific role
- Current step tracking

### 2. Complete Audit Trail
- Who approved/rejected
- When they approved/rejected
- Comments and reasons
- Timestamp for each action

### 3. Transparent Status
- Badge indicators for status
- Progress tracking (Step X of Y)
- Visual history in approval flow

### 4. Easy Integration with Permissions
The roles can be linked to Spatie permissions:
```php
// In ApprovalStep, check user role before allowing approval
if (!auth()->user()->hasRole($step->role_required)) {
    // Show error
}
```

### 5. Reusable for Other Models
The `Approvable` trait can be added to other models:
```php
class InterventionRequest extends Model {
    use Approvable;
    // ...
}
```

## 🔐 Permission Integration (Optional)

Add permissions to your application:

```php
// In a permission seeder
Permission::create(['name' => 'view_approval_flows']);
Permission::create(['name' => 'approve_widow_loans']);
Permission::create(['name' => 'reject_widow_loans']);

// Assign to roles
$director = Role::findByName('director');
$director->givePermissionTo(['approve_widow_loans', 'reject_widow_loans']);

$loanOfficer = Role::findByName('loan_officer');
$loanOfficer->givePermissionTo('view_approval_flows');
```

Then protect actions with:
```php
ApproveWidowLoanAction::make()
    ->visible(fn (WidowLoan $record) => 
        auth()->user()->hasPermissionTo('approve_widow_loans')
    )
```

## 📱 Filament UI Features

### Tables
- **Status Badges** - Color-coded status indicators
- **Filtering** - Filter by status, type, etc.
- **Inline Actions** - Approve/Reject from table row
- **Searchable** - Search by widow name, purpose, etc.
- **Sortable** - Sort by any column

### Forms
- **Organized Sections** - Logical grouping of fields
- **Readonly Fields** - Show-only computed values
- **Relationships** - Select related models
- **Validation** - Built-in validation

### Pages
- **View Page** - Header actions for Submit/Approve/Reject
- **Edit Page** - Modify loan details
- **Create Page** - Add new loan
- **List Page** - Overview of all loans

### Actions
- **Confirmation** - Confirm dangerous actions
- **Notifications** - Success/error feedback
- **Conditional Display** - Show only when applicable

## 🧪 Testing the Approval System

### Manual Testing
1. Create a test widow
2. Create a draft loan for that widow
3. Submit for approval
4. Approve Step 1
5. Approve Step 2
6. Approve Step 3
7. Verify loan status changed to APPROVED

### Using Sample Data
```bash
php artisan db:seed --class=WidowLoanWithApprovalsSeeder
```

This creates:
- 3 test widows
- For each widow:
  - 1 DRAFT loan
  - 1 PENDING loan with approval workflow
  - 1 APPROVED loan with completed workflow

## 🛠️ Customization

### Adding More Approval Steps
In `SubmitForApprovalAction::make()`, modify the approvers array:

```php
$approvers = [
    ['role' => 'loan_officer'],
    ['role' => 'loan_manager'],
    ['role' => 'director'],
    // Add more steps
    ['role' => 'ceo'],
];
```

### Custom Status Colors
In `WidowLoansTable::configure()`:

```php
BadgeColumn::make('status')
    ->colors([
        'custom-color' => 'custom_status',
        // ...
    ])
```

### Adding More Actions
Create new action classes and add to `ViewWidowLoan` or table:

```php
class DisburseWidowLoanAction extends Action {
    // ...
}

// In ViewWidowLoan
protected function getHeaderActions(): array {
    return [
        DisburseWidowLoanAction::make(),
        // ... other actions
    ];
}
```

## 📚 Database Schema

### approval_flows
```
id (UUID)
model_type (string) - e.g., "App\Models\WidowLoan"
model_id (UUID) - e.g., loan ID
status (enum) - pending|approved|rejected
current_step (integer) - current step number
total_steps (integer) - total number of steps
approver_id (UUID, nullable) - final approver
rejection_reason (text, nullable)
approved_at (timestamp, nullable)
rejected_at (timestamp, nullable)
created_at
updated_at
```

### approval_steps
```
id (UUID)
approval_flow_id (UUID) - FK to approval_flows
step_number (integer) - step sequence
role_required (string, nullable) - e.g., "director"
status (enum) - pending|approved|rejected|waiting
approver_id (UUID, nullable) - who approved/rejected
approved_at (timestamp, nullable)
rejected_at (timestamp, nullable)
rejection_reason (text, nullable)
comments (text, nullable)
created_at
updated_at
```

## 🎓 Next Steps

1. **Add Permissions** - Implement role-based access control
2. **Add Notifications** - Email when approval is requested
3. **Add Reporting** - Dashboard showing approval statistics
4. **Add Webhook** - Trigger external systems on approval
5. **Add Audit Logging** - Advanced audit trail for compliance

## 📞 Implementation Complete!

The approval system is now fully operational and ready to use. All actions are available in the Filament admin panel under "Widow Services" → "Widow Loans" and "Approval Flows".

Navigate to `/admin` to start managing widow loans with the approval workflow!

