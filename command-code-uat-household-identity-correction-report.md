# GOF MIS — UAT Household Identity Data Correction — Final Report

Task: correct the deterministic UAT/demo dataset so every UAT household, widow and orphan has
realistic deterministic human-readable identity data (fixing blank names in the Filament UI),
while preserving all existing scenario semantics, relationships, workflow/history records, and
idempotent convergence behavior.

---

## 1. Root cause

The UAT seeders had **always** populated `first_name` / `last_name` for every deterministic
record (verified: 0 blank first/last names). However, the Filament tables for Deceased, Widow
and Orphan all display the **`full_name` column** (`TextColumn::make('full_name')`), and that
column was **blank (NULL/empty)** for every UAT record:

- UAT deceased with blank `full_name`: **20 / 20**
- UAT widows with blank `full_name`: **16 / 16**
- UAT orphans with blank `full_name`: **21 / 21**

The `display_name` accessor renders correctly from first/last names, but the UI reads the
`full_name` **column**, not the accessor — hence the blank "Full name" / "Name" columns observed
during manual UAT.

Why `full_name` was blank despite the model's `creating` hook: the hook does compute
`full_name` on fresh `create()` calls (verified in isolation), but the existing UAT records were
created earlier in the session through a path where the computed `full_name` did not persist
(records existed with names but a NULL `full_name` column). The fix therefore requires
**convergent update semantics**, not just adding name fields to create values.

## 2. Actual identity schema / accessors

All three models use the same pattern:

| Model | first_name | middle_name | last_name | full_name | accessor |
|---|---|---|---|---|---|
| Deceased | `string(100)` NOT NULL | `string(100)` nullable | `string(100)` NOT NULL | `string` nullable | `getDisplayNameAttribute()` — returns `full_name` if set, else joins parts |
| Widow | `string(100)` NOT NULL | `string(100)` nullable | `string(100)` NOT NULL | `string` nullable | `getDisplayNameAttribute()` — same pattern |
| Orphan | `string(100)` NOT NULL | `string(100)` nullable | `string(100)` NOT NULL | `string` nullable | `getDisplayNameAttribute()` — same pattern |

All three models have a booted `creating`/`updating` hook that recomputes `full_name` from
first/middle/last when those parts are dirty. `full_name` is in `$fillable` for all three.

Filament tables render `TextColumn::make('full_name')`:
- `app/Filament/Resources/Deceased/Tables/DeceasedTable.php:46`
- `app/Filament/Resources/Widows/Tables/WidowsTable.php:54`
- `app/Filament/Resources/Orphans/Tables/OrphansTable.php:75`

## 3. Number of blank UAT names before correction

- UAT deceased (UAT-DEC-*) with blank displayed `full_name`: **20**
- UAT widows (UAT-WID-*) with blank displayed `full_name`: **16**
- UAT orphans (UAT-ORP-*) with blank displayed `full_name`: **21**
- UAT records with blank `first_name`/`last_name`: **0** (names were always present)

## 4. Files changed

- **`database/seeders/UatHouseholdSeeder.php`** (modified): switched the `household()` helper
  from `firstOrCreate` to `updateOrCreate` keyed on the stable UAT `reg_no`, and added explicit
  `full_name` values for every deceased, widow, and orphan. This is the only production-file
  change.
- **`tests/Feature/UatDemoSeederTest.php`** (modified): added 5 regression tests (see §7).
- No other application files were changed. No Filament resource change was needed (the UI
  correctly renders `full_name`; the data was the defect).

## 5. Deterministic naming strategy

- Names remain the deterministic, stable Hausa/Nigerian-style fictional demo names already
  defined in the seeder (no Faker, no randomness — identical on every run).
- Family surnames/patronymics are coherent within each household so relationships are visually
  understandable:
  - UAT-DEC-001 "Adamu Bello" → widow "Aisha Bello", orphan "Musa Bello"
  - UAT-DEC-004 "Dauda Yusuf" → widows "Hauwa Yusuf", "Laraba Yusuf"
  - UAT-DEC-005 "Emmanuel John" → widow "Mary John", orphans "David/Sarah/Joseph/Ruth John"
- Names are distinguishable across households (unique first names per household member).

## 6. Idempotent convergence strategy

