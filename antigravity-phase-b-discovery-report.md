# GOF MIS — Phase B Roadmap (Reconciled & Formalized)

> **Document status**: This is the authoritative, reconciled Phase B roadmap.
> It supersedes the earlier discovery snapshot whose "Next Action" pointed at
> B-02 — that item has long since been implemented. Historical discovery
> findings are preserved below (§3) but the current verified status is the
> source of truth for what comes next.

---

## 1. Repository & Branch Context

- **Repository**: `/home/salsafh/codes/projects/gof/gofmis-atg`
- **Branch**: `feat/foundation-phase-b`
- **Current HEAD**: `d94792c` (`fix: resolve MFA settings dashboard redirect for role-based access`)
- **Baseline**: Phase A closed with a fully green suite (711 tests, 2,588 assertions).

---

## 2. Current Verified Implementation Status (AUTHORITATIVE)

This section reflects the **verified repository state** from the Phase B
reconciliation audit. It is derived from code, migrations, permissions,
tests, and git history — not from the earlier discovery snapshot alone.

| Task | Scope | Status |
|------|-------|--------|
| B-01 | Welfare Package Item/Category source-of-truth | **COMPLETE** |
| B-02 | InterventionRequests `ItemsRelationManager` `$relatedResource` fix | **COMPLETE / SUPERSEDED ROADMAP ITEM** |
| B-03 | EducationFeeInvoices `PaymentsRelationManager` `$relatedResource` fix | **COMPLETE / SUPERSEDED ROADMAP ITEM** |
| B-04 | Sponsorship `AllocationsRelationManager` `$relatedResource` fix | **COMPLETE / SUPERSEDED ROADMAP ITEM** |
| B-05 … B-12 | (no authoritative definition exists) | **UNKNOWN / HISTORICAL LABEL UNCLEAR** |
| B-13 | Beneficiary CSV Import/Export (Deceased/Widow/Orphan) | **COMPLETE** |
| B-13A | Stock Availability Summary UI repair | **AD-HOC / NOT PART OF FORMAL BENEFICIARY PHASE B ROADMAP** |
| B-13B | Beneficiary optional `has_nin` (+ Deceased edit UTF-8 fix, Orphan eligibility edit fix) | **COMPLETE / UAT PASSED** |

---

## 3. Original Discovery Findings (Preserved History)

The following is the original discovery content recorded when this roadmap
was first written (HEAD `8233aa7`). It is retained for historical context.

### 3.1 Completed at discovery time
- **B-01 — Welfare Package Item/Category Source-of-Truth** (committed in `8233aa7`):
  - Removed redundant `category_id` column from `welfare_package_items`.
  - Defined dynamic `WelfarePackageItem->category` relationship via `HasOneThrough`.
  - Refactored `WelfarePackageForm` and `ItemsRelationManager` to display read-only derived category.
  - Added `tests/Feature/WelfarePackageItemCategoryInvariantTest.php` (10 tests).
  - Full Pest suite at the time: 721 passed / 0 failed.

### 3.2 Findings identified at discovery time (PRIORITY AS ORIGINALLY RANKED)
- **B-02 (P1)** — `InterventionRequests/RelationManagers/ItemsRelationManager.php` declared
  `protected static ?string $relatedResource = InterventionRequestResource::class;`,
  causing Filament v5 to misroute Create/Edit modal forms to the parent resource form.
  Recommended correction: remove `$relatedResource`.
- **B-03 (P2)** — `EducationFeeInvoices/RelationManagers/PaymentsRelationManager.php` declared
  `protected static ?string $relatedResource = EducationFeeInvoiceResource::class;`.
  Recommended correction: remove `$relatedResource`.
- **B-04 (P2)** — `Sponsorships/RelationManagers/AllocationsRelationManager.php` declared
  `protected static ?string $relatedResource = SponsorshipResource::class;`.
  Recommended correction: remove `$relatedResource`.

