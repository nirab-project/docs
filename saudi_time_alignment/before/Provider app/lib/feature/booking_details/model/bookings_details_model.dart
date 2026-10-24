import 'package:demandium_provider/feature/dashboard/model/dashboard_serviceman_model.dart';
import 'package:demandium_provider/feature/review/model/review_model.dart';
import 'package:demandium_provider/feature/serviceman/model/service_man_model.dart';

class BookingDetailsModel {
  String? responseCode;
  String? message;
  BookingDetailsContent? content;

  BookingDetailsModel({this.responseCode, this.message, this.content});

  BookingDetailsModel.fromJson(Map<String, dynamic> json) {
    responseCode = json['response_code'];
    message = json['message'];
    content = json['content'] != null
        ? BookingDetailsContent.fromJson(json['content'])
        : null;
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['response_code'] = responseCode;
    data['message'] = message;
    if (content != null) {
      data['content'] = content!.toJson();
    }
    return data;
  }
}

class BookingDetailsContent {
  String? id;
  String? readableId;
  String? customerId;
  String? providerId;
  String? zoneId;
  String? bookingStatus;
  Map<String, dynamic> workflow = {};
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

  List<ItemService>? details;
  List<ScheduleHistories>? scheduleHistories;
  List<StatusHistories>? statusHistories;
  List<PartialPayment>? partialPayments;
  ServiceAddress? serviceAddress;
  String? offlinePaymentMethodName;
  List<BookingOfflinePayment>? bookingOfflinePayment;
  Customer? customer;
  Provider? provider;
  BookingDetailsServiceman? serviceman;
  double? totalCampaignDiscountAmount;
  double? totalCouponDiscountAmount;
  double? additionalCharge;
  List<String>? photoEvidence;
  List<String>? photoEvidenceFullPath;
  double? extraFee;
  int? isGuest;
  double? totalReferralDiscountAmount;
  int? isRepeatBooking;
  String? time;
  String? startDate;
  String? endDate;
  int? totalCount;
  String? bookingType;
  List<String>? weekNames;
  int? completedCount;
  int? canceledCount;
  BookingDetailsContent? nextService;
  List<RepeatBooking>? repeatBookingList;
  List<RepeatHistory>? repeatEditHistory;
  BookingDetailsContent? subBooking;
  String? serviceLocation;
  double? proDiscountAmount;
  String? proDiscountType;

  BookingDetailsContent({
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
    this.details,
    this.scheduleHistories,
    this.statusHistories,
    this.partialPayments,
    this.serviceAddress,
    this.customer,
    this.provider,
    this.serviceman,
    this.totalCampaignDiscountAmount,
    this.totalCouponDiscountAmount,
    this.additionalCharge,
    this.photoEvidence,
    this.photoEvidenceFullPath,
    this.extraFee,
    this.isGuest,
    this.totalReferralDiscountAmount,

    this.bookingOfflinePayment,
    this.offlinePaymentMethodName,
    this.time,
    this.startDate,
    this.endDate,
    this.totalCount,
    this.bookingType,
    this.completedCount,
    this.canceledCount,
    this.nextService,
    this.isRepeatBooking,
    this.weekNames,
    this.repeatBookingList,
    this.subBooking,
    this.repeatEditHistory,
    this.serviceLocation,
    this.proDiscountAmount,
    this.proDiscountType,
  });

  BookingDetailsContent.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    readableId = json['readable_id'].toString();
    customerId = json['customer_id'];
    providerId = json['provider_id'];
    zoneId = json['zone_id'];
    bookingStatus = json['booking_status'];
    workflow = json['workflow'] is Map
        ? Map<String, dynamic>.from(json['workflow'])
        : {};
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

