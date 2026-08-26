# GOF MIS — Post-Acceptance Deceased Full-Name Display Correction — Final Report

Task: correct the `/admin/deceaseds` "Full name" column rendering blank during owner manual
browser acceptance, using the smallest production fix, without rerunning seeders, without
modifying the deterministic UAT dataset, and without executing the pending checkpoint.

---

## 1. Exact root cause

The `/admin/deceaseds` "Full name" column is `TextColumn::make('full_name')`, which renders the
raw `full_name` **database column** directly.

- The **UAT** deceased records (UAT-DEC-001…020) have `full_name` correctly populated
  ("Adamu Bello", etc.) — verified both raw and via Eloquent.
- However, the **20 baseline non-UAT deceased records** (`DEC-00001`…`DEC-00020`, created by
  `WelfarePackageSeeder` during the baseline `DatabaseSeeder` run) have a **blank `full_name`
  column** while their `first_name`/`last_name` are populated (e.g. `DeceasedFirst 1 /
  DeceasedLast 1`). Because the Filament column reads the raw `full_name` column and not the
  model's `display_name` accessor, these rows render a blank "Full name" cell.

So this was a **presentation/query-path defect**, not a seeding defect and not a UAT-data
defect:

- UAT identity data was correct (the identity-correction task's "zero blank UAT names" check
  was accurate — it counted only `UAT-DEC-%`).
- The blank cells observed in the browser belong to the baseline `DEC-*` fixtures whose
  `full_name` column was never persisted, and the Deceased table displayed the raw column
  instead of the model's canonical name representation.

## 2. Raw DB full_name values before correction

- UAT-DEC-001: `getRawOriginal('full_name')` = `Adamu Bello` (populated)
- UAT-DEC-004: `getRawOriginal('full_name')` = `Dauda Yusuf` (populated)
- UAT-DEC-005: `getRawOriginal('full_name')` = `Emmanuel John` (populated)
- DEC-00001 (baseline): `getRawOriginal('full_name')` = `` (blank) — first_name
  `DeceasedFirst 1`, last_name `DeceasedLast 1`

## 3. Eloquent full_name values before correction

- UAT-DEC-001: `->full_name` = `Adamu Bello`, `->display_name` = `Adamu Bello`
- UAT-DEC-004: `->full_name` = `Dauda Yusuf`, `->display_name` = `Dauda Yusuf`
- UAT-DEC-005: `->full_name` = `Emmanuel John`, `->display_name` = `Emmanuel John`
- DEC-00001: `->full_name` = `` (blank), `->display_name` = `DeceasedFirst 1 DeceasedLast 1`

Conclusion: the model's canonical `display_name` accessor already falls back to joining
first/middle/last when `full_name` is blank; the defect was that the Filament column bypassed
the accessor and rendered the raw column.

## 4. Exact Filament display defect

`app/Filament/Resources/Deceased/Tables/DeceasedTable.php` line 46:

```php
TextColumn::make('full_name')
    ->searchable(['first_name', 'last_name', 'middle_name'])
    ->sortable()
    ->description(fn ($record) => "Reg: {$record->reg_no}"),
```

`TextColumn::make('full_name')` without a `->state()` callback resolves the **raw attribute**
`$record->full_name`, which is blank for the baseline `DEC-*` records. The working Widow/Orphan
tables use the same pattern but their records all have `full_name` populated, so they render
correctly.

## 5. Files changed

- **`app/Filament/Resources/Deceased/Tables/DeceasedTable.php`** (modified): the Full Name
  column now renders the model's canonical `display_name` representation via `->state(...)`,
  with explicit sort ordering on the `full_name` column and the existing search on the name
  parts preserved.
- **`tests/Feature/DeceasedFullNameDisplayTest.php`** (new): 2 focused regression tests.
- No model, seeder, migration, or other resource files were changed. The deterministic UAT
  dataset was not modified.

## 6. Exact code correction

```php
TextColumn::make('full_name')
    ->label('Full Name')
    ->state(fn (Deceased $record): string => (string) $record->display_name)
    ->searchable(['first_name', 'last_name', 'middle_name'])
    ->sortable(query: fn ($query, string $direction) => $query->orderBy('full_name', $direction))
    ->description(fn ($record) => "Reg: {$record->reg_no}"),
```

- `->state(...)` uses the model's existing `getDisplayNameAttribute()` accessor (the canonical
  name representation) — no name-construction logic duplicated in the resource.
- `->sortable(query: ...)` preserves DB-level sort on the `full_name` column.
- `->searchable(['first_name','last_name','middle_name'])` preserves existing search behavior.
- Deceased forms, relationships, history pages, and the Widow/Orphan resources are untouched.

## 7. Regression test added

`tests/Feature/DeceasedFullNameDisplayTest.php`:

1. `deceased list renders the full name column value` — creates a Deceased with
   `full_name = 'Adamu Bello'`, renders the admin Deceased list via Livewire, and asserts the
   Full Name column resolves to `Adamu Bello`.
2. `deceased list full name column renders name even when full_name column is blank` — the
   exact regression: creates a Deceased with `full_name = null` but populated first/last
   names, and asserts the Full Name column resolves to `Adamu Bello` via the accessor
   fallback.

Existing UAT identity regression assertions in `tests/Feature/UatDemoSeederTest.php` were
preserved (not weakened); they still pass.

## 8. Narrow test command/results

Command: `php artisan test tests/Feature/DeceasedFullNameDisplayTest.php`

- **2 passed, 0 failed (3 assertions).**

## 9. Acceptance regression command/results

Command:

