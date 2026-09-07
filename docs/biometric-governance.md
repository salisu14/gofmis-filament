# Biometric Governance, Purpose & Audit

This document describes the minimal production-grade governance and audit
foundation for biometric enrollment (and future verification/identification) in
GOFMIS.

## Principle: Identity is Separate from Eligibility (PB-NEXT-02F Closure)

A Widow or Orphan undergoing a domain lifecycle transition (e.g., widow remarriage, widow divorce/reactivation, orphan archiving, ageing out) must **never** automatically cause biometric deletion, revocation, or destruction of biometric history. 

Identity and support eligibility are explicitly decoupled concerns:
- **Identity** is a property of the physical human and outlives their eligibility for a specific support program.
- **Eligibility** determines access to domain benefits (ID cards, loans, education).
- An explicit `BiometricOperation::REVOKE` is the *only* authorized mechanism to invalidate a biometric token.
- An archived or remarried beneficiary can still be safely identified (1:N) as the same human via `BiometricIdentificationService`, preventing duplicate identity enrollment under a new name.

## Purpose limitation

Every biometric operation carries an explicit, canonical purpose rather than
arbitrary free text.

Canonical purposes (`App\Enums\BiometricPurpose`):

- `enrollment`
- `identity_verification`
- `identification`
- `administrative_correction`

Canonical operations (`App\Enums\BiometricOperation`):

- `enrollment`
- `revocation`
- `verify` (future, PB-NEXT-02D)
- `identify` (future, PB-NEXT-02D)
- `correction`

## Lawful-basis / authorisation reference

The lawful basis is **not** hard-coded as "consent". It is modelled as a neutral,
configurable reference value (`BIOMETRICS_LAWFUL_BASIS_REFERENCE` /
`config('biometrics.governance.lawful_basis_reference')`) pointing to the
policy/legal-basis identifier that authorises biometric processing. The actual
lawful basis for a real deployment must be approved by the Foundation's
legal/privacy governance process. No legal conclusions are encoded in code.

## Audit persistence

Biometric operations are recorded as **append-only** structured events in the
existing application activity ledger (Spatie Activitylog) under a dedicated
`biometric` log name. This reuses the repository's immutable audit infrastructure
rather than introducing a parallel ledger.

Each event captures safe operational metadata:

- operation type
- purpose
- beneficiary type / id
- fingerprint record id (when applicable)
- operator / user id and attribution
- result / outcome
- reason / status category
- request / correlation id (when available)
- source / client type (when safe)

The audit record **never** contains:

- a fingerprint template
- an encrypted template
- a raw fingerprint image
- a bridge token
- an encryption key
- unnecessary beneficiary PII

## Append-only guarantees

Audit history is historically reliable. Ordinary application paths cannot edit
or delete audit rows. A correction is recorded as a new compensating event rather
than mutating history.

## Enrollment

After a successful enrollment persists, `BiometricAuditService` writes an
`enrollment` event with operator attribution, the resulting fingerprint record,
purpose, and outcome. Failed captures are not excessively logged; only
security-significant operations are recorded.

## Revocation

Admin fingerprint revocation writes a `revocation` event (operation, fingerprint,
operator, reason, timestamp, outcome). Revoking does not physically delete the
template unless an approved retention rule explicitly requires it; the historical
record is preserved.

## Future verification / identification

PB-NEXT-02D wire 1:1 verification and 1:N identification using the same
`BiometricAuditService`, recording `verify` and `identify` operations with their
canonical purposes (`identity_verification`, `identification`) without a schema
redesign.

### 1:1 Verification semantics

Verification means "known beneficiary + known enrolled fingerprint + live
capture → MATCH / NO_MATCH / ERROR". It is not merely "does a stored fingerprint
exist". The flow is:

1. Select a known Widow/Orphan.
2. Choose an active enrolled fingerprint/finger position.
3. Authorise VERIFY (server-side; Admin/Super Admin only in the default model).
4. Decrypt the reference template only inside the server-side service.
5. Invoke `FingerprintDeviceClientInterface::verify()` (live capture / matching).
6. Return a distinct `MATCH`, `NO_MATCH`, or scanner-`ERROR` result.
7. Audit the attempt and, on MATCH only, update `last_verified_at`.

The decrypted template is never exposed to Filament/browser state.

### 1:N Identification semantics

Identification means "unknown person + live capture → compare against an
authorised candidate set → MATCHED beneficiary or NO MATCH".

1. Capture a fingerprint.
2. Build an authorised, active-only candidate pool (`is_active = true`, `revoked_at IS NULL`).
3. Scope the candidate pool by the user's authority:
   - Admin/Super Admin may match across all accessible beneficiaries.
   - A Coordinator (if authorised at all) may only include candidates in their
     managed zone, enforced server-side.
4. Decrypt only required templates server-side.
5. Invoke `FingerprintDeviceClientInterface::identify()` with opaque candidate
   ids (no beneficiary PII).
6. Map a matched candidate back to its beneficiary and audit the result.