- Replaced `firstOrCreate` with `updateOrCreate` keyed on the **stable UAT identifier only**
  (`reg_no` matching `UAT-DEC-*`, `UAT-WID-*`, `UAT-ORP-*`).
- On every run the seeder converges UAT-owned records to their canonical deterministic
  definition: `first_name`, `last_name`, `full_name`, `nin`, `deceased_id`, `child_sequence`,
  eligibility/marital flags, status, dates, zone, and the orphan/widow count fields.
- Only UAT-owned fields are written; arbitrary real records are never matched (key is the
  `UAT-*` reg_no).
- No delete/truncate; UUIDs are preserved (updateOrCreate updates in place); relationships,
  welfare nominations/collections, WRL records, and education/healthcare/intervention links are
  preserved (verified: welfare_beneficiaries=31, widow_loans=5, repayments=27, prescriptions=6,
  intervention_requests=7, orphan_educations=7 all unchanged after convergence).

## 7. Tests added/changed

Added to `tests/Feature/UatDemoSeederTest.php`:

1. `every UAT deceased has a non-empty displayed full name` — 0 blank `full_name`, and every
   `display_name` non-empty.
2. `every UAT widow has a non-empty displayed full name` — same for widows.
3. `every UAT orphan has a non-empty displayed full name` — same for orphans.
4. `representative deterministic UAT names are exactly stable` — asserts
   `UAT-DEC-001 = Adamu Bello`, `UAT-WID-001 = Aisha Bello`, `UAT-ORP-001 = Musa Bello`, and
   that a re-run does not change them.
5. `re-running UatDemoSeeder repairs blanked UAT identity fields without duplicating records` —
   seeds, blanks `full_name` on UAT-DEC-001/WID-001/ORP-001, re-runs the seeder, asserts the
   canonical names are restored and row counts are unchanged. This verifies actual
   convergence/idempotency, not just duplicate avoidance.

(Note: the blanking step only blanks `full_name`, because `first_name`/`last_name` are NOT NULL
columns — blanking them violates the schema. `full_name` is the nullable column that drives the
UI.)

## 8. Exact narrow test result

Command: `php artisan test tests/Feature/UatDemoSeederTest.php`

- **13 passed, 0 failed (230 assertions).**

(One intermediate failure was fixed: the convergence test initially tried to blank
`first_name`/`last_name` which are NOT NULL — changed to blank only the nullable `full_name`
column.)

## 9. Development DB backup created

- Pre-update baseline recorded: `database/database.sqlite`, SHA-256
  `465ab9cbb1da9bbdf24a202dbaca76631f835b13b095f5ef3b896fbff22d643b`, size 1,380,352 bytes.
- New timestamped backup: **`database/database.sqlite.pre-identity-fix-20260826-070146.bak`**
  (copy verified byte-identical via `cmp`). Existing backups were not overwritten.

## 10. Development DB before/after counts

| Metric | Before | After |
|---|---|---|
| users | 11 | 11 |
| deceased | 40 | 40 |
| widows | 16 | 16 |
| orphans | 21 | 21 |
| welfare_packages | 11 | 11 |
| widow_loans | 5 | 5 |
| welfare_beneficiaries | 31 | 31 |
| prescriptions | 6 | 6 |
| intervention_requests | 7 | 7 |
| orphan_educations | 7 | 7 |
| blank UAT full_name (DEC/WID/ORP) | 20/16/21 | **0/0/0** |

## 11. Duplicate check

After convergence, zero duplicate `reg_no` values among UAT-DEC-*, UAT-WID-*, UAT-ORP-*
(verified with groupBy/having count > 1 queries).

## 12. Blank-name check after correction

Zero blank `full_name` among all 20 UAT deceased, 16 UAT widows, and 21 UAT orphans.

## 13. Representative household relationships after correction

- **UAT-DEC-001 [Adamu Bello]** (zone A1): widow UAT-WID-001 [Aisha Bello] eligible, not
  married; orphan UAT-ORP-001 [Musa Bello] MALE active.
- **UAT-DEC-004 [Dauda Yusuf]** (zone A1): two widows — UAT-WID-003 [Hauwa Yusuf] and
  UAT-WID-004 [Laraba Yusuf], both eligible.
- **UAT-DEC-005 [Emmanuel John]** (zone A2): widow UAT-WID-005 [Mary John] + four orphans
  UAT-ORP-004 [David John] MALE, UAT-ORP-005 [Sarah John] FEMALE, UAT-ORP-006 [Joseph John]
  MALE, UAT-ORP-007 [Ruth John] FEMALE.

