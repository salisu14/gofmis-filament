# GOF MIS — Welfare Consolidation Forensic Acceptance Audit Report

Read-only benchmark. No source files modified, no tests modified, no migrations run, no database changes, no git mutations. The only repository change is this report file.

---

## 1. Repository / Git state

- **Branch**: `checkpoint/welfare-consolidation-atg` (tracks `origin/checkpoint/welfare-consolidation-atg`, up to date).
- **HEAD**: `b52fafd564290a259b29f6a8ece87c4ce913e6d2` — "wip: preserve welfare consolidation correction checkpoint" (2026-08-24).
- **Working tree**: CLEAN for all tracked files (0 modified/deleted/staged). The only untracked entry is `.commandcode/` (`settings.json`, `taste/taste.md`) — the agent tooling dir, not part of the change.
- **180+ file diff**: VERIFIED as **HEAD vs `main`** = **187 files, +17,742/−1,195**. This is NOT a working-tree anomaly. `main` sits at `54d314e` (a single commit "Add AGENTS.md" on top of merge-base `60f5fd1`), while HEAD carries 52+ commits of real feature work. The diff is genuine content evolution (new resources, models, migrations, tests) plus deletions (`AGENTS.md` exists only on main; legacy Welfare files deleted). No file-mode-only or line-ending-only changes (`.gitattributes` enforces `* text=auto eol=lf`, no CRLF files, `core.autocrlf` unset). No untracked temp scripts, no leftover checkpoints, no branch mismatch.

## 2. Historical commit relationship

