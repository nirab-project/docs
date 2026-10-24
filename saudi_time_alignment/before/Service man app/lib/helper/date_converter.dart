import 'package:demandium_serviceman/utils/core_export.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

class DateConverter {
  static String dateToDateAndTime(DateTime ? dateTime) {
    return DateFormat('yyyy-MM-dd ${_timeFormatter()}').format(dateTime!);
  }

  static String convertStringTimeToDate(DateTime time) {
    return DateFormat(_timeFormatter()).format(time);
  }

  static String convertStringTimeToDateTime (DateTime time){
    return DateFormat('EEE \'at\' ${_timeFormatter()}').format(time.toLocal());
  }

  static String dateMonthYearTime(DateTime ? dateTime) {
    return DateFormat('d MMM,y, ${_timeFormatter()}').format(dateTime!);
  }

  static String dateStringMonthYear(DateTime ? dateTime) {
    return DateFormat('d MMM,y').format(dateTime!);
  }

  static String dateTimeStringToDate(String dateTime) {
    return DateFormat('yyyy-MM-dd').format(DateFormat('yyyy-MM-dd').parse(dateTime));
  }

  static String timeToTimeString(TimeOfDay time) {
    DateTime dateTime = DateTime(2000, 01, 01, time.hour, time.minute);
    return DateFormat('HH:mm:ss').format(dateTime);
  }

  static int countDays(DateTime ? dateTime) {
    final startDate = dateTime!;
    final endDate = DateTime.now();
    final difference = endDate.difference(startDate).inDays;
    return difference;
  }

  static String stringYear(DateTime ? dateTime) {
    return DateFormat('y').format(dateTime!);
  }

  static DateTime isoUtcStringToLocalDate(String dateTime) {
    return DateFormat('yyyy-MM-ddTHH:mm:ss.SSS').parse(dateTime, true).toLocal();
  }

  static String isoStringToLocalDateAndTime(String dateTime) {
    return DateFormat('dd MMM yyyy \'at\' ${_timeFormatter()}').format(isoUtcStringToLocalDate(dateTime));
  }

  static String convert24HourTimeTo12HourTimeWithDay(DateTime time, bool isToday) {
    if(isToday){
      return DateFormat('\'Today at\' ${_timeFormatter()}').format(time);
    }else{
      return DateFormat('\'Yesterday at\' ${_timeFormatter()}').format(time);
    }
  }

  static String _timeFormatter() {
    return Get.find<SplashController>().configModel?.content?.timeFormat == '24' ? 'HH:mm' : 'hh:mm a';
  }

}
