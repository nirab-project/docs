# NIRAB — Saudi Payments Integration Implementation Specification
## Tap + Tabby + Tamara via Tap Payments

> **Purpose:** This document is an implementation task specification for an autonomous coding agent working on the NIRAB / Demandium-based project.
>
> **Goal:** Fully integrate Saudi-focused digital payments into the existing PaymentModule architecture so that **Tap**, **Tabby**, and **Tamara** appear as normal configurable payment methods in the admin panel, customer app/web, and provider payment flows, with proper sandbox/live configuration, secure server-side verification, webhook handling, idempotency, and no secrets in Flutter.
>
> **Important architectural decision:**  
> For this implementation, **Tap Payments is the payment processor**.  
> - `tap` → Tap hosted checkout using `src_all`
> - `tabby` → Tap Charges API using `src_tabby.installement`
> - `tamara` → Tap Charges API using `src_tamara`
>
> The code MUST be structured behind a service/adapter abstraction so that direct Tabby/Tamara integrations can be added later without changing the apps or booking/payment orchestration.

---

# 1. Project packages to inspect before editing

The repository/package set contains four relevant codebases:

1. **Admin + Backend**
2. **User app + web**
3. **Provider app**
4. **Serviceman app**

The bulk of implementation belongs in **Admin + Backend**.

The customer and provider Flutter apps already consume payment gateway configuration dynamically and use a WebView/browser payment flow. Avoid adding native payment SDKs unless absolutely required. For this task they are **not required**.

---

# 2. Existing architecture — verified in current project

The existing backend already has a modular payment architecture in:

```text
Modules/PaymentModule
```

The current payment flow is approximately:

```text
Customer/Provider
    ↓
/payment?...payment_method=<gateway>
    ↓
Modules/PaymentModule/Http/Controllers/PaymentController.php
    ↓
Modules/PaymentModule/Traits/Payment.php
    ↓
PaymentRequest row is created
    ↓
gateway-specific /payment/<gateway>/pay?payment_id=<uuid>
    ↓
gateway checkout
    ↓
gateway callback / redirect
    ↓
success hook
    ↓
PaymentResponse
    ↓
booking / add fund / subscription / pay-to-admin is finalized
```

## Existing evidence relevant to Tap

### `Modules/PaymentModule/Library/Constant.php`

The project already contains:

```php
['key' => 'tap', 'value' => 'Tap Payment'],
```

### `Modules/PaymentModule/Traits/Payment.php`

The project already contains:

```php
'tap' => 'payment/tap/pay',
```

but the actual Tap controller/routes are missing in the current code package.

### `Modules/PaymentModule/Database/addon_settings.sql`

A Tap row already exists with a structure similar to:

```json
{
  "gateway": "tap",
  "mode": "test",
  "status": "0",
  "secret_key": "data"
}
```

### `app/Lib/Helpers.php`

Tap is already known as supporting currencies including:

```text
AED
SAR
BHD
KWD
OMR
QAR
```

Therefore the new work should **complete and harden the missing integration**, not create a parallel payment system.

---

# 3. Official payment-provider references

Use the official documentation as the source of truth during implementation.

## Tap

Create Charge:

```text
https://developers.tap.company/reference/create-a-charge
```

Charges / payment sources:

```text
https://developers.tap.company/reference/charges
```

Redirect flow:

```text
https://developers.tap.company/docs/redirect
```

Webhook validation:

```text
https://developers.tap.company/docs/webhook
```

Best practices:

```text
https://developers.tap.company/docs/recommendations-best-practices
```

Tap + Tabby:

```text
https://developers.tap.company/docs/tabby
```

Tap + Tamara:

```text
https://developers.tap.company/docs/tamara
```

### Required Tap source IDs for this implementation

```text
tap    => src_all
tabby  => src_tabby.installement
tamara => src_tamara
```

Do NOT silently rename or “correct” `src_tabby.installement`; use the exact source ID currently documented by Tap.

### Tap Charge endpoint

```http
POST https://api.tap.company/v2/charges/
Authorization: Bearer <secret_key>
Content-Type: application/json
```

### Retrieve Charge

Use the current official Tap endpoint/reference for retrieving a charge by charge ID.

The redirect handler MUST retrieve the charge from Tap server-side before trusting payment success.

---

# 4. Non-negotiable implementation rules

The agent MUST follow all of these rules.

## 4.1 Do not put secret keys in Flutter

Never place any of the following in the User app, Provider app, Serviceman app, JavaScript bundle, HTML source, or public API config response:

```text
Tap secret key
Tap live secret key
Tap test secret key
Merchant private credentials
Webhook verification secrets
```

All secrets remain backend-only.

---

## 4.2 Do not trust query parameters for payment success

A URL such as:

```text
...?flag=success
```

is NOT proof of payment.

A successful flow must be verified server-to-server against Tap.

---

## 4.3 Do not trust redirect alone

Both of these channels must be supported:

```text
Tap redirect
Tap webhook (post.url)
```

A customer may close the browser before redirecting back, while the payment still succeeds.

---

## 4.4 Finalization must be idempotent

Webhook and redirect may arrive at almost the same time.

The success hook MUST execute **once only** per `payment_requests.id`.

This is critical because:

```php
PaymentResponse::success(...)
```

can create a booking.

Never allow duplicate booking creation because both webhook and redirect finalized the same payment.

---

## 4.5 Preserve all existing payment use cases

The new gateway must work with the existing success hooks, not only normal booking checkout.

At minimum preserve/support the existing flows:

```text
digital_payment_success
add_fund_success
pay_to_admin_success
repeat_booking_payment_success
switch_offline_to_digital_payment_success
switch_cod_to_digital_payment_success
subscription_success
```

Do not hard-code the Tap implementation to only `digital_payment_success`.

Use the `PaymentRequest.success_hook` architecture already present.

---

# 5. Target admin-panel behavior

After implementation, under the existing Digital Payment Methods admin page, the following rows must be visible:

```text
Tap
Tabby
Tamara
```

Each must support:

```text
Enable / Disable
Gateway title
Gateway logo
Currency compatibility
```

## Tap settings

Tap must allow separate credentials for Test and Live.

Required credential fields:

