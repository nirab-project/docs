import 'package:demandium_provider/feature/dashboard/model/dashboard_json.dart';
import 'package:demandium_provider/feature/service_details/model/service_details_model.dart';

class DashboardRecentActivityModel {
  String? id;
  int? readableId;
  String? customerId;
  String? providerId;
  String? zoneId;
  String? bookingStatus;
  int? isPaid;
  String? paymentMethod;
  String? transactionId;
  String? totalBookingAmount;
  String? totalTaxAmount;
  String? totalDiscountAmount;
  String? serviceSchedule;
  String? serviceAddressId;
  String? createdAt;
  String? updatedAt;

  String? servicemanId;
  String? totalCampaignDiscountAmount;
  String? totalCouponDiscountAmount;
  String? couponCode;
  List<Detail>? detail;
  int? isRepeatBooking;

  DashboardRecentActivityModel({
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
    this.couponCode,
    this.detail,
    this.isRepeatBooking,
  });

  DashboardRecentActivityModel.fromJson(Map<String, dynamic> json) {
    id = json['id']?.toString();
    readableId = dashboardInt(json['readable_id']);
    customerId = json['customer_id']?.toString();
    providerId = json['provider_id']?.toString();
    zoneId = json['zone_id']?.toString();
    bookingStatus = json['booking_status']?.toString();
    isPaid = dashboardInt(json['is_paid']);
    paymentMethod = json['payment_method']?.toString();
    transactionId = json['transaction_id']?.toString();
    totalBookingAmount = json['total_booking_amount'].toString();
    totalTaxAmount = json['total_tax_amount'].toString();
    totalDiscountAmount = json['total_discount_amount'].toString();
    serviceSchedule = json['service_schedule']?.toString();
    serviceAddressId = json['service_address_id']?.toString();
    createdAt = json['created_at']?.toString();
    updatedAt = json['updated_at']?.toString();

    servicemanId = json['serviceman_id']?.toString();
    totalCampaignDiscountAmount = json['total_campaign_discount_amount']
        .toString();
    totalCouponDiscountAmount = json['total_coupon_discount_amount'].toString();
    couponCode = json['coupon_code']?.toString();
    detail = (json['detail'] is List ? json['detail'] as List : const [])
        .map(dashboardMap)
        .whereType<Map<String, dynamic>>()
        .map(Detail.fromJson)
        .toList();
    isRepeatBooking = int.tryParse(json['is_repeated'].toString());
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
    data['coupon_code'] = couponCode;
    if (detail != null) {
      data['detail'] = detail!.map((v) => v.toJson()).toList();
    }
    return data;
  }
}

class Detail {
  int? id;
  String? bookingId;
  String? serviceId;
  String? variantKey;
  String? serviceCost;
  int? quantity;
  String? discountAmount;
  String? taxAmount;
  String? totalCost;
  String? createdAt;
  String? updatedAt;
  String? campaignDiscountAmount;
  String? overallCouponDiscountAmount;
  ServiceModel? service;

  Detail({
    this.id,
    this.bookingId,
    this.serviceId,
    this.variantKey,
    this.serviceCost,
    this.quantity,
    this.discountAmount,
    this.taxAmount,
    this.totalCost,
    this.createdAt,
    this.updatedAt,
    this.campaignDiscountAmount,
    this.overallCouponDiscountAmount,
    this.service,
  });

  Detail.fromJson(Map<String, dynamic> json) {
    id = dashboardInt(json['id']);
    bookingId = json['booking_id']?.toString();
    serviceId = json['service_id']?.toString();
    variantKey = json['variant_key']?.toString();
    serviceCost = json['service_cost'].toString();
    quantity = dashboardInt(json['quantity']);
    discountAmount = json['discount_amount'].toString();
    taxAmount = json['tax_amount'].toString();
    totalCost = json['total_cost'].toString();
    createdAt = json['created_at']?.toString();
    updatedAt = json['updated_at']?.toString();
    campaignDiscountAmount = json['campaign_discount_amount'].toString();
    overallCouponDiscountAmount = json['overall_coupon_discount_amount']
        .toString();
    final serviceJson = dashboardMap(json['service']);
    service = serviceJson == null
        ? null
        : ServiceModel(
            id: serviceJson['id']?.toString(),
            name: serviceJson['name']?.toString(),
            thumbnail: serviceJson['thumbnail']?.toString(),
            thumbnailFullPath: serviceJson['thumbnail_full_path']?.toString(),
          );
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['booking_id'] = bookingId;
    data['service_id'] = serviceId;
    data['variant_key'] = variantKey;
    data['service_cost'] = serviceCost;
    data['quantity'] = quantity;
    data['discount_amount'] = discountAmount;
    data['tax_amount'] = taxAmount;
    data['total_cost'] = totalCost;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    data['campaign_discount_amount'] = campaignDiscountAmount;
    data['overall_coupon_discount_amount'] = overallCouponDiscountAmount;
    if (service != null) {
      data['service'] = service!.toJson();
    }
    return data;
  }
}