All relationships intact with coherent family surnames.

## 14. UI display verification

- Deceased table: `TextColumn::make('full_name')` → now renders "Adamu Bello" etc.
- Widow table: `TextColumn::make('full_name')` → now renders "Aisha Bello" etc.
- Orphan table: `TextColumn::make('full_name')` → now renders "Musa Bello" etc.
- No Filament resource change was required — the columns were correct; only the data was blank.

## 15. Orphan image-column audit finding

- All 21 UAT orphans and 16 UAT widows have **null** `picture_url` — the seeder never wrote an
  invalid image path (0 non-null picture_url values).
- The orphan table's `ImageColumn::make('picture_url')` has a `defaultImageUrl('https://via.placeholder.com/40')`
  placeholder, so records without photos render the placeholder — but that placeholder is an
  **external URL** (via.placeholder.com) that may not load in the local/offline environment,
  giving the appearance of missing/broken images.
- Classification: **A — UAT records legitimately have no photograph; the UI has a placeholder,
  but it is an external service URL.** Recommendation (separate from this task): replace the
  external placeholder with a local/generic avatar asset or a data-URI fallback. No broad
  image-management change was made.

## 16. Narrow regression batch result

Command:

```
php artisan test \
  tests/Feature/UatDemoSeederTest.php \
  tests/Feature/WelfareConsolidationRegressionTest.php \
  tests/Feature/WelfarePackageLifecycleTest.php
```

- **71 passed, 0 failed (347 assertions).**

Development DB verified unchanged by the test process afterward: users=11, deceased=40,
widows=16, orphans=21, welfare_packages=11, widow_loans=5, blank UAT full_name = 0/0/0.

## 17. git diff --stat

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
 phpunit.xml                                                               |  22 +++-
 tests/Feature/AdminWelfareFulfilmentWorkflowTest.php                      |  11 ++
 tests/Feature/CoordinatorWelfareRequestTest.php                           |   8 +-
 tests/Feature/WelfareCollectionSemanticsTest.php                          |  28 ++++-
 tests/Feature/WelfarePackageLifecycleTest.php                             |  65 ++++++++++--
 tests/TestCase.php                                                        |  34 +++++-
 20 files changed, 508 insertions(+), 143 deletions(-)
```

(This diff reflects the accumulated prior Welfare/UAT/isolation work; this task's own changes
are to `database/seeders/UatHouseholdSeeder.php` and `tests/Feature/UatDemoSeederTest.php`, both
untracked new files from the earlier UAT task, so they do not appear in `git diff` until staged.)

## 18. git status --short

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
 M phpunit.xml
 M tests/Feature/AdminWelfareFulfilmentWorkflowTest.php
 M tests/Feature/CoordinatorWelfareRequestTest.php
 M tests/Feature/WelfareCollectionSemanticsTest.php
 M tests/Feature/WelfarePackageLifecycleTest.php
 M tests/TestCase.php
?? .commandcode/
?? command-code-development-uat-population-report.md
?? command-code-test-database-isolation-fix-report.md
?? command-code-uat-demo-seeding-report.md
?? command-code-welfare-acceptance-audit.md
?? command-code-welfare-correction-implementation-report.md
?? database/seeders/UatDemoSeeder.php
?? database/seeders/UatEducationHealthcareSeeder.php
?? database/seeders/UatHouseholdSeeder.php
?? database/seeders/UatInventorySeeder.php
?? database/seeders/UatWelfareSeeder.php
?? database/seeders/UatWidowLoanSeeder.php
?? tests/Feature/UatDemoSeederTest.php
?? tests/Feature/WelfareConsolidationRegressionTest.php
```

## Safety confirmations

- Only `UatDemoSeeder` was run against the development database (explicitly authorized for
  convergence). `DatabaseSeeder` was NOT re-run.
- No `migrate:fresh`, `migrate:refresh`, `db:wipe`, truncate, or destructive migration was run.
- The legacy Welfare drop migration was NOT executed.
- No commits, no pushes, no tags, no branch switches, no merges/rebase, no repo-wide Pint.
- Tests ran only on the isolated in-memory test DB (phpunit.xml forces `:memory:`;
  `tests/TestCase.php` fail-fast guard active); the development DB data was verified unchanged
  by the test process.

