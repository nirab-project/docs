# Tap Embedded — Open items

Source publication to GitHub main authorized by the user on 2026-11-17. Runtime acceptance and enablement remain pending.

1. Tap must provide/approve Test Public Keys independently for Web, Android and iOS and confirm the merchant's enabled brands. MADA is not enabled by default.
2. Confirm registration/allowlisting of https://app.nirabclean.com and both native identifiers com.nirabclean.customer with Tap. Confirm the merchant's exact 3DS continuation hosts when using an explicit hosting allowlist.
3. Run manual Sandbox acceptance for each platform and every enabled entry point. Browser layout, Flutter renderer/platform-view attachment, SDK network/CSP behavior, 3DS and real native lifecycle behavior remain untested by explicit user instruction.
4. Run separate Android/iOS builds and device acceptance. The current SDK/runtime compatibility was reviewed in source; no store/web/native build was claimed. The official pinned wrapper has documented local lifecycle/privacy patches requiring normal device acceptance.
5. Complete the hosting CSP based on actual allowed application/Tap requests. The supplied Nginx policy is explicitly REPORT-ONLY, not full enforcement or a PCI certification.
6. Backend PHPUnit checks are authored but blocked by the incomplete local vendor tree (missing symfony/deprecation-contracts/function.php). Run the focused TapEmbeddedPaymentTest and PaymentClosureTest in normal CI/developer dependencies; no full environment install was performed here.
7. If authenticated Tap reference lookup returns no match, multiple matches, an incomplete page, or a provider failure, the attempt deliberately remains initiation_review. An operator must reconcile it with Tap if subsequent retrieval/webhook cannot resolve it; no automatic retry/charge is permitted.
8. Deployment/migration execution and production enablement remain owner-controlled, after source publication and normal deployment review. Keep all new flags OFF until the relevant acceptance is complete. Live approval is separate from Sandbox acceptance.
