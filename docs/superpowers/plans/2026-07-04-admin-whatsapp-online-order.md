# Admin WhatsApp Online Order Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an audited admin flow to credit the 5-point online order bonus for purchases reported by WhatsApp.

**Architecture:** Extend the existing online store order claim flow instead of creating a parallel points path. Add governance columns to `online_store_order_claims`, add one service method for admin-created WhatsApp claims, add one admin route/controller action, and render a compact form on the existing claims page.

**Tech Stack:** Laravel, Blade, Eloquent migrations/models, PHPUnit feature tests, existing Magento API client and WalletService.

---

### Task 1: Governance Columns

**Files:**
- Create: `backend/database/migrations/2026_07_04_090000_add_governance_fields_to_online_store_order_claims_table.php`
- Modify: `backend/app/Models/OnlineStoreOrderClaim.php`

- [ ] Add nullable columns `source`, `source_reported_at`, and `created_by_user_id` to `online_store_order_claims`.
- [ ] Cast `source_reported_at` as datetime.
- [ ] Add a `createdBy` relation to `User`.

### Task 2: Service Method

**Files:**
- Modify: `backend/app/Support/OnlineStoreOrderBonusService.php`

- [ ] Add `approveManualWhatsappClaim(User $targetUser, string $orderNumber, CarbonImmutable $reportedAt, User $admin, string $notes)`.
- [ ] Validate Magento enabled, order number present, notes present, report time before octavos, target user is client and not disqualified.
- [ ] Fetch Magento order, snapshot sanitized data, validate USD 25.00, allowed status, order date range, and no prior credit.
- [ ] Create an approved `OnlineStoreOrderClaim` with `source = admin_whatsapp`, `source_reported_at = $reportedAt`, `created_by_user_id = $admin->id`, `reviewed_by_user_id = $admin->id`, and `points_awarded = 5`.
- [ ] Credit through the existing wallet flow with audit metadata identifying `admin_whatsapp`.

### Task 3: Admin Route And Controller

**Files:**
- Modify: `backend/routes/web.php`
- Modify: `backend/app/Http/Controllers/Admin/BackofficeController.php`

- [ ] Add POST route `admin.online-order-claims.whatsapp.store`.
- [ ] Validate `cedula`, `order_number`, `source_reported_at`, and `review_notes`.
- [ ] Look up `User` by exact `cedula` and `role = client`.
- [ ] Call `approveManualWhatsappClaim`.
- [ ] Redirect back with success or validation errors.

### Task 4: Backoffice UI

**Files:**
- Modify: `backend/resources/views/admin/online-order-claims.blade.php`

- [ ] Add a card above filters named `Registrar compra reportada por WhatsApp`.
- [ ] Include fields for cedula, Magento order number, report date/time, and admin notes.
- [ ] Show old input values after validation failure.
- [ ] Add governance details to each claim row when available: source, report time, and created by admin.

### Task 5: Feature Tests

**Files:**
- Modify: `backend/tests/Feature/OnlineStoreOrderBonusTest.php`

- [ ] Test a valid manual WhatsApp credit with mismatched Magento email.
- [ ] Test unknown document number does not create a claim.
- [ ] Test disqualified user cannot receive manual credit.
- [ ] Test report time after octavos kickoff fails.
- [ ] Test already credited order cannot be credited again.
- [ ] Test wallet movement metadata contains `admin_whatsapp`.

### Task 6: Verification And Git

**Commands:**

```bash
cd backend
php artisan test --filter=OnlineStoreOrderBonusTest
php artisan test
cd ../frontend
npm run build
```

- [ ] Confirm focused backend tests pass.
- [ ] Confirm full backend tests pass.
- [ ] Confirm frontend build still passes.
- [ ] Commit and push the branch.
