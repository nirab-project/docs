# MKT-02 — Consent and privacy

Implemented: independent essential/analytics/advertising controls; essential remains available, optional controls default denied. No personalization purpose is collected separately; advertising grant controls Google's ad storage/user-data/personalization together. Reopening privacy preferences is available on Landing, Flutter Web and Native customer. Old cookie text/boolean never upgrades to a grant.

| Decision | GTM | Analytics events | Advertising / CAPI | Core booking |
|---|---|---|---|---|
| No decision / expired / old policy | Not loaded | Blocked | Blocked | Available |
| Reject optional | Not loaded | Blocked | Blocked | Available |
| Analytics only | Allowed with valid ID | Allowed by GA4 tag consent | Blocked | Available |
| Advertising only | Allowed with valid ID | GA4 tag must be blocked | Allowed per configured provider | Available |
| Both | Allowed | Allowed | Allowed per provider | Available |
| Withdraw | Consent updated immediately | Blocked immediately | Pending backend messages canceled after receipt revocation | Available |
| Receipt persistence fails | Local fail closed; visible retry | Blocked | Server withdrawal retried at next initialization | Available |
| iOS ATT denied | No Web pixel on Native | Firebase analytics if independently allowed | Native Meta/TikTok blocked | Available |

The first-party consent endpoint mints random 256-bit bearer receipts; only their SHA-256 hashes are stored. No client boolean is accepted as financial-event consent. Each grant records policy version, scope, choice time, expiration and independent purpose flags. A preference update revokes the previous receipt and cancels its unsent outbox messages. Old booked events are not reassigned to a new grant. Attribution is encrypted with Laravel's application key and cleared on revocation. Receipt validation is required after app/browser restart. Public configuration exposes only safe IDs/flags.

Landing transfers an opaque receipt to marked, server-generated customer links in the URL fragment; the customer removes it from browser history and validates it with the backend. The bearer does not contain personal data or a financial/account credential. Use HTTPS on both hosts. Do not forward it to arbitrary third-party links. Explicit UTM/click fields are collected only after a purpose grant; raw URLs/referrers/PII are never exported.

Google Consent Mode v2 default/update covers analytics_storage, ad_storage, ad_user_data, ad_personalization. Basic mode: GTM/network is not loaded until at least one permitted purpose. Non-Google tags MUST enforce advertising purpose via GTM additional consent and the `nirab_advertising` variable. Revocation cannot recall already delivered events. Offline withdrawal cannot update the server until connectivity returns; the UI reports failure and keeps local tracking disabled.

Lightweight checks: `node Docs/NIRAB_MARKETING_TRACKING/verify-web.cjs` (fake browser/fetch, no network). PHP lint and limited Dart analyze recorded in DELIVERY.md. Manual follow-up: account GTM consent checks; ATT on real iOS; blocked storage; cross-subdomain transfer; offline withdrawal recovery. Legal approval of this technical policy remains owner work.

Account linkage uses the authenticated first-party customer guard; no client-supplied customer ID is accepted. Guest receipts can be linked on an authenticated customer source request. Scope/environment/policy/expiry are rechecked, and a staging receipt never authorizes production. Optional UTMs returned to the client remain purpose-filtered; advertising click IDs stay server-side.
