# Tap Embedded Card — Implementation report

Date: 2026-11-17 (Asia/Riyadh). Source publication to GitHub main authorized by the user. Runtime enablement remains OFF; no production configuration, migration or payment operation was performed.

Both repositories were clean on main before implementation. `git pull --ff-only origin main` completed before any edit:
- admin-panel: b80db2a → cb38a14.
- user-app-web: 4ce4759 → 6f10764.

Before publication, all five repositories were fetched and confirmed current. The later customer update 20fff2d was pulled and our changes reapplied without conflicts; all eight upstream files and all local payment files were preserved. Provider/Serviceman sources remain unchanged. Customer Dockerfile now copies the pinned local Tap package before flutter pub get so the path dependency is available during image preparation; no image build was run.

## Implemented source

| Phase | Files / decision | Verification / remaining acceptance |
| --- | --- | --- |
| PH-01 | GatewaySettings, nirab_payments config, all three admin save controllers, admin gateways Blade and translations. Separate platform flags/public keys within test/live settings; blank keys and merchant IDs preserve existing values. Mode switches do not copy enabled switches into Live. Hosted readiness does not need a public key. | Reviewed source; PHP syntax passed. All new flags default OFF. Tap hashstring now uses the documented Secret API Key; legacy webhook-secret values are retained but are not the signing key. |
| PH-02 | PaymentSessionController, API routes, additive payment_sessions migration. Optional integration=embedded and sdk_platform; existing hosted contract remains the default. Payment mode and server amount are fixed before SDK setup. Only SHA-256 proof hashes are persisted. | Reviewed ownership, guest proof, request_key/request_hash and expiry. Resume rotates proof only before Charge; started attempts return status, not a fresh launch/token. |
| PH-03 | TapGateway::initiateWithToken, PaymentAttemptGuard, GatewayPaymentService, GatewayCheckoutUrl. One token dispatch uses the existing locked attempt, reference.idempotent, verification and finalization. Unknown responses remain initiation_review. | Source review; no real HTTP/payment test. Signed early webhooks can recover a reference only after authenticated provider retrieval and full matching. Unknown Tap references can also be recovered through documented POST charges/list with transaction/order reference arrays, throttled to once per 20 seconds, followed by Retrieve Charge. Multiple matches or an incomplete list stay in review. |
| PH-04 | feature/payment/tap Web adapter, JS bridge, payment_page, proceed button. A real DOM platform view hosts official Web Card SDK V2 distribution 1.0.2; no outer iframe/WebView. Inline fields appear after the existing booking/price approval action creates the protected session, below the selected Tap option. Form is removed on method/price change and disposal. | Dart analysis and three mocked Node bridge checks passed. No browser/renderer/layout/SDK-network acceptance was run. CSP frame allowlist is report-only pending integration acceptance. |
| PH-05 | Conditional native adapter and official card_flutter 1.0.23 runtime in packages/card_flutter. TapCardViewWidget, Token/Charge scope, SDK validity callbacks, token-only backend contract. Android minSdk is at least 24; Java 11 unchanged; existing iOS 15 unchanged. | Version 1.0.11 in the older guide still references removed Android Registrar APIs. 1.0.23 uses the current embedding. Its source is pinned with MIT license/archive hash and documented lifecycle/privacy patches. Dart analysis passed; no Gradle/Xcode/device build was run. |
| PH-06 | PaymentSessionHelper selects integration for all seven existing callers; one shared Tap panel handles new booking, payment dialog, existing booking, repeat visit, wallet and quotation. | Caller review, preserving existing success hooks and backend debt/quote/snapshot rules. Main booking is inline; other entry points open the same panel as an in-app page. Each scenario still requires separate Sandbox acceptance. |
| PH-07 | PaymentResultScreen, payment_outcome, payment attempt store, payment_screen, route_helper. New web callbacks return to /payment-result; older payment-ID returns are intercepted too. Mobile success/cancel/close routes query the authenticated backend. Recovery stores only payment ID/request mappings, never card/token/proof. | Nine pure Dart outcome checks passed. Paid with pending_review remains processing; success requires verified_at plus successful finalization. Polling is bounded; failed network status never enables an automatic new Charge. |
| PH-08 | Flags, explicit hosted fallback, no fallback after Charge/unknown initiation, exact HTTPS host allowlist, configuration and rollback guide. | Reviewed source. Provider/Serviceman sources, production settings, Dokploy and databases were not changed. |