    if (json['detail'] != null) {
      details = <ItemService>[];
      json['detail'].forEach((v) {
        details!.add(ItemService.fromJson(v));
      });
    }
    if (json['schedule_histories'] != null) {
      scheduleHistories = <ScheduleHistories>[];
      json['schedule_histories'].forEach((v) {
        scheduleHistories!.add(ScheduleHistories.fromJson(v));
      });
    }
    if (json['status_histories'] != null) {
      statusHistories = <StatusHistories>[];
      json['status_histories'].forEach((v) {
        statusHistories!.add(StatusHistories.fromJson(v));
      });
    }

    if (json['booking_partial_payments'] != null) {
      partialPayments = <PartialPayment>[];
      json['booking_partial_payments'].forEach((v) {
        partialPayments!.add(PartialPayment.fromJson(v));
      });
    }

    serviceAddress = json['service_address'] != null
        ? ServiceAddress.fromJson(json['service_address'])
        : null;

    if (json['booking_offline_payment'] != null) {
      bookingOfflinePayment = <BookingOfflinePayment>[];
      json['booking_offline_payment'].forEach((v) {
        bookingOfflinePayment!.add(BookingOfflinePayment.fromJson(v));
      });
    }

    customer = json['customer'] != null
        ? Customer.fromJson(json['customer'])
        : null;
    provider = json['provider'] != null
        ? Provider.fromJson(json['provider'])
        : null;

    serviceman = json['serviceman'] != null
        ? BookingDetailsServiceman.fromJson(json['serviceman'])
        : null;
    totalCampaignDiscountAmount = double.tryParse(
      json['total_campaign_discount_amount'].toString(),
    );
    totalCouponDiscountAmount = double.tryParse(
      json['total_coupon_discount_amount'].toString(),
    );
    additionalCharge = double.tryParse(json['additional_charge'].toString());
    totalReferralDiscountAmount = double.tryParse(
      json['total_referral_discount_amount'].toString(),
    );
    photoEvidence = json["evidence_photos"] != null
        ? json["evidence_photos"].cast<String>()
        : [];
    photoEvidenceFullPath = json["evidence_photos_full_path"] != null
        ? json["evidence_photos_full_path"].cast<String>()
        : [];
    extraFee = double.tryParse(json["extra_fee"].toString());
    isGuest = int.tryParse(json["is_guest"].toString());
    offlinePaymentMethodName = json["booking_offline_payment_method"];
    isRepeatBooking = int.tryParse(json['is_repeated'].toString());
    time = json['time'];
    startDate = json['startDate'];
    endDate = json['endDate'];
    totalCount = json['totalCount'];
    bookingType = json['bookingType'];
    weekNames = json['weekNames']?.cast<String>();
    completedCount = json['completedCount'];
    canceledCount = json['canceledCount'];
    nextService = json['nextService'] != null
        ? BookingDetailsContent?.fromJson(json['nextService'])
        : null;
    if (json['repeats'] != null) {
      repeatBookingList = <RepeatBooking>[];
      json['repeats'].forEach((v) {
        repeatBookingList!.add(RepeatBooking.fromJson(v));
      });
    }
    subBooking = json['booking'] != null
        ? BookingDetailsContent.fromJson(json['booking'])
        : null;

    if (json['repeatHistory'] != null) {
      repeatEditHistory = <RepeatHistory>[];
      json['repeatHistory'].forEach((v) {
        repeatEditHistory!.add(RepeatHistory.fromJson(v));
      });
    }
    serviceLocation = json['service_location'];
    proDiscountAmount = double.tryParse('${json['pro_discount_amount']}');
    proDiscountType = json['pro_benefit_type'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['readable_id'] = readableId;
    data['customer_id'] = customerId;
    data['provider_id'] = providerId;
    data['zone_id'] = zoneId;
    data['booking_status'] = bookingStatus;
    data['workflow'] = workflow;
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
    data['booking_offline_payment_method'] = offlinePaymentMethodName;
    if (details != null) {
      data['detail'] = details!.map((v) => v.toJson()).toList();
    }
    if (scheduleHistories != null) {
      data['schedule_histories'] = scheduleHistories!
          .map((v) => v.toJson())
          .toList();
    }
    if (statusHistories != null) {
      data['status_histories'] = statusHistories!
          .map((v) => v.toJson())
          .toList();
    }
    if (serviceAddress != null) {
      data['service_address'] = serviceAddress!.toJson();
    }
    if (customer != null) {
      data['customer'] = customer!.toJson();
    }
    if (provider != null) {
      data['provider'] = provider!.toJson();
    }

    if (bookingOfflinePayment != null) {
      data['booking_offline_payment'] = bookingOfflinePayment!
          .map((v) => v.toJson())
          .toList();
    }

    if (serviceman != null) {
      data['serviceman'] = provider!.toJson();
    }
    data['service_location'] = serviceLocation;
    return data;
  }
}

