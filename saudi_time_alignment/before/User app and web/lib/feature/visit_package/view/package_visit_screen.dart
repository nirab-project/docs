import 'package:nirab_clean/common/widgets/direct_service_fields.dart';
import 'package:nirab_clean/common/widgets/nirab/nirab_icon.dart';
import 'package:nirab_clean/common/widgets/nirab/nirab_foundation.dart';
import 'dart:convert';
import 'package:nirab_clean/util/core_export.dart';
import 'package:nirab_clean/feature/checkout/widget/order_details_section/available_slot_picker.dart';
import 'package:get/get.dart';

class PackageVisitScreen extends StatefulWidget {
  final String packageId;
  const PackageVisitScreen({super.key, required this.packageId});
  @override
  State<PackageVisitScreen> createState() => _PackageVisitScreenState();
}

class _PackageVisitScreenState extends State<PackageVisitScreen> {
  ApiClient get _api => Get.find<ApiClient>();
  String get _base => '/api/v1/customer/visit-packages/${widget.packageId}';
  String get _storageKey => 'ux_visit_booking_${widget.packageId}';
  Map<String, dynamic>? _context, _slot, _preview, _submitted;
  List<AddressModel> _addresses = [];
  AddressModel? _address;
  String? _variant;
  List<Map<String, dynamic>> _fieldAnswers = [];
  int _quantity = 1;
  String _key = const Uuid().v4();
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<Map<String, dynamic>> _content(Future<Response> request) async {
    final response = await request;
    if (response.statusCode != 200 && response.statusCode != 201) {
      // Even 409/422 may arrive after an uncertain network delivery.
      // Preserve the same idempotency key for manual reconciliation.
      final message = response.body is Map
          ? response.body['message']?.toString().trim()
          : null;
      throw StateError(
        message != null && message.isNotEmpty ? message : 'quality_failed'.tr,
      );
    }
    return Map<String, dynamic>.from(response.body['content']);
  }

