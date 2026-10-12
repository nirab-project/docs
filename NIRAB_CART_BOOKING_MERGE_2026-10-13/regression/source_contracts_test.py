#!/usr/bin/env python3
"""Source-level regression contract checks for NIRAB. Not a Laravel/Flutter/MySQL integration test."""
from pathlib import Path
import os
import re
import unittest

ROOT = Path(os.getenv('NIRAB_SOURCE_ROOT', str(Path(__file__).resolve().parents[2]/'patched')))
B = ROOT/'01-NIRAB-BACKEND-SOURCE'
C = ROOT/'02-NIRAB-CUSTOMER-SOURCE'
P = ROOT/'03-NIRAB-PROVIDER-SOURCE'
T = ROOT/'04-NIRAB-SERVICEMAN-SOURCE'


def file(p):
    if not p.is_file() and ROOT.name == 'patched':
        p = ROOT.parent/'source'/p.relative_to(ROOT)
    return p.read_text(encoding='utf-8')


class CartBookingContracts(unittest.TestCase):
    def test_private_cart_disables_shared_url_cache(self):
        src=file(C/'lib/feature/cart/repository/cart_repo.dart')
        self.assertIn("source == DataSourceEnum.local",src)
        self.assertIn("ApiResponseModel.withError('private_cart_cache_disabled')",src)
        self.assertIn('return ApiResponseModel.withSuccess(response as T)',src)
        self.assertNotIn('return await fetchData<T>(',src)

    def test_cart_write_refreshes_authenticated_guest_id(self):
        src=file(C/'lib/feature/cart/repository/cart_repo.dart')
        self.assertIn("if (!await _ensureGuestIdentity())",src)
        self.assertIn('cartModel.guestId = Get.find<SplashRepo>().getGuestId()',src)

    def test_cart_reload_is_awaited_not_fire_and_forget(self):
        src=file(C/'lib/feature/cart/controller/cart_controller.dart')
        self.assertIn('await DataSyncHelper.fetchAndSyncData(',src)

    def test_guest_cart_auth_bound_to_server_token(self):
        src=file(B/'Modules/CartModule/Http/Controllers/Api/V1/Customer/CartController.php')
        self.assertIn('PaymentPrincipal::class)->resolve($request)',src)
        self.assertIn('guest_session_required',src)
        self.assertNotIn('Guest::firstOrCreate',src)

    def test_guest_issue_does_not_trust_user_supplied_uuid(self):
        src=file(B/'Modules/PaymentModule/Services/PaymentPrincipal.php')
        self.assertIn('$guest = new Guest();',src)
        self.assertIn("'guest_payment_token'", src)
        self.assertIn('Guest::whereKey',src)

    def test_guest_web_cart_request_waits_for_issued_guest_session(self):
        src=file(C/'lib/main.dart')
        start=src.index('Future<void> _initializeWebCart()')
        end=src.index('@override\n  void initState()', start)
        block=src[start:end]
        self.assertLess(block.index('ensurePaymentGuestSession'),block.index('getCartListFromServer()'))
        self.assertNotIn('const Uuid().v1()',src)

    def test_guest_session_cannot_be_issued_twice_concurrently(self):
        src=file(C/'lib/feature/splash/repository/splash_repo.dart')
        self.assertIn('_guestSessionInFlight ??=',src)
        self.assertIn('updateGuestIdHeader',src)
        api=file(C/'lib/api/remote/client_api.dart')
        self.assertIn('void updateGuestIdHeader(String guestId)',api)
        self.assertIn("'X-Guest-Payment-Token':",api)

    def test_cart_routes_match_explicit_request_verbs(self):
        routes=file(B/'Modules/CartModule/Routes/api/v1/api.php')
        repo=file(C/'lib/feature/cart/repository/cart_repo.dart')
        self.assertRegex(routes,r"Route::delete\('remove/\{id\}'")
        self.assertRegex(routes,r"Route::delete\('data/empty'")
        self.assertRegex(routes,r"Route::put\('update-quantity/\{id\}'")
        self.assertRegex(routes,r"Route::put\('update/provider'")
        self.assertIn('apiClient.deleteData(',repo)
        self.assertIn('apiClient.putData(',repo)
        self.assertNotIn('"_method"',repo)

    def test_no_content_is_not_spoofed_as_network_error(self):
        src=file(C/'lib/api/remote/client_api.dart')
        # dart format may wrap this condition without changing its behavior.
        self.assertRegex(src, r'response0\.statusCode\s*!=\s*204\s*&&\s*response0\.body\s*==\s*null')

    def test_coupon_is_optional_in_stale_cart(self):
        src=file(B/'Modules/CartModule/Http/Controllers/Api/V1/Customer/CartController.php')
        self.assertIn('if (!$coupon?->discount)',src)
        self.assertIn("$cartItem['remaining_uses'] = 0;",src)

    def test_branch_zone_is_validated_when_switching_cart_provider(self):
        src=file(B/'Modules/CartModule/Http/Controllers/Api/V1/Customer/CartController.php')
        self.assertIn("(string) $branch->zone_id === (string) Config::get('zone_id')",src)

    def test_provider_ui_rolls_back_on_failed_switch(self):
        src=file(C/'lib/feature/cart/controller/cart_controller.dart')
        self.assertIn('final previousProvider = _selectedProvider;',src)
        self.assertIn('_selectedProvider = previousProvider;',src)
        self.assertIn('ApiChecker.checkApi(response)',src)

    def test_checkout_guest_token_guards_both_endpoints(self):
        src=file(B/'Modules/BookingModule/Http/Controllers/Api/V1/Customer/BookingController.php')
        self.assertIn('private function assertGuestBookingPrincipal',src)
        self.assertGreaterEqual(src.count('$this->assertGuestBookingPrincipal($request)'),2)

    def test_address_json_shape_is_validated(self):
        src=file(B/'Modules/BookingModule/Http/Controllers/Api/V1/Customer/BookingController.php')
        self.assertIn('!is_array($decoded) || array_is_list($decoded)',src)
        self.assertIn("if (!isset($decoded[$field]) || $decoded[$field] === '')",src)
        self.assertIn("'service_location' => ['required', 'in:customer,provider'",src)

    def test_repeated_booking_respects_explicit_payment_method(self):
        src=file(C/'lib/feature/checkout/widget/proceed_to_checkout_button_widget.dart')
        self.assertRegex(src, r'selectedPaymentMethod\s*==\s*PaymentMethodName\.digitalPayment')
        self.assertRegex(src, r'selectedPaymentMethod\s*==\s*PaymentMethodName\.cos')
        self.assertNotIn('if (scheduleController\n                                            .selectedServiceType ==\n                                        ServiceType.repeat) {\n                                      checkoutController.placeBookingRequest',src)

    def test_repeated_booking_has_single_authoritative_full_visit_check(self):
        src=file(C/'lib/feature/checkout/controller/checkout_controller.dart')
        start=src.index('if (!retrying && serviceType == ServiceType.repeat)')
        end=src.index('if (!retrying &&\n          !await reviewCheckoutPrice',start)
        self.assertNotIn('for (final date in dates)',src[start:end])
        self.assertIn('reviewCheckoutPrice(',src[end:])

    def test_checkout_callback_uses_valid_browser_origin(self):
        src=file(C/'lib/feature/checkout/widget/proceed_to_checkout_button_widget.dart')
        self.assertIn("Uri.base.replace(query: '', fragment: '').toString()",src)
        self.assertNotIn('"$protocol//$hostname:$port$path"',src)

    def test_post_timeout_is_inside_catch_boundary(self):
        src=file(C/'lib/api/remote/client_api.dart')
        start=src.index('Future<Response> postData(')
        end=src.index('Future<Response> postMultipartDataConversation(',start)
        chunk=src[start:end]
        self.assertLess(chunk.index('try {'),chunk.index('final response = await http'))
        self.assertIn('return Response(statusCode: 1, statusText: noInternetMessage)',chunk)

    def test_digital_repeat_has_type_and_dates(self):
        src=file(C/'lib/feature/checkout/widget/proceed_to_checkout_button_widget.dart')
        self.assertIn("'service_type': scheduling.selectedServiceType.name",src)
        self.assertIn("'dates': repeatDates",src)
        self.assertIn('CheckoutHelper.getRepeatBookingScheduleList',src)
        self.assertNotIn("'service_type': 'regular'",src)

    def test_digital_repeat_not_forced_to_one_slot(self):
        src=file(C/'lib/feature/checkout/widget/proceed_to_checkout_button_widget.dart')
        start=src.index('Future<void> _makeDigitalPayment(')
        block=src[start:]
        self.assertIn('final repeat = scheduling.selectedServiceType == ServiceType.repeat;',block)
        # Keep the same contract while accepting formatter-inserted newlines.
        self.assertRegex(block, r'!repeat\s*&&\s*\(scheduling\.scheduleTime\s*==\s*null')

    def test_repeat_gateway_does_not_require_single_schedule(self):
        src=file(B/'Modules/PaymentModule/Http/Controllers/PaymentController.php')
        self.assertIn("'service_schedule' => 'required_unless:service_type,repeat|nullable|date'",src)
        self.assertIn("'dates' => 'nullable|json'",src)

    def test_weekly_repeat_without_time_does_not_force_null(self):
        src=file(C/'lib/helper/checkout_helper.dart')
        self.assertIn('selectedDays == null || selectedDays.isEmpty || time == null',src)
        self.assertNotIn('combineDateAndTime(currentDate, time!)',src)

    def test_booking_confirmation_remains_idempotent(self):
        src=file(B/'Modules/BookingModule/Services/BranchCheckoutService.php')
        self.assertIn('if ($snapshot->result)',src)
        self.assertIn('->lockForUpdate()',src)
        self.assertIn('checkout_cart_changed',src)

    def test_branch_and_technician_keep_booking_line_id(self):
        for p in [P/'lib/feature/booking_details/repo/booking_details_repo.dart',T/'lib/feature/booking_details/repository/booking_details_repo.dart']:
            self.assertIn('"detail_id": cart!.id',file(p))

    def test_booking_data_scope_never_moves_to_marketplace_settlement(self):
        src=file(B/'Modules/BookingModule/Services/BranchCheckoutService.php')
        self.assertIn('assigned_by',src)
        self.assertIn("'branch_selection'",src)

if __name__=='__main__':
    unittest.main(verbosity=2)