class ItemService {
  String? id;
  List<Map<String, dynamic>> fieldAnswers = [];
  Map<String, dynamic>? executionSnapshot;
  String? bookingId;
  String? serviceId;
  String? serviceName;
  String? variantKey;
  double? serviceCost;
  int? quantity;
  double? discountAmount;
  double? taxAmount;
  double? totalCost;
  String? createdAt;
  String? updatedAt;
  double? campaignDiscountAmount;
  double? overallCouponDiscountAmount;
  BookingDetailsService? service;

  ItemService.copy(ItemService value) {
    quantity = value.quantity;
  }

  ItemService({
    this.id,
    this.fieldAnswers = const [],
    this.executionSnapshot,
    this.bookingId,
    this.serviceId,
    this.serviceName,
    this.variantKey,
    this.serviceCost,
    this.quantity,
    this.discountAmount,
    this.taxAmount,
    this.totalCost,
    this.createdAt,
    this.updatedAt,
    this.service,
    this.campaignDiscountAmount,
    this.overallCouponDiscountAmount,
  });

  ItemService.fromJson(Map<String, dynamic> json) {
    id = json['id']?.toString();
    fieldAnswers = (json['field_answers'] as List? ?? [])
        .map((f) => Map<String, dynamic>.from(f))
        .toList();
    executionSnapshot = json['execution_snapshot'] is Map
        ? Map<String, dynamic>.from(json['execution_snapshot'])
        : null;
    bookingId = json['booking_id']?.toString();
    serviceId = json['service_id']?.toString();
    serviceName = json['service_name'];
    variantKey = json['variant_key'];
    serviceCost = double.tryParse(json['service_cost'].toString());
    quantity = int.tryParse(json['quantity'].toString());
    discountAmount = double.tryParse(json['discount_amount'].toString());
    taxAmount = double.tryParse(json['tax_amount'].toString());
    totalCost = double.tryParse(json['total_cost'].toString());
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    campaignDiscountAmount = double.tryParse(
      json['campaign_discount_amount'].toString(),
    );
    service = json['service'] != null
        ? BookingDetailsService.fromJson(json['service'])
        : null;
    overallCouponDiscountAmount = double.tryParse(
      json['overall_coupon_discount_amount'].toString(),
    );
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    if (id != null) data['id'] = id;
    if (fieldAnswers.isNotEmpty) data['field_answers'] = fieldAnswers;
    if (executionSnapshot != null) {
      data['execution_snapshot'] = executionSnapshot;
    }
    data['booking_id'] = bookingId;
    data['service_id'] = serviceId;
    data['service_name'] = serviceName;
    data['variant_key'] = variantKey;
    data['service_cost'] = serviceCost;
    data['quantity'] = quantity;
    data['discount_amount'] = discountAmount;
    data['tax_amount'] = taxAmount;
    data['total_cost'] = totalCost;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    data['campaign_discount_amount'] = campaignDiscountAmount;
    if (service != null) {
      data['service'] = service!.toJson();
    }
    return data;
  }
}

class BookingDetailsService {
  String? id;
  String? name;
  String? shortDescription;
  String? description;
  String? coverImage;
  String? thumbnail;
  String? thumbnailFullPath;