### 3.3 Historic disposition of those findings
All three were resolved (commit `34b15b7` *fix(foundation-phase-b): stabilize
relation managers and financial workflows*). Each relation manager now declares
its own `form(Schema $schema)` and no longer declares `$relatedResource`.
They are therefore **superseded roadmap items**, not open work.

---

## 4. Verified Historical Task Status Detail

- **B-01 — COMPLETE.**
- **B-02 — COMPLETE / SUPERSEDED ROADMAP ITEM.** Verified: `ItemsRelationManager`
  defines its own form; the `InterventionRequestItemRelationManagerTest` suite passes (10 tests).
- **B-03 — COMPLETE / SUPERSEDED ROADMAP ITEM.** Verified: `PaymentsRelationManager`
  defines its own form; the `EducationFeeInvoicePaymentRelationManagerTest` suite passes (12 tests).
- **B-04 — COMPLETE / SUPERSEDED ROADMAP ITEM.** Verified: `AllocationsRelationManager`
  defines its own form; the `SponsorshipAllocationsRelationManagerTest` suite passes (10 tests).

- **B-05 through B-12 — UNKNOWN / HISTORICAL LABEL UNCLEAR.**
  Repository evidence does not provide an authoritative definition of these
  task numbers. No document in the repository enumerates B-05..B-12. They are
  recorded as **unknown** rather than inventing scope. They should not be
  treated as open work based on numbering alone.

- **B-13 — COMPLETE.** Beneficiary CSV Import/Export:
  - `DeceasedImporter`, `WidowImporter`, `OrphanImporter` exist and are wired.
  - Exporters exist and are wired (`ImportAction` / `ExportAction`) on all three
    beneficiary resources, gated by `import_*` / `export_*` permissions.
  - Canonical enum normalization (gender → `MALE/FEMALE`; vulnerability status → `A/B/C`),
    `birth_date` mapping, and NIN/`has_nin` handling verified.
  - Regression suite: `BeneficiaryCsvImportExportTest` — **5 passed / 27 assertions**.

- **B-13B — COMPLETE / UAT PASSED.** Beneficiary optional `has_nin`:
  - Migration adds `has_nin` to `deceased`, `widows`, `orphans`; makes `nin`
    nullable; backfills existing rows.
  - Models expose `has_nin` (fillable + boolean cast) with a saving-hook
    invariant (`has_nin = false ⇒ nin = NULL`).
  - Admin + Coordinator forms render a conditional "Has NIN?" toggle; NIN is
    visible/required only when `has_nin` is true; stored as an 11-digit string
    preserving leading zeros; `unique(ignoreRecord: true)`.
  - Malformed-UTF-8 Deceased edit defect fixed at source (`Str::after` instead of
    byte-based `substr`).
  - Orphan "Eligible for Support" is editable for non-archived records; archived
    records remain immutable.
  - Regression cluster: **34 passed / 228 assertions**.

---

## 5. B-13A Classification

**AD-HOC / NOT PART OF FORMAL BENEFICIARY PHASE B ROADMAP.**

- The only reference to "B-13A" in repository history is commit
  `c7e6d29 "Fix B-13A Stock Availability Summary UI rendering defect"`, which
  lives on the separate branch `checkpoint/welfare-consolidation-atg` — **not**
  on `feat/foundation-phase-b`.
- The current checkout does **not** contain that Stock Availability rework
  (it still uses the legacy manual Blade view and has no `StockAvailabilityStatsWidget`).
- Stock Availability work should be tracked **separately** from the beneficiary
  Phase B roadmap; it is unrelated to beneficiary functionality.

---

## 6. Phase B Functional-Domain Completeness

Verified against code, migrations, permissions, and tests.

