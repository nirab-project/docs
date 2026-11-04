# MKT-07 — TikTok / Snap / X

Local implementation: separate GTM tags/feature flags and reviewed Web adapters for all three. TikTok Events API and Snap CAPI transports integrate with the independent retry/dedup outbox. No external advertising request was executed. X CAPI intentionally has no speculative transport; it remains pending owner Ads/Developer approval and the approved account API contract.

| Canonical | TikTok Web / Events API 2.0 | Snap Web / CAPI v3 | X Web |
|---|---|---|---|
| page_view | PageView | PAGE_VIEW | Owner's page_view event ID |
| view_item_list | No inferred standard event | LIST_VIEW | Not configured |
| view_item | ViewContent | VIEW_CONTENT | Owner's view_item ID |
| add_to_cart | AddToCart | ADD_CART | Owner's add_to_cart ID |
| begin_checkout | InitiateCheckout | START_CHECKOUT | Owner's begin_checkout ID |
| generate_lead | SubmitForm | CUSTOM_EVENT_1, explicitly assigned Lead in account | Owner's generate_lead ID |
| purchase | Purchase (legacy CompletePayment alias is not mixed in this Web stream) | PURCHASE | Owner's purchase event ID |
| refund | GA4 only; no guessed standard Refund | GA4 only | No inferred adjustment |

Server transports emit verified purchase only; other funnel events remain Web. Refund remains a canonical/GA4 event and produces an explicit unsupported_event server reason, never a made-up standard name. Meta transport is covered in MKT-06. Native SDK names/streams are separate from Web contracts.

Dedup: TikTok Pixel third options `event_id` equals Events API event_id. Meta Pixel eventID equals CAPI event_id. Snap Pixel client_dedup_id equals CAPI event_id; for purchases Pixel transaction_id equals CAPI custom_data.order_id (the booking/package transaction ID). X Web conversion_id is the canonical event UUID; X event IDs such as tw-asset-event are account configuration, never a canonical UUID or a fabricated GA4 mapping. Admin accepts reviewed per-event X IDs; missing mappings do not fire.

TikTok `/open_api/v1.3/event/track/` uses Access-Token header, event_source=web, scoped Pixel ID, original event_time, safe page origin and consented ttclid; successful HTTP AND code=0 required. Staging requires a test_event_code. Meta staging likewise requires its test code. Events older than seven days are refused rather than timestamped as new.

Snap v3 `/v3/{pixel}/events` uses the documented token authentication and validates status=VALID; staging calls `/events/validate` and reports validated_only, not sent. IMPORTANT activation blocker: Snap's documented minimum matching set requires hashed email/phone OR consented client IP+UA; ScCid alone is insufficient. This implementation deliberately collects none of those profile fields. A separately reviewed `SnapMatchingData` backend binding must supply normalized permitted matching fields after policy approval. Without it, return matching_data_contract_required and issue no network request. Do not put matching data in Data Layer, public config, logs or Flutter. Scrub access_token in any future outbound HTTP tracing because Snap authenticates by query; this code never logs request URLs, bodies or exception text.

Web tags require advertising purpose and provider flag each time. Never turn on vendor automatic event/advanced-matching builders outside this contract. Withdrawn consent blocks new bridge events, revokes Meta consent, disables TikTok cookies and cancels pending server messages. Existing third-party scripts and already initiated network requests cannot be recalled. Review actual vendor network behavior with the owner before production, including raw-URL collection: application URLs must not contain customer contact/payment secrets.

Owner validation: use each platform's pixel helper/test events; match purchase event IDs across browser/server; replay callback/job; verify one attributed conversion; inspect retries/rejections and no event under rejection/withdrawal. Snap requires the matching policy extension; X CAPI requires approved documentation/permissions. These are explicit activation dependencies, not silently omitted tasks.

Reviewed references, 2026-11-05:
* https://developers.snap.com/marketing-api/Conversions-API/UsingTheAPI
* https://developers.snap.com/marketing-api/Conversions-API/Parameters
* https://developers.snap.com/marketing-api/Conversions-API/Deduplication
* https://business-api.tiktok.com/gateway/docs/index?doc_id=1771100865818625
* https://business.x.com/en/help/campaign-measurement-and-analytics/conversion-tracking-for-websites