  double? tax;
  int? orderCount;
  int? isActive;
  int? ratingCount;
  String? createdAt;
  String? updatedAt;

  BookingDetailsService({
    this.id,
    this.name,
    this.shortDescription,
    this.description,
    this.coverImage,
    this.thumbnail,
    this.thumbnailFullPath,

    this.tax,
    this.orderCount,
    this.isActive,
    this.ratingCount,
    this.createdAt,
    this.updatedAt,
  });

  BookingDetailsService.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    name = json['name'];
    shortDescription = json['short_description'];
    description = json['description'];
    coverImage = json['cover_image'];
    thumbnail = json['thumbnail'];
    thumbnailFullPath = json['thumbnail_full_path'];

    tax = double.tryParse(json['tax'].toString());
    orderCount = json['order_count'];
    isActive = json['is_active'];
    ratingCount = json['rating_count'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['name'] = name;
    data['short_description'] = shortDescription;
    data['description'] = description;
    data['cover_image'] = coverImage;
    data['thumbnail'] = thumbnail;
    data['thumbnail_full_path'] = thumbnailFullPath;

    data['tax'] = tax;
    data['order_count'] = orderCount;
    data['is_active'] = isActive;
    data['rating_count'] = ratingCount;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    return data;
  }
}

class ScheduleHistories {
  int? id;
  String? bookingId;
  String? changedBy;
  String? schedule;
  String? createdAt;
  String? updatedAt;
  User? user;

  ScheduleHistories({
    this.id,
    this.bookingId,
    this.changedBy,
    this.schedule,
    this.createdAt,
    this.updatedAt,
    this.user,
  });

  ScheduleHistories.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    bookingId = json['booking_id'];
    changedBy = json['changed_by'];
    schedule = json['schedule'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    user = json['user'] != null ? User.fromJson(json['user']) : null;
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['booking_id'] = bookingId;
    data['changed_by'] = changedBy;
    data['schedule'] = schedule;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    if (user != null) {
      data['user'] = user!.toJson();
    }
    return data;
  }
}

class StatusHistories {
  int? id;
  String? bookingId;
  String? changedBy;
  String? bookingStatus;
  String? createdAt;
  String? updatedAt;
  User? user;

  StatusHistories({
    this.id,
    this.bookingId,
    this.changedBy,
    this.bookingStatus,
    this.createdAt,
    this.updatedAt,
    this.user,
  });

  StatusHistories.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    bookingId = json['booking_id'];
    changedBy = json['changed_by'];
    bookingStatus = json['booking_status'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    user = json['user'] != null ? User.fromJson(json['user']) : null;
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['booking_id'] = bookingId;
    data['changed_by'] = changedBy;
    data['booking_status'] = bookingStatus;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    if (user != null) {
      data['user'] = user!.toJson();
    }
    return data;
  }
}

class ServiceAddress {
  int? id;
  String? userId;
  double? lat;
  double? lon;
  String? city;
  String? house;
  String? floor;
  String? street;
  String? zipCode;
  String? country;
  String? address;
  String? createdAt;
  String? updatedAt;
  String? addressType;
  String? contactPersonName;
  String? contactPersonNumber;
  String? addressLabel;

  ServiceAddress({
    this.id,
    this.userId,
    this.lat,
    this.lon,
    this.city,
    this.street,
    this.zipCode,
    this.country,
    this.address,
    this.createdAt,
    this.updatedAt,
    this.addressType,
    this.contactPersonName,
    this.contactPersonNumber,
    this.addressLabel,
    this.floor,
    this.house,
  });

