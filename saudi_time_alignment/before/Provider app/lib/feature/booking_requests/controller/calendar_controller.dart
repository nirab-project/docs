import 'package:get/get.dart';
import 'package:syncfusion_flutter_calendar/calendar.dart';
import 'package:demandium_provider/util/core_export.dart';

class BookingCalendarController extends GetxController implements GetxService {
  final BookingRequestRepo bookingRequestRepo;
  BookingCalendarController({required this.bookingRequestRepo});

  bool _isLoading = false;
  bool get isLoading => _isLoading;

  CalendarViewType _currentViewType = CalendarViewType.month;
  CalendarViewType get currentViewType => _currentViewType;

  CalendarView get calendarView {
    switch (_currentViewType) {
      case CalendarViewType.month:
        return CalendarView.month;
      case CalendarViewType.week:
        return CalendarView.week;
      case CalendarViewType.day:
        return CalendarView.day;
    }
  }

  DateTime _selectedDate = DateTime.now();
  DateTime get selectedDate => _selectedDate;

  // Store complete calendar data
  CalenderOrderModel? _calendarData;
  CalenderOrderModel? get calendarData => _calendarData;

  // Indexed maps for quick lookup by view type
  final Map<String, CalenderData> _monthDataMap = {};
  final Map<String, CalenderData> _weekDataMap = {};
  final Map<String, CalenderData> _dayDataMap = {};

  // Calendar controller to manage displayed date
  final CalendarController calendarController = CalendarController();

  // Filter properties
  DateTime? _filterStartDate;
  DateTime? get filterStartDate => _filterStartDate;

  DateTime? _filterEndDate;
  DateTime? get filterEndDate => _filterEndDate;

  List<String> _filterBookingStatuses = [];
  List<String> get filterBookingStatuses =>
      List.unmodifiable(_filterBookingStatuses);

  String _filterBookingType = 'all';
  String get filterBookingType => _filterBookingType;

  // Date range constraint state
  bool _isDateRangeConstrained = false;
  bool get isDateRangeConstrained => _isDateRangeConstrained;

  /// Set filter parameters and reload calendar
  void setFilters({
    DateTime? startDate,
    DateTime? endDate,
    List<String>? bookingStatuses,
    String? bookingType,
  }) {
    _filterStartDate = startDate;
    _filterEndDate = endDate;
    _filterBookingStatuses = bookingStatuses ?? [];
    _filterBookingType = bookingType ?? 'all';

    // Enable date range constraint if both start and end dates are provided
    _isDateRangeConstrained = startDate != null && endDate != null;

    // Navigate to start date if date range filter is applied
    if (_isDateRangeConstrained && startDate != null) {
      _selectedDate = startDate;
      calendarController.displayDate = _selectedDate;
    }

    update();
    loadBookingsForCurrentView();
  }

  /// Clear all filters and reload calendar
  void clearFilters() {
    _filterStartDate = null;
    _filterEndDate = null;
    _filterBookingStatuses = [];
    _filterBookingType = 'all';
    _isDateRangeConstrained = false;
    update();
    loadBookingsForCurrentView();
  }

  /// Check if any filters are active
  bool hasActiveFilters() {
    return _filterStartDate != null ||
        _filterEndDate != null ||
        _filterBookingStatuses.isNotEmpty ||
        _filterBookingType != 'all';
  }

  /// Check if next navigation is allowed
  bool canNavigateNext() {
    return _canNavigate(isForward: true, boundaryDate: _filterEndDate);
  }

  /// Check if previous navigation is allowed
  bool canNavigatePrevious() {
    return _canNavigate(isForward: false, boundaryDate: _filterStartDate);
  }

  /// Check if a specific period is disabled based on the active date range filter
  bool isPeriodDisabled(DateTime start, DateTime end) {
    if (!_isDateRangeConstrained ||
        _filterStartDate == null ||
        _filterEndDate == null) {
      return false;
    }

    // Check if the period is completely outside the filter range
    // Period ends before filter starts OR Period starts after filter ends
    return end.isBefore(_filterStartDate!) || start.isAfter(_filterEndDate!);
  }

