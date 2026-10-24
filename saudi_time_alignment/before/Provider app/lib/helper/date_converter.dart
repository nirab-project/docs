import 'package:demandium_provider/util/core_export.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

class DateConverter {
  static String stringYear(DateTime ? dateTime) {
    return DateFormat('y').format(dateTime!);
  }

  static String dateTimeStringToDateTime(String dateTime) {
    return DateFormat('dd MMM yyyy  ${_timeFormatter()}').format(DateFormat('yyyy-MM-dd HH:mm:ss').parse(dateTime));
  }


  static String convertStringDateTo24HourFormat(String dateTime) {
    try {
      return DateFormat('HH:mm').format(DateFormat('hh:mm a').parse(dateTime));
    } catch (e) {
      // If parsing fails (e.g., input is already HH:mm), return original string
      return dateTime;
    }
  }


  static String dateToDateAndTime(DateTime ? dateTime) {
    return DateFormat('yyyy-MM-dd ${_timeFormatter()}').format(dateTime!);
  }

  static String dateTimeStringToDateOnly(String dateTime) {
    return DateFormat(_timeFormatter()).format(DateFormat('hh:mm').parse(dateTime));
  }

  static String dateTimeStringToDate(String dateTime) {
    return DateFormat('yyyy-MM-dd').format(DateTime.parse(dateTime).toLocal());
  }

  static String stringToLocalDateOnly(String dateTime) {
    return _localDateFormatter('dd MMM,yyyy').format(DateTime.parse(dateTime).toLocal());
  }

  static String timeToTimeString(TimeOfDay time) {
    DateTime dateTime = DateTime(2000, 01, 01, time.hour, time.minute);
    return DateFormat('HH:mm:ss').format(dateTime);
  }

  static DateTime dateTimeString(String dateTime) {
    return DateFormat('yyyy-MM-dd HH:mm:ss').parse(dateTime);
  }

  static DateTime isoStringToLocalDate(String dateTime) {
    return DateFormat('yyyy-MM-ddTHH:mm:ss.SSS').parse(dateTime);
  }

  static DateTime isoUtcStringToLocalDate(String dateTime) {
    return DateFormat('yyyy-MM-ddTHH:mm:ss.SSS').parse(dateTime, true).toLocal();
  }

  static DateTime isoUtcStringToLocalDateOnly(String dateTime) {
    return DateFormat('yyyy-MM-dd').parse(dateTime, true).toLocal();
  }

  static DateTime isoUtcStringToLocalTimeOnly(String dateTime) {
    return DateFormat('yyyy-MM-ddTHH:mm:ss.SSS').parse(dateTime, true).toLocal();
  }

  static String isoStringToLocalDateAndTime(String dateTime) {
    return DateFormat('dd MMM yyyy \'at\' ${_timeFormatter()}').format(isoUtcStringToLocalDate(dateTime));
  }


  static String isoStringToLocalDateOnly(String dateTime) {
    return _localDateFormatter('dd MMM, yyyy').format(isoStringToLocalDate(dateTime));
  }


  static String localDateToIsoString(DateTime dateTime) {
    return DateFormat('yyyy-MM-ddTHH:mm:ss.SSS').format(dateTime);
  }

  static String convertStringTimeToDate(DateTime time) {
    return DateFormat('EEE \'at\' ${_timeFormatter()}').format(time.toLocal());
  }

  static String convertStringTimeOnly(DateTime time) {
    return DateFormat(_timeFormatter()).format(time);
  }


  static String dateMonthYearTime(DateTime ? dateTime) {
    return _localDateFormatter('d MMM, y ${_timeFormatter()}').format(dateTime!);
  }

  static String dateStringMonthYear(DateTime ? dateTime, {String format = "d MMM, y"}) {
    return _localDateFormatter(format).format(dateTime ?? DateTime.now());
  }


  static String convert24HourTimeTo12HourTimeWithDay(DateTime time, bool isToday) {
    if(isToday){
      return DateFormat('\'Today at\' ${_timeFormatter()}').format(time);
    }else{
      return DateFormat('\'Yesterday at\' ${_timeFormatter()}').format(time);
    }
  }


  static String convert24HourTimeTo12HourTime(DateTime? time) {
    return DateFormat(_timeFormatter()).format(time ?? DateTime.now());
  }



  /// Format date as yyyy-MM-dd (e.g., 2026-01-15)
  /// Standard ISO 8601 date format, commonly used for indexing and database storage
  static String formatDateToYYYYMMDD(DateTime date) {
    return DateFormat('yyyy-MM-dd').format(date);
  }

  /// Format datetime as yyyy-MM-dd-HH (e.g., 2026-01-15-14)
  /// Useful for hourly indexing and time-based data structures
  static String formatDateTimeToYYYYMMDDHH(DateTime dateTime) {
    return DateFormat('yyyy-MM-dd-HH').format(dateTime);
  }

