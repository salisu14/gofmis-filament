# GOF MIS — Deterministic UAT Data Seeding — Final Implementation Report

Task: build a deterministic, idempotent UAT/demo dataset for manual testing, screenshots,
reporting validation, workflow testing, and Foundation acceptance testing. No seeding was
executed against any real database; all validation ran on the isolated in-memory test DB.

---

## 1. Existing seeder audit

| Seeder | Tables | Idempotent? | Destructive? | Random/Faker? | Type |
|---|---|---|---|---|---|
| StatesTableSeeder | `states` | NO (`State::insert`) | duplicates Kano | no | reference |
| CitiesTableSeeder | `cities` | NO (`City::insert`) | duplicates Garko | no | reference |
| TownsTableSeeder | `towns` | NO (`Town::insert`) | duplicates 2 towns | no | reference |
| ZonesTableSeeder | `zones` | NO (`Zone::insert` A1-A20, B1-B5) | adds 25 zones per run | no | reference |
| PermissionsTableSeeder | `permissions` | YES (firstOrCreate) | no | no | reference |
| RolesTableSeeder | `roles` | YES (firstOrCreate) | no | no | reference |
| UsersTableSeeder | `users` | YES (firstOrCreate sadmin) | no | no | reference |
| RoleUserTableSeeder | `model_has_roles` | YES (syncRoles) | no | no | reference |
| ImprestSeeder | `users`,`imprest_funds`,`imprest_transactions` | **NO** — new custodian/supervisor users + 18 transactions each run | no | **YES** (`factory`, `fake()->boolean`) | demo |
| BankAccountsTableSeeder | `bank_accounts` | NO (`create` WRL account) | duplicates account | no | reference |
| ImprestPermissionSeeder | `permissions`,`roles` | YES (firstOrCreate + sync) | no | no | reference |
| IllnessSeeder | `illnesses` | YES (firstOrCreate) | no | no | reference |
| MedicationsTableSeeder | `medications` | **NO** (`Medication::insert` 15 rows) | duplicates | no | reference |
| EducationVerifierRoleSeeder | `roles`,`permissions` | Partial (firstOrCreate but new uuid each run) | no | no | reference |
| IdCardTemplateSeeder | `id_card_templates` | **NO** (`create` 2 templates) | duplicates | no | reference |
| OrphanClassesTableSeeder | `orphan_classes` | **NO** (`OrphanClass::insert`, needs User) | duplicates | no | reference |
| WelfarePackageSeeder | `categories`,`items`,`deceased`,`welfare_packages`,`welfare_package_items`,`welfare_beneficiaries` | **NO** — fresh packages + random nominations each run | no | **YES** (`random`, `rand`, factory) | demo |
| InterventionTypeSeeder | `intervention_types` | YES (firstOrCreate) | no | no | reference |
| ApprovalPermissionsSeeder | `permissions`,`roles` | Partial (findOrCreate + givePermissionTo) | no | no | reference |
| WidowLoanWithApprovalsSeeder | `widows`,`widow_loans`,`approval_flows`,`approval_steps` | **NO** — 9 loans + 6 approval flows each run | no | **YES** (`factory`) | demo |
| RolesAndPermissionsSeeder | `roles`,`permissions` | YES (firstOrCreate + sync) | no | no | reference |

### Existing seeder behaviour notes

- **WelfarePackageSeeder** (called by DatabaseSeeder): creates 3 packages (DRAFT/OPEN/CLOSED) with
  random items (`$items->random(3)`, `rand(1,5)`) and 10 random beneficiaries per open/closed
  package (`$deceased->random(10)`, `$coordinators->random()`), plus Category 1-5 / Item 1-10 if
  empty. Non-deterministic and non-idempotent.
- **WidowLoanWithApprovalsSeeder**: creates 3 loans (DRAFT/PENDING/APPROVED) + approval flows per
  existing widow via factories. Non-deterministic and non-idempotent.
- **ImprestSeeder**: creates custodian + supervisor users, a fund, 15 ACTIVE + 3 voided
  transactions with `fake()->boolean(70)` randomness and date-prefixed voucher numbers.
  Non-deterministic and non-idempotent.
