# MKT-08 — Customer Native and optional AdSense

Implemented: marketing starts after Firebase core and after the main UI starts; failures never block launch/login/booking. Every adapter has isolated error handling. Configuration operations have at most two attempts (150 ms between attempts); verified events retain one ID across retries and track SDK acceptance separately per adapter. An SDK accepting an event into its queue is not proof of delivery/attribution. No test device, emulator, mobile build or external event was run.

Firebase Native collection/default consent and automatic screen reporting are disabled in AndroidManifest and iOS Info.plist. The customer consent UI has essential/analytics/advertising, reject optional, save and a persistent preferences control. Receipt is revalidated at launch/resume; expiry disables adapters; iOS ATT is checked independently. ATT is requested only after an advertising choice in the privacy UI. Facebook login reads ATT status and uses Limited Login without requesting an advertising permission at login. Login and all essential flows remain independent of marketing.

Firebase emits standard ecommerce items/events on Android/iOS. Web never instantiates Firebase Analytics; GTM owns its events. Meta App Events and TikTok App Events are Native-only and guarded by master/consent/ATT/build/provider flags. Purchase/refund consumption uses the backend verified event stream, not a success screen. Native financial replay uses per-adapter offered IDs; Native is explicitly excluded from the Web CAPI connectors to avoid incorrect action_source/dedup assumptions.

TikTok uses a new UUID per successful cart addition; eventId is never serviceId. Initialization IDs come from Dart build definitions; empty/placeholder IDs refuse activation. Automatic installs/launch/retention/IAP/enhanced data collection are disabled. SDK tracking is disabled on withdrawal. Each platform can be configured independently; providing iOS IDs is not a prerequisite for the Android pair.

## Owner Native configuration

All Dart flags default false:

| Build setting | Purpose |
|---|---|
| MKT_MOBILE_ENABLED | Explicit Native master |
| MKT_STAGING | True must match server staging; false must match production |
| MKT_FIREBASE_ENABLED | Firebase analytics after independent analytics grant |
| MKT_META_ENABLED | Meta App Events after advertising grant + ATT |
| MKT_META_APP_ID | Numeric app ID; must match scoped Admin app_events_id |
| MKT_TIKTOK_ENABLED | TikTok App Events after advertising grant + ATT |
| MKT_TIKTOK_ANDROID_APP_ID / MKT_TIKTOK_ANDROID_ID | Android SDK pair |
| MKT_TIKTOK_IOS_APP_ID / MKT_TIKTOK_IOS_ID | iOS SDK pair |

Android uses deployment ENV NIRAB_FACEBOOK_APP_ID and NIRAB_FACEBOOK_CLIENT_TOKEN for generated resources and fb{AppID} login scheme. Removed YOUR_FACEBOOK_* placeholder resources. This is Meta's public Native client token, never the backend CAPI access token. iOS Info.plist references Xcode build settings NIRAB_FACEBOOK_APP_ID / NIRAB_FACEBOOK_CLIENT_TOKEN and a matching login scheme. Configure these in the private deployment/Xcode environment, never copy a CAPI token into them. Verify app IDs, bundle IDs, Firebase streams, TikTok URL schemes and SDK account review before enabling flags. Existing TikTok iOS URL registration still needs the owner's confirmed SDK identity.

The supplied ZIP audit stated Customer/Provider iOS sources were absent. The current workspace DOES contain Customer iOS source, so this implementation updates its privacy defaults and configurable Meta identity. That does not establish real iOS readiness: signing, entitlements, ATT behavior, SDK registration, store privacy declarations, Facebook Limited Login and receipt withdrawal still need real-device review. No Provider/Serviceman tracking was added or modified.

## AdSense decision

AdSense remains optional/disabled. No Native AdSense code, no AdMob dependency, no arbitrary ad placement and no fabricated ads.txt publisher entry were added. Admin can store a reviewed publisher ID for the later owner decision; this alone does not install advertising. To activate Landing/Blog monetization, obtain site approval, confirm the publisher's supplied ads.txt record and true hosting root (including reverse-proxy/public-path behavior), approve placements and consent rules, then implement that explicit scope. Never place an external ad over checkout or imply it is a NIRAB service.

## Manual closure still required

Real Android/iOS: reject/analytics-only/advertising/withdraw; ATT denied/authorized and OS settings changed; cold launch after prior grant/expiry; no automatic pre-consent SDK traffic; Firebase DebugView; Meta/TikTok app diagnostics; verified purchase once across callback/SDK retry; no purchase for top-up/prepaid visit. Review native SDK persistence/network behavior on-device before production. External account activation, iOS signing and AdSense approval are not locally verifiable.

Native TikTok SDK ID AND app ID must match the scoped public Admin configuration as well as the build definitions. Reopening preferences is in Account > Privacy settings; no persistent Native overlay covers booking actions.
