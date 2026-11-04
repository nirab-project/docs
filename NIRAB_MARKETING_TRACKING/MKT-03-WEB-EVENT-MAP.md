# MKT-03 — Web event map

Implemented owner: GTM for Landing + Flutter Web. Firebase Analytics receives Native events only. GTM is loaded only from a validated enabled deployment/admin configuration after purpose consent. Removed both placeholder loader and noscript iframe. HTML/JS never accepts injected service text as executable code; Flutter uses JSON DOM events.

| Event | Hook | Payload |
|---|---|---|
| page_view | Landing initial page; MarketingAnalyticsObserver on Flutter PageRoute push/pop/replace | coarse route; sanitized origin/path; referrer origin only |
| landing_cta | Marked customerUrl links in Landing | event UUID |
| view_item_list | ServiceWidgetVertical visible card wrapper | catalog item ID/quantity; price if known |
| select_item | Service card details/action | catalog item ID/quantity |
| view_item | ServiceDetailsController successful latest response | catalog item ID/quantity |
| view_promotion | Active banner >=50% in screen viewport | promotion ID |
| select_promotion | BannerController navigation handler | promotion ID |
| add_to_cart | CartController.addToCartApi successful server mutation | verified service ID, positive server quantity delta, actual total_cost per unit, ISO currency |
| remove_from_cart | Successful remove, clear, or negative quantity mutation | previous item(s)/delta |
| begin_checkout | Newly approved checkout price snapshot | approved value/currency + selected items |
| generate_lead | Successful custom quote request | event UUID; no request text or location |
| booking_created/confirmed/cancelled/payment_captured/purchase/refund | Authenticated opaque consent receipt + verified-events read/ack | Server canonical event only |

GA4 ecommerce is exported in `ecommerce.items/value/currency/transaction_id` with canonical root fields for vendor adapters. Prior ecommerce state is cleared before each push. Fields are allowlisted. No Firebase Web calls, History Change trigger or automatic GA4 pageviews should be enabled. Routes are intentionally coarse to exclude addresses, personal IDs and query contents.

Cart add returns `Future<bool>` for the whole requested batch and preserves per-mutation success events when a later item fails. Server responds with an additive `marketing_quantity_delta`; unchanged replacement emits no add. The dialog no longer guesses success or exports display currency `$`. Older clients ignore the additive response field. Updated client against older backend safely skips an add event without a verified delta.

UTM/click attribution is purpose-filtered at first receipt creation, encrypted server-side, and shared across the server-generated Landing/customer transition by a validated opaque receipt. No raw campaign URL is put in Data Layer. Preserve this bridge in Flutter deployment: `web/marketing_tracking.js` is the same reviewed file as Laravel `public/assets/marketing/tracking.js` (the lightweight verifier checks equality).

Locally ready: implementation and static Web consent/schema/dedup checks. Owner pending: GTM ID, scoped provider IDs, GTM import/publication and actual network diagnostics. Manual cases: failed/partial cart batch; unchanged replacement; quantity increment/decrement/clear; service/banners in/out of view; navigating back; consent changes; transfer from Landing to customer; no duplicate Firebase Web events.

Navigation dedup uses a random navigation UUID, independent of the exported coarse path: navigating from one service to another still emits one new page_view, while replaying the same navigation ID does not. Only the customer Web surface consumes financial verified-events; Landing cannot acknowledge customer conversion events.