```
php artisan test tests/Feature/UatDemoSeederTest.php \
  tests/Feature/WelfareConsolidationRegressionTest.php \
  tests/Feature/WelfarePackageLifecycleTest.php
```

- **71 passed, 0 failed (347 assertions).**

## 10. Development DB before/after checksum

- Pre-suite canonical snapshot (from the acceptance task): SHA-256
  `ff1014e66d88f6e2dd9858d8466365fa2d332d0dbca7628c04ea478dc72f8388`, size 1,384,448 bytes.
- Automated test runs (narrow Deceased test + 71-test acceptance batch) ran against the
  isolated in-memory test DB (`:memory:` enforced by phpunit.xml `<env>`+`<server>`
  `force="true"`; fail-fast guard in `tests/TestCase.php` active and verified).
- After the batch, the dev DB file size was unchanged (1,388,544 in one window — the known
  SQLite file-growth-on-open artifact) and content was verified identical for every key table
  (users=11, deceased=40, widows=16, orphans=21, welfare_packages=11, widow_loans=5,
  prescriptions=6, UAT_DEC=20, UAT_pkgs=8; zero blank UAT names; `PRAGMA integrity_check =
  ok`).
- The file was then restored from the verified pre-acceptance backup
  (`database/database.sqlite.pre-full-suite-acceptance-20260826-070811.bak`) to the exact
  canonical byte state (`ff1014e6...`, 1,384,448 bytes) for a clean handoff.

## 11. Development DB unchanged YES/NO

**YES — data unchanged.** All automated tests ran against `:memory:`; the dev DB content was
verified identical before/after every test run. The only file-level deltas observed were the
known SQLite open/checkpoint artifact (size/checksum drift with identical content), and the
file was restored to the canonical byte state from the verified backup. No seeding, no
restore-from-damage, no reseeding was required.

## 12. git diff --stat

```
 app/Filament/Coordinator/Resources/WelfareRequestResource.php             |   3 +-
 app/Filament/Coordinator/Resources/WelfareRequestResource/Pages/CreateWelfareRequest.php |  31 ++++--
 app/Filament/Coordinator/Widgets/PendingItemsWidget.php                   |   5 +-
 app/Filament/Resources/Deceased/Tables/DeceasedTable.php                  |   3 +-
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
 phpunit.xml                                                               |  22 +++-
 tests/Feature/AdminWelfareFulfilmentWorkflowTest.php                      |  11 ++
 tests/Feature/CoordinatorWelfareRequestTest.php                           |   8 +-
 tests/Feature/WelfareCollectionSemanticsTest.php                          |  28 ++++-
 tests/Feature/WelfarePackageLifecycleTest.php                             |  65 ++++++++++--
 tests/TestCase.php                                                        |  34 +++++-
 21 files changed, 511 insertions(+), 144 deletions(-)
```

(This diff includes this task's change to `DeceasedTable.php` on top of the accumulated cycle
work. The new test file `tests/Feature/DeceasedFullNameDisplayTest.php` is untracked.)

## 13. git status --short

```
 M app/Filament/Coordinator/Resources/WelfareRequestResource.php
 M app/Filament/Coordinator/Resources/WelfareRequestResource/Pages/CreateWelfareRequest.php
 M app/Filament/Coordinator/Widgets/PendingItemsWidget.php
 M app/Filament/Resources/Deceased/Tables/DeceasedTable.php
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
 M phpunit.xml
 M tests/Feature/AdminWelfareFulfilmentWorkflowTest.php
 M tests/Feature/CoordinatorWelfareRequestTest.php
 M tests/Feature/WelfareCollectionSemanticsTest.php
 M tests/Feature/WelfarePackageLifecycleTest.php
 M tests/TestCase.php
?? .commandcode/
?? command-code-development-uat-population-report.md
?? command-code-full-suite-acceptance-checkpoint-report.md
?? command-code-test-database-isolation-fix-report.md
?? command-code-uat-demo-seeding-report.md
?? command-code-uat-household-identity-correction-report.md
?? command-code-welfare-acceptance-audit.md
?? command-code-welfare-correction-implementation-report.md
?? database/seeders/UatDemoSeeder.php
?? database/seeders/UatEducationHealthcareSeeder.php
?? database/seeders/UatHouseholdSeeder.php
?? database/seeders/UatInventorySeeder.php
?? database/seeders/UatWelfareSeeder.php
?? database/seeders/UatWidowLoanSeeder.php
?? tests/Feature/DeceasedFullNameDisplayTest.php
?? tests/Feature/UatDemoSeederTest.php
?? tests/Feature/WelfareConsolidationRegressionTest.php
```

## 14. MANUAL BROWSER VERIFICATION REQUIRED — YES

The owner must refresh **http://127.0.0.1:8000/admin/deceaseds** and confirm the "Full name"
column now renders for all rows (including the baseline `DEC-*` records whose `full_name`
column is blank — they now render via the `display_name` accessor fallback). This task did not
perform browser testing; automated (Livewire) verification confirms the column state resolves
correctly.

## 15. READY / NOT READY FOR OWNER CHECKPOINT APPROVAL

**READY** (pending owner browser verification of `/admin/deceaseds`).

- Root cause identified and fixed with a 3-line production change using the model's canonical
  `display_name` accessor.
- Search and sort behavior preserved.
- UAT data untouched; no seeders rerun; no migrations run; dev DB content verified intact.
- New regression test covers the exact blank-`full_name`-column scenario.
- Narrow test (2 passed) and acceptance batch (71 passed) green.
- The checkpoint commit/tag/push remains ON HOLD until the owner confirms the browser renders
  the Full name column, per the checkpoint-status rule.