- **ZonesTableSeeder**: raw `Zone::insert` of 25 zones; each run adds 25 more rows.
- **UsersTableSeeder**: `firstOrCreate(['email' => 'sadmin@admin.com'])` with password
  `password123@`; safe and idempotent.

## 2. Destructive/unsafe existing seeders found

- **Non-idempotent (duplicate on re-run):** States, Cities, Towns, Zones, Medications,
  IdCardTemplate, OrphanClasses, BankAccounts, Imprest, WelfarePackage,
  WidowLoanWithApprovals.
- **Non-deterministic (random/Faker):** ImprestSeeder, WelfarePackageSeeder,
  WidowLoanWithApprovalsSeeder.
- **None delete or truncate data** — no data-destruction risk was found in any existing seeder;
  the risks are duplication and non-determinism on repeated `db:seed`.
- These were reported but NOT fixed (out of scope for this task unless they blocked it).

## 3. Final seeder architecture

- **`DatabaseSeeder`** — left untouched; remains baseline/reference setup only. The UAT seeder
  is intentionally NOT wired into it, so it never runs automatically.
- **`UatDemoSeeder`** (new, explicit UAT entry point) — production guard, then orchestrates five
  focused deterministic child seeders:
  1. `UatHouseholdSeeder` — actors (super_admin, admin, 3 coordinators on distinct zones) + 20
     households with a coherent Widow/Orphan graph.
  2. `UatInventorySeeder` — 5 categories, 12 items, opening StockMovement ledger entries.
  3. `UatWelfareSeeder` — 8 deterministic welfare package scenarios.
  4. `UatEducationHealthcareSeeder` — orphan classes, institutions, education, prescriptions,
     interventions for a subset of orphans.
  5. `UatWidowLoanSeeder` — 5 WRL scenarios with deterministic anchored dates and reconciling
     balances.

Execution entry point (after approval):

```bash
php artisan db:seed --class=Database\\Seeders\\UatDemoSeeder
```

## 4. Files added/changed

**Added (untracked, new files):**

- `database/seeders/UatDemoSeeder.php` (1,429 bytes)
- `database/seeders/UatHouseholdSeeder.php` (16,109 bytes)
- `database/seeders/UatInventorySeeder.php` (4,510 bytes)
- `database/seeders/UatWelfareSeeder.php` (12,701 bytes)
- `database/seeders/UatEducationHealthcareSeeder.php` (8,177 bytes)
- `database/seeders/UatWidowLoanSeeder.php` (13,151 bytes)
- `tests/Feature/UatDemoSeederTest.php` (9,474 bytes)

**Modified:** none by this task. The 18 modified files already in the working tree are entirely
from the prior Welfare Consolidation Correction pass (canonical Welfare architecture) and were
left unchanged. No existing seeder was modified.

## 5. Deterministic users/roles/zones created

| Email | Password | Role | Zone |
|---|---|---|---|
| `sadmin@admin.com` | `password123@` | super_admin | — (global) |
| `admin@admin.com` | `password123@` | admin | — (global) |
| `coordinator.a1@admin.com` | `password123@` | coordinator | A1 |
| `coordinator.a2@admin.com` | `password123@` | coordinator | A2 |
| `coordinator.b1@admin.com` | `password123@` | coordinator | B1 |

- `sadmin@admin.com` preserves the existing UsersTableSeeder convention.
- Coordinators are assigned to distinct zones so zone-isolation testing is meaningful.
- No invented roles; only existing `super_admin`, `admin`, `coordinator` roles are used.

## 6. Deterministic household scenarios (20 households, 16 widows, 21 orphans)

| # | Scenario | Household reg_no | Zone |
|---|---|---|---|
| 1 | Eligible widow + eligible orphan(s) | UAT-DEC-001 | A1 |
| 2 | Eligible widow only | UAT-DEC-002 | A1 |
| 3 | Eligible orphan(s) only | UAT-DEC-003 | A1 |
| 4 | Multiple widows | UAT-DEC-004 | A1 |
| 5 | Multiple orphans of varied ages/sexes | UAT-DEC-005 | A2 |
| 6 | Remarried widow (ineligible) | UAT-DEC-006 | A2 |
| 7 | Aged-out orphan (ARCHIVED) | UAT-DEC-007 | A2 |
| 8 | No eligible welfare beneficiary | UAT-DEC-008 | B1 |
| 9 | Zone-isolation spread (A1/A2/B1) | UAT-DEC-009..010 + extras 011-020 | mixed |
| extra | Additional mixed households | UAT-DEC-011..020 | A1/A2/B1 |