  /// Common method to check if navigation is allowed in a given direction
  /// [isForward] - true for next navigation, false for previous
  /// [boundaryDate] - the date boundary (end date for forward, start date for backward)
  bool _canNavigate({
    required bool isForward,
    required DateTime? boundaryDate,
  }) {
    // If no date range constraint, allow navigation
    if (!_isDateRangeConstrained) return true;

    // If no boundary date is set, allow navigation
    if (boundaryDate == null) return true;

    // Calculate the target date based on direction and view type
    final targetDate = _calculateNavigationDate(isForward: isForward);

    // Check if target date is within the boundary
    return _isDateWithinBoundary(
      date: targetDate,
      boundaryDate: boundaryDate,
      isForward: isForward,
    );
  }

  /// Calculate the target date for navigation based on view type and direction
  DateTime _calculateNavigationDate({required bool isForward}) {
    switch (_currentViewType) {
      case CalendarViewType.month:
        // Navigate by month
        final monthOffset = isForward ? 1 : -1;
        return DateTime(
          _selectedDate.year,
          _selectedDate.month + monthOffset,
          1,
        );

      case CalendarViewType.week:
        // Navigate by week (7 days)
        final duration = const Duration(days: 7);
        return isForward
            ? _selectedDate.add(duration)
            : _selectedDate.subtract(duration);

      case CalendarViewType.day:
        // Navigate by day
        final duration = const Duration(days: 1);
        return isForward
            ? _selectedDate.add(duration)
            : _selectedDate.subtract(duration);
    }
  }

  /// Check if a date is within the allowed boundary
  bool _isDateWithinBoundary({
    required DateTime date,
    required DateTime boundaryDate,
    required bool isForward,
  }) {
    if (_currentViewType == CalendarViewType.month) {
      // For month view, compare year and month
      if (isForward) {
        // Check if next month doesn't exceed end date's month
        return date.year < boundaryDate.year ||
            (date.year == boundaryDate.year &&
                date.month <= boundaryDate.month);
      } else {
        // Check if previous month is not before start date's month
        return date.year > boundaryDate.year ||
            (date.year == boundaryDate.year &&
                date.month >= boundaryDate.month);
      }
    } else {
      // For week/day views, compare full dates
      if (isForward) {
        return date.isBefore(boundaryDate) ||
            date.isAtSameMomentAs(boundaryDate);
      } else {
        return date.isAfter(boundaryDate) ||
            date.isAtSameMomentAs(boundaryDate);
      }
    }
  }

  void changeViewType(CalendarViewType viewType) {
    _currentViewType = viewType;
    calendarController.view = calendarView;
    update();
    loadBookingsForCurrentView();
  }

  void onDateChanged(DateTime date) {
    _selectedDate = date;
    update();
    loadBookingsForCurrentView();
  }

  /// Update date only if the view period has changed
  void updateDateIfViewChanged(DateTime newDate) {
    bool isSamePeriod = false;

    switch (_currentViewType) {
      case CalendarViewType.month:
        // Same month and year
        isSamePeriod =
            newDate.year == _selectedDate.year &&
            newDate.month == _selectedDate.month;
        break;
      case CalendarViewType.week:
        // Same week
        DateTime startOfWeek1 = newDate.subtract(
          Duration(days: newDate.weekday - 1),
        );
        DateTime startOfWeek2 = _selectedDate.subtract(
          Duration(days: _selectedDate.weekday - 1),
        );
        isSamePeriod =
            startOfWeek1.year == startOfWeek2.year &&
            startOfWeek1.month == startOfWeek2.month &&
            startOfWeek1.day == startOfWeek2.day;
        break;
      case CalendarViewType.day:
        // Same day
        isSamePeriod =
            newDate.year == _selectedDate.year &&
            newDate.month == _selectedDate.month &&
            newDate.day == _selectedDate.day;
        break;
    }

    if (!isSamePeriod) {
      onDateChanged(newDate);
    }
  }

