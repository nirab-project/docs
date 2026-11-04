# MKT-01 — Event contract

Date: 2026-11-05. Local source implementation; external platform acceptance is separate.

## Canonical envelope

```json
{"schema_version":1,"event":"purchase","event_id":"12df3142-d795-4556-a2a4-0f91be1aa97e","source":"backend","occurred_at":"2026-11-05T09:00:00Z","transaction_id":"booking:12df3142-d795-4556-a2a4-0f91be1aa97e","currency":"SAR","value":115.00}
```

One UUID per occurrence. Retries preserve UUID. Financial events also have a unique SHA-256 semantic key, independent of callback/job/browser retries. Money is the actual booking/package customer charge, inclusive of tax and service fees, after discounts; it is marketing gross collected value, not accounting revenue recognition. Currency is an uppercase ISO code, never a display symbol. No zero-value prepaid visit purchase.

## Sources and transitions

| Flow | Source | Event / rule |
|---|---|---|
| Landing booking CTA | Landing layout/welcome links | landing_cta; not a lead or sale |
| Navigation | Flutter PageRoute observer; Landing initial view | page_view; no history-change second owner; strip query/identifiers |
| Service list / selection / details | Rendered service cards and successful details response | view_item_list / select_item / view_item |
| Banner visible / selected | Visible banner index / navigation handler | view_promotion / select_promotion |
| Cart add / remove / quantity | Successful cart mutation response | add_to_cart / remove_from_cart; never failed API |
| Quote | Successful customer quote request | generate_lead; never quote price as collected revenue |
| Checkout | Approved checkout snapshot | begin_checkout; one event per newly approved snapshot |
| New booking | Persisted Booking | booking_created; cash/transfer pending are not purchases |
| Accepted booking | accepted / ongoing / completed | booking_confirmed; secondary conversion only |
| Verified gateway | verified_at + finalized_at + paid gateway state | payment_captured, allocation to bookings; no unfinalized charge sale |
| Cash / manual collection | Branch and accountant approved PaymentCollection | payment_captured; submit/branch approval alone is insufficient |
| Bank transfer | Approved and reviewed BookingOfflinePayment | payment_captured for outstanding non-wallet share |
| Wallet payment | Customer wallet debit linked to booking | payment_captured; company ledger credit is excluded |
| Wallet top-up / bonus / cashback | Wallet ledger | No purchase; funding is not service revenue |
| Fully collected booking | is_paid plus total trusted receipts >= final charge | purchase once per booking, transaction_id booking:UUID |
| Recurring booking | Aggregate parent charge | One parent purchase; children/visits never repeat parent revenue |
| Visit package / renewal | Package creation backed by subscription wallet debit | purchase once per package; renewal is a new package/payment |
| Consumed prepaid visit / warranty | prepaid_visit / is_warranty | No purchase or new revenue |
| Completed refund | PaymentRefund refunded + completed_at | refund with original purchase transaction_id; each refund UUID once |
| Cancellation | Persisted canceled/cancelled state | booking_cancelled; no refund until money is actually returned |

Partial receipts are secondary payment_captured events; purchase becomes primary only when the full charge is collected. Split bookings have distinct booking transaction IDs and allocated shares of a common gateway charge. Historical financial data without a consent link is not backfilled into advertising. Legacy provider subscription sales are outside customer marketing and remain excluded.

## Data boundary

Allowed: event IDs, internal product/booking transaction references, numeric price/quantity/value, ISO currency, catalog promotion/list IDs, coarse route name, timestamps and controlled attribution fields. Data Layer excludes names, phone, email, access tokens, addresses, coordinates, field answers, gateway references, payment details, arbitrary request maps and raw page URLs. Catalog item names are omitted from exported payloads as an extra minimization measure.

Consent receipt tokens are opaque secrets used only in first-party headers/local storage and an optional trusted customer-link fragment. Click IDs are stored encrypted by the backend and used only with advertising consent. Consent IDs are never matching identifiers for an advertising platform.

## Closure

Contract and source inventory complete. No runtime, Docker, migration, external API or payment performed. Source implementation and static verification continue in MKT-02–08. Manual acceptance: test cash, partial wallet+gateway, split/repeat bookings, package consumption, duplicate callbacks and partial refunds against their real financial ledgers.