  ServiceAddress.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    userId = json['user_id'];
    lat = double.tryParse(json['lat'].toString());
    lon = double.tryParse(json['lon'].toString());
    city = json['city'];
    house = json['house'];
    floor = json['floor'];
    street = json['street'];
    zipCode = json['zip_code'];
    country = json['country'];
    address = json['address'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    addressType = json['address_type'];
    contactPersonName = json['contact_person_name'];
    contactPersonNumber = json['contact_person_number'];
    addressLabel = json['address_label'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['user_id'] = userId;
    data['lat'] = lat;
    data['lon'] = lon;
    data['city'] = city;
    data['house'] = house;
    data['floor'] = floor;
    data['street'] = street;
    data['zip_code'] = zipCode;
    data['country'] = country;
    data['address'] = address;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    data['address_type'] = addressType;
    data['contact_person_name'] = contactPersonName;
    data['contact_person_number'] = contactPersonNumber;
    data['address_label'] = addressLabel;
    return data;
  }
}

class Customer {
  String? id;
  String? firstName;
  String? lastName;
  String? email;
  String? phone;
  String? identificationType;
  String? gender;
  String? profileImage;
  String? profileImageFullPath;
  int? isPhoneVerified;
  int? isEmailVerified;
  int? isActive;
  String? userType;
  String? createdAt;
  String? updatedAt;

  Customer({
    this.id,
    this.firstName,
    this.lastName,
    this.email,
    this.phone,
    this.identificationType,
    this.gender,
    this.profileImage,
    this.profileImageFullPath,
    this.isPhoneVerified,
    this.isEmailVerified,
    this.isActive,
    this.userType,
    this.createdAt,
    this.updatedAt,
  });

  Customer.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    firstName = json['first_name'];
    lastName = json['last_name'];
    email = json['email'];
    phone = json['phone'];
    identificationType = json['identification_type'];
    gender = json['gender'];
    profileImage = json['profile_image'];
    profileImageFullPath = json['profile_image_full_path'];
    isPhoneVerified = json['is_phone_verified'];
    isEmailVerified = json['is_email_verified'];
    isActive = json['is_active'];
    userType = json['user_type'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['first_name'] = firstName;
    data['last_name'] = lastName;
    data['email'] = email;
    data['phone'] = phone;
    data['identification_type'] = identificationType;
    data['gender'] = gender;
    data['profile_image'] = profileImage;
    data['profile_image_full_path'] = profileImageFullPath;
    data['is_phone_verified'] = isPhoneVerified;
    data['is_email_verified'] = isEmailVerified;
    data['is_active'] = isActive;
    data['user_type'] = userType;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    return data;
  }
}

class BookingDetailsServiceman {
  String? id;
  String? providerId;
  String? userId;
  String? createdAt;
  String? updatedAt;
  ServicemanModel? user;

  BookingDetailsServiceman({
    this.id,
    this.providerId,
    this.userId,
    this.createdAt,
    this.updatedAt,
    this.user,
  });

  BookingDetailsServiceman.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    providerId = json['provider_id'];
    userId = json['user_id'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    user = json['user'] != null ? ServicemanModel.fromJson(json['user']) : null;
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['provider_id'] = providerId;
    data['user_id'] = userId;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    if (user != null) {
      data['user'] = user!.toJson();
    }
    return data;
  }
}

class PartialPayment {
  String? id;
  String? bookingId;
  String? paidWith;
  double? paidAmount;
  double? dueAmount;
  String? createdAt;
  String? updatedAt;

  PartialPayment({
    this.id,
    this.bookingId,
    this.paidWith,
    this.paidAmount,
    this.dueAmount,
    this.createdAt,
    this.updatedAt,
  });

  PartialPayment.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    bookingId = json['booking_id'];
    paidWith = json['paid_with'];
    paidAmount = double.tryParse(json['paid_amount'].toString());
    dueAmount = double.tryParse(json['due_amount'].toString());
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['booking_id'] = bookingId;
    data['paid_with'] = paidWith;
    data['paid_amount'] = paidAmount;
    data['due_amount'] = dueAmount;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    return data;
  }
}

class BookingOfflinePayment {
  String? key;
  String? value;