- All dates respect Deceased model validation: DOB not future, DOD not future, registration not
  future, DOD >= DOB, registration >= DOD.
- Guardian name/phone populated on every household.
- `number_of_orphans_left` / `number_of_widows_left` are set from the actual associated record
  counts and verified by test.
- Actual enum values used: `VulnerabilityStatus::A/B/C`, `Gender::MALE/FEMALE`,
  `OrphanStatus::ACTIVE/ARCHIVED`, booleans for widow eligibility/marital status.

## 7. Category/item/stock scenarios

**Categories (5):** Food Items, School Supplies, Uniform & Clothing, Medical Supplies,
Household Essentials.

**Items (12)** with unit_of_measure, reorder_level, is_active=true, and opening stock:

| Item | Unit | Reorder | Opening qty |
|---|---|---|---|
| Rice (50kg Bag) | Bags | 20 | 120 |
| Maize (50kg Bag) | Bags | 15 | 60 |
| Cooking Oil (5L) | Gallons | 25 | 90 |
| Beans (25kg Bag) | Bags | 15 | 40 |
| School Bag | Pieces | 30 | 150 |
| Exercise Books (Pack of 5) | Packs | 50 | 300 |
| School Uniform | Sets | 20 | 5 (low) |
| Basic Medical Kit | Kits | 10 | 25 |
| Mosquito Net | Pieces | 20 | 45 |
| Bar Soap (Carton) | Cartons | 10 | 18 |
| Detergent (2kg) | Pieces | 15 | 35 |
| Kerosene Stove | Pieces | 5 | 8 |

- Opening stock is posted through the canonical `StockMovement` ledger
  (`StockMovementType::OPENING_BALANCE`, reference `uat_opening` + item id for idempotency).
- No derived on-hand balances are written; no negative opening stock.
- Coverage: healthy (Rice 120), low (School Uniform 5), out-of-stock potential (Kerosene Stove 8
  vs a package requiring 5/family), insufficient-for-package (package H), sufficient for multiple
  successful collections (Rice/Beans for package F).

## 8. Welfare UAT scenarios (8 packages)

| Pkg | Name | Status | Nominations | Notes |
|---|---|---|---|---|
| A | UAT Welfare Draft Package | DRAFT | 0 | items: Rice×1, Cooking Oil×2 |
| B | UAT Welfare Open No Nominations | OPEN | 0 | opened via lifecycle service |
| C | UAT Welfare Open Pending | OPEN | 2 pending | canonical nomination service (DEC-001, DEC-003) |
| D | UAT Welfare Open Approved | OPEN | 3 approved/not-collected | canonical nominate + approve (DEC-002,005,009) |
| E | UAT Welfare Open Rejected History | OPEN | 1 rejected + 1 pending | rejected via canonical reject; pending via canonical nominate |
| F | UAT Welfare Closed Collected | CLOSED | 4 collected | historical-state direct insertion (documented); matching WELFARE_ISSUE ledger rows |
| G | UAT Welfare Reopened Prior Nominations | OPEN (reopened) | 2 | lifecycle open → nominate → close → reopen; composition immutable |
| H | UAT Welfare Insufficient Stock | OPEN | 0 | Kerosene Stove ×5/family vs 8 on hand (capacity-limited) |

- Canonical services used wherever live state allows: `WelfarePackageLifecycleService`
  (open/close/reopen), `WelfareNominationService` (nominate/nominateSingle),
  `BeneficiaryService` (approve/reject).
- Package F uses direct insertion with documented rationale: its window has already ended so the
  canonical services (which enforce the live date window) correctly refuse; invariants
  (APPROVED → COLLECTED ordering, collected_by/collected_at, WELFARE_ISSUE ledger rows) are
  preserved manually and verified by test.
- No impossible state combinations are created.

## 9. Education/healthcare/intervention test coverage

- 4 institutions (2 western, 1 islamiyya, 1 vocational) via `InstitutionType` enum.
- 12 orphan classes (Primary 1-6, JSS I-III, SS I-III) created idempotently.
- 7 education enrollments (`OrphanEducation`) for UAT-ORP-001..005, 010, 013 with class,
  school_fee, support_amount, is_current.