```text
secret_key
merchant_id
```

Suggested admin display:

```text
Tap Payment
Status: ON/OFF
Mode: Test / Live

Test:
- Secret Key
- Merchant ID

Live:
- Secret Key
- Merchant ID

Gateway Title
Gateway Logo
```

If the UI remains a “selected mode” editor instead of showing both sections simultaneously, switching mode MUST load/save the matching credential set without overwriting the other set.

---

## Tabby admin settings

Tabby uses Tap credentials in this version.

Do not ask the admin to duplicate Tap Secret Key.

Tabby row should contain:

```text
Status
Gateway Title
Gateway Logo
```

Optionally show a read-only hint:

```text
Processed through Tap Payments.
Requires Tabby to be enabled/approved on the Tap merchant account.
```

The mode is inherited from Tap.

---

## Tamara admin settings

Tamara uses Tap credentials in this version.

Do not ask the admin to duplicate Tap Secret Key.

Tamara row should contain:

```text
Status
Gateway Title
Gateway Logo
```

Optionally show:

```text
Processed through Tap Payments.
Requires an active Tamara merchant relationship and activation through Tap.
```

The mode is inherited from Tap.

---

# 6. Fix the existing Test/Live configuration defect

## Current problem

Both of these files currently save the same validated payload into both environments:

```text
Modules/PaymentModule/Http/Controllers/Web/Admin/PaymentConfigController.php
Modules/PaymentModule/Http/Controllers/Api/V1/Admin/PaymentConfigController.php
```

Current pattern:

```php
'live_values' => $validation,
'test_values' => $validation,
```

This destroys proper separation between sandbox and production credentials.

## Required behavior

Test and Live must remain separate.

Conceptually:

```php
if ($mode === 'test') {
    update test_values only;
    preserve live_values;
}

if ($mode === 'live') {
    update live_values only;
    preserve test_values;
}
```

Shared fields such as active status/title/logo may be stored centrally or synchronized deliberately, but private credentials must not be copied between environments.

## Important

Do not couple payment mode to:

```php
env('APP_ENV')
```

Gateway execution should use:

```php
$gatewayConfig->mode
```

to select:

```php
live_values
```

or:

```php
test_values
```

The application environment and payment gateway environment are separate concepts.

---

# 7. Normalize gateway availability logic

There is currently inconsistent gateway activation logic.

For example:

- Customer config checks `is_active`.
- Provider config currently reads credentials based on `APP_ENV` and checks the credential `status`.

Refactor to one consistent rule.

## Canonical availability rule

A gateway is visible when:

```text
addon_settings.settings_type = payment_config
AND addon_settings.is_active = 1
AND currency is supported
AND gateway prerequisites are satisfied
```

The backend gateway service selects Test/Live credentials using the row's `mode`.

Do not use `APP_ENV` to decide which payment credentials to expose/use.

---

# 8. Constants that must be updated

## `app/Lib/Constant.php`

Add the three payment methods to the appropriate arrays.

Ensure these keys exist in both places required by current validation/filtering:

```php
tap
tabby
tamara
```

At minimum update:

```php
PAYMENT_METHODS
DIGITAL_PAYMENT_METHODS
```

Preserve the existing keys and naming conventions.

Suggested labels:

```php
['key' => 'tap', 'value' => 'Tap'],
['key' => 'tabby', 'value' => 'Tabby'],
['key' => 'tamara', 'value' => 'Tamara'],
```

If `tap` is already present elsewhere, do not create duplicate array entries.

---

## `Modules/PaymentModule/Library/Constant.php`

`tap` already exists in `GATEWAYS_PAYMENT_METHODS`.

Add:

```php
['key' => 'tabby', 'value' => 'Tabby'],
['key' => 'tamara', 'value' => 'Tamara'],
```

Do not duplicate `tap`.

---

# 9. Payment link route mapping

## `Modules/PaymentModule/Traits/Payment.php`

The existing map already contains:

```php
'tap' => 'payment/tap/pay',
```

Add:

```php
'tabby' => 'payment/tabby/pay',
'tamara' => 'payment/tamara/pay',
```

The three URLs may be handled by one controller internally.

---

# 10. Database / seeding for addon_settings

Do not rely on manually running the old SQL file on production.

Create a proper Laravel migration or idempotent seeder/update mechanism for the three rows.

The implementation must safely handle all scenarios:

```text
Tap row already exists
Tap row does not exist
Tabby row already exists
Tabby row does not exist
Tamara row already exists
Tamara row does not exist
```

Never overwrite real credentials during migration.

## Recommended default Tap row

Conceptual shape:

```json
{
  "key_name": "tap",
  "settings_type": "payment_config",
  "mode": "test",
  "is_active": 0,
  "test_values": {
    "gateway": "tap",
    "mode": "test",
    "status": 0,
    "secret_key": "",
    "merchant_id": ""
  },
  "live_values": {
    "gateway": "tap",
    "mode": "live",
    "status": 0,
    "secret_key": "",
    "merchant_id": ""
  },
  "additional_data": {
    "gateway_title": "Tap",
    "gateway_image": "",
    "storage": "public"
  }
}
```

Adapt `storage` to the project's actual `getDisk()` behavior.

## Recommended Tabby row

No duplicate Tap credentials:

```json
{
  "key_name": "tabby",
  "settings_type": "payment_config",
  "mode": "test",
  "is_active": 0,
  "test_values": {
    "gateway": "tabby",
    "mode": "test",
    "status": 0
  },
  "live_values": {
    "gateway": "tabby",
    "mode": "live",
    "status": 0
  },
  "additional_data": {
    "gateway_title": "Tabby",
    "gateway_image": "",
    "storage": "public"
  }
}
```

## Recommended Tamara row

Same concept:

```json
{
  "key_name": "tamara",
  "settings_type": "payment_config",
  "mode": "test",
  "is_active": 0,
  "test_values": {
    "gateway": "tamara",
    "mode": "test",
    "status": 0
  },
  "live_values": {
    "gateway": "tamara",
    "mode": "live",
    "status": 0
  },
  "additional_data": {
    "gateway_title": "Tamara",
    "gateway_image": "",
    "storage": "public"
  }
}
```

---

# 11. Currency support

