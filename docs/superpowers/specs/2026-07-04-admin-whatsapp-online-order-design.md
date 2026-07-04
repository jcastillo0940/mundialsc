# Admin WhatsApp Online Order Bonus Design

## Goal

Allow an admin to credit the 5-point online store bonus for a customer who reported a Super Carnes online purchase by WhatsApp instead of using the client frontend.

The flow must preserve data governance and auditability: every manual credit must record who reported it, who processed it, what Magento data was validated, and why points were awarded.

## Current Behavior

Customers can submit an exact Magento order number from the frontend. The system creates an `online_store_order_claims` row in `pending` status. An admin reviews the claim from `/adminrepus1car/online-order-claims`, approves it, and the system credits 5 points through `WalletService`.

If a customer reports by WhatsApp, there is currently no claim row for the admin to approve.

## Selected Approach

Use the manual WhatsApp flow with explicit report time.

The admin may process the request after the first round-of-16 kickoff, but must enter the date and time when the customer reported the purchase by WhatsApp. The system will only credit points if that WhatsApp report time was before the first round-of-16 kickoff.

This keeps the business rule focused on the customer's timely action while allowing operational review to happen later.

## Backoffice Flow

Add a card at the top of the online order claims page named `Registrar compra reportada por WhatsApp`.

The form will collect:

- Customer document number (`cedula` or passport value).
- Magento order number.
- WhatsApp report date/time.
- Required admin notes.

On submit, the backend will:

1. Find exactly one active client by `users.cedula`.
2. Reject if the user is not a client or is disqualified.
3. Query Magento by exact `increment_id`.
4. Reject if Magento does not return the order.
5. Snapshot only sanitized Magento fields already used by the online order flow.
6. Validate the bonus rules.
7. Create an `online_store_order_claims` record with manual source metadata.
8. Approve the claim immediately and credit 5 points through `WalletService`.
9. Redirect back with a success or validation error message.

## Bonus Rules

The manual WhatsApp credit must require:

- Magento order exists.
- Order total is at least USD 25.00.
- Order status is allowed by `MAGENTO_ORDER_BONUS_STATUSES`, currently `processing`, `complete`, and `authorized_payment`.
- Order date is on or after `2026-06-02 00:00:00` America/Panama.
- Order date is before the first `octavos` kickoff.
- WhatsApp report date/time is before the first `octavos` kickoff.
- The Magento order has not already credited points to any user.

The Magento customer email may differ from the app user email. This is allowed in the manual WhatsApp flow, but the claim must show the mismatch for audit review.

## Data Governance

Add structured governance fields to `online_store_order_claims` instead of relying only on free-text notes:

- `source`: examples `client_frontend`, `admin_whatsapp`.
- `source_reported_at`: date/time the customer reported the claim by WhatsApp.
- `created_by_user_id`: admin who created the manual claim, nullable for client-created claims.

Existing client-created claims should remain valid. New client claims can default to `source = client_frontend`.

Only sanitized Magento data may be stored. Do not persist billing address, payment details, card details, full item payloads, or unrelated customer profile data.

## Audit Trail

The claim should store:

- User receiving the points.
- Admin who created the manual claim.
- Admin who reviewed/approved the claim.
- Source `admin_whatsapp`.
- WhatsApp report timestamp.
- Magento order number and sanitized snapshot.
- Review notes.
- Points awarded.

The wallet movement metadata should include:

- Source `admin_whatsapp`.
- Admin id.
- User id.
- Magento order number.
- Magento status.
- Order total.
- Order date.
- Minimum total rule.
- Promotion start and cutoff timestamps.

## Error Handling

The backoffice should show validation errors without creating partial credits.

Expected failures:

- Customer document not found.
- Customer is not a client.
- Customer is disqualified.
- Magento order not found.
- Order total below USD 25.00.
- Order status not allowed.
- Order date outside promo window.
- WhatsApp report time after cutoff.
- Order already credited.
- Admin notes missing.

## Testing

Add feature tests for:

- Manual WhatsApp credit succeeds for a valid order and mismatched Magento email.
- Customer document not found does not create a claim.
- Disqualified user cannot receive credit.
- Order below USD 25.00 fails.
- Order already credited cannot be credited again.
- WhatsApp report timestamp after cutoff fails.
- Wallet movement contains audit metadata.

## Production Notes

This change requires one additive migration for governance columns. It should not alter existing invoice registration behavior or existing client-created online order claims.

After deployment, production should run:

```bash
php artisan migrate --force
php artisan optimize:clear
```
