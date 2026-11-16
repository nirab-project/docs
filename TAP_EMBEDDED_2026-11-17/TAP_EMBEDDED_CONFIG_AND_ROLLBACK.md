# Tap Embedded — Configuration and rollback

Source publication to GitHub main authorized on 2026-11-17. No production settings change or migration execution was performed.

## Defaults

| Setting / environment variable | Default |
| --- | --- |
| embedded_web_enabled / TAP_EMBEDDED_WEB_ENABLED | false |
| embedded_android_enabled / TAP_EMBEDDED_ANDROID_ENABLED | false |
| embedded_ios_enabled / TAP_EMBEDDED_IOS_ENABLED | false |
| hosted_fallback_enabled / TAP_HOSTED_FALLBACK_ENABLED | true |
| TAP_CARD_BRANDS | VISA,MASTERCARD |

Platform keys: TAP_TEST_PUBLIC_KEY_WEB, TAP_TEST_PUBLIC_KEY_ANDROID, TAP_TEST_PUBLIC_KEY_IOS and the corresponding TAP_LIVE_PUBLIC_KEY_* variables. Admin fields public_key_web/android/ios are stored inside the selected test_values/live_values and override environment defaults. Keys must have the selected pk_test_/pk_live_ prefix. Existing TAP_PUBLIC_KEY is not reused across platforms/environments.

Existing settings still apply: NIRAB_PAYMENT_MODE, TAP_ENABLED, TAP_TEST_SECRET_KEY, TAP_LIVE_SECRET_KEY, TAP_TEST_MERCHANT_ID, TAP_LIVE_MERCHANT_ID, NIRAB_PAYMENT_RETURN_HOSTS and NIRAB_GATEWAY_CHECKOUT_HOSTS. No secret values belong in these documents or source control. Blank admin key/merchant inputs keep saved values. A mode switch preserves that environment's embedded flags; save the mode first, then edit its flags. Admin values override environment switches, including an explicit OFF.

Tap hashstring validation uses the matching Secret API Key, as specified in official Tap documentation. Legacy TAP_*_WEBHOOK_SECRET/admin webhook-secret fields remain stored for compatibility but do not sign Tap Charge webhooks. Hosted readiness no longer requires a separate webhook secret.

## Sandbox deployment sequence — owner action after source review

1. Deploy backend first with all embedded flags OFF. Apply additive migration 2026_11_17_000001_add_tap_embedded_payment_sessions using the existing deployment process before serving this version of the session APIs. It adds integration (hosted default) and sdk_platform only.
2. Keep NIRAB_PAYMENT_MODE=test for Sandbox. Verify existing encrypted Test Secret Key and merchant ID are present without re-entering or exposing them.
3. Register https://app.nirabclean.com and native identifiers com.nirabclean.customer (both Android and iOS) with Tap; obtain platform-specific public keys.
4. Configure allowed return host app.nirabclean.com. If NIRAB_GATEWAY_CHECKOUT_HOSTS is explicitly overridden, include the required exact Tap hosts as appropriate: checkout.tap.company, checkout.payments.tap.company, sandbox.payments.tap.company and api.tap.company. Preserve the existing Tabby/Tamara entries. Never use wildcard/substring trust or add arbitrary bank/customer-supplied hosts.
5. Deploy the customer application. The official web distribution is loaded lazily from https://tap-sdks.b-cdn.net/card/1.0.2/index.js and its own secure frame is https://sdk.tap.company/v2/card/index.html. NIRAB hosts a DOM container, not an outer iframe. Its pinned native wrapper is packages/card_flutter, with documented upstream attribution and local changes.
6. Existing Nginx config adds a REPORT-ONLY frame-src allowlist for sdk.tap.company, object-src none and base-uri self. This does not block or claim to enforce a complete CSP. For hosting with an enforced CSP, add the exact Tap script/frame/asset hosts to that policy alongside existing application dependencies; inspect Sandbox requests and enforce the completed policy after acceptance. No wildcard sources are needed for the Tap loader.
7. Enable only the Sandbox platform under acceptance. Add MADA to TAP_CARD_BRANDS only after merchant approval. Test all enabled entry points independently before wider rollout.
8. Live is a separate operational decision with different credentials and approvals. Do not turn Live on merely because the source or one platform passed.

## Disable and rollback

- Immediate functional rollback: set the three embedded_*_enabled admin switches OFF for the active environment; retain hosted_fallback_enabled ON. Clear configuration cache through the normal deployment procedure if environment configuration changed. No production operation was performed by the coding agent.
- Already-started Charges must finish verification/webhook/status recovery. Turning flags off must not reset initiation_review, reuse a token or create a Hosted Charge for a pending attempt.
- Before Charge, an owner-authorized session can be abandoned or converted to Hosted by the protected API. Expiry cancels only unstarted attempts.
- For code rollback, disable flags first, let in-flight attempts settle or review them, then deploy the previous application/backend in the normal controlled sequence. Keep payment/session/event records.
- The additive migration can remain in place during rollback. Do not drop payment tables, delete requests or roll back unrelated migrations. Only after all embedded attempts are settled and old code is active may an operator remove the two added session columns if desired; this is not required for rollback.

Unknown Charge recovery uses Tap List Charges by the server's transaction/order references, then Retrieve Charge with full amount/currency/metadata checks. Empty/ambiguous/incomplete responses remain in review. They never authorize another Charge automatically.