- 6 prescriptions (`Prescription`) for UAT-ORP-001,002,004,005,010,013 with illness, lab/drug
  costs, TREATED or PENDING status.
- 7 intervention requests (`InterventionRequest`) with FULFILLED/APPROVED/PENDING/UNDER_REVIEW
  statuses linked to real `InterventionType` rows.
- Sufficient cross-module data for a future Comprehensive Orphan Profile Report; the report
  itself was NOT implemented (out of scope).

## 10. WRL scenarios (5 loans)

| Widow | Purpose | Principal | Status | Repayments |
|---|---|---|---|---|
| UAT-WID-001 | Small trading business support | ₦60,000 | DISBURSED | 3 of 48 weekly installments paid |
| UAT-WID-002 | Agricultural inputs support | ₦40,000 | DISBURSED | 12 of 24 paid (half repaid) |
| UAT-WID-003 | Petty trading startup | ₦25,000 | COMPLETED | all 12 paid (fully repaid) |
| UAT-WID-005 | Tailoring equipment purchase | ₦80,000 | PENDING | — |
| UAT-WID-008 | Provision shop restock | ₦30,000 | DRAFT | — |

- Loans/schedules/repayments are created directly (documented historical-state setup); the
  canonical `WidowLoan::refreshBalance()` is called so `total_paid`, `outstanding_balance`, and
  schedule paid-flags reconcile exactly with repayment records — the basis for future 58mm
  weekly repayment receipt printing.
- Repayment totals reconcile mathematically (test-verified: `total_paid` == sum of repayments,
  `outstanding_balance` == max(0, total_payable − total_paid), repayments never exceed
  principal).
- Two deterministic bank accounts: WRL Disbursement (1000000001) and WRL Repayment (1000000002).
- Dates are anchored to a fixed constant `ANCHOR_DATE = 2026-07-01` so the same seed command
  always produces the same dates and scenarios.

## 11. Idempotency strategy

- Users/roles/zones/items/categories/bank accounts: `firstOrCreate` on stable natural keys
  (email, name, account_number).
- Households/widows/orphans: `firstOrCreate` on unique `reg_no`.
- Stock openings: existence check keyed `(item_id, OPENING_BALANCE, uat_opening, item_id)`.
- Welfare packages: `firstOrCreate` on name; beneficiaries: unique `(package, deceased)`.
- Repayments: keyed on deterministic `UAT-REP-<loan-id-8>-<installment>` notes reference;
  schedules regenerated deterministically per loan.
- **No truncation anywhere.** Idempotency is test-verified (second run produces zero new
  households/widows/orphans/items/stock-openings/packages/nominations/loans/repayments).

## 12. Production guard

- `UatDemoSeeder::assertNotProduction()` throws `RuntimeException` when
  `app()->environment('production')` — the seeder aborts clearly in production.
- The seeder is NOT registered in `DatabaseSeeder`, so it cannot run as part of a default
  `php artisan db:seed` in any environment.
- Test-verified: forcing the environment to `production` makes `run()` throw.

## 13. Tests added

`tests/Feature/UatDemoSeederTest.php` — 8 tests:

1. `UatDemoSeeder refuses to run in production environment`
2. `UatDemoSeeder creates deterministic actors and zone assignments`
3. `UatDemoSeeder is idempotent - second run does not duplicate entities`
4. `UatDemoSeeder creates coherent household relationships`
5. `UatDemoSeeder represents eligible and ineligible scenarios`
6. `UatDemoSeeder stock movement totals reconcile with item stock`
7. `UatDemoSeeder welfare nomination and collection state is internally consistent`
8. `UatDemoSeeder WRL repayment balances reconcile`

## 14. Tests actually executed and exact results

All tests ran against the isolated in-memory SQLite test database (`DB_DATABASE=:memory:`
per phpunit.xml); no real database was touched.