  void navigateNext() {
    // Prevent navigation if date range is constrained
    if (!canNavigateNext()) return;

    switch (_currentViewType) {
      case CalendarViewType.month:
        _selectedDate = DateTime(
          _selectedDate.year,
          _selectedDate.month + 1,
          1,
        );
        break;
      case CalendarViewType.week:
        _selectedDate = _selectedDate.add(const Duration(days: 7));
        break;
      case CalendarViewType.day:
        _selectedDate = _selectedDate.add(const Duration(days: 1));
        break;
    }
    calendarController.displayDate = _selectedDate;
    update();
    loadBookingsForCurrentView();
  }

  void navigatePrevious() {
    // Prevent navigation if date range is constrained
    if (!canNavigatePrevious()) return;

    switch (_currentViewType) {
      case CalendarViewType.month:
        _selectedDate = DateTime(
          _selectedDate.year,
          _selectedDate.month - 1,
          1,
        );
        break;
      case CalendarViewType.week:
        _selectedDate = _selectedDate.subtract(const Duration(days: 7));
        break;
      case CalendarViewType.day:
        _selectedDate = _selectedDate.subtract(const Duration(days: 1));
        break;
    }
    calendarController.displayDate = _selectedDate;
    update();
    loadBookingsForCurrentView();
  }

  /// Navigate to a specific month
  /// Used when selecting a month from the month picker dialog
  void navigateToMonth(DateTime date) {
    _selectedDate = DateTime(date.year, date.month, 1);
    calendarController.displayDate = _selectedDate;
    update();
    loadBookingsForCurrentView();
  }

  /// Navigate to a specific week
  /// Used when selecting a week from the week picker dialog
  void navigateToWeek(DateTime date) {
    // date is the first day of the selected week
    _selectedDate = date;
    calendarController.displayDate = _selectedDate;
    update();
    loadBookingsForCurrentView();
  }

  /// Navigate to a specific day
  /// Used when selecting a day from the day picker dialog
  void navigateToDay(DateTime date) {
    _selectedDate = date;
    calendarController.displayDate = _selectedDate;
    update();
    loadBookingsForCurrentView();
  }

  String getFormattedDateRange() {
    switch (_currentViewType) {
      case CalendarViewType.month:
        // Format: "January 2026"
        return DateConverter.dateStringMonthYear(
          _selectedDate,
          format: 'MMMM y',
        );
      case CalendarViewType.week:
        // Format: "Jan 1-7, 2026"
        DateTime firstDayOfWeek = _selectedDate.subtract(
          Duration(days: _selectedDate.weekday - 1),
        );
        DateTime lastDayOfWeek = firstDayOfWeek.add(const Duration(days: 6));

        if (firstDayOfWeek.month == lastDayOfWeek.month) {
          // Same month: "Jan 1-7, 2026"
          String monthAbbrev = DateConverter.dateStringMonthYear(
            firstDayOfWeek,
            format: 'MMM',
          );
          return '$monthAbbrev ${firstDayOfWeek.day}-${lastDayOfWeek.day}, ${firstDayOfWeek.year}';
        } else {
          // Different months: "Dec 29 - Jan 4, 2026"
          String startMonth = DateConverter.dateStringMonthYear(
            firstDayOfWeek,
            format: 'MMM d',
          );
          String endMonth = DateConverter.dateStringMonthYear(
            lastDayOfWeek,
            format: 'MMM d',
          );
          return '$startMonth - $endMonth, ${lastDayOfWeek.year}';
        }
      case CalendarViewType.day:
        // Format: "Jan 14, 2026"
        return DateConverter.dateStringMonthYear(
          _selectedDate,
          format: 'MMM d, y',
        );
    }
  }

  Future<void> loadBookingsForCurrentView() async {
    _isLoading = true;
    update();

    // Calculate date range based on current view
    DateTimeRange dateRange = _getDateRangeForView();

    // Fetch bookings for the date range
    await _fetchBookingsForDateRange(dateRange.start, dateRange.end);

    _isLoading = false;
    update();
  }

