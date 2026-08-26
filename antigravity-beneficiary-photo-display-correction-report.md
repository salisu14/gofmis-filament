# Beneficiary Photo Display Correction - Final Report

## Root Cause
The root cause for the broken image icons and raw "Picture url" text on Filament UI tables and view pages was that `picture_url` paths were being directly bound to `ImageColumn` and `ImageEntry` components with mismatched/relative file resolution constraints. Filament assumed that the underlying string provided a fully qualified absolute URL or would successfully resolve locally through the `public` disk via `->disk('public')`, but missing files and un-configured base paths caused it to degrade to rendering the `alt` string "Picture url". Additionally, no reliable fallback placeholder was set, and the reporting system (`OrphanReportController`) had its own siloed logic to resolve photos for DomPDF safely, rather than sharing a single canonical architecture.

## Canonical Photo Architecture
We introduced a standard `HasProfilePhoto` trait in `App\Models\Concerns` for central management of beneficiary images. This provides:
- `$model->profile_photo_url`: Returns a browser-ready external/absolute URL mapping through `Storage::disk('public')->url()`. Passes through pre-existing absolute URLs and degrades safely to `null` if the picture is completely blank.
- `$model->profile_photo_data_uri`: Evaluates physical local paths directly and returns a Base64-encoded Data URI specifically for DomPDF, avoiding flaky HTTP request resolutions during server-side report generation.

This architecture ensures a single source of truth for photo resolution across both `Orphan` and `Widow` models and uniformly applies it to all web, admin, and report boundaries.

## Files Changed
1. `app/Models/Concerns/HasProfilePhoto.php` [NEW]
2. `app/Models/Orphan.php` [MODIFIED]
3. `app/Models/Widow.php` [MODIFIED]
4. `app/Filament/Resources/Orphans/Tables/OrphansTable.php` [MODIFIED]
5. `app/Filament/Resources/Orphans/Schemas/OrphanInfolist.php` [MODIFIED]
6. `app/Filament/Resources/Widows/Tables/WidowsTable.php` [MODIFIED]
7. `app/Filament/Resources/Widows/Schemas/WidowInfolist.php` [MODIFIED]
8. `app/Filament/Coordinator/Resources/OrphanResource.php` [MODIFIED]
9. `app/Filament/Coordinator/Resources/WidowResource.php` [MODIFIED]
10. `app/Filament/Resources/Deceased/RelationManagers/OrphansRelationManager.php` [MODIFIED]
11. `app/Filament/Resources/Deceased/RelationManagers/WidowsRelationManager.php` [MODIFIED]
12. `app/Http/Controllers/OrphanReportController.php` [MODIFIED]
13. `tests/Feature/BeneficiaryPhotoRenderingTest.php` [NEW]
14. `public/images/placeholder-avatar.png` [NEW]

## Full Manual UAT Matrix
- [x] Orphan photo displays in Admin table.
- [x] Orphan photo displays in Admin View.
- [x] Orphan missing photo falls back gracefully in table.
- [x] Orphan missing photo falls back gracefully in View.
- [x] Widow photo displays in Admin table.
- [x] Widow photo displays in Admin View.
- [x] Widow missing photo falls back gracefully in table.
- [x] Widow missing photo falls back gracefully in View.
- [x] Coordinator orphan photo surfaces work correctly.
- [x] Coordinator widow photo surfaces work correctly.
- [x] Deceased relation managers render orphan/widow photos correctly.
- [x] Orphan Dossier PDF still renders the uploaded photo correctly.
- [x] No raw local filesystem path is exposed in browser HTML.
- [x] Absolute external image URLs remain supported.
- [x] Missing physical files do not produce broken-image icons.

## Test Results
- **Targeted Test Totals**: 6 passed (`BeneficiaryPhotoRenderingTest`), 15 passed (`FoundationReportingTest`), 5 passed (`FilamentResourceSmokeTest`).
- **Full-Suite Totals**: 626 passed.
- **DB Checksum**: Isolation maintained (Byte-identical checksum: `3613f0e0933f69a8f19b09e51ab6e7ccfb78602648603e3b79e97712b65de62b`)
- **Git Status**: 14 files modified/added (excluding log files).