| Functional Domain | Status |
|-------------------|--------|
| Beneficiary core registration (Deceased/Widow/Orphan CRUD) | IMPLEMENTED |
| Deceased | IMPLEMENTED |
| Widows | IMPLEMENTED |
| Orphans | IMPLEMENTED |
| Household/family relationships (contextual create of widows/orphans under a deceased) | IMPLEMENTED |
| Eligibility (widow + orphan) | IMPLEMENTED |
| Vulnerability | IMPLEMENTED |
| Registration numbering (auto reg_no per type) | IMPLEMENTED |
| NIN / has_nin | IMPLEMENTED |
| Widow history | IMPLEMENTED |
| Orphan history | IMPLEMENTED |
| Zone transfers | IMPLEMENTED |
| ID cards | IMPLEMENTED |
| Biometrics | **FOUNDATION IMPLEMENTED / CAPTURE WORKFLOW BREADTH UNVERIFIED** |
| Welfare packages | IMPLEMENTED |
| Education | IMPLEMENTED |
| Medicals | IMPLEMENTED |
| Sponsorship | IMPLEMENTED |
| Interventions | IMPLEMENTED |
| Projects | **IMPLEMENTED / AUTHORIZATION TEST GAP OUTSTANDING** |
| Revolving loans | IMPLEMENTED |
| Finance integration | IMPLEMENTED (Imprest intentionally deactivated; central treasury + consolidated reporting active) |
| CSV import/export (beneficiary) | IMPLEMENTED |
| Reporting | IMPLEMENTED |
| Audit/history | IMPLEMENTED (activity log; orphan/widow history separation) |
| Permissions/security | IMPLEMENTED (RBAC, MFA, zone scoping, demo observer) |

---

## 7. GENUINE REMAINING VERIFIED GAPS

1. **Projects authorization/lifecycle**: `AdminProjectOperationalLifecycleTest`
   fails with HTTP 403 when a Coordinator mounts the Admin `ViewProject` page.
   Classified as an unrelated existing Projects-domain authorization/context
   issue (not a beneficiary/CSV/has_nin regression).
2. **Biometrics completeness**: only the foundation exists (model, morph
   relationships, relation managers); full capture/enrollment workflow breadth
   is not verified.
3. **Roadmap reconciliation**: B-05..B-12 have no authoritative definition.

---

## 8. POST-B-13 VERIFIED BACKLOG

> Identifiers below (`PB-NEXT-*`) are **reconstructed post-audit backlog
> identifiers, not recovered historical Phase B numbers**. Permanent
> phase/task numbering will be assigned later after reviewing the overall
> GOF MIS roadmap. They are **not** "B-14", "B-15", and so on.

### Priority 1 — PB-NEXT-01: Projects Authorization/Lifecycle Investigation — RESOLVED
- **Problem**: `AdminProjectOperationalLifecycleTest` failed with HTTP 403 when a
  Coordinator mounted the Coordinator-panel `ViewProject` page for a project in
  their own zone.
- **Root cause**: The Coordinator `ProjectResource` overrode every `ZoneScoped`
  authorization method (`canViewAny`/`canView`/`canCreate`/`canEdit`/`canDelete`)
  with raw `*_projects` permission checks, and the `coordinator` role was not
  granted any project permission. Coordination could therefore never access
  project pages, despite the Coordinator panel shipping a full `ProjectResource`
  and the sibling resources all relying on the zone-scoped `managesZone()` model.
- **Classification**: D — PERMISSION/POLICY DRIFT.
- **Correction**:
  - `app/Filament/Coordinator/Resources/ProjectResource.php` — aligned the `can*`
    methods with the canonical coordinator-resource pattern: `canViewAny`/`canView`
    from the `ZoneScoped` trait (zone-managed), `canCreate`/`canEdit` gated on
    `isCoordinator() && permission && managesZone()`, `canDelete` admin-only.
  - `database/seeders/RolesAndPermissionsSeeder.php` — granted the `coordinator`
    role `view_projects`, `create_projects`, `edit_projects` (NOT delete/manage,
    so project lifecycle stays admin-managed).
- **Test evidence**:
  - `AdminProjectOperationalLifecycleTest` — 3 passed / 43 assertions (was failing).
  - `CoordinatorProjectTest` — 3 passed / 11 assertions (was failing).
  - Project/permission/zone batch (10 suites incl. RBAC coordinator, zone
    isolation, project expense/KPI, reports) — 46 passed / 220 assertions / 0 failures.
- **Status**: RESOLVED / VERIFIED.

