# MKT-05 — Configuration

Admin navigation: Marketing / تكاملات التسويق -> `/admin/marketing-integrations`. Only authenticated super-admin may read/edit/retry. CSRF protects writes; provider/staff/customer roles have no access. Changes and retries create an audit entry. No free-form JavaScript, endpoint URLs, SDK code or secrets can be configured in the public-ID form. Notes are public operational notes, never a place for credentials.

Each record is unique by environment (`staging`, `production`), scope (`landing`, `customer_web`, `customer_android`, `customer_ios`) and provider. Default is disabled. Deployment master `MARKETING_ENABLED=false` overrides all rows. Set `MARKETING_ENVIRONMENT=staging` explicitly before testing. Native scopes reject GTM, Google Ads Web, Snap/X Browser Pixels and AdSense. Native Meta/TikTok IDs use app_events_id; Web uses pixel_id. Owner must align Android/iOS bundle IDs and reviewed native SDK resources separately.

| Provider | Owner public values | Secret / separate requirements |
|---|---|---|
| GTM | GTM-... for Landing/customer Web | Account publication permission; import reviewed container |
| GA4 | G-... Web stream; Firebase project Native stream | Link Ads/Firebase in owner account |
| Google Ads | AW-... and conversion_label | Purchase primary conversion, avoid importing same sale twice |
| Meta | Web Pixel/Dataset ID; separate Native App ID | MARKETING_META_TOKEN, optional MARKETING_META_TEST_CODE |
| TikTok | Web Pixel code; separate SDK IDs + bundle IDs | MARKETING_TIKTOK_TOKEN, optional MARKETING_TIKTOK_TEST_CODE |
| Snap | Web Pixel ID | MARKETING_SNAP_TOKEN; matching-data approval before CAPI activation |
| X | Pixel ID; conversion_label = account's purchase event ID | CAPI remains pending Ads/Developer approval |
| AdSense | ca-pub-16digits | Site approval and explicit placement decision; optional, disabled |

Secrets are ENV-only on the backend; never accepted by Admin REST forms, public config, Flutter or Data Layer. The public response is exactly enabled/environment/policy_version/providers with safe IDs; consent response contains only the opaque first-party receipt and its purpose state, never provider credentials. Use the host's secret manager for ENV injection. Laravel APP_KEY encrypts attribution; retain it securely during deploy/rotation.

Server connectors require BOTH the scoped provider flag and deployment-only `MARKETING_META_SERVER_ENABLED`, `MARKETING_TIKTOK_SERVER_ENABLED`, `MARKETING_SNAP_SERVER_ENABLED`; all default false. Staging Meta/TikTok additionally require test event codes. Native additionally needs explicit Dart build flags described in MKT-08. No production request was made and no owner ID was invented.

Diagnostics show status, reason, attempt count and update time without payload/PII. Common reasons: provider_disabled, test_code_required, missing_matching_data, consent_denied, native_web_connector_excluded, unsupported_event, transport_failed, remote_rejected, dead_letter. A manual retry cannot bypass consent or retention. Read actual platform diagnostics before calling an integration live.

Validation: regexes/allowlists per provider, max lengths, fixed scopes/environments, CSRF and super-admin guard; PHP lint. Manual later: prove staff/provider 403, invalid ID 422, master disabled no network, environment isolation, secret absence in network/log output, audit entries and revoked-consent retry refusal. No accounts/settings were changed live.