- `60f5fd1` ("merge: complete GOF MIS financial integrity and reconciliation hardening") is a **merge commit** with parents `60ab0886` + `bbb8598e`. It is an **ancestor of HEAD** and of `main` (merge-base of HEAD/main = 60f5fd1).
- `9359556` ("feat(inventory): canonical stock movements ledger…") is a **direct parent of b52fafd** (`b52fafd`'s only parent).
- `b52fafd` itself is the welfare-consolidation checkpoint: it deletes `app/Models/Welfare.php`, `app/Models/DeceasedWelfare.php`, `app/Services/WelfareService.php`, `app/Providers/WelfareServiceProvider.php`, adds `WelfarePackageLifecycleService`, the drop-legacy migration, and `WelfarePackageLifecycleTest`.
- **Prior-agent claims contradicted**: no 180-file working-tree diff exists; there was no checkpoint reconstruction; the branch is a clean linear chain (`60f5fd1 → … → 9359556 → b52fafd`), only two merge commits in the last 60 (`60f5fd1`, `5b0cc59`).

## 3. Current Welfare architecture map

- **Models**: `WelfarePackage` (status DRAFT/OPEN/CLOSED, `isCompositionEditable()`, `canBeOpened/Closed/Reopened()`, `hasNominations()`), `WelfarePackageItem`, `WelfareBeneficiary` (status PENDING/APPROVED/REJECTED + collection_status NOT_COLLECTED/COLLECTED; `markAsCollected/Approved/Rejected`), `Deceased` (household head; `widows()`, `orphans()`, global zone scope), `Widow` (`isOperationalBeneficiary()`, `isEligibleForSupport()`, `recalculateEligibility()`), `Orphan` (`isOperationalBeneficiary()`, `isOverAged()`, `scopeOperational/Historical`, `EligibleOrphanScope`), `StockMovement`.
- **Services**: `WelfarePackageLifecycleService` (canonical DRAFT→OPEN→CLOSED→OPEN transitions), `WelfareNominationService` (bulk nomination), `WelfarePackageService` (create/update/duplicate/syncItems/stats/export + legacy open/close/reopen), `BeneficiaryService` (suggest/approve/reject/collect/bulk), `OrphanEligibilityService`.
- **Filament**: `WelfarePackageResource` (admin panel; pages List/Create/Edit; `WelfarePackagesTable` with Open/Close/Reopen/Duplicate/Edit/Delete actions; `ItemsRelationManager`; `BeneficiariesRelationManager`), `WelfareRequestResource` (coordinator panel "Welfare Nominations"; pages List/Create/Edit/View; header `nominate_beneficiaries` bulk action; `mark_collected` row action).
- **Policies**: `WelfarePackagePolicy` (viewAny/view: admin/super_admin or managesZone; create/update/delete/open/close/reopen/duplicate: admin only; update restricted to DRAFT; `collect` on package: admin + isOpen), `WelfareBeneficiaryPolicy` (view: zone + own suggestion; create/suggest: zone manager; approve/reject/collect/delete: `admin` role only).
- **Named components that DO NOT exist**: none missing — all listed components exist. `BeneficiariesRelationManager` exists under `WelfarePackages/RelationManagers/`.

## 4. Lifecycle state machine (Part C)

`WelfarePackageStatus::canTransitionTo()` implements exactly: **DRAFT→OPEN, OPEN→CLOSED, CLOSED→OPEN** allowed; **DRAFT→CLOSED, OPEN→DRAFT, CLOSED→DRAFT, DRAFT→DRAFT, OPEN→OPEN, CLOSED→CLOSED** forbidden. Confirmed by test file.

Domain guards in `WelfarePackageLifecycleService`:

- **OPEN**: package must have ≥1 `WelfarePackageItem` (throws RuntimeException otherwise). No date guard.
- **CLOSE**: no additional guards.
- **REOPEN**: no item/nomination guard in the service (docs say items "must still exist" but the code does NOT check — reopen of an item-less package succeeds).

**Bypass check**: `WelfarePackagesTable` Open/Close/Reopen actions delegate to `WelfarePackageLifecycleService`. BUT `WelfarePackageService::openPackage/closePackage/reopenPackage` still exist and write status directly (guarded only by model helpers, not the canonical service, and no item guard on open). Currently no Filament action calls them for lifecycle (only `duplicatePackage` is used), but they are public API and a latent bypass.

## 5. Filament action matrix (Part D)

| State | View | Edit | Open | Close | Reopen | Duplicate | Delete |
|---|---|---|---|---|---|---|---|
| DRAFT / 0 noms | ✓ | ✓ (EditAction `isCompositionEditable`; **but policy `update` = DRAFT only**) | ✓ | ✗ | ✗ | ✓ | ✓ |
| DRAFT / >0 noms | ✓ | ✓ (isCompositionEditable true; **policy update false — mismatch**) | ✓ | ✗ | ✗ | ✓ | ✗ (delete requires draft && no noms) |
| OPEN / 0 noms | ✓ | ✓ (EditAction visible via isCompositionEditable; **policy update false — mismatch**) | ✗ | ✓ | ✗ | ✓ | ✗ |
| OPEN / >0 noms | ✓ | ✗ (isCompositionEditable false) | ✗ | ✓ | ✗ | ✓ | ✗ |
| CLOSED / 0 noms | ✓ | ✗ | ✗ | ✗ | ✓ | ✓ | ✗ |
| CLOSED / >0 noms | ✓ | ✗ | ✗ | ✗ | ✓ | ✓ | ✗ |

Discrepancies:

- **`WelfarePackagePolicy::update()`** restricts edit to `isDraft()` while the table shows Edit for OPEN/0-noms — policy and UI disagree (UI is more permissive than policy; the policy is the tighter one).
- Duplicate has **no policy check** in the action (`->visible` never set) though `WelfarePackagePolicy::duplicate` exists — authorization relies on default Filament policy check, but the explicit `duplicate` method is unused.

## 6. Composition immutability (Part E)

- **Server-side**: `WelfarePackage::isCompositionEditable()` = DRAFT, or OPEN with zero nominations; `WelfarePackageService::updatePackage()`/`syncItems()` enforce it (throw RuntimeException). The Edit page `mutateFormDataBeforeFill` blocks navigation when not editable.
- **UI**: `ItemsRelationManager` gates Create/Edit/Delete/BulkDelete on `isDraft()` only — so OPEN/0-noms composition is editable via the Edit page form repeater (`WelfarePackageForm` Repeater), but **NOT** via the relation manager (inconsistent).
- Answer per state: DRAFT/no-noms editable (both paths); DRAFT/noms editable (server says yes — contradicts the "lock once nominations exist" message); OPEN/no-noms editable (Edit form only); OPEN/noms NOT; CLOSED/no-noms NOT; CLOSED/noms NOT; REOPENED-with-prior-noms NOT (test asserts this).

## 7. Nomination entry points (Part F)

1. **Coordinator ListWelfareRequests `nominate_beneficiaries`** (header action) → `WelfareNominationService::nominate()` → validates (OPEN package, zone, duplicate, household eligibility) → `WelfareBeneficiary::create` inside `DB::transaction`.
2. **Admin ListWelfarePackages `nominate_beneficiaries`** → same `WelfareNominationService`.
3. **Coordinator `CreateWelfareRequest`** (form) → `handleRecordCreation` → `parent::handleRecordCreation` — **direct model create, NO service**. Duplicate check is ad-hoc in `mutateFormDataBeforeCreate` + DB unique constraint catch.
4. **Admin `BeneficiariesRelationManager` CreateAction** → `mutateDataUsing` sets suggested_by/status/collection_status → Filament `RelationManager` persists directly — **NO service call** (server-side package-OPEN and eligibility checks absent; only the select query and a duplicate pre-check).
5. **`BeneficiaryService::suggestBeneficiary()`** — used by tests, not by any Filament path.
6. Seeder `WelfarePackageSeeder` creates beneficiaries directly (dev data only).

**Conclusion**: entry points do NOT converge on one canonical service. Three separate write paths.

## 8. BeneficiaryService server-side validation matrix (Part G)

`suggestBeneficiary()`:

1. Package exists — ✓ (passed as arg; `isOpen()` would fatal on null).
2. Package OPEN — ✓ (`isOpen()`).
3. End date passed — ✓ (`now()->isAfter(end_date)`).
4. Deceased exists — ✗ (no existence check; FK will fail with QueryException).
5. Coordinator zone restriction — ✗ **NO zone check server-side** (only UI selectors + `Deceased` global zone scope, which can be bypassed by passing any ID).
6. Admin across zones — ✓ by absence of restriction.
7. Household has eligible widow/orphan — ✗ **NOT checked** (WelfareNominationService checks this; BeneficiaryService does not).
8. No duplicate nomination — ✓ pre-check + DB unique (`unique_package_deceased`), but **non-transactional race window** (see below).
9. Concurrent duplicate prevention — ✗ pre-check `exists()` is outside the `DB::transaction`; the unique constraint is the only backstop, and the transaction lacks a lock.
10. Bypass by direct call — the service itself IS the bypass for the missing checks; and all four UI paths bypass the service entirely.

**DB constraints**: unique `(welfare_package_id, deceased_id)` = `unique_package_deceased` — a hard duplicate backstop (with `softDeletes`, so deleted rows still block re-nomination). No trigger/constraint enforces package-OPEN or eligibility.

## 9. Eligibility semantics (Part H)

- **Widow**: `isOperationalBeneficiary()` = not married && not trashed; `isEligibleForSupport()` = operational && `is_eligible`. `recalculateEligibility()` flips `is_eligible` on remarriage (false) or divorce (recompute, preserving write-off blocker).
- **Orphan**: `isOperationalBeneficiary()` excludes archived/rejected, over-18 males, married females; `EligibleOrphanScope` = is_eligible && status ACTIVE && (male <18 || female unmarried).
- **Nomination eligibility** (WelfareNominationService + selectors): household has ≥1 operational && eligible widow/orphan.
- **Collection eligibility**: only `canBeCollected()` = status APPROVED && NOT_COLLECTED. **No re-validation of widow/orphan eligibility at collection time** — a widow who remarries after approval can still collect (no linkage from beneficiary to a specific widow/orphan).
- **Ambiguity**: eligibility is evaluated on the *household* (Deceased), not the specific individual, and nothing re-checks between nomination and collection.

## 10. Collection semantics (Part I)

- **Who**: `WelfareBeneficiaryPolicy::collect` = admin role only + `canBeCollected()`. Coordinator `mark_collected` action visible to any user who can see the record and `$record->canBeCollected()` — **but policy check `->can('collect', $record)` is NOT in the coordinator action** (only `canBeCollected()`); `BeneficiaryService::collectPackage` also has no role check, so a coordinator CAN call it directly (test 7 in WelfareCollectionSemanticsTest uses a coordinator successfully).
- **Package state required**: none checked (collection allowed on CLOSED packages).
- **Idempotency**: not idempotent — `canBeCollected()` prevents double-collect, throws RuntimeException.
- **Stock posting**: `markAsCollected()` writes `StockMovement` WELFARE_ISSUE rows via `firstOrCreate` (reference_type=WelfareBeneficiary, reference_id=beneficiary.id). **Bulk collect** (`bulkCollect`) updates the record directly and **does NOT post stock movements** — inconsistency.
- **Negative stock**: no stock-level check anywhere; negative balances possible.
- **Transactional**: `collectPackage` wraps in `DB::transaction` with `lockForUpdate`; `markAsCollected` also does the stock write in the same call (inside the transaction). `bulkCollect` is a plain query update, no transaction.
- **Events**: `BeneficiaryCollected` is defined and `LogBeneficiaryCollection` logs — but **nothing dispatches `BeneficiaryCollected`** (grep found zero dispatch sites). `BeneficiaryApproved` likewise never dispatched.

## 11. Event/listener findings (Part J)

- `app/Models/Welfare.php`, `app/Models/DeceasedWelfare.php`, `app/Services/WelfareService.php`, `app/Providers/WelfareServiceProvider.php` — **all deleted in b52fafd**. No runtime references remain (grep clean; only the drop migration mentions the table names).
- `WelfareServiceProvider` previously registered `BeneficiaryCollected→LogBeneficiaryCollection` and `OrphanBecameIneligible→HandleOrphanIneligibility`. After deletion, `php artisan event:list` STILL shows both — Laravel **auto-discovery** picks up `app/Events`+`app/Listeners` (no explicit EventServiceProvider exists). So **no listener-registration regression** from removing the provider.
- However, since `BeneficiaryCollected` is never dispatched and `markAsCollected` doesn't dispatch it, the listener is dead code. `OrphanBecameIneligible` IS dispatched by `OrphanEligibilityService` (which appears to be a scheduled/manual job).

## 12. Legacy drop migration audit (Part K)

`2026_09_01_000001_drop_legacy_welfare_tables.php` (PENDING in migrate:status — not yet run):

- Drops `deceased_welfare` then `welfare`.
- **Guard**: refuses to run if either table has rows (throws RuntimeException) — prevents silent data loss, but means the migration **cannot ever run** on any DB where legacy data exists (must be archived manually first).
- **FK/order**: `deceased_welfare` (references welfare) dropped first — correct order.
- **down() is broken**: recreates `welfare` with schema totally different from the original (original: uuid id + name/date/collection_status/welfare_status; down: bigint id + deceased_id/status/collected_at) — `migrate:rollback` would produce an incompatible table and `deceased_welfare` down references `welfare_id` as `foreignId` (bigint) against a uuid PK, and its down() calls `dropIfExists('deceased_welafares')` (typo, original table was `deceased_welfare`).
- **Timestamp ordering**: `2026_09_01` sorts after all existing migrations (latest `2026_08_23`), so it runs last — sensible. Current app code has **zero** references to these tables (verified by grep), so dropping is safe w.r.t. current code; only the down()/schema fidelity is defective.

## 13. Existing Welfare-related tests (Part L)

Present: **`WelfarePackageLifecycleTest`** (328 lines; state machine + service guards + model helpers + visibility matrix), **`WelfareMultiNominationAndEligibilityTest`** (515 lines; A1–E2 scenarios), **`WelfareCollectionSemanticsTest`** (258 lines; filters + invariants + zone isolation), plus `CoordinatorWelfareRequestTest` (171), `AdminWelfareFulfilmentWorkflowTest` (167). All three named tests physically exist.

## 14. Tests actually executed & exact results

Command: `php artisan test` on the five files → **6 failed, 58 passed (142 assertions)**.

- 5 failures in `WelfarePackageLifecycleTest` — all `QueryException: NOT NULL constraint failed: deceased.guardian_name` at the `addNomination()` helper (line 84) — the helper's `Deceased::create` omits `guardian_name`/`guardian_phone`, which the migration requires (NOT NULL). These are **test-fixture defects, not product defects** (product logic never reached).
- 1 failure in `CoordinatorWelfareRequestTest` test 6 — "valid welfare request can be submitted by coordinator" — form validation errors `welfare_package_id` + `deceased_id` required. Because test 5's duplicate protection / field wiring leaves the form not filling `deceased_id` correctly for a fresh `Deceased::factory()` record — the create form's `deceased_id` options for a coordinator apparently exclude the factory-created record (no widow/orphan → filtered out by the eligibility filter). This is a test-data problem (factory creates a Deceased with no widow/orphan, so the household-eligibility filter removes it from options).

No product code was modified; DB is in-memory SQLite (`:memory:`); repo remained clean (`git status` unchanged).

## 15. Critical defects

1. **Nomination authorization bypass via `BeneficiariesRelationManager` CreateAction** — direct model write, no `WelfareNominationService`, no package-OPEN check, no zone check, no household-eligibility check.
   - Severity: Critical. File: `app/Filament/Resources/WelfarePackages/RelationManagers/BeneficiariesRelationManager.php` (CreateAction ~line 164). Risk: any user with `suggest` policy (any zone manager) can nominate arbitrary households (incl. ineligible, cross-zone) into any package. Correction: route through `WelfareNominationService::nominate()` or `BeneficiaryService::suggestBeneficiary()` + policy checks.
2. **`CoordinatorWelfareRequestResource::form()` (CreateWelfareRequest) writes directly** — no service; eligibility/zone/OPEN checks live only in form rules, and `canCreate` returns true for any zone manager; the model write path is `parent::handleRecordCreation`.
   - Severity: Critical. File: `app/Filament/Coordinator/Resources/WelfareRequestResource.php` + `Pages/CreateWelfareRequest.php`. Risk: bypass of `WelfareNominationService` guards. Correction: unify on one nomination service.
3. **`BeneficiaryService::suggestBeneficiary()` missing zone + household-eligibility server-side checks** (matrix §8 items 5,7). It is invoked by tests and is the "canonical" single-nominate API, yet is weaker than `WelfareNominationService`.
   - Severity: Critical. File: `app/Services/BeneficiaryService.php`. Risk: direct-call or future UI reuse permits cross-zone/ineligible nominations. Correction: add zone + eligibility validation.

## 16. High defects

4. **Two lifecycle authorities**: `WelfarePackageLifecycleService` (canonical) vs `WelfarePackageService::openPackage/closePackage/reopenPackage` (writes status directly, no item guard). `app/Services/WelfarePackageService.php:52-91`. Risk: divergence; correction: deprecate/remove and delegate.
5. **`reopenPackage` has no item-existence guard** despite docblock claim. `WelfarePackageLifecycleService.php:72-83`. Risk: reopening an empty package. Correction: add `items()->exists()` guard.
6. **Duplicate pre-check race in `suggestBeneficiary`** — exists() outside transaction; relies on DB unique. `BeneficiaryService.php:28-52`. Risk: concurrent duplicate under race; correction: lock or catch unique constraint inside transaction (already partly done).
7. **`bulkCollect` bypasses `canBeCollected` locking, transaction, and stock posting** (`BeneficiaryService.php:114-127`) — silent divergence from single collect. Risk: no stock ledger for bulk collections, negative stock possible, no idempotency protection (query filters readyForCollection only at SQL level). Correction: loop `collectPackage` or add stock posting + transaction.
8. **Coordinator `mark_collected` action has no policy check** — `WelfareRequestResource.php:402-417` visible only on `canBeCollected()`, and `BeneficiaryService::collectPackage` has no role gate. Tests confirm a coordinator can collect. Correction: require admin policy or explicit role.

## 17. Medium defects

9. **Policy vs UI mismatch on Edit**: `WelfarePackagePolicy::update()` = DRAFT-only; table EditAction visible for OPEN/0-noms. `app/Policies/WelfarePackagePolicy.php:28-31`. Correction: align policy with `isCompositionEditable()`.
10. **ItemsRelationManager gating on `isDraft()`** while Edit page allows OPEN/0-noms edits — inconsistent. `ItemsRelationManager.php:76-87`. Correction: use `isCompositionEditable()`.
11. **`WelfareInterventionWidget` queries `whereIn('status', ['approved','collected'])`** — `'collected'` is not a `BeneficiaryStatus`; widget silently misses collected records. `app/Filament/Widgets/WelfareInterventionWidget.php:28`. Correction: use `approved()` and filter by collection_status separately.
12. **`BeneficiaryCollected`/`BeneficiaryApproved` never dispatched** — dead events/listeners; collection logging never happens. Correction: dispatch in `markAsCollected`/`markAsApproved`.
13. **Drop-legacy migration down() is non-functional** (schema mismatch + `deceased_welafares` typo). `2026_09_01_000001_drop_legacy_welfare_tables.php:30-49`. Correction: fix down() to recreate original uuid schema.
14. **No eligibility revalidation at collection** — remarried/overaged beneficiaries can collect. Risk: domain violation. Correction: recheck at collection or document as accepted.

## 18. Low defects

15. `EditWelfarePackage` header DeleteAction gated on `isDraft() && !hasNominations()` but server-side `WelfarePackagePolicy::delete` = `isDraft()` only — policy would allow deleting a DRAFT-with-nominations via API.
16. `WelfarePackagePolicy::duplicate` unused (DuplicateAction relies on default policy).
17. Bulk `approve`/`collect` in `BeneficiariesRelationManager` gated on admin role but not per-record `canBeCollected` — bulkCollect re-filters via scope so OK, bulkApprove filters pending — acceptable but note.
18. `PendingItemsWidget` welfare count uses `WelfareBeneficiary::where(status=PENDING)` without zone filter (relies on global scope only for deceased — WelfareBeneficiary has no zone global scope, so **cross-zone leak** in widget counts for coordinators). `PendingItemsWidget.php:44`.

## 19. Architecture inconsistencies

- Two nomination services with divergent validation (`WelfareNominationService` strong, `BeneficiaryService::suggestBeneficiary` weak); UI mostly bypasses both.
- Two lifecycle services; canonical one is new, legacy one still public.
- Relation-manager CreateAction vs header bulk-action vs coordinator form: three different validation stacks.
- `Deceased::welfarePackages()` BelongsToMany pivot still references `welfare_beneficiaries` — fine, but `Deceased` model comment "Legacy welfare relationship removed" is stale (it's still there).
- `WelfareStatus` enum (OPENED/CLOSED) is now orphaned — only legacy `Welfare` model used it; model deleted, enum remains.

## 20. Security/authorization concerns

- **Zone isolation is incomplete server-side**: `WelfareBeneficiary` has no global zone scope; coordinator-visible lists rely on `whereHas('deceased', zone)` in resource query — OK for the resource, but `BeneficiaryService` and `WelfareNominationService` are the only server-side gate and `BeneficiaryService` lacks it.
- **Policy `suggest` = managesZone** but the CreateAction also requires `isOpen` only — no eligibility.
- `Gate::before` grants super_admin all abilities (bypasses policies for super_admin) — intended, but means super_admin can call any service.

## 21. Data-integrity / concurrency concerns

- Unique `(package, deceased)` exists and blocks duplicates including soft-deleted rows (no partial unique index on `deleted_at IS NULL`), so a deleted PENDING nomination permanently blocks re-nomination. (Medium: consider partial unique index.)
- No stock-balance guard — collections can drive stock negative; `StockAvailabilityService::calculatePackageCapacity` is advisory only.
- Bulk operations lack transactions/locking.
- Collection stock posting is `firstOrCreate` on (item, type, reference) — safe against double-posting per beneficiary, but `bulkCollect` never posts.

## 22. Missing test coverage

- No test for `BeneficiariesRelationManager` CreateAction authorization/eligibility.
- No test for `BeneficiaryService::suggestBeneficiary` zone restriction or eligibility (matrix gaps 5,7).
- No test for `bulkCollect` stock posting (it doesn't post — untested behavior).
- No test that `BeneficiaryCollected` is dispatched (it isn't — no test could pass).
- No test for package date-window vs nomination (open-but-ended package nomination attempt).
- No test for concurrent duplicate nomination (race).
- No test for the drop-legacy migration (down()).

## 23. Claims disproven by current repo evidence

- "180+ file diff in the working tree / checkpoint inconsistency" — FALSE; tree is clean; 187-file diff is simply HEAD vs main over 52 commits.
- "Conflicting claims about which files belonged to the change" — the b52fafd change is exactly its 22-file stat; nothing else.
- "WelfareServiceProvider removal caused listener-regression" — FALSE; auto-discovery still registers both listeners (verified via `event:list`).
- "Legacy Welfare/DeceasedWelfare still referenced at runtime" — FALSE; zero references remain.
- "Collection dispatches BeneficiaryCollected" — FALSE; never dispatched.

## 24. Recommended correction plan (priority order — NOT implemented)

1. Consolidate all nomination paths on one canonical service (`WelfareNominationService`) and delete/neutralize `BeneficiaryService::suggestBeneficiary`'s bypass; add zone + household-eligibility checks to it or remove it.
2. Fix `BeneficiariesRelationManager` CreateAction to delegate to the canonical service with package-OPEN, zone, eligibility, and policy checks.
3. Make `WelfarePackageService::open/close/reopen` delegate to `WelfarePackageLifecycleService`; add the missing reopen item guard.
4. Harden `collectPackage`/`bulkCollect`: single transaction, `lockForUpdate`, dispatch `BeneficiaryCollected`, post stock for bulk, add admin-role enforcement.
5. Fix drop-legacy migration `down()` (schema fidelity + typo) before it is ever run; document the archive step for non-empty legacy tables.
6. Align `WelfarePackagePolicy::update()` with `isCompositionEditable()`; gate Duplicate with policy; gate coordinator collect with policy.
7. Fix `WelfareInterventionWidget` status filter; fix `PendingItemsWidget` cross-zone count.
8. Re-add eligibility revalidation at collection (or a documented decision).
9. Fix test fixtures (`guardian_name`/`guardian_phone` in `WelfarePackageLifecycleTest::addNomination`) and the `CoordinatorWelfareRequestTest` test-6 data so the suite is green.

**Repository left untouched**: no files modified, no migrations run, no DB changes, no git mutations. `git status` clean (only pre-existing untracked `.commandcode/`).
