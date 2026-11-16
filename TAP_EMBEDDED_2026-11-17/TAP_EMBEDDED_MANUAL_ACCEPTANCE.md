# Tap Embedded — Manual Sandbox acceptance

NOT RUN. These are owner acceptance steps after source publication and the normal authorized deployment process. Use Tap Sandbox cards only; no real card/payment is needed for this acceptance.

## Web

- [ ] With embedded OFF, confirm existing Hosted, cash after service, wallet and Tabby/Tamara behavior.
- [ ] Register domain, configure Test Web Public Key, enable only embedded_web_enabled in Test.
- [ ] Approve a new booking's details/price, then confirm: Tap fields appear under the selected Tap option inside NIRAB. Check Arabic/English, desktop and narrow phone width, LTR fields, keyboard focus and dynamic SAR total.
- [ ] Submit a Sandbox card; observe Token-only NIRAB charge body. Verify no PAN/CVV/token/proof in NIRAB logs, analytics or persistent storage. Do not save screenshots/network exports containing card data or proofs.
- [ ] Complete required Sandbox 3DS. Confirm /payment-result queries the backend and shows the confirmed booking number/details; flag=success alone must not show success.
- [ ] Confirm paid + pending_review displays received/processing, not booking success. Check wallet top-up uses its own success wording.
- [ ] Reject a card and cancel/close 3DS. A backend-confirmed terminal state permits a new attempt with a new token/key.
- [ ] Double-click, refresh/back, lose network after submitting, and return late. Confirm one Charge and one booking/wallet credit. Unknown initiation must remain processing, never offer automatic Hosted/new Charge.
- [ ] Delay/duplicate webhook and simulate webhook before the initiation response. Confirm correct signature, server retrieval and single finalization. Reconcile by idempotent reference if both initial response and webhook are lost.
- [ ] Fail SDK loading before Charge: retry card or explicit Hosted fallback only when configured. Switch method/change price and ensure old fields are disposed.
- [ ] Test both authenticated customer and protected guest flow, session expiry, unauthorized/foreign payment IDs, altered proof, changed amount/quote/snapshot and request_key payload conflict.
- [ ] Accept each shared entry separately: new booking, payment dialog, existing unpaid booking, repeat visit, wallet top-up and quotation.
- [ ] Inspect real Sandbox CSP reports/allowed domains, verify required Tap hosts and then approve any enforced hosting policy.
- [ ] Run one policy-compliant Sandbox refund and repeated webhook/return checks; existing financial records must remain consistent.

## Android

- [ ] Obtain Android-specific Test Public Key for com.nirabclean.customer. Keep iOS/Web independent.
- [ ] Perform a local/CI Android build separately; minimum API 24, Java 11, existing internet permission, current AGP. No build was run for this delivery.
- [ ] On a real device, repeat the new-booking, 3DS return/close, refresh/reconnect, rejection and repeat-entry tests.
- [ ] Navigate away/reopen card multiple times; verify subscription cleanup and one SDK instance/tokenization. Confirm no native SDK configuration/payload logs.

## iOS

- [ ] Obtain iOS-specific Test Public Key for com.nirabclean.customer. Keep other platform flags OFF until accepted.
- [ ] Run CocoaPods/Xcode build on macOS separately. Existing iOS minimum is 15.0; pinned Card-iOS is 1.0.8. No iOS build was run.
- [ ] On a real device, repeat the new-booking, 3DS return/close, poor-network, rejection and repeated-navigation tests.
- [ ] Verify keyboard/form layout, lifecycle cleanup and no raw SDK payload logging.

Record platform, entry point, payment ID, booking ID and outcome. Mark acceptance only from actual server/Tap evidence. Do not mark Android/iOS accepted because Web succeeds.
