import 'package:demandium_provider/helper/date_converter.dart';

Map<String, dynamic>? dashboardMap(dynamic value) => value is Map
    ? value.map((key, value) => MapEntry(key.toString(), value))
    : null;

double? dashboardNumber(dynamic value) {
  final number = value is num ? value.toDouble() : double.tryParse('$value');
  return number != null && number.isFinite ? number : null;
}

int? dashboardInt(dynamic value) => dashboardNumber(value)?.toInt();

String dashboardDateLabel(String? value) {
  final parsed = DateTime.tryParse(value ?? '');
  // The API's timestamps without an offset are UTC, as in DateConverter.
  final date = parsed == null
      ? null
      : (parsed.isUtc
                ? parsed
                : DateTime.utc(
                    parsed.year,
                    parsed.month,
                    parsed.day,
                    parsed.hour,
                    parsed.minute,
                    parsed.second,
                    parsed.millisecond,
                    parsed.microsecond,
                  ))
            .toLocal();
  if (date == null) return '—';
  return DateConverter.dateMonthYearTime(date);
}
