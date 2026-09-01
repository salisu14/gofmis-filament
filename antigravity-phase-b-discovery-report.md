# GOF MIS — Phase B Discovery Report & Backlog

---

## 1. Repository & Branch Context
- **Repository**: `/home/salsafh/codes/projects/gof/gofmis-atg`
- **Branch**: `feat/foundation-phase-b`
- **Current HEAD**: `8233aa7 fix(welfare): derive package category from selected item`

---

## 2. Completed Phase B Work
- **B-01**: `Welfare Package Item/Category Source-of-Truth` (COMPLETED & COMMITTED in `8233aa7`).
  - Removed redundant `category_id` column from `welfare_package_items`.
  - Defined dynamic `WelfarePackageItem->category` relationship via `HasOneThrough`.
  - Refactored `WelfarePackageForm` and `ItemsRelationManager` to display read-only derived category.
  - Added full regression test suite `tests/Feature/WelfarePackageItemCategoryInvariantTest.php` (10 tests passed).
  - Full Pest suite: 721 passed / 0 failed.

---

## 3. Discovered Findings & Priority Ranking

### **Finding B-02 (P1 — High Priority)**
- **ID**: `B-02`
- **Module**: Intervention Requests (`app/Filament/Resources/InterventionRequests/RelationManagers/ItemsRelationManager.php`)
- **Finding**: `ItemsRelationManager` declares `protected static ?string $relatedResource = InterventionRequestResource::class;`.
- **Evidence**: `app/Filament/Resources/InterventionRequests/RelationManagers/ItemsRelationManager.php#L26`
- **Business Consequence**: Causes Filament v5 to misroute `CreateAction` and `EditAction` modal forms to parent `InterventionRequestResource::form()` instead of `ItemsRelationManager::form()`, breaking item creation/editing in relation manager modals.
- **Recommended Correction**: Remove `protected static ?string $relatedResource` and ensure `item_name` snapshot remains synchronized with `Item->name`.
- **Priority**: **P1**
- **Confidence**: **Confirmed**

### **Finding B-03 (P2 — Medium Priority)**
- **ID**: `B-03`
- **Module**: Education Fee Invoices (`app/Filament/Resources/EducationFeeInvoices/RelationManagers/PaymentsRelationManager.php`)
- **Finding**: `PaymentsRelationManager` declares `protected static ?string $relatedResource = EducationFeeInvoiceResource::class;`.
- **Evidence**: `app/Filament/Resources/EducationFeeInvoices/RelationManagers/PaymentsRelationManager.php#L28`
- **Business Consequence**: Causes payment action modals in relation manager to attempt resolving parent `EducationFeeInvoiceResource::form()`.
- **Recommended Correction**: Remove `protected static ?string $relatedResource`.
- **Priority**: **P2**
- **Confidence**: **Confirmed**

### **Finding B-04 (P2 — Medium Priority)**
- **ID**: `B-04`
- **Module**: Sponsorship Allocations (`app/Filament/Resources/Sponsorships/RelationManagers/AllocationsRelationManager.php`)
- **Finding**: `AllocationsRelationManager` declares `protected static ?string $relatedResource = SponsorshipResource::class;`.
- **Evidence**: `app/Filament/Resources/Sponsorships/RelationManagers/AllocationsRelationManager.php#L26`
- **Business Consequence**: Potential modal form resolution misrouting when allocating orphans to sponsors.
- **Recommended Correction**: Remove `protected static ?string $relatedResource`.
- **Priority**: **P2**
- **Confidence**: **Confirmed**

---

## 4. Recommended Phase B Instruction 2

### **Instruction Title**: Intervention Request Items RelationManager Form Resolution & Snapshot Hardening

1. **Exact Objective**: Fix `InterventionRequests -> ItemsRelationManager` form modal resolution by removing `$relatedResource` and ensuring robust `item_name` snapshot behavior.
2. **Existing Defect**: Declaring `$relatedResource = InterventionRequestResource::class;` on `ItemsRelationManager` causes Filament v5 to bypass relation manager `form(Schema $schema)`.
3. **Expected Architecture**: Relation manager actions resolve `ItemsRelationManager::form()` directly.
4. **Likely Files**:
   - `app/Filament/Resources/InterventionRequests/RelationManagers/ItemsRelationManager.php`
   - `tests/Feature/InterventionRequestItemRelationManagerTest.php` [NEW]
5. **Required Invariants**:
   - Creating an item via `ItemsRelationManager` renders the correct item modal schema.
   - Parent `InterventionRequest` foreign key is automatically bound without user selection.
   - `item_name` snapshot derives authoritatively from selected `Item`.
6. **Required Tests**:
   - Test relation manager form components do not misroute to parent resource.
   - Test item addition under intervention request works cleanly in Livewire test harness.
7. **Manual UAT Scenario**:
   - Admin/Coordinator goes to `Admin -> Intervention Requests -> Edit specific request -> Requested Items relation manager -> Add Item`.
   - Form modal opens with item dropdown and qty fields instead of parent request fields.
8. **Explicit Non-Goals**:
   - Do not modify `InterventionRequest` approval workflows or stock dispatch logic.
9. **Risk Level**: Low.
10. **Acceptance Criteria**: All 721 existing tests pass + new relation manager test passes.

---

## 5. Ordered Phase B Backlog

```
B-01  [DONE] Welfare Package Item/Category source-of-truth cleanup
B-02  [P1] Intervention Requests ItemsRelationManager relatedResource form resolution fix
B-03  [P2] Education Fee Invoices PaymentsRelationManager relatedResource form resolution fix
B-04  [P2] Sponsorship AllocationsRelationManager relatedResource form resolution fix
```

---

## 6. Next Action for Next Session
Run **Phase B Instruction 2**: Refactor `app/Filament/Resources/InterventionRequests/RelationManagers/ItemsRelationManager.php` to remove `$relatedResource` and add targeted regression tests.