## `app/Lib/Helpers.php`

Preserve existing Tap supported currencies.

For this Saudi deployment:

```php
'tabby' => [
    'SAR' => 'Saudi Riyal',
],

'tamara' => [
    'SAR' => 'Saudi Riyal',
],
```

If business requirements later enable other officially supported markets, extend only after confirming current provider documentation.

For NIRAB Saudi checkout, SAR is the required first-class currency.

---

# 12. Admin payment configuration controller

Update:

```text
Modules/PaymentModule/Http/Controllers/Web/Admin/PaymentConfigController.php
```

and, if the API endpoint remains used:

```text
Modules/PaymentModule/Http/Controllers/Api/V1/Admin/PaymentConfigController.php
```

## Validation

Tap:

```php
status
mode
secret_key
merchant_id
```

Credentials are required when enabling Tap for the selected mode.

Tabby/Tamara:

```php
status
gateway_title
gateway_image
```

Their enablement must be rejected or clearly blocked if Tap is not configured sufficiently.

Recommended prerequisite check:

```text
Tabby/Tamara cannot be active unless:
- Tap configuration row exists
- selected Tap mode has non-empty secret_key
- selected Tap mode has non-empty merchant_id if required by the implementation
```

Do not validate Tabby/Tamara using fake duplicated keys.

---

# 13. Admin Blade changes

Main files:

```text
Modules/BusinessSettingsModule/Resources/views/admin/configurations/third-party/payment/payment-digital.blade.php

Modules/BusinessSettingsModule/Resources/views/admin/configurations/third-party/partials/offcanvas-edit-digital-payment-method.blade.php

Modules/BusinessSettingsModule/Http/Controllers/Web/Admin/ConfigurationController.php
```

The current page obtains rows dynamically from `addon_settings` restricted by:

```php
DIGITAL_PAYMENT_METHODS
```

Therefore once constants and DB rows are correct, the gateways should be available automatically.

## Required Blade hardening

The current editor loops over `live_values`.

Change it so credentials displayed correspond to the selected gateway mode, not always `live_values`.

For Tap, allow editing the correct environment safely.

For Tabby/Tamara:
- hide credential fields
- do not expose Tap keys
- preferably display “Uses Tap credentials”
- allow title/logo/status

## Gateway-ready logic

Current view determines “Not Configured” by checking `live_values`.

Update it so readiness is based on the **selected mode**.

For `tabby` and `tamara`, readiness should be based on:
- their own active configuration row
- Tap credentials being configured for Tap's selected mode

Do not mark them “ready” merely because their alias row contains `gateway` and `mode`.

---

# 14. Create a dedicated Tap gateway service

Create a backend service, for example:

```text
Modules/PaymentModule/Services/TapGatewayService.php
```

Do not put all API logic into a controller.

Suggested responsibilities:

```php
class TapGatewayService
{
    public function resolveCredentials(): array;
    public function sourceFor(string $gateway): string;
    public function createCharge(PaymentRequest $payment, string $gateway): array;
    public function retrieveCharge(string $chargeId): array;
    public function verifyWebhookHash(array $payload, ?string $hashString): bool;
    public function isSuccessfulCharge(array $charge): bool;
    public function validateChargeAgainstPayment(array $charge, PaymentRequest $payment): void;
    public function normalizeCustomer(PaymentRequest $payment): array;
}
```

Names can differ, but responsibility separation must remain clear.

Use Laravel's HTTP client:

```php
Illuminate\Support\Facades\Http
```

unless an existing project HTTP abstraction is clearly better.

Do not add an unnecessary third-party Tap SDK if the REST API can be integrated cleanly with the existing stack.

---

# 15. Gateway adapter abstraction

Create a lightweight abstraction so source selection is not scattered through controllers.

Example:

```text
Modules/PaymentModule/Contracts/PaymentGatewayAdapter.php
Modules/PaymentModule/Gateways/TapAdapter.php
```

or a simpler internal mapping if adding a formal interface is excessive.

At minimum centralize:

```php
[
    'tap' => 'src_all',
    'tabby' => 'src_tabby.installement',
    'tamara' => 'src_tamara',
]
```

Do not repeat this mapping in multiple controllers/views.

Future direct adapters should be possible:

```text
TapAdapter
TabbyDirectAdapter (future)
TamaraDirectAdapter (future)
```

Do not implement direct adapters in this task unless the current repository already contains them.

---

# 16. Tap Charge request payload

Construct the Tap request from the existing `PaymentRequest`.

Do not trust amount/currency coming from the browser once the PaymentRequest exists.

Use:

```php
$payment->payment_amount
$payment->currency_code
$payment->payer_information
$payment->id
```

## Conceptual payload

```json
{
  "amount": 100.00,
  "currency": "SAR",
  "customer_initiated": true,
  "threeDSecure": true,
  "save_card": false,
  "description": "NIRAB payment",
  "metadata": {
    "payment_request_id": "<uuid>",
    "payment_method": "tabby"
  },
  "reference": {
    "transaction": "<stable reference>",
    "order": "<payment_request_uuid>",
    "idempotent": "<payment_request_uuid + gateway>"
  },
  "customer": {
    "first_name": "...",
    "last_name": "...",
    "email": "...",
    "phone": {
      "country_code": "966",
      "number": "5xxxxxxxx"
    }
  },
  "merchant": {
    "id": "<tap merchant id>"
  },
  "source": {
    "id": "<src_all | src_tabby.installement | src_tamara>"
  },
  "post": {
    "url": "https://<domain>/payment/tap/webhook"
  },
  "redirect": {
    "url": "https://<domain>/payment/tap/redirect?payment_id=<uuid>"
  }
}
```

Use the exact parameter types/current API contract from Tap docs at implementation time.

---

# 17. Reference and idempotency

Tap recommends using:

```text
reference.order
reference.transaction
reference.idempotent
```

Use a stable value derived from the NIRAB `PaymentRequest`.

Recommended:

```text
reference.order = payment_request.id
reference.transaction = stable project reference
reference.idempotent = "nirab:<gateway>:<payment_request.id>"
```

Do not generate a different idempotency value every time the customer reloads `/pay`.