  /// Add time string (HH:mm:ss or HH:mm format) to a date
  /// Returns the DateTime with the time added to it
  /// If [timeString] is null or empty, returns the original date
  /// 
  /// Throws [FormatException] if the time string is invalid
  static DateTime addTimeStringToDate(DateTime date, String? timeString) {
    if (timeString == null || timeString.trim().isEmpty) return date;
    
    try {
      final timeParts = timeString.split(':');
      if (timeParts.isEmpty || timeParts.length > 3) {
        throw FormatException('Invalid time format. Expected HH:mm or HH:mm:ss, got: $timeString');
      }
      
      final hours = int.parse(timeParts[0]);
      final minutes = timeParts.length > 1 ? int.parse(timeParts[1]) : 0;
      final seconds = timeParts.length > 2 ? int.parse(timeParts[2]) : 0;
      
      // Validate time ranges
      if (hours < 0 || hours > 23) {
        throw FormatException('Invalid hour value: $hours. Must be between 0 and 23');
      }
      if (minutes < 0 || minutes > 59) {
        throw FormatException('Invalid minute value: $minutes. Must be between 0 and 59');
      }
      if (seconds < 0 || seconds > 59) {
        throw FormatException('Invalid second value: $seconds. Must be between 0 and 59');
      }
      
      return date.add(Duration(
        hours: hours,
        minutes: minutes,
        seconds: seconds,
      ));
    } on FormatException catch (e) {
      throw FormatException('Failed to parse time string "$timeString": ${e.message}');
    }
  }

  static String dateMonthYearLocalTime(DateTime dateTime) {
    return DateFormat('dd MMM yyyy, ${_timeFormatter()}').format(dateTime);
  }



  static int countDays({DateTime ? dateTime, DateTime? endDate}) {
    final start = DateTime(dateTime?.year ?? DateTime.now().year, dateTime?.month ?? DateTime.now().month, dateTime?.day ?? DateTime.now().day) ;
    final end = DateTime(endDate?.year ?? DateTime.now().year, endDate?.month ?? DateTime.now().month, endDate?.day ?? DateTime.now().day) ;
    final difference = end.difference(start).inDays + 1;
    return difference;
  }


  static String _timeFormatter() {
    return Get.find<SplashController>().configModel.content?.timeFormat == '24' ? 'HH:mm' : 'hh:mm a';
  }


  static DateFormat _localDateFormatter(String format){
    return DateFormat(format
        //Get.find<LocalizationController>().locale.languageCode
    );
  }

  static String convertDateTimeRangeToString(DateTimeRange dateRange, {String format = 'dd / MM / yy'}) {
    final startDate = DateFormat(format).format(dateRange.start);
    final endDate = DateFormat(format).format(dateRange.end);
    if (startDate == endDate) {
      return startDate;
    }
    return '$startDate  -  $endDate';
  }

  static String convertDateTimeToTime(DateTime time) {
    return DateFormat(_timeFormatter()).format(time);
  }

  /// Formats a date range into a display string with the following behavior:
  /// - If both [startDate] and [endDate] are provided: "startDate → endDate"
  /// - If only [startDate] is provided: "startDate"
  /// - If no dates are provided: returns [fallbackText]
  ///
  /// [dateFormat] can be customized, defaults to 'dd/MM/yy'
  /// [fallbackText] defaults to 'No date selected'
  static String formatDateRangeText({
    DateTime? startDate,
    DateTime? endDate,
    DateFormat? dateFormat,
    String fallbackText = 'No date selected',
  }) {
    final format = dateFormat ?? DateFormat('dd-MMM-yyyy');
    
    if (startDate != null && endDate != null) {
      return '${format.format(startDate)} → ${format.format(endDate)}';
    } else if (startDate != null) {
      return format.format(startDate);
    } else {
      return fallbackText;
    }
  }

  /// Checks if a date falls within a specified date range (inclusive)
  /// 
  /// Returns `true` if:
  /// - No filter is active (both [startDate] and [endDate] are null)
  /// - The [date] is within the range [startDate, endDate] (inclusive)
  /// 
  /// Returns `false` if the date is outside the range
  /// 
  /// The comparison ignores time components and only compares dates
  static bool isDateWithinRange(
    DateTime date, {
    DateTime? startDate,
    DateTime? endDate,
  }) {
    // If no filter is active, all dates are within range
    if (startDate == null || endDate == null) {
      return true;
    }

    // Normalize dates to compare only date parts (ignore time)
    final dateOnly = DateTime(date.year, date.month, date.day);
    final startOnly = DateTime(startDate.year, startDate.month, startDate.day);
    final endOnly = DateTime(endDate.year, endDate.month, endDate.day);

    // Check if date is within the range (inclusive)
    return (dateOnly.isAtSameMomentAs(startOnly) || dateOnly.isAfter(startOnly)) &&
           (dateOnly.isAtSameMomentAs(endOnly) || dateOnly.isBefore(endOnly));
  }


}