## 19. READY / NOT READY for full-suite checkpoint acceptance

**READY.**

- All UAT identity data is corrected and converged (0 blank displayed names).
- Relationships and all workflow/history data are preserved.
- The seeder is idempotent AND convergent (verified by a dedicated test that blanks then
  restores identity fields without duplicating records).
- The 13-test UAT seeder suite and the 71-test narrow regression batch pass.
- The only outstanding item is the orphan image placeholder using an external URL
  (via.placeholder.com), which is a separate cosmetic/UI recommendation, not a blocker for the
  identity correction.

---

# Supplement — Legacy Placeholder Deceased Name Cleanup

Follow-up correction to the same UAT identity work: the Deceased module also contained 20
legacy placeholder records created by the baseline `WelfarePackageSeeder` (`DEC-00001` …
`DEC-00020`, names "DeceasedFirst N / DeceasedLast N"). These looked artificial in UAT
demonstrations.

## Audit result

- 20 legacy placeholder records (`DEC-00001` … `DEC-00020`).
- **14 referenced** by `welfare_beneficiaries` (1–2 links each) → must be renamed, not removed.
- **6 completely unreferenced** (`DEC-00001`, `DEC-00002`, `DEC-00004`, `DEC-00005`,
  `DEC-00010`, `DEC-00017` — no widows, no orphans, no welfare, no prescriptions) → safe to
  remove.

## Correction

`database/seeders/UatHouseholdSeeder.php` gained `replaceLegacyPlaceholderHouseholds()`,
called from `run()` after `seedHouseholds()`:

1. **Rename** (in-place `update()` on the stable `DEC-*` reg_no) every referenced placeholder
   record to a realistic deterministic Nigerian name (e.g. DEC-00003 → "Kabiru Danladi",
   DEC-00006 → "Ahmad Suleiman"), preserving reg_no, NIN, zone, and all welfare references.
2. **Remove** (`forceDelete()`) only the 6 completely unreferenced records.

Deterministic and idempotent: a second run finds nothing to rename and nothing to remove.

## Results on the development database

| Metric | Before | After |
|---|---|---|
| Placeholder deceased names | 20 | **0** |
| Total deceased | 40 | **34** (6 unreferenced removed) |
| UAT deceased (UAT-DEC-*) | 20 | 20 (unchanged) |
| Legacy DEC remaining (renamed) | 20 | 14 |
| welfare_beneficiaries | 31 | 31 (unchanged — all references preserved) |
| UAT widows / orphans / packages | 16 / 21 / 8 | 16 / 21 / 8 (unchanged) |
| widow_loans / prescriptions / intervention_requests | 5 / 6 / 7 | 5 / 6 / 7 (unchanged) |

- Duplicate check: zero duplicate reg_no values among all remaining deceased.
- Coherence: all 14 renamed records retain their welfare links; UAT household relationships
  intact; Widow/Orphan/Widow-History/Orphan-History modules unaffected (they reference UAT
  records, not the legacy DEC records).
- Idempotency: a second `UatDemoSeeder` run changed no counts.

## Regression coverage

`tests/Feature/UatDemoSeederTest.php` gained one test:
`UatDemoSeeder removes zero placeholder deceased names` — creates one referenced and one
unreferenced legacy placeholder record, runs the seeder, asserts: zero `DeceasedFirst%`
names remain, the referenced record is renamed to "Kabiru Danladi" with its welfare link
intact, the unreferenced record is removed, and a second run changes nothing.

## Verification

- `php artisan test tests/Feature/UatDemoSeederTest.php` → **14 passed (240 assertions)**.
- Narrow batch (UatDemoSeederTest + WelfareConsolidationRegressionTest +
  WelfarePackageLifecycleTest + DeceasedFullNameDisplayTest) → **74 passed (360 assertions)**.
- Development DB content verified unchanged by the test process (deceased=34, UAT_DEC=20,
  legacy_DEC=14, placeholder=0 before and after).
- Safety backup created before the dev-DB convergence:
  `database/database.sqlite.pre-legacy-cleanup-20260826-075312.bak`.

## Status

The placeholder cleanup is complete and converged. The checkpoint remains ON HOLD pending
owner browser verification of `/admin/deceaseds` (Full name column now renders via the
`display_name` accessor, including the renamed legacy records).