### Priority 2 — PB-NEXT-02: Biometrics Capture/Enrollment Completion Assessment — ASSESSED
- **Assessment complete** (read-only; no implementation performed).
- **Verified finding**: A biometric **foundation** exists (single commit
  `2ae572c`): polymorphic `BeneficiaryFingerprint` model, `encrypted_template`
  (Laravel `encrypted` cast, APP_KEY), `FingerprintDeviceClientInterface` with
  `MockFingerprintDeviceClient` and a stubbed `HttpBiometricBridgeClient`,
  `FingerprintsRelationManager` (enroll + revoke on Widow/Orphan), and 29 passing
  tests. Only **Widow and Orphan** support fingerprints (Deceased does not).
- **Gaps identified**: No 1:1 verification / 1:N identification / search workflow
  or UI is wired (interface methods exist but are unused); the default
  `HttpBiometricBridgeClient` is a stub that always errors; `encrypted_template`
  uses APP_KEY (no dedicated biometric key); two coordinator-permission tests
  fail because the Coordinator role is not granted `biometrics.enroll`/`view`;
  governance metadata (consent, purpose, verification audit) is minimal.
- **Classification**: **C — APPLICATION-LEVEL COMPLETION REQUIRED** (foundation
  exists; enrollment/verification/search/security workflow needs completion
  before hardware integration).
- **Tests (assessment baseline)**: Biometrics suite — 29 passed / 2 failed (both
  coordinator-permission expectation mismatches).
- **Status**: IN PROGRESS — sub-slices below; PB-NEXT-02B..F not yet started.

### PB-NEXT-02A — Biometric Security / Storage Hardening — IMPLEMENTED / VERIFIED
- **Objective**: separate biometric template encryption from APP_KEY using a
  dedicated `BIOMETRICS_ENCRYPTION_KEY` with a versioned envelope and a safe,
  idempotent legacy migration path.
- **Implemented**:
  - `App\Services\Biometrics\BiometricTemplateCipher` — dedicated-key encrypt /
    decrypt, `biometric:v1:` versioned envelope, legacy APP_KEY read path,
    fail-safe (no silent fallback to APP_KEY for new writes), invalid/missing key
    raises `RuntimeException`.
  - `BeneficiaryFingerprint` model — removed the `encrypted` cast; mutator
    encrypts with the dedicated key, accessor decrypts (plaintext only in-app),
    `encrypted_template` + `decrypted_template` hidden from serialization,
    activity log excludes the template; `key_version` column written.
  - Config `config/biometrics.php` + `BIOMETRICS_ENCRYPTION_KEY` /
    `BIOMETRICS_ENCRYPTION_KEY_VERSION` env; documented in `.env.example`.
  - Migration adds nullable `key_version` (self-versioning envelope retained).
  - `php artisan biometrics:reencrypt-templates` command: dry-run, idempotent,
    failure-safe, no plaintext/ciphertext output, non-zero exit on unresolvable
    failures.
- **Tests**: new `BiometricTemplateEncryptionTest` — 14 passed / 53 assertions
  (new-template encryption, dedicated-key boundary, APP_KEY not the boundary,
  serialization hiding, activity-log exclusion, fail-safe invalid/missing key,
  legacy migration, idempotency, failed-decryption preservation, metadata
  survival, revoked-row preservation, eligibility-state identity survival,
  orphan support).
- **Existing biometric tests**: 29 passed / 2 failed — the 2 failures are the
  **pre-existing Coordinator-permission expectation mismatches**, deferred to
  PB-NEXT-02C and NOT weakened here.
- **Live data**: 5 legacy (APP_KEY) fingerprint rows exist; dry-run confirmed
  (reports 5 legacy, would re-encrypt 5) without altering data. Actual data
  re-encryption is a separate deployment-time step.
- **Status**: IMPLEMENTED / VERIFIED — acceptance criteria 1-12 met (hardware not
  required; no unrelated behavior changed).