## API additions

All customer endpoints use existing Customer Bearer / X-Guest-Payment-Token authorization. Session proof alone or a payment UUID alone is insufficient. Customer POSTs do not use cookie-session authentication. Existing CORS policy remains in place; new endpoints are rate limited and no-store.

- GET /api/v1/customer/tap-capability?platform=web|android|ios
- POST /api/v1/customer/payment-sessions (optional integration, sdk_platform)
- POST /api/v1/customer/payment-sessions/{id}/tap-charge — session_proof, token_id
- POST /api/v1/customer/payment-sessions/{id}/tap-resume — authenticated recovery before Charge; hosted recovery also supported
- POST /api/v1/customer/payment-sessions/{id}/hosted-fallback — only before Charge, with proof and server flag
- POST /api/v1/customer/payment-sessions/{id}/abandon — only a server-unstarted attempt
- POST /api/v1/customer/payment-sessions/{id}/events — enumerated tokenization_failed / 3ds_started only
- Existing GET /api/v1/customer/payments/{id} — confirmed state, finalization, booking references, amount/currency, safe next action

Operational events contain payment_id as the correlation key. No SDK callback payload, card data, raw token, or session proof is logged. The SDK public configuration contains only platform public data and the existing payer contact required by Tap.

## Checks actually run

- PHP syntax for changed/new PHP and Blade files: passed.
- Dart formatting of changed/new Dart files: passed.
- Scoped Dart analysis of payment code, helper, routing and changed checkout UI: no errors or warnings; existing route/controller style infos remain. Final analysis of new payment code and the pinned SDK wrapper: no issues.
- node --check and node --test test/tap_card_bridge.test.cjs: 3 passed; no browser.
- dart run tool/check_payment_outcome.dart: 9 passed; no Flutter engine.
- flutter pub get: resolved only the added card_flutter dependency; lockfile changes are limited to it.
- git diff --check: passed.
- PHPUnit tests were added/updated but could NOT run: the local vendor installation is incomplete (missing symfony/deprecation-contracts/function.php); the repository also lacks its PHPUnit runner. The pre-existing local PHPUnit PHAR cannot bootstrap this vendor tree. No full Composer install was attempted.
- No build, browser, server, migration, live endpoint, Sandbox charge, refund, or production action was run.

## Official references checked

- https://developers.tap.company/docs/card-sdk-web-v2
- https://developers.tap.company/docs/flutter-card-sdk
- https://pub.dev/packages/card_flutter/versions/1.0.23
- https://github.com/Tap-Payments/Card-Flutter
- https://developers.tap.company/docs/webhook
- https://developers.tap.company/reference/list-all-charges
- https://developers.tap.company/reference/create-a-charge

## Required delivery status

```text
Backend embedded token charge: IMPLEMENTED
Flutter Web embedded card: IMPLEMENTED
Flutter Android card: IMPLEMENTED
Flutter iOS card: IMPLEMENTED
Payment success/pending/failure UX: IMPLEMENTED
Existing Hosted checkout compatibility: VERIFIED BY CODE
Webhook & idempotency protection: VERIFIED BY CODE
Formatting/static checks: PASSED
Sandbox manual payment: NOT RUN (user acceptance required)
Production enablement: DISABLED
External Tap dependencies: per-platform public keys; app.nirabclean.com domain registration; Android/iOS com.nirabclean.customer registration; merchant card-brand approval; independent Sandbox acceptance
```

IMPLEMENTED describes source delivery, not a completed browser/device/payment acceptance or production certification. See OPEN_ITEMS and MANUAL_ACCEPTANCE for remaining work.
