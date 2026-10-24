import 'package:demandium_provider/feature/reporting/model/booking_report_model.dart';

class BusinessReportExpenseModel {
  Content? content;
  BusinessReportExpenseModel({this.content});

  BusinessReportExpenseModel.fromJson(Map<String, dynamic> json) {
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
  FilteredBookingAmounts? filteredBookingAmounts;
  ChartData? chartData;
  TotalPromotionalCost? totalPromotionalCost;
  String? deterministic;

  Content({
    this.zones,
    this.services,

    this.filteredBookingAmounts,
    this.chartData,
    this.totalPromotionalCost,
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

    filteredBookingAmounts = json['filtered_booking_amounts'] != null
        ? FilteredBookingAmounts.fromJson(json['filtered_booking_amounts'])
        : null;
    chartData = json['chart_data'] != null
        ? ChartData.fromJson(json['chart_data'])
        : null;
    totalPromotionalCost = json['total_promotional_cost'] != null
        ? TotalPromotionalCost.fromJson(json['total_promotional_cost'])
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

    if (filteredBookingAmounts != null) {
      data['filtered_booking_amounts'] = filteredBookingAmounts!.toJson();
    }
    if (chartData != null) {
      data['chart_data'] = chartData!.toJson();
    }
    if (totalPromotionalCost != null) {
      data['total_promotional_cost'] = totalPromotionalCost!.toJson();
    }
    data['deterministic'] = deterministic;
    return data;
  }
}

class FilteredBookingAmounts {
  int? currentPage;
  List<BusinessReportFilterData>? data;

  int? lastPage;
  int? total;

  FilteredBookingAmounts({
    this.currentPage,
    this.data,
    this.lastPage,
    this.total,
  });

  FilteredBookingAmounts.fromJson(Map<String, dynamic> json) {
    currentPage = json['current_page'];
    if (json['data'] != null) {
      data = <BusinessReportFilterData>[];
      json['data'].forEach((v) {
        data!.add(BusinessReportFilterData.fromJson(v));
      });
    }
    lastPage = json['last_page'];
    total = json['total'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['current_page'] = currentPage;
    if (this.data != null) {
      data['data'] = this.data!.map((v) => v.toJson()).toList();
    }
    data['last_page'] = lastPage;
    data['total'] = total;
    return data;
  }
}

class BusinessReportFilterData {
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
  String? createdAt;
  String? updatedAt;
  Booking? booking;

  BusinessReportFilterData({
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
    this.createdAt,
    this.updatedAt,
    this.booking,
  });

  BusinessReportFilterData.fromJson(Map<String, dynamic> json) {
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
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    booking = json['booking'] != null
        ? Booking.fromJson(json['booking'])
        : null;
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
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    if (booking != null) {
      data['booking'] = booking!.toJson();
    }
    return data;
  }
}

class Booking {
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

  String? servicemanId;
  double? totalCampaignDiscountAmount;
  double? totalCouponDiscountAmount;

  Booking({
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

    this.servicemanId,
    this.totalCampaignDiscountAmount,
    this.totalCouponDiscountAmount,
  });

  Booking.fromJson(Map<String, dynamic> json) {
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

    servicemanId = json['serviceman_id'];
    totalCampaignDiscountAmount = double.tryParse(
      json['total_campaign_discount_amount'].toString(),
    );
    totalCouponDiscountAmount = double.tryParse(
      json['total_coupon_discount_amount'].toString(),
    );
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

    data['serviceman_id'] = servicemanId;
    data['total_campaign_discount_amount'] = totalCampaignDiscountAmount;
    data['total_coupon_discount_amount'] = totalCouponDiscountAmount;
    return data;
  }
}

class ChartData {
  List<double>? expenses;
  List<int>? timeline;

  ChartData({this.expenses, this.timeline});

  ChartData.fromJson(Map<String, dynamic> json) {
    if (json['expenses'] != null) {
      expenses = [];
      json['expenses'].forEach((v) {
        expenses?.add(double.parse(v.toString()));
      });
    }
    timeline = json['timeline'].cast<int>();
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['expenses'] = expenses;
    data['timeline'] = timeline;
    return data;
  }
}

class TotalPromotionalCost {
  double? totalExpense;
  double? discount;
  double? coupon;
  double? campaign;

  TotalPromotionalCost({
    this.totalExpense,
    this.discount,
    this.coupon,
    this.campaign,
  });

  TotalPromotionalCost.fromJson(Map<String, dynamic> json) {
    totalExpense = double.tryParse(json['total_expense'].toString());
    discount = double.tryParse(json['discount'].toString());
    coupon = double.tryParse(json['coupon'].toString());
    campaign = double.tryParse(json['campaign'].toString());
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['total_expense'] = totalExpense;
    data['discount'] = discount;
    data['coupon'] = coupon;
    data['campaign'] = campaign;
    return data;
  }
}