### PB-NEXT-02C — Coordinator Biometric Access + Zone-Scoped Enrollment — IMPLEMENTED / VERIFIED
- **Objective**: allow an authorized Coordinator to view + enroll fingerprints for
  beneficiaries in their assigned zone (least privilege), preserving zone
  isolation, Admin authority, PB-NEXT-02A encryption, lifecycle rules, history,
  and audit.
- **Implemented**:
  - `RolesAndPermissionsSeeder` — granted the `coordinator` role the canonical
    `biometrics.view` and `biometrics.enroll` permissions (NOT revoke/verify/
    identify; no broad role bypass).
  - `FingerprintsRelationManager` — added `biometricAccessAllowed($owner)` which
    enforces server-side zone scope: admins/super_admins pass; a coordinator must
    manage the beneficiary's zone (resolved via `beneficiary -> deceased -> zone`
    WITHOUT global scopes). The `enroll` action's `visible()` and server-side
    `abort_unless` both enforce it, so out-of-zone / forged actions cannot create
    a biometric row. The `revoke` action remains gated on `biometrics.revoke`
    (admin-only).
  - Coordinator `WidowResource` / `OrphanResource` — registered the shared
    `FingerprintsRelationManager` so it appears on the coordinator panel for
    in-zone beneficiaries.
- **Tests**: new `BiometricCoordinatorAccessTest` — 18 tests / 85 assertions
  (view/enroll in-zone, view/enroll denial out-of-zone, forged/direct denial,
  no-enroll-permission denial, no-view denial, no revoke/delete, no template
  exposure, admin enroll/revoke preserved, dedicated-key encryption, revoked
  history preservation, mock mode, operator attribution).
- **Result**: The two previously-failing Coordinator biomee tests
  (`BiometricSecurityTest::test_coordinator_cannot_enroll_outside_permitted_zone`
  and `BiometricRelationManagerUiTest::test_coordinator_zone_isolation_behavior_remains_intact`)
  now pass for the correct reason.
- **Tests**: Biometrics suite — 63 passed / 266 assertions / 0 failures.
  Coordinator/zone/widow/orphan regressions — 88 passed / 387 assertions / 0 failures.
- **Status**: IMPLEMENTED / VERIFIED (acceptance criteria 1-18 met; no hardware,
  no verification/identification, no unrelated changes).

### PB-NEXT-02B — Biometric Device Client / Scanner Bridge Completion — IMPLEMENTED / VERIFIED
- **Objective**: complete the real biometric device-client boundary behind the
  existing `FingerprintDeviceClient` abstraction so GOFMIS can communicate safely
  with a supported local fingerprint scanner bridge without coupling to vendor SDKs.
- **Implemented**:
  - Replaced the stub `HttpBiometricBridgeClient` with a robust HTTP client: versioned
    bridge API (`GET /api/v1/health`, `POST /api/v1/fingerprints/capture`),
    PII-minimal capture payload (`finger_position`, `request_id`), response
    validation (template present/non-empty/≤ `max_template_bytes`, numeric quality
    in range, recognized format), bounded/configurable timeouts, isolated bearer
    token, distinct safe exceptions, and no template/token logging. `verify`/
    `identify` remain interface-preserving but throw a clear "not yet available"
    (PB-NEXT-02D owns them).
  - New `FingerprintCaptureResult` value object (canonical result shape).
  - New biometric exception hierarchy under `App\Exceptions\Biometrics`.
  - Harden the container binding: `mock` -> Mock, `http` -> Http, anything else
    fails closed with a clear `RuntimeException` (no silent fallback to mock).
  - Config: `BIOMETRICS_BRIDGE_CONNECT_TIMEOUT`, `BIOMETRICS_BRIDGE_TIMEOUT` (30s),
    `BIOMETRICS_MAX_TEMPLATE_BYTES`, `biometrics.mock.force_low_quality`;
    documented in `.env.example` and `docs/biometric-scanner-bridge.md`.