  DateTimeRange _getDateRangeForView() {
    DateTime now = _selectedDate;
    switch (_currentViewType) {
      case CalendarViewType.month:
        // Get first and last day of the month
        DateTime firstDayOfMonth = DateTime(now.year, now.month, 1);
        DateTime lastDayOfMonth = DateTime(now.year, now.month + 1, 0);
        return DateTimeRange(start: firstDayOfMonth, end: lastDayOfMonth);

      case CalendarViewType.week:
        // Get first and last day of the week
        DateTime firstDayOfWeek = now.subtract(Duration(days: now.weekday - 1));
        DateTime lastDayOfWeek = firstDayOfWeek.add(const Duration(days: 6));
        return DateTimeRange(start: firstDayOfWeek, end: lastDayOfWeek);

      case CalendarViewType.day:
        // Get start and end of the day
        DateTime startOfDay = DateTime(now.year, now.month, now.day);
        DateTime endOfDay = DateTime(now.year, now.month, now.day, 23, 59, 59);
        return DateTimeRange(start: startOfDay, end: endOfDay);
    }
  }

  Future<void> _fetchBookingsForDateRange(
    DateTime startDate,
    DateTime endDate,
  ) async {
    try {
      // Map CalendarViewType to API mode parameter
      String mode;
      switch (_currentViewType) {
        case CalendarViewType.month:
          mode = 'dayGridMonth';
          break;
        case CalendarViewType.week:
          mode = 'timeGridWeek';
          break;
        case CalendarViewType.day:
          mode = 'timeGridDay';
          break;
      }

      // Call the new calendar API with filters
      Response response = await bookingRequestRepo.getBookingCalendarData(
        mode: mode,
        month: _currentViewType == CalendarViewType.month
            ? _selectedDate.month
            : null,
        year: _currentViewType == CalendarViewType.month
            ? _selectedDate.year
            : null,
        startDate: _currentViewType == CalendarViewType.week
            ? DateConverter.dateMonthYearLocalTime(startDate)
            : null,
        endDate: _currentViewType == CalendarViewType.week
            ? DateConverter.dateMonthYearLocalTime(endDate)
            : null,
        date: _currentViewType == CalendarViewType.day
            ? DateConverter.dateMonthYearLocalTime(_selectedDate)
            : null,
        // Filter parameters
        filterStartDate: _filterStartDate?.toIso8601String(),
        filterEndDate: _filterEndDate?.toIso8601String(),
        bookingStatus: _filterBookingStatuses.isNotEmpty
            ? _filterBookingStatuses
            : null,
        bookingType: _filterBookingType,
      );

      if (response.statusCode == 200) {
        // Clear previous data
        _monthDataMap.clear();
        _weekDataMap.clear();
        _dayDataMap.clear();

        // Parse the CalenderOrderModel response
        try {
          _calendarData = CalenderOrderModel.fromJson(response.body['content']);

          // Build indexed maps for quick lookup
          _buildDataMaps(_calendarData!);
        } catch (e) {
          if (kDebugMode) {
            print('Error parsing calendar data: ${e.runtimeType}');
          }
        }
      } else {
        ApiChecker.checkApi(response);
      }
    } catch (e) {
      // Handle error
      _calendarData = null;
      _monthDataMap.clear();
      _weekDataMap.clear();
      _dayDataMap.clear();
      if (kDebugMode) {
        print('Error fetching calendar data: ${e.runtimeType}');
      }
    }
  }

  /// Build indexed maps for efficient data lookup
  void _buildDataMaps(CalenderOrderModel data) {
    // Build month data map (key: YYYY-MM-DD)
    if (data.dayGridMonth != null) {
      for (var calenderData in data.dayGridMonth!) {
        String dateKey = DateConverter.formatDateToYYYYMMDD(calenderData.start);
        _monthDataMap[dateKey] = calenderData;
      }
    }

    // Build week data map (key: YYYY-MM-DD-HH)
    if (data.timeGridWeek != null) {
      for (var calenderData in data.timeGridWeek!) {
        DateTime startWithHour = DateConverter.addTimeStringToDate(
          calenderData.start,
          calenderData.startHourTime,
        );
        String dateTimeKey = DateConverter.formatDateTimeToYYYYMMDDHH(
          startWithHour,
        );
        _weekDataMap[dateTimeKey] = calenderData;
      }
    }

    // Build day data map (key: YYYY-MM-DD-HH)
    if (data.timeGridDay != null) {
      for (var calenderData in data.timeGridDay!) {
        DateTime startWithHour = DateConverter.addTimeStringToDate(
          calenderData.start,
          calenderData.startHourTime,
        );
        String dateTimeKey = DateConverter.formatDateTimeToYYYYMMDDHH(
          startWithHour,
        );
        _dayDataMap[dateTimeKey] = calenderData;
      }
    }
  }