  Future<void> _perform(Future<void> Function() action) async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await action();
    } catch (error) {
      if (mounted) {
        final message = error is StateError
            ? error.message.toString()
            : 'quality_failed'.tr;
        setState(() => _error = message);
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _load() => _perform(() async {
    final saved = Get.find<SharedPreferences>().getString(_storageKey);
    if (saved != null) {
      _submitted = Map<String, dynamic>.from(jsonDecode(saved));
    }
    final data = await _content(_api.getData('$_base/booking-context'));
    await Get.find<LocationController>().getAddressList();
    if (!mounted) return;
    setState(() {
      _context = data;
      _addresses = Get.find<LocationController>().addressList ?? [];
      _address = _addresses.firstOrNull;
      _reset();
    });
  });

  void _reset() {
    _slot = null;
    _preview = null;
    _key = const Uuid().v4();
    final variants = _variants;
    if (!variants.any((item) => item['variant_key'] == _variant)) {
      _variant = variants.firstOrNull?['variant_key']?.toString();
    }
  }

  List<Map> get _variants => (_context?['variations'] as List? ?? [])
      .whereType<Map>()
      .where((item) =>
          item['zone_id'] == _address?.zoneId || item['zone_id'] == null)
      .toList();

  Future<void> _pickSlot() async {
    if (_address == null) return;
    final fields = (_context?['service']?['active_fields'] as List? ?? [])
        .map((f) => Map<String, dynamic>.from(f))
        .toList();
    if (fields.isNotEmpty) {
      final form = GlobalKey<FormState>();
      final accepted = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: Text('service_details_required'.tr),
          content: SingleChildScrollView(
            child: Form(
              key: form,
              child: DirectServiceFields(
                fields: fields,
                answers: _fieldAnswers,
                onChanged: (answers) => _fieldAnswers = answers,
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () {
                if (form.currentState!.validate()) Navigator.pop(ctx, true);
              },
              child: Text('continue'.tr),
            ),
          ],
        ),
      );
      if (accepted != true || !mounted) return;
    }
    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (_) => AvailableSlotPicker(
        address: _address,
        items: [
          {
            'service_id': _context!['service']['id'],
            'variant_key': _variant,
            'quantity': _quantity,
            'field_answers': _fieldAnswers,
          },
        ],
        updateRegularSchedule: false,
        onSelected: (slot) => setState(() {
          _slot = slot;
          _preview = null;
        }),
      ),
    );
  }

  Map<String, dynamic> get _input => {
    'idempotency_key': _key,
    'service_address_id': _address!.id,
    'variant_key': _variant,
    'quantity': _quantity,
    'field_answers': _fieldAnswers,
    // Preserve the availability API offset so the server receives the selected instant.
    'service_schedule': _slot!['starts_at'].toString(),
    'provider_id': _slot!['provider_id'],
  };

  Future<void> _review() => _perform(() async {
    final data = await _content(
      _api.postData('$_base/booking-preview', _input),
    );
    if (mounted) setState(() => _preview = data);
  });

  Future<void> _confirm() => _perform(() async {
    _submitted ??= Map<String, dynamic>.from(_input);
    // Persist only IDs, quantity and slot; never payment credentials or a paid flag.
    await Get.find<SharedPreferences>().setString(
      _storageKey,
      jsonEncode(_submitted),
    );
    final result = await _content(_api.postData('$_base/book', _submitted!));
    await Get.find<SharedPreferences>().remove(_storageKey);
    _submitted = null;
    if (mounted) {
      Get.offNamed(
        RouteHelper.getBookingDetailsScreen(
          bookingID: result['booking_id'].toString(),
        ),
      );
    }
  });

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: CustomAppBar(title: 'quality_reserve_visit'.tr),
    body: SafeArea(
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 760),
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              if (_busy) const LinearProgressIndicator(),
              if (_error != null) Text(_error!),
              if (_context == null && !_busy)
                TextButton(onPressed: _load, child: Text('retry'.tr)),
              if (_submitted != null) ...[
                Text('ux_package_pending_retry'.tr),
                TextButton(
                  onPressed: _busy ? null : _confirm,
                  child: Text('retry'.tr),
                ),
                TextButton(
                  onPressed: () =>
                      Get.toNamed(RouteHelper.getBookingScreenRoute(true)),
                  child: Text('my_bookings'.tr),
                ),
              ] else if (_context != null && _context!['eligible'] != true)
                NirabInfoPanel(message: 'ux_package_ineligible'.tr)
              else if (_context != null) ...[
                Text(
                  '${_context!['package']['pricing_snapshot']['name']}',
                  style: robotoBold,
                ),
                Text('${_context!['service']['name']}', style: robotoMedium),
                Text(
                  '${'quality_remaining_visits'.tr}: ${_context!['package']['remaining_visits']}',
                ),
                Text(
                  '${'ux_package_units'.tr}: ${_context!['max_units_per_visit']}',
                ),
                const SizedBox(height: 16),
                DropdownButtonFormField<String>(
                  key: ValueKey(_address?.id),
                  initialValue: _address?.id,
                  isExpanded: true,
                  decoration: InputDecoration(labelText: 'address'.tr),
                  items: _addresses
                      .map(
                        (address) => DropdownMenuItem(
                          value: address.id,
                          child: Text(
                            address.address ?? '',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      )
                      .toList(),
                  onChanged: _busy
                      ? null
                      : (id) => setState(() {
                          _address = _addresses.firstWhere(
                            (item) => item.id == id,
                          );
                          _reset();
                        }),
                ),
                TextButton(
                  onPressed: _busy
                      ? null
                      : () async {
                          await Get.toNamed(
                            RouteHelper.getAddressRoute('profile'),
                          );
                          if (mounted) _load();
                        },
                  child: Text('ux_manage_addresses'.tr),
                ),
                if (_variants.isEmpty)
                  Text('ux_package_no_variants'.tr)
                else
                  DropdownButtonFormField<String>(
                    key: ValueKey('${_address?.id}:$_variant'),
                    initialValue: _variant,
                    isExpanded: true,
                    decoration: InputDecoration(
                      labelText: 'ux_package_service_option'.tr,
                    ),
                    items: _variants
                        .map(
                          (item) => DropdownMenuItem(
                            value: item['variant_key'].toString(),
                            child: Text('${item['variant']}'),
                          ),
                        )
                        .toList(),
                    onChanged: _busy
                        ? null
                        : (value) => setState(() {
                            _variant = value;
                            _reset();
                          }),
                  ),
                Row(
                  children: [
                    Expanded(child: Text('${'quantity'.tr}: $_quantity')),
                    IconButton(
                      onPressed: _busy || _quantity <= 1
                          ? null
                          : () => setState(() {
                              _quantity--;
                              _reset();
                            }),
                      icon: const NirabIcon(Icons.remove),
                    ),
                    IconButton(
                      onPressed:
                          _busy ||
                              _quantity >=
                                  (_context!['max_units_per_visit'] as num)
                          ? null
                          : () => setState(() {
                              _quantity++;
                              _reset();
                            }),
                      icon: const NirabIcon(Icons.add),
                    ),
                  ],
                ),
                ListTile(
                  title: Text('available_slots'.tr),
                  subtitle: Text(
                    _slot?['starts_at']?.toString() ?? 'ux_choose_slot'.tr,
                  ),
                  trailing: const NirabIcon(Icons.event),
                  onTap: _busy || _address == null || _variant == null
                      ? null
                      : _pickSlot,
                ),
                if (_preview == null)
                  CustomButton(
                    buttonText: 'ux_review_visit'.tr,
                    isLoading: _busy,
                    onPressed: _busy || _slot == null || _variant == null
                        ? null
                        : _review,
                  )
                else
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '${_preview!['service_name']}  ·  ${_preview!['quantity']}',
                          ),
                          Text('${_preview!['address']}'),
                          Text(
                            '${_preview!['branch_name']}\n${_preview!['service_schedule']}  ·  ${_preview!['timezone']}',
                          ),
                          Text(
                            '${'ux_visit_balance_summary'.tr}: ${_preview!['remaining_before']}  →  ${_preview!['remaining_after']}',
                          ),
                          Text(
                            '${'ux_package_amount_due'.tr}: ${_preview!['amount_due']} ${_preview!['currency']}',
                          ),
                          const SizedBox(height: 12),
                          CustomButton(
                            buttonText: 'confirm_booking'.tr,
                            isLoading: _busy,
                            onPressed: _busy ? null : _confirm,
                          ),
                        ],
                      ),
                    ),
                  ),
              ],
            ],
          ),
        ),
      ),
    ),
  );
}