| Command | Result |
|---|---|
| `php artisan test tests/Feature/UatDemoSeederTest.php` | **8 passed (155 assertions)** |
| `php artisan test tests/Feature/UatDemoSeederTest.php tests/Feature/WelfareConsolidationRegressionTest.php tests/Feature/WelfarePackageLifecycleTest.php` | **66 passed (272 assertions)** |
| `php artisan test tests/Feature/UatDemoSeederTest.php tests/Feature/WelfareConsolidationRegressionTest.php tests/Feature/WelfareMultiNominationAndEligibilityTest.php tests/Feature/WelfareCollectionSemanticsTest.php tests/Feature/StockAvailabilityAndCapacityTest.php tests/Feature/AdminWelfareFulfilmentWorkflowTest.php` | **66 passed (315 assertions)** |
| Final combined run (8 UAT + 24 regression + 34 lifecycle + 13 multi-nom + 6 collection + 6 coordinator + 6 admin + 9 stock) | **106 passed (389 assertions)** |

PHP lint (`php -l`) on all 7 new files: no syntax errors.

During development, intermediate failures were observed and fixed:
- OrphanClassesTableSeeder NOT NULL `user_id` when no user existed (test setup ordering) — UAT
  seeder now creates orphan classes itself after seeding actors.
- Unique constraint `(deceased_id, child_sequence)` on widows and orphans — child_sequence is
  now assigned sequentially per household.
- Canonical nomination service correctly rejected nominations into an ended package (package F)
  — package F switched to documented historical direct insertion.
- WRL repayment duplication on second run (dates differed run-to-run) — repayments now keyed by
  deterministic per-installment reference and anchored to a fixed date.
- Float-vs-int strict comparison in the WRL balance test — cast to float.

## 15. Command(s) to run AFTER approval to populate the current development database

```bash
# 1. Ensure baseline/reference seeders have run once (if the dev DB is fully empty):
php artisan db:seed

# 2. Populate the deterministic UAT dataset:
php artisan db:seed --class=Database\\Seeders\\UatDemoSeeder
```

## 16. Expected post-seed record counts

- Users: 5 (super_admin, admin, 3 coordinators)
- Deceased households: 20
- Widows: 16
- Orphans: 21
- Zones assigned to coordinators: 3 (A1, A2, B1)
- Categories: 5
- Items: 12
- StockMovement opening balances: 12
- Welfare packages: 8
- Welfare beneficiaries: ~13 (2 pending + 3 approved + 1 rejected + 1 pending + 4 collected + 2
  reopened-package nominations)
- Institutions: 4
- Orphan classes: 12
- OrphanEducation: 7
- Prescriptions: 6
- InterventionRequest: 7
- Bank accounts: 2
- WidowLoan: 5
- WidowLoanRepayment: 27
- WidowLoanSchedule: 84+ (48 + 24 + 12 for the three disbursed loans)

## 17. Development login accounts for manual testing

| Email | Password | Role | Zone |
|---|---|---|---|
| `sadmin@admin.com` | `password123@` | super_admin | — |
| `admin@admin.com` | `password123@` | admin | — |
| `coordinator.a1@admin.com` | `password123@` | coordinator | A1 |
| `coordinator.a2@admin.com` | `password123@` | coordinator | A2 |
| `coordinator.b1@admin.com` | `password123@` | coordinator | B1 |

These are the project's existing development credential conventions (`password123@`); no new
secrets are introduced.

## 18. git diff --stat

The 18 modified files shown by `git diff --stat` are entirely from the prior Welfare
Consolidation Correction pass (canonical Welfare architecture) and were NOT changed by this
task:

```
 app/Filament/Coordinator/Resources/WelfareRequestResource.php             |   3 +-
 app/Filament/Coordinator/Resources/WelfareRequestResource/Pages/CreateWelfareRequest.php |  31 ++++--
 app/Filament/Coordinator/Widgets/PendingItemsWidget.php                   |   5 +-
 app/Filament/Resources/WelfarePackages/RelationManagers/BeneficiariesRelationManager.php |  19 ++--
 app/Filament/Resources/WelfarePackages/RelationManagers/ItemsRelationManager.php |   8 +-
 app/Filament/Resources/WelfarePackages/Tables/WelfarePackagesTable.php    |   1 +
 app/Filament/Widgets/WelfareInterventionWidget.php                        |   2 +-
 app/Models/WelfareBeneficiary.php                                         | 105 ++++++++++++++++++
 app/Policies/WelfarePackagePolicy.php                                     |   6 +-
 app/Services/BeneficiaryService.php                                       | 117 ++++++++++++++-------
 app/Services/Welfare/WelfareNominationService.php                         | 112 +++++++++++++++++---
 app/Services/Welfare/WelfarePackageLifecycleService.php                   |  17 ++-
 app/Services/WelfarePackageService.php                                    |  26 +----
 database/migrations/2026_09_01_000001_drop_legacy_welfare_tables.php      |  31 ++++--
 tests/Feature/AdminWelfareFulfilmentWorkflowTest.php                      |  11 ++
 tests/Feature/CoordinatorWelfareRequestTest.php                           |   8 +-
 tests/Feature/WelfareCollectionSemanticsTest.php                          |  28 ++++-
 tests/Feature/WelfarePackageLifecycleTest.php                             |  65 ++++++++++--
 18 files changed, 456 insertions(+), 139 deletions(-)
```

