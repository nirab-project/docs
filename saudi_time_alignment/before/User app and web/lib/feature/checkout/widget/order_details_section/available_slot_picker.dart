import 'package:nirab_clean/common/widgets/nirab/nirab_icon.dart';
import 'package:nirab_clean/util/core_export.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

class AvailableSlotPicker extends StatefulWidget {
  final String? quotationId;
  final String? providerId;
  final List<DateTime>? days;
  final bool updateRegularSchedule;
  final AddressModel? address;
  final List<Map<String, dynamic>>? items;
  final ValueChanged<Map<String, dynamic>>? onSelected;
  const AvailableSlotPicker({
    super.key,
    this.quotationId,
    this.address,
    this.items,
    this.onSelected,
    this.providerId,
    this.days,
    this.updateRegularSchedule = true,
  });

  @override
  State<AvailableSlotPicker> createState() => _AvailableSlotPickerState();
}

class _AvailableSlotPickerState extends State<AvailableSlotPicker> {
  DateTime _day = DateUtils.dateOnly(DateTime.now());
  List<Map<String, dynamic>> _slots = [];
  Map<String, dynamic>? _selected;
  String? _timezone;
  bool _loading = false;
  bool _failed = false;
  String _errorKey = 'slots_load_failed';
  int _contextRevision = 0;
  int _request = 0;

  @override
  void initState() {
    super.initState();
    if (widget.days?.isNotEmpty == true) {
      _day = DateUtils.dateOnly(widget.days!.first);
    }
    _load();
  }

