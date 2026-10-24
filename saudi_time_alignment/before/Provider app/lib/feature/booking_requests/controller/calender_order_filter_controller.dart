import 'package:get/get.dart';
import 'package:demandium_provider/util/core_export.dart';
import 'package:demandium_provider/feature/booking_requests/controller/calendar_controller.dart';

class CalenderOrderFilterController extends GetxController
    implements GetxService {
  // Filter states
  ServiceType _selectedBookingType = ServiceType.all;
  ServiceType get selectedBookingType => _selectedBookingType;

  DateTime? _startDate;
  DateTime? get startDate => _startDate;

  DateTime? _endDate;
  DateTime? get endDate => _endDate;

  final Set<BookingStatusEnum> _selectedStatuses = {};
  Set<BookingStatusEnum> get selectedStatuses =>
      Set.unmodifiable(_selectedStatuses);

  /// Set booking type filter
  void setBookingType(ServiceType type) {
    _selectedBookingType = type;
    update();
  }

  /// Set date range filter
  void setDateRange(DateTime? start, DateTime? end) {
    _startDate = start;
    _endDate = end;
    update();
  }

  /// Set start date
  void setStartDate(DateTime? date) {
    _startDate = date;
    update();
  }

  /// Set end date
  void setEndDate(DateTime? date) {
    _endDate = date;
    update();
  }

  /// Toggle booking status selection
  void toggleBookingStatus(BookingStatusEnum status) {
    if (_selectedStatuses.contains(status)) {
      _selectedStatuses.remove(status);
    } else {
      _selectedStatuses.add(status);
    }
    update();
  }

  /// Check if a status is selected
  bool isStatusSelected(BookingStatusEnum status) {
    return _selectedStatuses.contains(status);
  }

  /// Get API values for selected statuses
  List<String> get selectedStatusValues {
    return _selectedStatuses.map((status) => status.value).toList();
  }

  /// Check if any filters are active
  bool hasActiveFilters() {
    return _selectedBookingType != ServiceType.all ||
        _startDate != null ||
        _endDate != null ||
        _selectedStatuses.isNotEmpty;
  }

  /// Apply filters and reload calendar
  void applyFilter() {
    // Get calendar controller
    final calendarController = Get.find<BookingCalendarController>();

    // Set filters on calendar controller with API values
    calendarController.setFilters(
      startDate: _startDate,
      endDate: _endDate,
      bookingStatuses: selectedStatusValues,
      bookingType: _selectedBookingType.name,
    );

    update();
  }

  void initFilterState(CalenderOrderModel calenderOrder) {
    _selectedBookingType = calenderOrder.bookingType ?? ServiceType.all;
    _startDate = calenderOrder.filterStartDate;
    _endDate = calenderOrder.filterEndDate;

    if (calenderOrder.bookingStatus != null) {
      _selectedStatuses.clear();
      _selectedStatuses.addAll(calenderOrder.bookingStatus!);
    }
  }

  /// Reset all filters
  void resetFilter() {
    _selectedBookingType = ServiceType.all;
    _startDate = null;
    _endDate = null;
    _selectedStatuses.clear();

    // Clear filters on calendar controller
    final calendarController = Get.find<BookingCalendarController>();
    calendarController.clearFilters();

    update();
  }

  /// Select date picker (start or end)
  Future<void> selectDate(String type, BuildContext context) async {
    DateTime? pickedDate = await showDatePicker(
      context: context,
      initialDate: type == 'start'
          ? (_startDate ?? DateTime.now())
          : (_endDate ?? _startDate ?? DateTime.now()),
      firstDate: DateTime(2000),
      lastDate: DateTime(2100),
      builder: (context, child) {
        return Theme(
          data: ThemeData.light().copyWith(
            primaryColor: Theme.of(context).primaryColor,
            colorScheme: Theme.of(context).colorScheme,
          ),
          child: child!,
        );
      },
    );

    if (pickedDate != null) {
      if (type == 'start') {
        setStartDate(pickedDate);
      } else {
        setEndDate(pickedDate);
      }
    }
  }

  /// Clear date range filter
  void clearDateFilter() {
    _startDate = null;
    _endDate = null;
    applyFilter();
  }

  /// Clear booking type filter (reset to all)
  void clearBookingTypeFilter() {
    _selectedBookingType = ServiceType.all;
    applyFilter();
  }

  /// Clear a specific status filter
  void clearStatusFilter(BookingStatusEnum status) {
    _selectedStatuses.remove(status);
    applyFilter();
  }
}