  int getOrderCountForDate(DateTime date) {
    String dateKey = DateConverter.formatDateToYYYYMMDD(date);
    CalenderData? data;
    switch (_currentViewType) {
      case CalendarViewType.month:
        data = _monthDataMap[dateKey];
        break;
      case CalendarViewType.week:
        // For week view, we might want to aggregate counts for the day
        // or just show the count for the specific day if available.
        // The current API structure provides dayGridMonth for daily counts.
        // If timeGridWeek also has a 'count' per day, use that.
        // For now, let's assume monthDataMap is the source for daily counts.
        data = _monthDataMap[dateKey]; // Fallback to month data for daily count
        break;
      case CalendarViewType.day:
        // For day view, we might want to aggregate counts for the day
        data = _monthDataMap[dateKey]; // Fallback to month data for daily count
        break;
    }
    return data?.count ?? 0;
  }

  /// Get order count for a specific date-time (used in week/day views)
  int getOrderCountForDateTime(DateTime dateTime) {
    String dateTimeKey = DateConverter.formatDateTimeToYYYYMMDDHH(dateTime);
    CalenderData? data;

    switch (_currentViewType) {
      case CalendarViewType.week:
        data = _weekDataMap[dateTimeKey];
        break;
      case CalendarViewType.day:
        data = _dayDataMap[dateTimeKey];
        break;
      case CalendarViewType.month:
        // Month view uses date-based count, not datetime
        return getOrderCountForDate(dateTime);
    }

    return data?.count ?? 0;
  }

  /// Get CalenderData for a specific date (month view)
  CalenderData? getCalenderDataForDate(DateTime date) {
    String dateKey = DateConverter.formatDateToYYYYMMDD(date);
    return _monthDataMap[dateKey];
  }

  /// Get CalenderData for a specific date-time (week/day views)
  CalenderData? getCalenderDataForDateTime(DateTime dateTime) {
    String dateTimeKey = DateConverter.formatDateTimeToYYYYMMDDHH(dateTime);

    switch (_currentViewType) {
      case CalendarViewType.week:
        return _weekDataMap[dateTimeKey];
      case CalendarViewType.day:
        return _dayDataMap[dateTimeKey];
      case CalendarViewType.month:
        return getCalenderDataForDate(dateTime);
    }
  }

  /// Get bookings for a specific date from CalenderData
  List<CalenderBooking> getBookingsForDate(DateTime date) {
    CalenderData? data = getCalenderDataForDate(date);
    if (data == null) return <CalenderBooking>[];
    return List<CalenderBooking>.from(data.bookings);
  }

  /// Get bookings for a specific date-time from CalenderData
  List<CalenderBooking> getBookingsForDateTime(DateTime dateTime) {
    CalenderData? data = getCalenderDataForDateTime(dateTime);
    if (data == null) return [];
    return List<CalenderBooking>.from(data.bookings);
  }

  Color getColorForBookingStatus(String? status) {
    switch (status?.toLowerCase()) {
      case 'pending':
        return BrandTokens.warningLight; // Orange
      case 'accepted':
        return BrandTokens.infoLight; // Blue
      case 'ongoing':
        return BrandTokens.brandInk; // Purple
      case 'completed':
        return BrandTokens.successLight; // Green
      case 'canceled':
        return BrandTokens.errorLight; // Red
      default:
        return BrandTokens.textSecondaryLight; // Gray
    }
  }

  /// Reset calendar state to initial values
  /// This ensures a clean state when the screen is initialized
  void resetToInitialState() {
    _currentViewType = CalendarViewType.month;
    _selectedDate = DateTime.now();
    calendarController.view = calendarView;
    calendarController.displayDate = selectedDate;
  }
}