  Future<void> _load() async {
    final request = ++_request;
    _contextRevision = Get.find<ScheduleController>().contextRevision;
    setState(() {
      _loading = true;
      _failed = false;
      _selected = null;
      _slots = [];
    });
    try {
      final cart = Get.find<CartController>().checkoutItems;
      final post = widget.quotationId == null
          ? null
          : Get.find<CheckOutController>().postDetails;
      final address =
          widget.address ??
          post?.serviceAddress ??
          CheckoutHelper.selectedAddressModel(
            selectedAddress: Get.find<LocationController>().selectedAddress,
            pickedAddress: Get.find<LocationController>().getUserAddress(),
            selectedLocationType:
                Get.find<LocationController>().selectedServiceLocationType,
          );
      if (address == null) throw StateError('add_address_first');
      if (post == null && cart.isEmpty && (widget.items?.isEmpty ?? true)) {
        throw StateError('select_your_desired_service');
      }
      final first = post != null || widget.items != null || cart.isEmpty
          ? null
          : cart.first;
      final items =
          widget.items ??
          (post != null
              ? <Map<String, dynamic>>[]
              : cart.map((item) => item.toBookingItem()).toList());
      List<Map<String, dynamic>>? common;
      for (final day in widget.days ?? [_day]) {
        final response = await Get.find<ScheduleController>().scheduleRepo
            .getRealAvailability(
              zoneId: address.zoneId ?? '',
              items: items,
              startsAt: day,
              providerId: widget.providerId ?? first?.provider?.id,
              quotationId: widget.quotationId,
              branchSelection: first?.provider?.id == null ? 'auto' : 'manual',
              latitude: double.tryParse(address.latitude ?? ''),
              longitude: double.tryParse(address.longitude ?? ''),
              validateOnly: false,
            )
            .timeout(const Duration(seconds: 30));
        if (!mounted || request != _request) return;
        if (response.statusCode != 200 ||
            response.body is! Map ||
            response.body['content'] is! Map) {
          throw StateError('availability');
        }
        _timezone = response.body['content']['timezone']?.toString();
        final values = response.body['content']['slots'];
        if (values is! List) throw StateError('availability');
        final slots = values
            .whereType<Map>()
            .map((value) => Map<String, dynamic>.from(value))
            .where(
              (slot) =>
                  DateTime.tryParse(slot['starts_at']?.toString() ?? '') !=
                      null &&
                  slot['provider_id'] != null,
            )
            .toList();
        String key(Map<String, dynamic> slot) =>
            '${slot['starts_at'].toString().substring(11, 19)}|${slot['provider_id']}';
        final keys = slots.map(key).toSet();
        common = common == null
            ? slots
            : common.where((slot) => keys.contains(key(slot))).toList();
        if (common.isEmpty) break;
      }
      if (mounted && request == _request) setState(() => _slots = common ?? []);
    } catch (error) {
      if (mounted && request == _request) {
        setState(() {
          _failed = true;
          _errorKey =
              error is StateError &&
                  [
                    'add_address_first',
                    'select_your_desired_service',
                  ].contains(error.message)
              ? error.message
              : 'slots_load_failed';
        });
      }
    } finally {
      if (mounted && request == _request) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Material(
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: SizedBox(
            width: 550,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text('available_slots'.tr, style: robotoBold),
                if (_timezone != null) Text(_timezone!, style: robotoRegular),
                ListTile(
                  title: Text(
                    DateFormat.yMMMMd(Get.locale?.languageCode).format(_day),
                  ),
                  trailing: const NirabIcon(Icons.calendar_month),
                  onTap: widget.days != null
                      ? null
                      : () async {
                          final now = DateUtils.dateOnly(DateTime.now());
                          final day = await showDatePicker(
                            context: context,
                            initialDate: _day,
                            firstDate: now,
                            lastDate: now.add(const Duration(days: 365)),
                          );
                          if (day != null && mounted) {
                            setState(() => _day = day);
                            await _load();
                          }
                        },
                ),
                if (_loading) const LinearProgressIndicator(),
                if (_failed)
                  Column(
                    children: [
                      Text(_errorKey.tr),
                      TextButton(onPressed: _load, child: Text('retry'.tr)),
                    ],
                  ),
                if (!_loading && !_failed && _slots.isEmpty)
                  Text('no_available_slots'.tr),
                Flexible(
                  child: SingleChildScrollView(
                    child: Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: _slots.map((slot) {
                        // This offset-free value is for display only.
                        final wallTime = DateTime.parse(
                          slot['starts_at'].toString().substring(0, 19),
                        );
                        return ChoiceChip(
                          label: Text(
                            DateFormat.Hm(
                              Get.locale?.languageCode,
                            ).format(wallTime),
                          ),
                          selected: identical(_selected, slot),
                          onSelected: (_) => setState(() => _selected = slot),
                        );
                      }).toList(),
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                Wrap(
                  alignment: WrapAlignment.end,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    TextButton(
                      onPressed: () => Navigator.of(context).pop(),
                      child: Text('cancel'.tr),
                    ),
                    TextButton(
                      onPressed: _loading || _selected == null
                          ? null
                          : () {
                              final slot = _selected!;
                              if (!_slots.contains(slot)) return;
                              final controller = Get.find<ScheduleController>();
                              final originalStartsAt = slot['starts_at']
                                  .toString();
                              final wallTime = DateTime.parse(
                                slot['starts_at'].toString().substring(0, 19),
                              );
                              if (widget.onSelected != null) {
                                widget.onSelected!(
                                  Map<String, dynamic>.from(slot),
                                );
                                Navigator.of(context).pop(wallTime);
                                return;
                              }
                              if (_contextRevision !=
                                  controller.contextRevision) {
                                customSnackBar(
                                  'selected_slot_is_no_longer_available'.tr,
                                );
                                _load();
                                return;
                              }
                              final config = Get.find<SplashController>()
                                  .configModel
                                  .content;
                              if (config?.scheduleBookingTimeRestriction == 1 &&
                                  config?.advanceBooking != null) {
                                final error = controller
                                    .checkValidityOfTimeRestriction(
                                      config!.advanceBooking!,
                                      originalStartsAt: originalStartsAt,
                                    );
                                if (error != null) {
                                  customSnackBar(error);
                                  return;
                                }
                              }
                              Get.find<CheckOutController>().invalidatePrice();
                              controller.selectedDate = DateFormat(
                                'yyyy-MM-dd',
                              ).format(wallTime);
                              controller.selectedTime = DateFormat(
                                'HH:mm:ss',
                              ).format(wallTime);
                              if (widget.updateRegularSchedule) {
                                controller.buildSchedule(
                                  scheduleType: ScheduleType.schedule,
                                  schedule: DateFormat(
                                    'yyyy-MM-dd HH:mm:ss',
                                  ).format(wallTime),
                                  originalStartsAt: originalStartsAt,
                                );
                              }
                              controller.resolvedProviderId =
                                  slot['provider_id'].toString();
                              Navigator.of(context).pop(wallTime);
                            },
                      child: Text('ok'.tr),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
