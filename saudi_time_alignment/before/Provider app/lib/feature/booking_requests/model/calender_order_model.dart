// To parse this JSON data, do
//
//     final calenderOrderModel = calenderOrderModelFromJson(jsonString);

import 'dart:convert';

import 'package:demandium_provider/common/enums/enums.dart';

CalenderOrderModel calenderOrderModelFromJson(String str) => CalenderOrderModel.fromJson(json.decode(str));

String calenderOrderModelToJson(CalenderOrderModel data) => json.encode(data.toJson());

class CalenderOrderModel {
  List<CalenderData>? dayGridMonth;
  List<CalenderData>? timeGridWeek;
  List<CalenderData>? timeGridDay;
  DateTime? filterStartDate;
  DateTime? filterEndDate;
  ServiceType? bookingType;
  List<BookingStatusEnum>? bookingStatus;

  CalenderOrderModel({
    this.dayGridMonth,
    this.timeGridWeek,
    this.timeGridDay,
    this.filterStartDate,
    this.filterEndDate,
    this.bookingType,
    this.bookingStatus,
  });

  factory CalenderOrderModel.fromJson(Map<String, dynamic> json) => CalenderOrderModel(
    dayGridMonth: json["dayGridMonth"] != null ? List<CalenderData>.from(json["dayGridMonth"].map((x) => CalenderData.fromJson(x))) : null,
    timeGridWeek: json["timeGridWeek"] != null ?  List<CalenderData>.from(json["timeGridWeek"].map((x) => CalenderData.fromJson(x))) : null,
    timeGridDay: json["timeGridDay"] != null ? List<CalenderData>.from(json["timeGridDay"].map((x) => CalenderData.fromJson(x))) : null,
    filterStartDate: json["filter_start_date"] != null ? DateTime.tryParse(json["filter_start_date"]) : null,
    filterEndDate: json["filter_end_date"] != null ? DateTime.tryParse(json["filter_end_date"]) : null,
    bookingType: json["booking_type"] != null ? ServiceTypeExtension.serviceTypeFromValue(json["booking_type"]) : null,
    bookingStatus: json["booking_status"] != null
        ? List<BookingStatusEnum>.from(json["booking_status"].map((x) =>
        BookingStatusEnumExtension.fromValue(x)).where((x) => x != null)) : null,
  );

  Map<String, dynamic> toJson() => {
    "dayGridMonth": dayGridMonth != null ? List<dynamic>.from(dayGridMonth!.map((x) => x.toJson())) : null,
    "timeGridWeek": timeGridWeek != null ? List<dynamic>.from(timeGridWeek!.map((x) => x.toJson())) : null,
    "timeGridDay": timeGridDay != null ? List<dynamic>.from(timeGridDay!.map((x) => x.toJson())) : null,
    "filter_start_date": filterStartDate,
    "filter_end_date": filterEndDate,
    "booking_type": bookingType?.value,
    "booking_status": bookingStatus != null ? List<dynamic>.from(bookingStatus!.map((x) => x.value)) : null,
  };
}

class CalenderData {
  String mode;
  int count;
  DateTime start;
  DateTime end;
  String? startHourTime;
  String? endHourTime;
  List<CalenderBooking> bookings;

  CalenderData({
    required this.mode,
    required this.count,
    required this.start,
    required this.end,
    this.startHourTime,
    this.endHourTime,
    required this.bookings,
  });

  factory CalenderData.fromJson(Map<String, dynamic> json) => CalenderData(
    mode: json["mode"],
    count: json["count"],
    start: DateTime.parse(json["start"]),
    end: DateTime.parse(json["end"]),
    startHourTime: json["start_hour_time"],
    endHourTime: json["end_hour_time"],
    bookings: List<CalenderBooking>.from(json["bookings"].map((x) => CalenderBooking.fromJson(x))),
  );

  Map<String, dynamic> toJson() => {
    "mode": mode,
    "count": count,
    "start": "${start.year.toString().padLeft(4, '0')}-${start.month.toString().padLeft(2, '0')}-${start.day.toString().padLeft(2, '0')}",
    "end": "${end.year.toString().padLeft(4, '0')}-${end.month.toString().padLeft(2, '0')}-${end.day.toString().padLeft(2, '0')}",
    "start_hour_time": startHourTime,
    "end_hour_time": endHourTime,
    "bookings": List<dynamic>.from(bookings.map((x) => x.toJson())),
  };
}

class CalenderBooking {
  String id;
  int readableId;
  DateTime serviceSchedule;
  String bookingStatus;
  ServiceLocation serviceLocation;
  double totalBookingAmount;
  DateTime createdAt;
  bool? isRepeatBooking;


  CalenderBooking({
    required this.id,
    required this.readableId,
    required this.serviceSchedule,
    required this.bookingStatus,
    required this.serviceLocation,
    required this.totalBookingAmount,
    required this.createdAt,
    required this.isRepeatBooking,
  });

  factory CalenderBooking.fromJson(Map<String, dynamic> json) => CalenderBooking(
    id: json["id"],
    readableId: json["readable_id"],
    serviceSchedule: DateTime.parse(json["service_schedule"]),
    bookingStatus: json["booking_status"],
    serviceLocation: serviceLocationValues.map[json["service_location"]]!,
    totalBookingAmount: double.tryParse('${json["total_booking_amount"]}') ?? 0,
    createdAt: DateTime.parse(json["created_at"]),
    isRepeatBooking: '${json["is_repeated"]}'.contains('1'),
  );

  Map<String, dynamic> toJson() => {
    "id": id,
    "readable_id": readableId,
    "service_schedule": serviceSchedule.toIso8601String(),
    "booking_status": bookingStatus,
    "service_location": serviceLocationValues.reverse[serviceLocation],
    "total_booking_amount": totalBookingAmount,
    "created_at": createdAt.toIso8601String(),
    "is_repeated": isRepeatBooking,
  };
}

enum ServiceLocation {
  customer,
  provider
}

final serviceLocationValues = EnumValues({
  "customer": ServiceLocation.customer,
  "provider": ServiceLocation.provider
});

class EnumValues<T> {
  Map<String, T> map;
  late Map<T, String> reverseMap;

  EnumValues(this.map);

  Map<T, String> get reverse {
    reverseMap = map.map((k, v) => MapEntry(v, k));
    return reverseMap;
  }
}