- **Tests**: new `HttpBiometricBridgeClientTest` — 23 tests / 34 assertions
  (successful capture, endpoint, payload, PII-minimization, token, mapping,
  unavailable/timeout/disconnected/busy/cancelled/poor-quality, malformed/missing/
  empty/oversized/invalid-quality/unsupported-format, no-template-logging,
  no-token-logging, encrypted persistence, mock unaffected). Existing
  `BiometricMockTest` updated to assert the new fail-closed container contract.
- **Result**: Biometrics suite — 86 passed / 298 assertions / 0 failures.
  Coordinator/zone/widow/orphan/NIN regressions — 116 passed / 564 assertions / 0 failures.
- **Boundary**: Application-to-bridge HTTP contract COMPLETE; physical scanner /
  vendor SDK (SecuGen) POC DEFERRED (requires a later POC; no claim of scanner
  certification). No verification/identification, no governance, no lifecycle
  changes, no database migration.
- **Status**: IMPLEMENTED / VERIFIED (acceptance criteria 1-24 met).

### PB-NEXT-02E — Biometric Governance, Purpose & Audit Foundation — IMPLEMENTED / VERIFIED
- **Objective**: add the minimum production-grade governance and audit foundation
  needed for biometric enrollment and future verification/identification.
- **Implemented**:
  - `App\Enums\BiometricOperation` (enrollment / revocation / verify / identify /
    correction) and `App\Enums\BiometricPurpose` (enrollment / identity_verification /
    identification / administrative_correction) — canonical, stable values that
    PB-NEXT-02D can reuse without schema change.
  - `App\Services\Biometrics\BiometricAuditService` — writes structured,
    append-only biometric events into the existing Spatie activity ledger under a
    dedicated `biometric` log name. Records operation, purpose, beneficiary type/id,
    fingerprint id, operator attribution, result, reason, request id, source, and a
    neutral `lawful_basis_reference`. Never stores templates, encrypted templates,
    bridge tokens, or encryption keys.
  - `config/biometrics.governance.lawful_basis_reference` (env
    `BIOMETRICS_LAWFUL_BASIS_REFERENCE`) — a neutral authorisation-basis reference,
    NOT a hard-coded "consent"; actual lawful basis remains for the legal/privacy
    governance process.
  - Wired enrollment `+` admin revocation in `FingerprintsRelationManager` to emit
    governance/audit events after successful persistence.
  - `biometrics.audit.view` permission — granted to admin/super_admin only; NOT to
    coordinators (who keep only `biometrics.view` + `biometrics.enroll`).
  - Docs: `docs/biometric-governance.md`.
- **Tests**: new `BiometricGovernanceTest` — 18 tests / 108 assertions (enrollment +
  revocation audit events, operator/beneficiary/fingerprint attribution, canonical
  operation/purpose, out-of-zone denial creates no success audit, no template/
  encrypted-template/bridge-token/key in audit, append-only from ordinary paths,
  archived/ineligible history preserved, disabled-operator attribution preserved,
  future verify/identify readiness).
- **Result**: Biometrics suite — 104 passed / 406 assertions / 0 failures.
  Audit/coordinator/zone/widow/orphan regressions — 120 passed / 473 assertions / 0 failures.
- **Status**: IMPLEMENTED / VERIFIED (acceptance criteria 1-20 met; no physical
  scanner dependency; no verification/identification implemented).

### PB-NEXT-02D — Biometric Verification / Identification — IMPLEMENTED / VERIFIED
- **Objective**: wire safe application-level 1:1 verification and 1:N
  identification using the device abstraction, PB-NEXT-02A encryption,
  PB-NEXT-02C zone scope, and PB-NEXT-02E audit foundation.
- **Contract classification**: B — minor evolution needed. The former
  `verify(): bool` / `identify(): ?string` collapsed scanner errors / quality
  failures into "no match" and leaked raw DB ids to the bridge. Evolved to explicit
  result objects: `FingerprintVerificationResult` and `FingerprintIdentificationResult`
  with distinct `match` / `no_match` / `error(category)`.
- **Intended authorisation model**: Option A — **Admin/Super Admin only** verification
  and identification (coordinator retains only `biometrics.view` + `biometrics.enroll`).
  No evidence justified broadening biometrics.verify/identify to Coordinators.
