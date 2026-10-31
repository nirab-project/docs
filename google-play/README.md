# Google Play declarations

`prepare_data_safety.py` builds reviewable CSV drafts for the provider and technician apps from a blank CSV exported by Play Console. Run from the parent workspace containing both app repositories, or supply `--workspace` and `--template` explicitly. The generated drafts are saved under each app's `docs/store-listing/` directory.

These declarations were prepared against the source reviewed on 29 October 2026. Re-audit the source and SDKs before reusing them after feature or dependency changes. Required data covers account and core operation; optional data covers permission-dependent or optional features. Sharing includes service participants, and provider Maps SDK telemetry. All ephemeral-processing flags are false because immediate disposal is not guaranteed.

SDK references:

- https://firebase.google.com/docs/android/play-data-disclosure
- https://developers.google.com/maps/documentation/android-sdk/play-data-disclosure

The public privacy policy and manual account-deletion request page are hosted at https://nirab-clean-apps-privacy.super-goat-3024.chatgpt.site/ . Both apps link to these pages from the account menu. The deletion link starts a request; it does not automatically erase an account.

No reviewer passwords, signing keys, user records, or tester addresses belong in these files.