A retry of the same NIRAB PaymentRequest must not cause an accidental duplicate charge.

---

# 18. Customer data / phone normalization

`PaymentRequest.payer_information` is currently generated by:

```text
Modules/PaymentModule/Library/Payer.php
```

and contains:

```json
{
  "name": "...",
  "email": "...",
  "phone": "...",
  "address": "..."
}
```

Create a robust server-side normalization helper.

For Saudi numbers, support common forms:

```text
+9665XXXXXXXX
9665XXXXXXXX
05XXXXXXXX
5XXXXXXXX
```

For a Saudi payment method, normalize to:

```json
{
  "country_code": "966",
  "number": "5XXXXXXXX"
}
```

Do not send the local leading `0` as part of the Tap subscriber number.

Do not silently manufacture an invalid phone. If the payment method requires valid customer phone data and it cannot be normalized, return a user-safe payment error before creating the Tap charge.

Name splitting:
- split payer name safely into first/last
- if only one name exists, use it as first name and keep last name empty or use a safe project convention

Email may be absent in guest flows. Follow current Tap requirements and current project guest behavior. Do not invent fake customer emails unless absolutely required and explicitly justified.

---

# 19. Payment controller

Create:

```text
Modules/PaymentModule/Http/Controllers/TapPaymentController.php
```

One controller can serve all three gateway aliases.

Recommended methods:

```php
pay(Request $request, string $gateway)
redirect(Request $request)
webhook(Request $request)
```

or explicit route methods.

## `pay`

Requirements:

1. Validate:
   ```text
   payment_id is UUID
   gateway is tap/tabby/tamara
   ```

2. Load:
   ```php
   PaymentRequest::where('id', ...)
       ->where('is_paid', 0)
       ->first()
   ```

3. Verify:
   ```text
   payment.payment_method matches route gateway
   gateway is active
   currency supported
   Tap credentials configured
   ```

4. Call Tap Create Charge.

5. Persist Tap charge ID/reference if useful, without marking payment paid.

6. Redirect to:
   ```text
   transaction.url
   ```

7. If Tap returns a synchronous successful response without a redirect URL, handle it through the same verification/finalization service — do not create a special insecure success path.

8. If Tap rejects the request:
   - log sanitized details
   - never log full secret key
   - return normal project payment failure flow

---

# 20. Web routes

Update:

```text
Modules/PaymentModule/Routes/web.php
```

Add controller import and explicit routes.

Example shape:

```php
Route::group(['prefix' => 'payment'], function () {

    Route::get('tap/pay', [TapPaymentController::class, 'payTap']);
    Route::get('tabby/pay', [TapPaymentController::class, 'payTabby']);
    Route::get('tamara/pay', [TapPaymentController::class, 'payTamara']);

    Route::match(['get', 'post'], 'tap/redirect', [TapPaymentController::class, 'redirect'])
        ->name('tap.redirect');

    Route::post('tap/webhook', [TapPaymentController::class, 'webhook'])
        ->name('tap.webhook')
        ->withoutMiddleware([VerifyCsrfToken::class]);
});
```

Exact method naming is flexible.

## CSRF

Only exempt the server-to-server webhook endpoint that requires it.

Do not disable CSRF for unrelated admin/payment routes.

---

# 21. Redirect verification

Tap redirects back after checkout.

Do not treat redirect as success automatically.

The redirect handler must:

1. Obtain the Tap charge ID (`tap_id` or current official equivalent).
2. Load the related local `PaymentRequest`.
3. Retrieve the Charge from Tap server-to-server.
4. Validate:
   ```text
   charge ID
   status
   amount
   currency
   local reference/order/payment request ID
   selected payment source/gateway where available
   ```
5. Only after verification, pass to the shared finalizer.
6. Return the existing project success/failure redirect shape through `Processor::payment_response()`.

Success should continue ending in the current app-recognized URL pattern:

```text
...success...flag=success...
```

Failure/cancel must likewise remain compatible with current User and Provider WebViews.

---

# 22. Webhook verification

Tap's webhook endpoint must be implemented.

## Endpoint

Example:

```text
POST /payment/tap/webhook
```

## Security

Read the `hashstring` header exactly as documented by Tap.

Recalculate the expected HMAC/hash according to the current official Tap webhook documentation.

Use the Tap secret key corresponding to the active payment mode.

Use constant-time comparison where appropriate, e.g.:

```php
hash_equals(...)
```

Do not process an invalid webhook.

Respond with an appropriate 2xx only after the payload is accepted/handled according to the desired retry behavior.

---

# 23. Webhook amount formatting

Tap's webhook hash calculation depends on correctly formatted amount precision.

For SAR:

```text
2 decimal places
```

Example:

```text
100.00
```

Implement currency-aware formatting based on Tap's documented rules rather than naïve float string casting.

Avoid floating-point comparison for money.

Use decimal-safe comparison/string normalization.

---

# 24. Shared secure finalization service

Create one finalization path used by:

```text
redirect
webhook
synchronous Tap success (if any)
```

Suggested service:

```text
Modules/PaymentModule/Services/PaymentFinalizationService.php
```

## Required behavior

Pseudo-code:

```php
DB::transaction(function () use ($paymentId, $charge) {

    $payment = PaymentRequest::whereKey($paymentId)
        ->lockForUpdate()
        ->firstOrFail();

    if ((int) $payment->is_paid === 1) {
        return alreadyFinalizedResult;
    }

    verify amount;
    verify currency;
    verify reference;
    verify successful gateway status;

    $payment->transaction_id = $chargeId;

    // Make transaction ID visible to the existing success hook.
    $payment->save();

    if ($payment->success_hook && function_exists($payment->success_hook)) {
        call_user_func($payment->success_hook, $payment->fresh());
    }

    // Mark paid only after successful business finalization.
    $payment->is_paid = 1;
    $payment->save();
});
```

Adapt carefully to the project's hook behavior.

## Important

Existing gateway controllers often mark `is_paid = 1` BEFORE calling the success hook.

For the new Tap flow, do not repeat that unsafe sequence if it risks leaving a charge marked paid while booking/subscription finalization failed.

The DB transaction should cover:
- payment state update
- existing hook DB writes where possible