- **Implemented**:
  - `BiometricVerificationService` — auth + zone guard, active-only fingerprint,
    server-side template decrypt, device verify, result mapping, audit, and
    `last_verified_at` update on MATCH only.
  - `BiometricIdentificationService` — bounded, active-only, zone-scoped candidate
    pool (`max_candidates`, `max_total_bytes`), opaque candidate ids, no PII to
    bridge, safe match-back, audit; cross-scope/revoked matches are never reported.
  - Mock client: deterministic `verify_outcome` / `identify_outcome` config.
  - HTTP client: `POST /api/v1/fingerprints/verify` and
    `POST /api/v1/fingerprints/identify` with PII-minimal payloads; strict response
    validation; no auto-retry; timeout/error mapping.
  - UI: `Verify Identity` (per-row) and an **Identify Beneficiary** operational
    header action in `FingerprintsRelationManager`, showing distinct MATCH /
    NO_MATCH / ERROR notifications; never display templates.
  - Config: `biometrics.identification.max_candidates` / `max_total_bytes`.
  - **Coordinator permission control**: Coordinator biometric capability remains
    permission-driven and revocable by Super Admin through the existing
    permission-management mechanism (no role-name bypass).
- **Tests**: `BiometricVerificationIdentificationTest` — 24 tests / 65 assertions
  (domain + HTTP + end-to-end Identify Beneficiary acceptance for Widow and Orphan,
  no-match, scanner-error-not-no-match); `BiometricPermissionControlTest` — 8 tests /
  17 assertions (no role-name bypass, grant/revoke/restore, records & audit preserved,
  immediate effect).
- **Result**: Biometrics suite — **148 passed / 506 assertions / 0 failures**;
  coordinator/zone/widow/orphan regressions — **70 passed / 257 assertions / 0 failures**.
- **Status**: **VERIFY IDENTITY (1:1) — COMPLETE; IDENTIFY BENEFICIARY (1:N) —
  COMPLETE; APPLICATION BIOMETRIC MATCHING WORKFLOWS — COMPLETE;
  PHYSICAL SCANNER / SecuGen SDK POC — DEFERRED.**

### Priority 3 — PB-NEXT-03: Newly Approved Phase B Functionality
- Any additional Phase B functionality requested by the user, evaluated
  against this roadmap after PB-NEXT-01/PB-NEXT-02 are shaped.

---

## 9. Recommended Next Implementation Task

**PB-NEXT-01 — Projects Authorization/Lifecycle Investigation** is the
recommended next implementation task:

- It targets the only verifiable failing test remaining in the beneficiary/Phase B
  audit surface.
- It is independent of, and does not disturb, the completed B-13/B-13B work,
  Stock Availability, or Biometrics.

**Do not begin PB-NEXT-01 until approved.**

---

## 10. Test Evidence (Verified Audit)

| Suite / Batch | Result |
|---------------|--------|
| B-13B regression cluster (NinTest, NinUiTest, DeceasedEditUtf8, OrphanEligibilityEdit) | **34 passed / 228 assertions / 0 failures** |
| Beneficiary CSV Import/Export (`BeneficiaryCsvImportExportTest`) | **5 passed / 27 assertions** |
| Audit Batch 1 (B-02/03/04 relation mgrs, Stock Availability, item stock, orphan immutability, B-13B UAT) | **94 passed / 448 assertions / 0 failures** |
| Audit Batch 2 (beneficiary lifecycle, history, ID cards, welfare, education, medicals, loans, projects, finance deactivation) | **284 passed / 997 assertions / 1 failure** |

**Outstanding failure**:
- `AdminProjectOperationalLifecycleTest` — Coordinator → Admin `ViewProject` → **HTTP 403**.
- Classification: unrelated existing Projects-domain authorization/context issue.
PB-NEXT-02F — IMPLEMENTED / VERIFIED
PB-NEXT-02 — APPLICATION-LEVEL BIOMETRIC FOUNDATION COMPLETE

PHYSICAL SCANNER / VENDOR SDK POC — DEFERRED