  BookingOfflinePayment({String? key, String? value}) {
    if (key != null) {
      key = key;
    }
    if (value != null) {
      value = value;
    }
  }

  BookingOfflinePayment.fromJson(Map<String, dynamic> json) {
    key = json['key'];
    value = json['value'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['key'] = key;
    data['value'] = value;
    return data;
  }
}

class RepeatBooking {
  String? id;
  String? readableId;
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

  List<ItemService>? details;
  List<ScheduleHistories>? scheduleHistories;
  List<StatusHistories>? statusHistories;
  List<PartialPayment>? partialPayments;
  ServiceAddress? serviceAddress;
  BookingDetailsServiceman? serviceman;
  String? totalCampaignDiscountAmount;
  String? totalCouponDiscountAmount;
  double? additionalCharge;
  List<String>? photoEvidence;
  List<String>? photoEvidenceFullPath;
  double? extraFee;
  int? isGuest;
  double? totalReferralDiscountAmount;
  String? serviceLocation;

  RepeatBooking({
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
    this.details,
    this.scheduleHistories,
    this.statusHistories,
    this.partialPayments,
    this.serviceAddress,
    this.serviceman,
    this.totalCampaignDiscountAmount,
    this.totalCouponDiscountAmount,
    this.additionalCharge,
    this.photoEvidence,
    this.photoEvidenceFullPath,
    this.extraFee,
    this.isGuest,
    this.totalReferralDiscountAmount,

    this.serviceLocation,
  });

  RepeatBooking.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    readableId = json['readable_id'].toString();
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

    if (json['detail'] != null) {
      details = <ItemService>[];
      json['detail'].forEach((v) {
        details!.add(ItemService.fromJson(v));
      });
    }
    if (json['schedule_histories'] != null) {
      scheduleHistories = <ScheduleHistories>[];
      json['schedule_histories'].forEach((v) {
        scheduleHistories!.add(ScheduleHistories.fromJson(v));
      });
    }
    if (json['status_histories'] != null) {
      statusHistories = <StatusHistories>[];
      json['status_histories'].forEach((v) {
        statusHistories!.add(StatusHistories.fromJson(v));
      });
    }

    if (json['booking_partial_payments'] != null) {
      partialPayments = <PartialPayment>[];
      json['booking_partial_payments'].forEach((v) {
        partialPayments!.add(PartialPayment.fromJson(v));
      });
    }

    serviceAddress = json['service_address'] != null
        ? ServiceAddress.fromJson(json['service_address'])
        : null;
    serviceman = json['serviceman'] != null
        ? BookingDetailsServiceman.fromJson(json['serviceman'])
        : null;
    totalCampaignDiscountAmount = json['total_campaign_discount_amount']
        .toString();
    totalCouponDiscountAmount = json['total_coupon_discount_amount'].toString();
    additionalCharge = double.tryParse(json['additional_charge'].toString());
    totalReferralDiscountAmount = double.tryParse(
      json['total_referral_discount_amount'].toString(),
    );
    photoEvidence = json["evidence_photos"] != null
        ? json["evidence_photos"].cast<String>()
        : [];
    photoEvidenceFullPath = json["evidence_photos_full_path"] != null
        ? json["evidence_photos_full_path"].cast<String>()
        : [];
    extraFee = double.tryParse(json["extra_fee"].toString());
    isGuest = int.tryParse(json["is_guest"].toString());
    serviceLocation = json['service_location'];
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
    if (details != null) {
      data['detail'] = details!.map((v) => v.toJson()).toList();
    }
    if (scheduleHistories != null) {
      data['schedule_histories'] = scheduleHistories!
          .map((v) => v.toJson())
          .toList();
    }
    if (statusHistories != null) {
      data['status_histories'] = statusHistories!
          .map((v) => v.toJson())
          .toList();
    }
    if (serviceAddress != null) {
      data['service_address'] = serviceAddress!.toJson();
    }
    return data;
  }
}