If an existing hook creates nested transactions, confirm Laravel's behavior and make the finalization robust.

---

# 25. Do not double-create bookings

The current:

```text
Modules/PaymentModule/Lib/PaymentResponse.php
```

contains booking/subscription handling.

Do not call:

```php
PaymentResponse::success(...)
```

directly in both webhook and redirect without a locking/idempotency guard.

The finalizer must guarantee exactly-once business finalization for a given `payment_requests.id`.

---

# 26. Optional payment state audit table

If implementation becomes cleaner/safer with a dedicated gateway transaction table, create one.

Recommended only if useful:

```text
payment_gateway_transactions
```

Possible fields:

```text
id UUID
payment_request_id UUID unique
gateway
provider_transaction_id unique nullable
provider_status
amount decimal
currency
request_payload sanitized/json nullable
response_payload sanitized/json nullable
finalized_at nullable
created_at
updated_at
```

Never store secret keys.

Never store unnecessary PCI-sensitive card details.

A dedicated table is optional; correct idempotent finalization is mandatory.

---

# 27. Charge status handling

Do not assume every non-failed response is paid.

For Tap Charges, accept success only when the current official status represents completed capture, typically:

```text
CAPTURED
```

Treat pending/initiated/in-progress statuses as not yet paid.

Examples of handling categories:

```text
CAPTURED          => finalize success
INITIATED/PENDING => do not finalize; await retrieval/webhook
DECLINED/FAILED   => failure
CANCELED          => cancel/failure UX
```

Use the current Tap status reference, not guesswork.

---

# 28. Validate charge ownership

Before finalizing, verify at least:

```text
Tap charge amount == PaymentRequest.payment_amount
Tap charge currency == PaymentRequest.currency_code
Tap reference/order maps to PaymentRequest.id
PaymentRequest.payment_method is expected
PaymentRequest is not already paid/finalized
```

Where Tap returns source/channel information, also validate it consistently with the selected alias when practical.

Example:

```text
tabby local method should not accidentally finalize a completely unrelated charge
```

Never finalize by charge ID alone without local-reference checks.

---

# 29. Failure flow

Implement one consistent helper for failure/cancel redirect.

Existing apps recognize URLs from the NIRAB domain containing:

```text
success
fail
cancel
```

and in customer app typically also `flag`.

Preserve compatibility with:

```text
Modules/PaymentModule/Traits/Processor.php
```

and current `payment_response()` behavior.

Do not change the app protocol unless required.

---

# 30. Logging

Use Laravel logs for provider/API errors.

Log:

```text
payment_request_id
gateway alias
Tap charge id
HTTP status
Tap error code/message (sanitized)
verification failure reason
```

Never log:

```text
secret_key
Authorization header
full credentials
sensitive payment instrument data
```

---

# 31. Timeout and HTTP resilience

Tap API requests must have explicit sensible timeout settings.

Example concept:

```php
Http::timeout(20)
    ->connectTimeout(10)
```

Avoid infinite/default hanging behavior.

For charge creation, be careful with automatic retries because duplicate POST retries can create payment duplication if idempotency is not guaranteed.

Only retry safely when the Tap idempotency reference protects the request and behavior is confirmed.

---

# 32. Customer config API

Relevant file:

```text
Modules/CustomerModule/Http/Controllers/Api/V1/Customer/ConfigController.php
```

The customer app receives `payment_gateways` dynamically.

Ensure the three gateways return shape compatible with current model:

```json
{
  "gateway": "tabby",
  "gateway_image_full_path": "...",
  "gateway_title": "Tabby",
  "label": "Tabby"
}
```

Do not return secrets.

Normalize gateway activation logic as described earlier.

---

# 33. Provider config API

Relevant file:

```text
Modules/ProviderManagement/Http/Controllers/Api/V1/Provider/ConfigController.php
```

The provider app also receives payment methods dynamically.

Fix its current environment-selection inconsistency.

Do not choose credentials using:

```php
env('APP_ENV')
```

Gateway visibility should be based on `is_active` and currency support.

Credentials are only consumed server-side by `TapGatewayService`.

---

# 34. Provider subscription / pay-to-admin flows

The Provider app builds a payment URL similar to:

```text
/payment?payment_method=<gateway>&provider_id=...&...&is_pay_to_admin=true
```

The backend then creates a normal PaymentRequest and routes by payment method.

Therefore the new aliases must work automatically through the same payment module.

Test all provider flows that use digital payments, especially:

```text
pay to admin
subscription purchase
subscription renew
subscription shift / plan change
provider registration payment if applicable
```

Do not assume testing a customer booking is enough.

---

# 35. Customer Flutter app

Relevant classes already show the architecture is dynamic:

```text
User app and web/lib/common/models/config_model.dart
User app and web/lib/feature/checkout/view/payment_screen.dart
```

Current `DigitalPaymentMethod` accepts:

```text
gateway
gateway_image
gateway_image_full_path
label
```

The payment WebView follows backend URLs and detects success/fail/cancel from the NIRAB domain.

## Required app work

Ideally **no gateway-specific SDK changes** are needed.

Verify only:

1. Tap, Tabby, Tamara render correctly.
2. Uploaded logos render correctly.
3. Payment URLs open successfully.
4. External schemes opened by the hosted payment page still work through:
   ```dart
   shouldOverrideUrlLoading
   ```
5. NIRAB success/failure redirect is detected after Tap redirect.

Do not hard-code gateway names into the app unless a display-specific exception is required.

---

# 36. Provider Flutter app

Relevant files include:

```text
Provider app/lib/common/model/config_model.dart
Provider app/lib/feature/dashboard/view/payment_screen.dart
Provider app/lib/feature/dashboard/widgets/payment_method_dialog.dart
```

The Provider app already selects a dynamic gateway and builds:

```text
/payment?payment_method=${paymentMethod.gateway}...
```

Therefore new gateways should work without native SDK changes.

Verify:
- titles
- logos
- selection
- WebView success/failure
- subscription/pay-to-admin completion

---

# 37. Serviceman app

Do not integrate Tap/Tabby/Tamara SDKs into the Serviceman app.

The Serviceman app does not need to initiate these digital payments for the current architecture.

Only verify that existing booking/payment status displays remain compatible with:

