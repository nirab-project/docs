import 'package:nirab_clean/feature/checkout/widget/order_details_section/available_slot_picker.dart';
import 'package:nirab_clean/util/core_export.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';

class _Api extends Fake implements ApiClient {
  final List<Uri> requests = [];
  bool available = true;
  Map<String, dynamic>? bookingPayload;
  Completer<void>? gate;

  @override
  Future<Response> getData(
    String uri, {
    Map<String, dynamic>? query,
    Map<String, String>? headers,
  }) async {
    requests.add(Uri.parse(uri));
    await gate?.future;
    return Response(
      statusCode: 200,
      body: {
        'content': {
          'timezone': 'UTC',
          'provider_id': available ? 'branch' : null,
          'requested_slot_available': available,
          'slots': [
            {'starts_at': '2026-10-15T08:00:00+00:00', 'provider_id': 'branch'},
          ],
        },
      },
    );
  }

  @override
  Future<Response> postData(
    String? uri,
    dynamic body, {
    Map<String, String>? headers,
  }) async {
    bookingPayload = Map<String, dynamic>.from(body as Map);
    return const Response(statusCode: 200);
  }
}

class _CartRepo extends Fake implements CartRepo {}

class _SplashRepo extends Fake implements SplashRepo {
  @override
  String getGuestId() => 'guest';
}

class _Preferences extends Fake implements SharedPreferences {}

void main() {
  late _Api api;
  late ScheduleController controller;
  final address = AddressModel(zoneId: 'zone');

  setUp(() {
    Get.testMode = true;
    api = _Api();
    controller = Get.put(
      ScheduleController(scheduleRepo: ScheduleRepo(apiClient: api)),
    );
    final cart = Get.put(CartController(cartRepo: _CartRepo()));
    cart.cartList.add(
      CartModel(
        'cart',
        'service',
        'standard',
        10,
        1,
        0,
        0,
        0,
        0,
        null,
        null,
        0,
        10,
        Service(),
      ),
    );
  });
  tearDown(Get.reset);

  testWidgets(
    'picker displays wall time and sends the exact API slot for revalidation and booking',
    (tester) async {
      final bookingRepo = CheckoutRepo(
        apiClient: api,
        sharedPreferences: _Preferences(),
      );
      Get.put(CheckOutController(checkoutRepo: bookingRepo));
      Get.put(SplashController(splashRepo: _SplashRepo()));
      await tester.pumpWidget(
        GetMaterialApp(
          home: Builder(
            builder: (context) => Scaffold(
              body: TextButton(
                onPressed: () => showModalBottomSheet(
                  context: context,
                  builder: (_) => AvailableSlotPicker(address: address),
                ),
                child: const Text('open'),
              ),
            ),
          ),
        ),
      );
      await tester.tap(find.text('open'));
      await tester.pumpAndSettle();
      expect(
        api.requests.single.queryParameters.containsKey('starts_at'),
        isFalse,
      );
      expect(find.text('08:00'), findsOneWidget);
      await tester.tap(find.byType(ChoiceChip));
      await tester.pump();
      await tester.tap(find.text('ok'));
      await tester.pumpAndSettle();
      expect(controller.displayScheduleTime, '2026-10-15 08:00:00');
      expect(controller.scheduleTime, '2026-10-15T08:00:00+00:00');
      expect(controller.resolvedProviderId, 'branch');
      expect(
        await controller.validateRealAvailability(
          address,
          controller.displayScheduleTime!,
        ),
        isTrue,
      );
      expect(
        api.requests.last.queryParameters['starts_at'],
        controller.selectedSlotStartsAt,
      );
      expect(api.requests.last.queryParameters['date'], '2026-10-15');
      await bookingRepo.placeBookingRequest(
        paymentMethod: 'cash_after_service',
        serviceAddress: address,
        schedule: controller.scheduleTime,
        zoneId: 'zone',
        isPartial: 0,
        serviceLocation: 'customer',
        serviceType: 'regular',
        providerId: controller.resolvedProviderId,
      );
      expect(
        api.bookingPayload!['service_schedule'],
        api.requests.last.queryParameters['starts_at'],
      );
      expect(api.bookingPayload!['provider_id'], 'branch');
      api.available = false;
      expect(
        await controller.validateRealAvailability(
          address,
          controller.scheduleTime!,
        ),
        isFalse,
      );
      expect(tester.takeException(), isNull);
    },
  );

  test(
    'equivalent offsets are preserved and DateTime fallback includes UTC',
    () async {
      for (final value in [
        '2026-10-15T08:00:00+00:00',
        '2026-10-15T08:00:00.000Z',
        '2026-10-15T11:00:00+03:00',
      ]) {
        await controller.scheduleRepo.getRealAvailability(
          zoneId: 'zone',
          items: [],
          startsAt: DateTime.parse(value),
          originalStartsAt: value,
        );
        expect(api.requests.last.queryParameters['starts_at'], value);
        expect(DateTime.parse(value), DateTime.utc(2026, 10, 15, 8));
      }
      final instant = DateTime(2026, 10, 15, 8);
      await controller.scheduleRepo.getRealAvailability(
        zoneId: 'zone',
        items: [],
        startsAt: instant,
      );
      final wire = api.requests.last.queryParameters['starts_at']!;
      expect(wire.endsWith('Z'), isTrue);
      expect(DateTime.parse(wire).isAtSameMomentAs(instant), isTrue);
    },
  );

  test(
    'changing date, time, service, address or resetting removes the original slot',
    () {
      for (final clear in <void Function()>[
        () => controller.selectedDate = '2026-10-16',
        () => controller.selectedTime = '09:00:00',
        () => controller.updateSelectedDate('2026-10-16 09:00:00'),
        () => controller.updateSelectedBookingType(
          type: ServiceType.repeat,
          shouldUpdate: false,
        ),
        () => controller.invalidateBookingContext(notify: false),
        controller.resetSchedule,
        () => controller.resetScheduleData(shouldUpdate: false),
      ]) {
        controller.buildSchedule(
          scheduleType: ScheduleType.schedule,
          schedule: '2026-10-15 08:00:00',
          originalStartsAt: '2026-10-15T08:00:00+00:00',
          shouldUpdate: false,
        );
        controller.resolvedProviderId = 'branch';
        clear();
        expect(controller.selectedSlotStartsAt, isNull);
        expect(controller.resolvedProviderId, isNull);
      }
    },
  );

  test(
    'an availability response cannot restore a slot after context changes',
    () async {
      controller.buildSchedule(
        scheduleType: ScheduleType.schedule,
        schedule: '2026-10-15 08:00:00',
        originalStartsAt: '2026-10-15T08:00:00+00:00',
        shouldUpdate: false,
      );
      api.gate = Completer<void>();
      final result = controller.validateRealAvailability(
        address,
        controller.scheduleTime!,
      );
      controller.invalidateBookingContext(notify: false);
      api.gate!.complete();
      expect(await result, isFalse);
      expect(controller.selectedSlotStartsAt, isNull);
      expect(controller.resolvedProviderId, isNull);
    },
  );
}
