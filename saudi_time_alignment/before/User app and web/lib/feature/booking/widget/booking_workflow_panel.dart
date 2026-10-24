import 'package:nirab_clean/common/widgets/nirab/nirab_icon.dart';
import 'package:nirab_clean/feature/booking/widget/booking_invoices_panel.dart';
import 'package:nirab_clean/util/core_export.dart';
import 'package:get/get.dart';

class BookingWorkflowPanel extends StatefulWidget {
  final BookingDetailsContent booking;
  final bool isSubBooking;
  const BookingWorkflowPanel({
    super.key,
    required this.booking,
    required this.isSubBooking,
  });

  @override
  State<BookingWorkflowPanel> createState() => _BookingWorkflowPanelState();
}

class _BookingWorkflowPanelState extends State<BookingWorkflowPanel>
    with WidgetsBindingObserver {
  Timer? _timer;
  GoogleMapController? _map;
  Map<String, dynamic> _tracking = {};
  bool _busy = false, _failed = false;
  bool _resumed = true;
  bool _authorized = true;
  bool _active = false;
  bool get _watch => ![
    'completed',
    'canceled',
    'rejected',
    'failed',
  ].contains(widget.booking.bookingStatus);

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _reset();
  }

  @override
  void didUpdateWidget(covariant BookingWorkflowPanel oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.booking.id != widget.booking.id ||
        oldWidget.booking.bookingStatus != widget.booking.bookingStatus) {
      if (oldWidget.booking.id != widget.booking.id) _authorized = true;
      _reset();
    }
  }

  void _reset() {
    _timer?.cancel();
    _tracking = widget.booking.dispatchTracking ?? {};
    _active = widget.booking.bookingStatus == 'on_the_way';
    _failed = false;
    _start();
  }

  void _start() {
    _timer?.cancel();
    if (!_authorized ||
        !_watch ||
        !_resumed ||
        !Get.find<AuthController>().isLoggedIn()) {
      return;
    }
    _timer = Timer.periodic(const Duration(seconds: 30), (_) => _poll());
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _poll();
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _resumed = state == AppLifecycleState.resumed;
    if (_resumed) {
      _start();
    } else {
      _timer?.cancel();
      _timer = null;
    }
  }

  Future<void> _poll() async {
    if (!mounted ||
        _busy ||
        !_authorized ||
        !Get.find<AuthController>().isLoggedIn() ||
        !_watch ||
        !_resumed ||
        ModalRoute.of(context)?.isCurrent == false) {
      return;
    }
    final id = widget.isSubBooking
        ? widget.booking.bookingId
        : widget.booking.id;
    if (id == null || id.isEmpty) return;
    final subject = widget.booking.id;
    _busy = true;
    try {
      if (widget.booking.bookingStatus != 'on_the_way') {
        final controller = Get.find<BookingDetailsController>();
        if (widget.isSubBooking) {
          await controller.getSubBookingDetails(
            bookingId: subject!,
            reload: false,
          );
        } else {
          await controller.getBookingDetails(bookingId: id, reload: false);
        }
        if ([401, 403, 404].contains(controller.lastDetailsStatusCode)) {
          _authorized = false;
          _timer?.cancel();
        }
        return;
      }
      final suffix = widget.isSubBooking
          ? '?booking_repeat_id=${Uri.encodeQueryComponent(subject ?? '')}'
          : '';
      final response = await Get.find<ApiClient>()
          .getData('${AppConstants.dispatchTracking}/$id$suffix')
          .timeout(const Duration(seconds: 20));
      if (!mounted || subject != widget.booking.id) return;
      if ([401, 403, 404].contains(response.statusCode)) {
        _authorized = false;
        _active = false;
        _timer?.cancel();
      }
      if (response.statusCode != 200 ||
          response.body is! Map ||
          response.body['content'] is! Map) {
        throw StateError('tracking');
      }
      final tracking = Map<String, dynamic>.from(response.body['content']);
      setState(() {
        _tracking = tracking;
        _failed = false;
        _active = tracking['tracking_active'] == true;
      });
      await _fitMap();
      if (!_active) {
        _timer?.cancel();
        final controller = Get.find<BookingDetailsController>();
        if (widget.isSubBooking) {
          await controller.getSubBookingDetails(
            bookingId: subject!,
            reload: false,
          );
        } else {
          await controller.getBookingDetails(bookingId: id, reload: false);
        }
      }
    } catch (_) {
      if (mounted) setState(() => _failed = true);
    } finally {
      _busy = false;
    }
  }

  Future<void> _fitMap() async {
    final first = _point(_tracking['customer_location']);
    final second = _point(_tracking['location']);
    if (_map == null || first == null || second == null || first == second) {
      return;
    }
    try {
      await _map!.animateCamera(
        CameraUpdate.newLatLngBounds(
          LatLngBounds(
            southwest: LatLng(
              first.latitude < second.latitude
                  ? first.latitude
                  : second.latitude,
              first.longitude < second.longitude
                  ? first.longitude
                  : second.longitude,
            ),
            northeast: LatLng(
              first.latitude > second.latitude
                  ? first.latitude
                  : second.latitude,
              first.longitude > second.longitude
                  ? first.longitude
                  : second.longitude,
            ),
          ),
          36,
        ),
      );
    } catch (_) {
      /* The map may have been disposed while the booking status changed. */
    }
  }

  LatLng? _point(dynamic value) {
    if (value is! Map) return null;
    final lat = double.tryParse(value['latitude']?.toString() ?? '');
    final lon = double.tryParse(value['longitude']?.toString() ?? '');
    if (lat == null ||
        lon == null ||
        !lat.isFinite ||
        !lon.isFinite ||
        lat.abs() > 90 ||
        lon.abs() > 180) {
      return null;
    }
    return LatLng(lat, lon);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _timer?.cancel();
    _map?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final booking = widget.booking;
    final flow = booking.workflow;
    final members = (flow['team'] as List? ?? []).whereType<Map>();
    final customer = _point(_tracking['customer_location']);
    final technician = _point(_tracking['location']);
    final captured = _tracking['location'] is Map
        ? DateTime.tryParse(
            _tracking['location']['captured_at']?.toString() ?? '',
          )
        : null;
    final stale =
        _tracking['stale'] == true ||
        captured != null &&
            DateTime.now().difference(captured).inSeconds >=
                (_tracking['max_age_seconds'] as num? ?? 900);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (flow['visit_package'] is Map)
                  Text(
                    '${'ux_package_covered'.tr} · ${'ux_visit_${flow['visit_package']['usage_status']}'.tr}',
                  ),
                Text(
                  '${'service_status'.tr}: ${(booking.bookingStatus ?? 'pending').tr}',
                  style: robotoMedium,
                ),
                Text(
                  '${'payment_status'.tr}: ${(flow['payment_status'] ?? (booking.isPaid == 1 ? 'paid' : 'pending')).toString().tr}',
                ),
                Text(
                  '${'invoice_state'.tr}: ${'invoice_${flow['invoice_status'] ?? 'pending'}'.tr}',
                ),
                if (flow['invoice_reference'] != null)
                  SelectableText(
                    '${'invoice_reference'.tr}: ${flow['invoice_reference']}',
                  ),
                if (flow['execution_type'] == 'team')
                  Text('team_assignment'.tr, style: robotoBold),
                ...members.map(
                  (member) => Text(
                    '${member['name'] ?? ''} · ${'team_role_${member['role']}'.tr} · ${'assignment_${member['status']}'.tr}',
                  ),
                ),
              ],
            ),
          ),
        ),
        if (_active || booking.bookingStatus == 'on_the_way')
          Card(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('technician_tracking'.tr, style: robotoBold),
                  if (_failed) Text('tracking_load_failed'.tr),
                  if (!_active) Text('tracking_inactive'.tr),
                  if (stale) Text('tracking_location_stale'.tr),
                  if (technician == null)
                    Text('tracking_location_unavailable'.tr),
                  if (customer != null || technician != null)
                    SizedBox(
                      height: 240,
                      child: GoogleMap(
                        key: ValueKey(
                          '${booking.id}-${customer?.latitude}-${customer?.longitude}',
                        ),
                        initialCameraPosition: CameraPosition(
                          target: customer ?? technician!,
                          zoom: 12,
                        ),
                        onMapCreated: (controller) {
                          _map = controller;
                          _fitMap();
                        },
                        myLocationButtonEnabled: false,
                        zoomControlsEnabled: false,
                        markers: {
                          if (customer != null)
                            Marker(
                              markerId: const MarkerId('customer'),
                              position: customer,
                              infoWindow: InfoWindow(
                                title: 'customer_location'.tr,
                              ),
                            ),
                          if (technician != null)
                            Marker(
                              markerId: const MarkerId('technician'),
                              position: technician,
                              icon: BitmapDescriptor.defaultMarkerWithHue(
                                stale
                                    ? BitmapDescriptor.hueOrange
                                    : BitmapDescriptor.hueAzure,
                              ),
                              infoWindow: InfoWindow(title: 'technician'.tr),
                            ),
                        },
                      ),
                    ),
                  if (_tracking['eta_minutes'] != null && !stale)
                    Text(
                      '${'estimated_arrival'.tr}: ${_tracking['eta_minutes']} ${'minutes'.tr}',
                    ),
                  if (captured != null)
                    Text(
                      '${'tracking_last_updated'.tr}: ${DateConverter.dateMonthYearTimeTwentyFourFormat(captured.toLocal())}',
                    ),
                  if (_active)
                    TextButton.icon(
                      onPressed: _poll,
                      icon: const NirabIcon(Icons.refresh),
                      label: Text('retry'.tr),
                    ),
                ],
              ),
            ),
          ),
        if (flow['visit_package'] != null)
          Padding(
            padding: const EdgeInsets.all(12),
            child: Text('ux_package_change_policy'.tr),
          ),
        if (flow['invoice_status'] != 'not_applicable')
          BookingInvoicesPanel(
            subjectId: booking.id ?? '',
            isSubBooking: widget.isSubBooking,
          ),
      ],
    );
  }
}