```text
payment_method = tap/tabby/tamara
is_paid = 0/1
```

If there are UI labels that assume a closed enum, make them robust/dynamic.

---

# 38. Tap vs alias mode behavior

`tabby` and `tamara` are currently aliases processed by Tap.

Therefore they must use the **same active Tap credential mode**.

Example:

```text
Tap.mode = test
Tabby request → Tap test secret
Tamara request → Tap test secret
```

Do not let Tabby say “live” while Tap uses “test”.

If the alias rows still have a `mode` database field due to schema compatibility:
- keep it synchronized with Tap for display only
- or ignore it in execution
- document that Tap mode is authoritative

---

# 39. Enable/disable dependencies

Recommended behavior:

## If Tap is disabled

Two possible interpretations:
1. Tap hosted checkout is disabled, but its credential container remains usable by Tabby/Tamara.
2. Tap is the master processor switch and disabling it disables all aliases.

For this project use the following policy unless product requirements say otherwise:

```text
Tap row "is_active" controls whether Tap appears as a customer-facing method.
Tap credentials may still be used by active Tabby/Tamara.
```

Therefore:
- do not require `tap.is_active == 1` to process Tabby/Tamara
- DO require valid Tap credentials
- Tabby/Tamara each have their own `is_active`

This allows:

```text
Tap   OFF
Tabby ON
Tamara ON
```

while still processing Tabby/Tamara through Tap.

If the implementation chooses a different dependency policy, document it prominently and ensure admin UX is clear.

---

# 40. Gateway titles and Arabic support

The project should allow admin-entered gateway titles such as:

```text
تاب
تابي
تمارا
```

or mixed:

```text
Tap
Tabby - تابي
Tamara - تمارا
```

Do not force English text from the gateway key if `gateway_title` exists.

Customer config currently maps a display label from gateway title. Preserve admin-configurable title.

---

# 41. Hosted checkout language

Tap Create Charge supports language selection/current documented mechanism.

Prefer the current NIRAB/customer language when safe.

At minimum:
- Arabic locale → request Arabic checkout language if Tap supports it
- otherwise English

Do not make language choice block the payment.

---

# 42. Payment page return URLs

Build URLs with Laravel route helpers rather than string concatenation wherever possible.

Production URLs MUST be HTTPS.

Examples:

```text
post.url:
https://domain.example/payment/tap/webhook

redirect.url:
https://domain.example/payment/tap/redirect?payment_id=<uuid>
```

Never use localhost in production values.

Do not expose arbitrary external callback URLs as Tap webhook destinations.

Tap always posts to the NIRAB backend.

---

# 43. External redirect handling

The project already stores:

```text
payment_requests.external_redirect_link
```

After secure payment finalization, use the project's:

```php
Processor::payment_response(...)
```

so web/app callbacks continue to work normally.

Do not redirect Tap directly to arbitrary client callback URLs and bypass NIRAB verification.

Tap should return to the NIRAB backend first.

Correct:

```text
Tap
 ↓
NIRAB /payment/tap/redirect
 ↓
verify charge
 ↓
finalize
 ↓
NIRAB payment_response()
 ↓
client callback / payment-success
```

---

# 44. Admin API compatibility

If the mobile/admin API endpoint:

```text
Modules/PaymentModule/Http/Controllers/Api/V1/Admin/PaymentConfigController.php
```

is active, update its allowed gateway validation to include:

```text
tap
tabby
tamara
```

Do not leave the web panel working while API updates reject the new gateways.

Apply the same Test/Live separation rules there.

---

# 45. Existing published Gateways Addon compatibility

The project checks:

```text
Modules/Gateways/Addon/info.php
```

and changes behavior when an external Gateways Addon is published.

Do not break this mechanism.

Before implementing routes, understand this block in:

```text
Modules/PaymentModule/Routes/web.php
```

The built-in routes are currently inside:

```php
if (!$isPublished) {
    ...
}
```

Decide intentionally where the NIRAB Tap routes belong.

## Requirement

NIRAB Tap/Tabby/Tamara must remain reachable in the deployment configuration actually used by this project.

If an installed/published `Modules/Gateways` addon exists and would shadow/disable the built-in routes:
- inspect it
- avoid route collisions
- either implement there or keep NIRAB routes in a non-conflicting always-loaded location

Do not blindly add routes inside an inactive block.

---

# 46. Do not assume the legacy SQL file is authoritative

The old:

```text
Modules/PaymentModule/Database/addon_settings.sql
```

contains historic gateway rows.

Use current database migrations/seeders for production-safe updates.

Updating the SQL dump alone is insufficient.

---

# 47. Tests to add

Add automated tests where the project's test structure allows.

At minimum create feature/unit coverage for:

## Configuration

```text
Tap test credentials can be saved without overwriting live credentials
Tap live credentials can be saved without overwriting test credentials
Tabby/Tamara appear when active
Inactive methods do not appear
Unsupported currency methods do not appear
No secrets appear in customer/provider config JSON
```

## Source mapping

```text
tap → src_all
tabby → src_tabby.installement
tamara → src_tamara
```

## Charge creation

Mock Tap API and assert:
```text
amount from PaymentRequest
currency from PaymentRequest
correct source
correct local reference
webhook URL
redirect URL
authorization header server-side only
```

## Redirect

```text
fake success query without verified Tap charge does NOT mark paid
CAPTURED verified charge finalizes
amount mismatch rejected
currency mismatch rejected
reference mismatch rejected
```

## Webhook

```text
invalid hash rejected
valid hash accepted
CAPTURED finalizes
duplicate webhook does not run success hook twice
webhook + redirect concurrency does not create duplicate booking
```

## Existing hooks

Test at least:
```text
normal booking
add fund
provider/pay-to-admin or subscription
```

---

# 48. Manual sandbox test matrix

The implementation is not complete until these manual tests pass.

Use Tap sandbox/test credentials.

## Admin

- [ ] Tap appears.
- [ ] Tabby appears.
- [ ] Tamara appears.
- [ ] Tap test secret saves.
- [ ] Tap live secret can be saved separately.
- [ ] Saving Test does not modify Live.
- [ ] Saving Live does not modify Test.
- [ ] Logo upload works for all three.
- [ ] Custom title works.
- [ ] Enable/disable works.
- [ ] Currency warning works.

