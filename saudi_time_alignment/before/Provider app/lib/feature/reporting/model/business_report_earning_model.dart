import 'package:demandium_provider/feature/reporting/model/booking_report_model.dart';

class BusinessReportEarningModel {
  Content? content;

  BusinessReportEarningModel({this.content});

  BusinessReportEarningModel.fromJson(Map<String, dynamic> json) {
    content = json['content'] != null
        ? Content.fromJson(json['content'])
        : null;
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    if (content != null) {
      data['content'] = content!.toJson();
    }
    return data;
  }
}

class Content {
  List<ZonesList>? zones;
  List<ReportService>? services;
  Bookings? bookings;
  ChartData? chartData;
  String? deterministic;
  Content({
    this.zones,
    this.services,

    this.bookings,
    this.chartData,
    this.deterministic,
  });

  Content.fromJson(Map<String, dynamic> json) {
    if (json['zones'] != null) {
      zones = <ZonesList>[];
      json['zones'].forEach((v) {
        zones!.add(ZonesList.fromJson(v));
      });
    }
    if (json['serviceCatalog'] != null) {
      services = <ReportService>[];
      json['serviceCatalog'].forEach((v) {
        services!.add(ReportService.fromJson(v));
      });
    }

    bookings = json['bookings'] != null
        ? Bookings.fromJson(json['bookings'])
        : null;

    chartData = json['chart_data'] != null
        ? ChartData.fromJson(json['chart_data'])
        : null;
    deterministic = json['deterministic'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    if (zones != null) {
      data['zones'] = zones!.map((v) => v.toJson()).toList();
    }
    if (services != null) {
      data['services'] = services!.map((v) => v.toJson()).toList();
    }

    if (bookings != null) {
      data['bookings'] = bookings!.toJson();
    }

    if (chartData != null) {
      data['chart_data'] = chartData!.toJson();
    }
    return data;
  }
}

class Bookings {
  int? currentPage;
  int? lastPage;
  int? to;
  int? total;
  List<BusinessReportEarningFilterData>? filterData;

  Bookings({
    this.currentPage,
    this.lastPage,
    this.to,
    this.total,
    this.filterData,
  });

  Bookings.fromJson(Map<String, dynamic> json) {
    currentPage = json['current_page'];
    lastPage = json['last_page'];
    to = json['to'];
    total = json['total'];
    if (json['data'] != null) {
      filterData = <BusinessReportEarningFilterData>[];
      json['data'].forEach((v) {
        filterData!.add(BusinessReportEarningFilterData.fromJson(v));
      });
    }
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['current_page'] = currentPage;
    data['last_page'] = lastPage;
    data['to'] = to;
    data['total'] = total;

    if (filterData != null) {
      data['data'] = filterData!.map((v) => v.toJson()).toList();
    }
    return data;
  }
}

class BusinessReportEarningFilterData {
  String? id;
  int? readableId;
  String? customerId;
  String? providerId;
  String? zoneId;
  String? bookingStatus;
  int? isPaid;
  String? paymentMethod;
  String? transactionId;
  double? totalBookingAmount;
  double? totalTaxAmount;
  double? totalDiscountAmount;
  String? serviceSchedule;
  String? serviceAddressId;
  String? createdAt;
  String? updatedAt;

  double? totalCampaignDiscountAmount;
  double? totalCouponDiscountAmount;
  BookingDetailsAmounts? bookingDetailsAmounts;

  BusinessReportEarningFilterData({
    this.id,
    this.readableId,
    this.customerId,
    this.providerId,
    this.zoneId,
    this.bookingStatus,
    this.isPaid,
    this.paymentMethod,
    this.transactionId,
    this.totalBookingAmount,
    this.totalTaxAmount,
    this.totalDiscountAmount,
    this.serviceSchedule,
    this.serviceAddressId,
    this.createdAt,
    this.updatedAt,

    this.totalCampaignDiscountAmount,
    this.totalCouponDiscountAmount,
    this.bookingDetailsAmounts,
  });

  BusinessReportEarningFilterData.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    readableId = json['readable_id'];
    customerId = json['customer_id'];
    providerId = json['provider_id'];
    zoneId = json['zone_id'];
    bookingStatus = json['booking_status'];
    isPaid = json['is_paid'];
    paymentMethod = json['payment_method'];
    transactionId = json['transaction_id'];
    totalBookingAmount = double.tryParse(
      json['total_booking_amount'].toString(),
    );
    totalTaxAmount = double.tryParse(json['total_tax_amount'].toString());
    totalDiscountAmount = double.tryParse(
      json['total_discount_amount'].toString(),
    );
    serviceSchedule = json['service_schedule'];
    serviceAddressId = json['service_address_id'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];