class RepeatHistory {
  int? id;
  String? bookingId;
  String? bookingRepeatId;
  String? bookingRepeatDetailsId;
  String? readableId;
  int? oldQuantity;
  int? newQuantity;
  int? isMultiple;
  double? totalBookingAmount;
  double? totalTaxAmount;
  double? totalDiscountAmount;
  double? extraFee;
  String? createdAt;
  String? updatedAt;
  List<RepeatHistoryLog>? repeatHistoryLogs;

  RepeatHistory({
    this.id,
    this.bookingId,
    this.bookingRepeatId,
    this.bookingRepeatDetailsId,
    this.readableId,
    this.oldQuantity,
    this.newQuantity,
    this.isMultiple,
    this.createdAt,
    this.updatedAt,
    this.repeatHistoryLogs,
    this.totalBookingAmount,
    this.totalDiscountAmount,
    this.totalTaxAmount,
    this.extraFee,
  });

  RepeatHistory.fromJson(Map<String, dynamic> json) {
    id = int.tryParse(json['id'].toString());
    bookingId = json['booking_id'];
    bookingRepeatId = json['booking_repeat_id'];
    bookingRepeatDetailsId = json['booking_repeat_details_id'];
    readableId = json['readable_id'];
    oldQuantity = int.tryParse(json['old_quantity'].toString());
    newQuantity = int.tryParse(json['new_quantity'].toString());
    isMultiple = int.tryParse(json['is_multiple'].toString());
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    totalBookingAmount = double.tryParse(
      json['total_booking_amount'].toString(),
    );
    totalTaxAmount = double.tryParse(json['total_tax_amount'].toString());
    totalDiscountAmount = double.tryParse(
      json['total_discount_amount'].toString(),
    );
    extraFee = double.tryParse(json['extra_fee'].toString());
    if (json['log_details'] != null) {
      repeatHistoryLogs = <RepeatHistoryLog>[];
      json['log_details'].forEach((v) {
        repeatHistoryLogs!.add(RepeatHistoryLog.fromJson(v));
      });
    }
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['booking_id'] = bookingId;
    data['booking_repeat_id'] = bookingRepeatId;
    data['booking_repeat_details_id'] = bookingRepeatDetailsId;
    data['readable_id'] = readableId;
    data['old_quantity'] = oldQuantity;
    data['new_quantity'] = newQuantity;
    data['is_multiple'] = isMultiple;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    return data;
  }
}

class RepeatHistoryLog {
  String? serviceId;
  int? quantity;
  String? variantKey;
  String? serviceName;
  double? serviceCost;
  double? discountAmount;
  double? taxAmount;
  double? totalCost;
  String? repeatDetailsId;

  RepeatHistoryLog({
    this.serviceId,
    this.quantity,
    this.variantKey,
    this.serviceName,
    this.serviceCost,
    this.discountAmount,
    this.taxAmount,
    this.totalCost,
    this.repeatDetailsId,
  });

  RepeatHistoryLog.fromJson(Map<String, dynamic> json) {
    serviceId = json['service_id'];
    quantity = json['quantity'];
    variantKey = json['variant_key'].toString();
    serviceName = json['service_name'];
    serviceCost = double.tryParse(json['service_cost'].toString());
    discountAmount = double.tryParse(json['discount_amount'].toString());
    taxAmount = double.tryParse(json['tax_amount'].toString());
    totalCost = double.tryParse(json['total_cost'].toString());
    repeatDetailsId = json['repeat_details_id'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['service_id'] = serviceId;
    data['quantity'] = quantity;
    data['variant_key'] = variantKey;
    data['service_name'] = serviceName;
    data['service_cost'] = serviceCost;
    data['discount_amount'] = discountAmount;
    data['tax_amount'] = taxAmount;
    data['total_cost'] = totalCost;
    data['repeat_details_id'] = repeatDetailsId;
    return data;
  }
}