## Customer config

- [ ] Active gateways returned.
- [ ] Inactive gateways hidden.
- [ ] No secret keys in JSON.
- [ ] Correct title.
- [ ] Correct logo URL.

## Customer app/web

- [ ] Tap checkout opens.
- [ ] Tabby checkout opens with Tabby source.
- [ ] Tamara checkout opens with Tamara source.
- [ ] Successful test returns to NIRAB.
- [ ] Payment finalizes only after server verification.
- [ ] App detects success.
- [ ] Failed payment shows failure.
- [ ] Cancellation is handled.
- [ ] Back/reload does not duplicate charge/booking.

## Provider app

- [ ] Gateway list displays.
- [ ] Pay-to-admin flow works.
- [ ] Subscription payment works where applicable.
- [ ] Provider WebView recognizes success.
- [ ] Provider WebView recognizes failure.

## Webhook

- [ ] Public HTTPS webhook reachable.
- [ ] Invalid signature/hash rejected.
- [ ] Valid webhook accepted.
- [ ] Success can finalize even if browser never returns.
- [ ] Duplicate webhook is idempotent.

---

# 49. Production-readiness checklist

Before switching live:

- [ ] HTTPS production domain is configured.
- [ ] `APP_URL` is production HTTPS URL.
- [ ] Tap live credentials entered.
- [ ] Sandbox key is not used in live mode.
- [ ] Live key is not exposed in logs/client/config API.
- [ ] Tap Merchant ID is correct.
- [ ] Tap account approved for required Saudi methods.
- [ ] Mada/other desired methods are activated if using `src_all`.
- [ ] Tabby merchant/Tap activation completed.
- [ ] Tamara merchant/Tap activation completed.
- [ ] Production webhook URL configured/reachable.
- [ ] Production redirect URL correct.
- [ ] One low-value live transaction is tested per enabled method according to provider requirements/process.
- [ ] Refund/operational process documented separately if required.

---

# 50. Direct Tamara integration — future extension, not current implementation

Do NOT mix direct Tamara into the initial Tap-backed integration.

The adapter architecture should make it easy later.

Official Tamara direct flow uses concepts including:

```text
POST /checkout
order_id
checkout_url
approved webhook
POST /orders/{order_id}/authorise
POST /payments/capture
```

Official docs:

```text
https://docs.tamara.co/reference/createcheckoutsession
https://docs.tamara.co/reference/authoriseorder
https://docs.tamara.co/reference/captureorder
https://docs.tamara.co/reference/getting-started-with-webhooks
```

Direct Tamara has its own lifecycle and should be implemented as a separate adapter in a later task if desired.

Do not partially implement it now.

---

# 51. Direct Tabby integration — future extension

Likewise, do not create a half-complete direct Tabby integration while this task uses Tap as the processor.

Keep application-facing gateway key:

```text
tabby
```

independent of implementation detail so a future:

```text
TabbyDirectAdapter
```

can replace the Tap source without changing Flutter payment selection or `PaymentController` callers.

---

# 52. Recommended code organization

Suggested final structure:

```text
Modules/PaymentModule/
├── Contracts/
│   └── PaymentGatewayAdapter.php               # optional
├── Services/
│   ├── TapGatewayService.php
│   └── PaymentFinalizationService.php
├── Http/Controllers/
│   └── TapPaymentController.php
├── Gateways/
│   └── TapAdapter.php                          # optional if service already acts as adapter
├── Traits/
│   └── Payment.php                             # add tabby/tamara route map
├── Routes/
│   └── web.php
└── Database/Migrations/
    └── <timestamp>_add_saudi_payment_gateways.php
```

Keep code simple. Do not create unnecessary abstractions that obscure the current project architecture.

---

# 53. Example TapGatewayService responsibilities

Pseudo-code only; adapt to codebase style:

```php
final class TapGatewayService
{
    private const SOURCE_MAP = [
        'tap' => 'src_all',
        'tabby' => 'src_tabby.installement',
        'tamara' => 'src_tamara',
    ];

    public function sourceFor(string $gateway): string
    {
        if (!isset(self::SOURCE_MAP[$gateway])) {
            throw new InvalidArgumentException('Unsupported Tap-backed gateway');
        }

        return self::SOURCE_MAP[$gateway];
    }

    public function credentials(): array
    {
        $config = DB::table('addon_settings')
            ->where('key_name', 'tap')
            ->where('settings_type', 'payment_config')
            ->firstOrFail();

        $values = $config->mode === 'live'
            ? json_decode($config->live_values, true)
            : json_decode($config->test_values, true);

        // Validate required fields.
        // Never return these through a public API.
        return $values;
    }
}
```

---

# 54. Example creation route logic

Pseudo-code:

```php
public function pay(Request $request, string $gateway)
{
    $request->validate([
        'payment_id' => ['required', 'uuid'],
    ]);

    abort_unless(in_array($gateway, ['tap', 'tabby', 'tamara'], true), 404);

    $payment = PaymentRequest::whereKey($request->payment_id)
        ->where('is_paid', 0)
        ->firstOrFail();

    abort_unless($payment->payment_method === $gateway, 422);

    $charge = $tap->createCharge($payment, $gateway);

    if (!empty(data_get($charge, 'transaction.url'))) {
        return redirect()->away(data_get($charge, 'transaction.url'));
    }

    // If a final status is returned synchronously:
    // re-verify/retrieve then use the shared finalizer.

    return $this->safeFailureResponse($payment);
}
```

---

# 55. Example redirect logic

Pseudo-code:

```php
public function redirect(Request $request)
{
    $request->validate([
        'payment_id' => ['required', 'uuid'],
        'tap_id' => ['required', 'string'],
    ]);

    $payment = PaymentRequest::findOrFail($request->payment_id);

    $charge = $tap->retrieveCharge($request->tap_id);

    $tap->validateChargeAgainstPayment($charge, $payment);

    if ($tap->isSuccessfulCharge($charge)) {
        $finalizer->finalize($payment->id, $charge);

        return $this->payment_response(
            PaymentRequest::findOrFail($payment->id),
            'success'
        );
    }

    return $this->payment_response($payment, 'fail');
}
```