Candidate arrays are bounded (`BIOMETRICS_IDENTIFICATION_MAX_CANDIDATES`) and the
total payload is bounded (`BIOMETRICS_IDENTIFICATION_MAX_TOTAL_BYTES`). Exceeding
a limit fails safely rather than silently dropping candidates.

### Result semantics

- VERIFY: `match` / `no_match` / `error` (scanner unavailable, timeout, low
  quality, malformed response).
- IDENTIFY: `match(candidate)` / `no_match` / `error`.

Scanner failure or low quality is never reported as "no match". Match confidence
is preserved as safe metadata only when the device/bridge provides it; it is not
treated as `quality_score` and no threshold is invented in GOFMIS.

### `last_verified_at` semantics

Updated **only** on a successful 1:1 verification MATCH. It is not updated on
NO_MATCH, on scanner failure, or as a side effect of 1:N identification.

### Template confidentiality in verify/identify

Templates are decrypted only in the server-side biometric service boundary. They
are never placed in Livewire state, serialized, logged, audited, or returned to a
browser-facing API. Candidates sent to the local bridge carry only opaque
fingerprint ids plus the template bytes technically required, with no beneficiary
PII.

### Verification/identification audit

Every security-significant attempt is audited via `BiometricAuditService`:

- VERIFY: operation `verify`, purpose `identity_verification`, beneficiary,
  fingerprint, operator, result (`match` / `no_match` / `error`), request id,
  source, lawful-basis reference.
- IDENTIFY: operation `identify`, purpose `identification`, operator, result
  (`match` / `no_match` / `error`), matched beneficiary/fingerprint on match,
  request id, source, lawful-basis reference.

Audit properties never contain templates, candidate template arrays, encrypted
templates, bridge tokens, or encryption keys.

## Retention

No automatic biometric deletion/retention schedule is implemented. Retention is
policy-driven; the audit architecture supports future retention actions but does
not invent a "delete after X years" rule.

## Operator / beneficiary preservation

Historical audit attribution survives operator account deactivation, beneficiary
archival, and fingerprint revocation. Audit references use the project's stable
nullable/reference strategy so historical records remain interpretable.

## Coordinator biometric permission control

Biometric functionality is primarily an Admin/Super Admin responsibility. A small
set of capabilities has been extended to Coordinator users (see PB-NEXT-02C), but
every Coordinator biometric action remains **permission-driven** and revocable by
a Super Admin at any time through the existing roles/permissions administration
mechanism.

- Coordinator operations require the corresponding canonical permission
  (`biometrics.view`, `biometrics.enroll`, and later `biometrics.verify`,
  `biometrics.identify` if ever required) **and** the beneficiary belonging to the
  Coordinator's managed zone **and** normal beneficiary authorization passing.
- There is **no role-name bypass** (e.g. `if ($user->hasRole('coordinator')) { allow }`).
- Revoking a biometric permission takes effect immediately; it never deletes
  existing fingerprint records or biometric audit history. Re-granting restores the
  capability without altering historical records.
- Zone isolation remains an additional boundary even when a permission is granted.

## Identify Beneficiary (1:N) — operational status

Identify Beneficiary is a first-class GOFMIS operational feature, exposed through
an authoritative **Identify Beneficiary** page for Admin/Super Admin users.

1. Staff capture the unknown person's fingerprint.
2. GOFMIS builds the authorised candidate pool server-side (only
   `is_active = true` and `revoked_at IS NULL` fingerprints; Admin/Super Admin may
   search across accessible beneficiaries; a Coordinator, if ever authorised, only
   their managed-zone candidates).
3. Candidates are sent to the bridge with opaque fingerprint ids — no beneficiary
   PII.
4. On MATCH the candidate id is resolved server-side to the Widow or Orphan and
   re-authorisation is checked again before any beneficiary summary is returned.
5. A biometric audit entry (`identify`, purpose `identification`) records the
   outcome.

Result states: `match(candidate)` / `no_match` / `error(scanner_unavailable |
timeout | low_quality | malformed_response | ambiguous)`.

- NO_MATCH → "No matching beneficiary was found." Nothing is created or enrolled.
- Scanner/bridge failure → a distinct safe scanner/capture error, never "no match".
- Limits: `BIOMETRICS_IDENTIFICATION_MAX_CANDIDATES` (default 100) and
  `BIOMETRICS_IDENTIFICATION_MAX_TOTAL_BYTES` (default 1 MiB); exceeding either
  fails safely.

**Status**

- VERIFY IDENTITY (1:1) — COMPLETE.
- IDENTIFY BENEFICIARY (1:N) — COMPLETE (including end-to-end Widow/Orphan
  identification and a dedicated operational UI page).
- APPLICATION BIOMETRIC MATCHING WORKFLOWS — COMPLETE.
- PHYSICAL SCANNER / SecuGen SDK POC — DEFERRED. No biometric matching accuracy or
  certification is claimed by this document.

See also: [biometric-scanner-bridge.md](./biometric-scanner-bridge.md) for the
device/bridge boundary. Physical scanner POC remains deferred.