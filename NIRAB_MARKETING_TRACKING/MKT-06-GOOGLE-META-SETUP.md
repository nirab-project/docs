# MKT-06 — Google and Meta Web

Implemented: reviewed Web provider adapters invoked by GTM; portable container `GTM-NIRAB-WEB.json`; verified backend purchase/refund feed. Import/validation/publication in the owner's GTM account is NOT performed. There are no invented account IDs.

## GTM import and review

1. Create an unpublished staging workspace in the owner's Web container. Import GTM-NIRAB-WEB.json with merge; inspect collisions. The export is a local configuration artifact, not evidence that GTM accepts/publishes it.
2. Review `NIRAB Event ID` Data Layer variable (v2 -> event_id), `NIRAB Canonical Events` custom-event allowlist and six provider tags. Each Custom HTML tag calls only the checked-in `window.nirabMarketing.sendTag(provider, event_id)` adapter. Event payloads are retrieved by UUID, preventing asynchronous GTM processing from mixing two cart events.
3. GA4 requires analytics_storage; advertising tags require ad_storage, ad_user_data, ad_personalization. The adapter independently checks fresh first-party consent, scoped enabled flag, validated ID and per-provider event dedup. No All Pages, History Change, automatic ecommerce scraping or Custom HTML from admin input.
4. Configure staging IDs in Admin for Landing/customer_web. Align the same GA4 property and advertising datasets across these owned surfaces if the business wants a continuous funnel. Keep Native streams separate.
5. GA4 adapter loads Google tag through GTM once, configures send_page_view=false and sends sanitized virtual page_view explicitly. Disable enhanced-measurement automatic pageviews/history changes for this stream. Never add a Firebase Analytics Web observer or another standalone Google installation.
6. Google Ads adapter sends only backend-verified purchase to AW-ID/conversion_label with transaction_id/value/currency/event_id. In Ads choose purchase as primary, booking_created/confirmed/payment_captured as secondary or diagnostic only. Choose either this direct Ads conversion OR the imported GA4 purchase as primary; never count both as separate sales. Review Conversion Linker/cross-domain and GA4 attribution needs in the actual account; do not enable URL passthrough before consent.
7. Meta Web maps PageView, ViewContent, AddToCart, InitiateCheckout, Lead, Purchase. Automatic event configuration is disabled. It uses trackSingle for the configured pixel, `eventID=event_id`; CAPI uses the same backend purchase UUID as event_id. Refund is sent to GA4 only; no invented Meta standard Refund event.
8. Publish only after the owner's staged diagnostics succeed. No platform is active merely because its code/config exists.

## Manual platform acceptance

* Tag Assistant/GTM Preview: before consent no container/pixels; analytics-only triggers GA4 and blocks Meta/Ads; reject then withdraw suppresses new events. Check all four Google consent keys and additional consent in every tag.
* GA4 DebugView: one page_view per initial page/navigation; view list/detail, successful cart delta/removal, approved checkout, quote lead; no failed-cart event. Check ecommerce.items and ISO SAR.
* Cash/transfer pending booking: booking_created only, never purchase. Complete one approved collection or verified gateway receipt; read the backend verified feed and prove one purchase, then replay callback/reload and prove it remains one sale.
* Google Ads diagnostics: conversion ID/label/value/currency and transaction_id match; ensure primary conversion configuration has one sale owner. Check consent-based conversion linker behavior in the account.
* Meta Test Events: use test code for staging CAPI, verify identical eventID/event_id and one deduplicated Purchase for browser/server. Browser SDK queueing does not prove ingestion. Missing matching click data leaves server delivery failed with a reason rather than sending a guessed identity.
* Refund: completed partial refund keeps original transaction_id and actual refunded amount in GA4. Review any Ads conversion adjustment separately; none is silently inferred from cancellation.

All checks above are pending owner accounts. Local validation covers consent gating, payload minimization, stable IDs, PHP syntax and JSON/JS parsing. The Web verified-events acknowledgement means offered to Data Layer (`browser_offered_at`), never confirmed external platform delivery. External dashboards are the acceptance evidence.