Do not trust the pseudo-code blindly; reconcile it with the actual Tap redirect parameter names and project response types.

---

# 56. Example webhook logic

Pseudo-code:

```php
public function webhook(Request $request)
{
    $payload = $request->all();
    $hashString = $request->header('hashstring');

    if (!$tap->verifyWebhookHash($payload, $hashString)) {
        return response()->json(['message' => 'Invalid webhook signature'], 401);
    }

    $paymentId = data_get($payload, 'reference.order');

    $payment = PaymentRequest::find($paymentId);

    if (!$payment) {
        return response()->json(['message' => 'Unknown payment'], 404);
    }

    $tap->validateChargeAgainstPayment($payload, $payment);

    if ($tap->isSuccessfulCharge($payload)) {
        $finalizer->finalize($payment->id, $payload);
    }

    return response()->json(['ok' => true]);
}
```

Where practical, retrieve the charge from Tap again for high-assurance final verification, especially if any webhook fields are insufficient.

---

# 57. Backward compatibility

Do not break existing gateways:

```text
Stripe
PayPal
Razorpay
Paystack
Senang Pay
Paytm
Flutterwave
SSLCommerz
etc.
```

Changes to common Test/Live saving logic must be checked against existing gateway forms.

Existing rows may have slightly different `live_values`/`test_values` schemas.

Migrations must be non-destructive.

---

# 58. Coding quality

Follow existing project conventions where reasonable:

```text
Laravel module namespaces
response_formatter
translate()
business_config()
Setting / addon_settings model
Processor trait
PaymentRequest
```

But do not copy insecure/buggy behavior merely for consistency.

Use:
- typed private methods where practical
- centralized constants
- validation
- explicit exceptions/errors
- transactions
- row locks
- sanitized logging

Avoid:
- duplicated source maps
- duplicated credentials
- magic strings throughout controllers
- secrets in request URLs
- client-side final payment decisions

---

# 59. Deliverables expected from the coding agent

The agent must finish with all of the following:

## Code

- [ ] Backend Tap API service.
- [ ] Tap/Tabby/Tamara source mapping.
- [ ] Tap payment controller.
- [ ] Pay routes.
- [ ] Redirect route.
- [ ] Webhook route.
- [ ] Secure webhook verification.
- [ ] Server-side Retrieve Charge verification.
- [ ] Idempotent finalization.
- [ ] Admin gateway rows.
- [ ] Admin validation.
- [ ] Proper separate Test/Live credential persistence.
- [ ] Currency mapping.
- [ ] Customer config exposure.
- [ ] Provider config exposure.
- [ ] No secrets leaked.
- [ ] Migration/seeder.
- [ ] Automated tests where feasible.

## Validation

- [ ] Run PHP syntax/lint checks.
- [ ] Run project test suite or relevant tests.
- [ ] Run Laravel route listing and confirm no collisions.
- [ ] Run migration in a safe test DB.
- [ ] Confirm config/cache behavior.
- [ ] Verify customer Flutter compile/static analysis if changed.
- [ ] Verify provider Flutter compile/static analysis if changed.

## Documentation

Add a project markdown file describing:

```text
how to obtain/configure Tap keys
where to enter Test credentials
where to enter Live credentials
how Tabby/Tamara depend on Tap
webhook URL
redirect URL
sandbox testing procedure
production go-live checklist
```

Do not include real secrets in documentation.

---

# 60. Required final report from the coding agent

When implementation is complete, the agent must return a concise report containing:

```text
1. Files added
2. Files modified
3. Database migrations added
4. Routes added
5. New admin settings
6. Exact payment source mapping
7. How Test/Live credentials now work
8. How webhook verification works
9. How duplicate finalization is prevented
10. Tests executed and results
11. Any remaining manual Tap/Tamara/Tabby merchant-account activation required
12. Exact steps for the owner to enter sandbox credentials and test the first transaction
```

Do not report completion if any of the following are missing:

```text
server-side charge verification
webhook authentication
idempotency
separate Test/Live persistence
customer/provider visibility
sandbox testability
```

---

# 61. Important implementation caveats

## Merchant activation

Code alone cannot guarantee Tabby/Tamara availability.

The actual merchant accounts/payment methods must be approved/enabled by Tap and the respective providers.

The implementation must show a meaningful error if Tap says the source is not enabled for the merchant.

---

## Tap `src_all`

`src_all` displays payment methods enabled for the Merchant ID on Tap's hosted page.

Therefore the visible methods inside the Tap page depend on merchant activation.

---

## Tabby via Tap

Use:

```text
src_tabby.installement
```

and ensure the Tap merchant account has the required Tabby capability.

---

## Tamara via Tap

Use:

```text
src_tamara
```

and ensure merchant prerequisites are completed.

---

# 62. Definition of Done

This task is DONE only when the following end-to-end scenario works:

```text
Admin enters Tap TEST credentials
    ↓
Admin enables Tabby
    ↓
Customer config API returns Tabby
    ↓
Customer selects Tabby
    ↓
NIRAB creates PaymentRequest
    ↓
NIRAB creates Tap Charge with src_tabby.installement
    ↓
Customer is redirected to hosted checkout
    ↓
Tap returns/webhooks payment result
    ↓
NIRAB validates authenticity + amount + currency + reference
    ↓
Exactly one success hook runs
    ↓
Booking/subscription/payment operation is finalized
    ↓
PaymentRequest is marked paid once
    ↓
Customer/Provider WebView reaches existing NIRAB success URL
```

The exact same architecture must work for:

```text
tap    → src_all
tamara → src_tamara
```

No secret key may ever leave the backend.

---

# 63. Agent execution instruction

Proceed autonomously.

Do not stop after analysis or only provide a plan.

Inspect the actual repository before every significant modification and adapt filenames/namespaces to the real project structure.

Prefer modifying the smallest correct set of files.

Do not remove existing gateway support.

Do not overwrite existing production credentials.

Create migrations that are safe to run on an existing installation.

Implement, test, and provide the required completion report.

If provider documentation differs from assumptions in this specification, follow the current official provider documentation and record the difference in the final report.

