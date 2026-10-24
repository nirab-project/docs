class EasyReBookingModel {
  String? responseCode;
  String? message;
  Content? content;
  List<String>? errors;

  EasyReBookingModel({
    this.responseCode,
    this.message,
    this.content,
    this.errors,
  });

  EasyReBookingModel.fromJson(Map<String, dynamic> json) {
    responseCode = json['response_code'];
    message = json['message'];
    content = json['content'] != null
        ? Content.fromJson(json['content'])
        : null;
    errors = json['errors'].cast<String>();
  }
}

class Content {
  int? currentPage;
  List<Data>? data;
  String? firstPageUrl;
  int? from;
  int? lastPage;
  String? lastPageUrl;
  List<Links>? links;
  String? path;
  int? perPage;
  int? to;
  int? total;

  Content({
    this.currentPage,
    this.data,
    this.firstPageUrl,
    this.from,
    this.lastPage,
    this.lastPageUrl,
    this.links,
    this.path,
    this.perPage,
    this.to,
    this.total,
  });

  Content.fromJson(Map<String, dynamic> json) {
    currentPage = json['current_page'];
    if (json['data'] != null) {
      data = <Data>[];
      json['data'].forEach((v) {
        data!.add(Data.fromJson(v));
      });
    }
    firstPageUrl = json['first_page_url'];
    from = json['from'];
    lastPage = json['last_page'];
    lastPageUrl = json['last_page_url'];
    if (json['links'] != null) {
      links = <Links>[];
      json['links'].forEach((v) {
        links!.add(Links.fromJson(v));
      });
    }
    path = json['path'];
    perPage = json['per_page'];
    to = json['to'];
    total = json['total'];
  }
}

class Data {
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
  int? totalDiscountAmount;
  String? serviceSchedule;
  String? serviceAddressId;
  String? createdAt;
  String? updatedAt;

  String? servicemanId;
  double? totalCampaignDiscountAmount;
  double? totalCouponDiscountAmount;
  String? couponCode;
  int? isChecked;
  int? additionalCharge;
  int? additionalTaxAmount;
  int? additionalDiscountAmount;
  int? additionalCampaignDiscountAmount;
  String? removedCouponAmount;
  List<String>? evidencePhotos;
  String? bookingOtp;
  int? isGuest;
  int? isVerified;
  int? extraFee;
  int? totalReferralDiscountAmount;
  int? isRepeated;
  String? assignedBy;
  String? serviceLocation;
  String? serviceAddressLocation;
  int? isProviderAvailable;
  int? isServiceInfoUnchanged;
  int? isRebookingAvailable;
  List<Services>? services;
  List<String>? evidencePhotosFullPath;
  Provider? provider;

  Data({
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
    this.isChecked,
    this.additionalCharge,
    this.additionalTaxAmount,
    this.additionalDiscountAmount,
    this.additionalCampaignDiscountAmount,
    this.removedCouponAmount,
    this.evidencePhotos,
    this.bookingOtp,
    this.isGuest,
    this.isVerified,
    this.extraFee,
    this.totalReferralDiscountAmount,
    this.isRepeated,
    this.assignedBy,
    this.serviceLocation,
    this.serviceAddressLocation,
    this.isProviderAvailable,
    this.isServiceInfoUnchanged,
    this.isRebookingAvailable,
    this.services,
    this.evidencePhotosFullPath,
    this.provider,
  });

  Data.fromJson(Map<String, dynamic> json) {
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
    totalDiscountAmount = json['total_discount_amount'];
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
    couponCode = json['coupon_code'];
    isChecked = json['is_checked'];
    additionalCharge = json['additional_charge'];
    additionalTaxAmount = json['additional_tax_amount'];
    additionalDiscountAmount = json['additional_discount_amount'];
    additionalCampaignDiscountAmount =
        json['additional_campaign_discount_amount'];
    removedCouponAmount = json['removed_coupon_amount'];
    bookingOtp = json['booking_otp'];
    isGuest = json['is_guest'];
    isVerified = json['is_verified'];
    extraFee = json['extra_fee'];
    totalReferralDiscountAmount = json['total_referral_discount_amount'];
    isRepeated = json['is_repeated'];
    assignedBy = json['assigned_by'];
    serviceLocation = json['service_location'];
    serviceAddressLocation = json['service_address_location'];
    isProviderAvailable = json['is_provider_available'];
    isServiceInfoUnchanged = json['is_service_info_unchanged'];
    isRebookingAvailable = json['is_rebooking_available'];
    if (json['services'] != null) {
      services = <Services>[];
      json['services'].forEach((v) {
        services!.add(Services.fromJson(v));
      });
    }
    provider = json['provider'] != null
        ? Provider.fromJson(json['provider'])
        : null;
  }
}

class Services {
  String? serviceId;
  String? serviceName;
  String? variantKey;
  int? serviceUnitCost;
  int? bookingServiceUnitCost;
  int? isAvailable;
  int? isPriceChanged;

  Services({
    this.serviceId,
    this.serviceName,
    this.variantKey,
    this.serviceUnitCost,
    this.bookingServiceUnitCost,
    this.isAvailable,
    this.isPriceChanged,
  });

  Services.fromJson(Map<String, dynamic> json) {
    serviceId = json['service_id'];
    serviceName = json['service_name'];
    variantKey = json['variant_key'];
    serviceUnitCost = json['service_unit_cost'];
    bookingServiceUnitCost = json['booking_service_unit_cost'];
    isAvailable = json['is_available'];
    isPriceChanged = json['is_price_changed'];
  }
}

class Provider {
  String? id;
  String? companyName;
  String? companyPhone;
  String? logo;
  int? isVerified;
  String? logoFullPath;
  int? verifiedBadge;

  Provider({
    this.id,
    this.companyName,
    this.companyPhone,
    this.logo,
    this.isVerified,
    this.logoFullPath,
    this.verifiedBadge,
  });

  Provider.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    companyName = json['company_name'];
    companyPhone = json['company_phone'];
    logo = json['logo'];
    isVerified = json['is_verified'];
    logoFullPath = json['logo_full_path'];
    verifiedBadge = json['verified_badge'];
  }
}

class Links {
  String? url;
  String? label;
  int? page;
  bool? active;

  Links({this.url, this.label, this.page, this.active});

  Links.fromJson(Map<String, dynamic> json) {
    url = json['url'];
    label = json['label'];
    page = json['page'];
    active = json['active'];
  }
}