This task's additions are all new untracked files (seeders + test), so they do not appear in
`git diff --stat` until added.

## 19. git status --short (including untracked files)

```
 M app/Filament/Coordinator/Resources/WelfareRequestResource.php
 M app/Filament/Coordinator/Resources/WelfareRequestResource/Pages/CreateWelfareRequest.php
 M app/Filament/Coordinator/Widgets/PendingItemsWidget.php
 M app/Filament/Resources/WelfarePackages/RelationManagers/BeneficiariesRelationManager.php
 M app/Filament/Resources/WelfarePackages/RelationManagers/ItemsRelationManager.php
 M app/Filament/Resources/WelfarePackages/Tables/WelfarePackagesTable.php
 M app/Filament/Widgets/WelfareInterventionWidget.php
 M app/Models/WelfareBeneficiary.php
 M app/Policies/WelfarePackagePolicy.php
 M app/Services/BeneficiaryService.php
 M app/Services/Welfare/WelfareNominationService.php
 M app/Services/Welfare/WelfarePackageLifecycleService.php
 M app/Services/WelfarePackageService.php
 M database/migrations/2026_09_01_000001_drop_legacy_welfare_tables.php
 M tests/Feature/AdminWelfareFulfilmentWorkflowTest.php
 M tests/Feature/CoordinatorWelfareRequestTest.php
 M tests/Feature/WelfareCollectionSemanticsTest.php
 M tests/Feature/WelfarePackageLifecycleTest.php
?? .commandcode/
?? command-code-welfare-acceptance-audit.md
?? command-code-welfare-correction-implementation-report.md
?? command-code-uat-demo-seeding-report.md
?? database/seeders/UatDemoSeeder.php
?? database/seeders/UatEducationHealthcareSeeder.php
?? database/seeders/UatHouseholdSeeder.php
?? database/seeders/UatInventorySeeder.php
?? database/seeders/UatWelfareSeeder.php
?? database/seeders/UatWidowLoanSeeder.php
?? tests/Feature/UatDemoSeederTest.php
?? tests/Feature/WelfareConsolidationRegressionTest.php
```

## 20. Safety confirmations

- **`php artisan db:seed` was NOT run against the development database.**
- **`UatDemoSeeder` was NOT run against the development database.** It was executed only inside
  the isolated in-memory test database via Pest/RefreshDatabase.
- **No destructive database command was run** — no `migrate:fresh`, `migrate:refresh`,
  `db:wipe`, no drops/truncates, and the pending legacy Welfare drop migration
  (`2026_09_01_000001_drop_legacy_welfare_tables.php`) was NOT executed.
- **No real development database data was modified.** All seeding and test activity used the
  in-memory SQLite test database only.
- No commit was created; nothing was pushed; no branch created/switched; Git history was not
  rewritten.

## 21. READY / NOT READY recommendation

**READY** for owner approval to execute the UAT seeder.

The dataset is deterministic and idempotent, guarded against production, covered by 8 dedicated
tests (plus a 106-test combined Welfare/Stock/UAT batch that is fully green), and uses the
canonical Welfare/lifecycle/nomination/collection services and the canonical StockMovement
ledger wherever live-state setup permits. Direct historical inserts (package F collections; WRL
loan state) are explicitly documented in the seeder docblocks with domain invariants preserved
and verified by tests.

One operational note for the owner: if the development database is fully empty, run the baseline
`php artisan db:seed` first (reference seeders) and then `php artisan db:seed
--class=Database\\Seeders\\UatDemoSeeder`.