    totalCampaignDiscountAmount = double.tryParse(
      json['total_campaign_discount_amount'].toString(),
    );
    totalCouponDiscountAmount = double.tryParse(
      json['total_coupon_discount_amount'].toString(),
    );
    bookingDetailsAmounts = json['booking_details_amounts'] != null
        ? BookingDetailsAmounts.fromJson(json['booking_details_amounts'])
        : null;
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['readable_id'] = readableId;
    data['customer_id'] = customerId;
    data['provider_id'] = providerId;
    data['zone_id'] = zoneId;
    data['booking_status'] = bookingStatus;
    data['is_paid'] = isPaid;
    data['payment_method'] = paymentMethod;
    data['transaction_id'] = transactionId;
    data['total_booking_amount'] = totalBookingAmount;
    data['total_tax_amount'] = totalTaxAmount;
    data['total_discount_amount'] = totalDiscountAmount;
    data['service_schedule'] = serviceSchedule;
    data['service_address_id'] = serviceAddressId;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;

    data['total_campaign_discount_amount'] = totalCampaignDiscountAmount;
    data['total_coupon_discount_amount'] = totalCouponDiscountAmount;
    if (bookingDetailsAmounts != null) {
      data['booking_details_amounts'] = bookingDetailsAmounts!.toJson();
    }
    return data;
  }
}

class BookingDetailsAmounts {
  String? id;
  String? bookingDetailsId;
  String? bookingId;
  double? serviceUnitCost;
  double? discountByAdmin;
  double? discountByProvider;
  double? couponDiscountByAdmin;
  double? couponDiscountByProvider;
  double? campaignDiscountByAdmin;
  double? campaignDiscountByProvider;
  double? adminCommission;
  double? providerEarning;
  String? createdAt;
  String? updatedAt;

  BookingDetailsAmounts({
    this.id,
    this.bookingDetailsId,
    this.bookingId,
    this.serviceUnitCost,
    this.discountByAdmin,
    this.discountByProvider,
    this.couponDiscountByAdmin,
    this.couponDiscountByProvider,
    this.campaignDiscountByAdmin,
    this.campaignDiscountByProvider,
    this.adminCommission,
    this.providerEarning,
    this.createdAt,
    this.updatedAt,
  });

  BookingDetailsAmounts.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    bookingDetailsId = json['booking_details_id'];
    bookingId = json['booking_id'];
    serviceUnitCost = double.tryParse(json['service_unit_cost'].toString());
    discountByAdmin = double.tryParse(json['discount_by_admin'].toString());
    discountByProvider = double.tryParse(
      json['discount_by_provider'].toString(),
    );
    couponDiscountByAdmin = double.tryParse(
      json['coupon_discount_by_admin'].toString(),
    );
    couponDiscountByProvider = double.tryParse(
      json['coupon_discount_by_provider'].toString(),
    );
    campaignDiscountByAdmin = double.tryParse(
      json['campaign_discount_by_admin'].toString(),
    );
    campaignDiscountByProvider = double.tryParse(
      json['campaign_discount_by_provider'].toString(),
    );
    adminCommission = double.tryParse(json['admin_commission'].toString());
    providerEarning = double.tryParse(json['provider_earning'].toString());
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['booking_details_id'] = bookingDetailsId;
    data['booking_id'] = bookingId;
    data['service_unit_cost'] = serviceUnitCost;
    data['discount_by_admin'] = discountByAdmin;
    data['discount_by_provider'] = discountByProvider;
    data['coupon_discount_by_admin'] = couponDiscountByAdmin;
    data['coupon_discount_by_provider'] = couponDiscountByProvider;
    data['campaign_discount_by_admin'] = campaignDiscountByAdmin;
    data['campaign_discount_by_provider'] = campaignDiscountByProvider;
    data['admin_commission'] = adminCommission;
    data['provider_earning'] = providerEarning;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    return data;
  }
}

class ChartData {
  List<double>? netProfit;
  List<double>? totalEarning;
  List<double>? totalExpense;
  List<int>? timeline;

  ChartData({
    this.netProfit,
    this.totalEarning,
    this.totalExpense,
    this.timeline,
  });

  ChartData.fromJson(Map<String, dynamic> json) {
    if (json['net_profit'] != null) {
      netProfit = [];
      json['net_profit'].forEach((v) {
        netProfit?.add(double.parse(v.toString()));
      });
    }
    if (json['total_earning'] != null) {
      totalEarning = [];
      json['total_earning'].forEach((v) {
        totalEarning?.add(double.parse(v.toString()));
      });
    }
    if (json['total_expense'] != null) {
      totalExpense = [];
      json['total_expense'].forEach((v) {
        totalExpense?.add(double.parse(v.toString()));
      });
    }
    timeline = json['timeline'].cast<int>();
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['net_profit'] = netProfit;
    data['total_earning'] = totalEarning;
    data['total_expense'] = totalExpense;
    data['timeline'] = timeline;
    return data;
  }
}
