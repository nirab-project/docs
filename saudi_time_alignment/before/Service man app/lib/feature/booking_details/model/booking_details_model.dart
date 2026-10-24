import 'dart:convert';
import 'package:demandium_serviceman/utils/core_export.dart';

class BookingDetailsModel {
  String? responseCode;
  String? message;
  BookingContent? bookingContent;

  BookingDetailsModel({this.responseCode, this.message, this.bookingContent});
  BookingDetailsModel.fromJson(Map<String, dynamic> json) {
    responseCode = json['response_code'];
    message = json['message'];
    bookingContent = json['content'] != null
        ? BookingContent.fromJson(json['content'])
        : null;
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['response_code'] = responseCode;
    data['message'] = message;
    if (bookingContent != null) {
      data['content'] = bookingContent!.toJson();
    }

    return data;
  }
}

class BookingContent {
  BookingDetailsContent? bookingDetailsContent;
  int? providerServicemanCanCancelBooking;
  int? providerServicemanCanEditBooking;

  BookingContent({
    this.bookingDetailsContent,
    this.providerServicemanCanCancelBooking,
    this.providerServicemanCanEditBooking,
  });

  BookingContent.fromJson(Map<String, dynamic> json) {
    bookingDetailsContent = json['booking'] != null
        ? BookingDetailsContent.fromJson(json['booking'])
        : null;
    providerServicemanCanCancelBooking = int.tryParse(
      json['provider_serviceman_can_cancel_booking'].toString(),
    );
    providerServicemanCanEditBooking = int.tryParse(
      json['provider_serviceman_can_edit_booking'].toString(),
    );
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    if (bookingDetailsContent != null) {
      data['booking'] = bookingDetailsContent!.toJson();
    }
    data['provider_serviceman_can_cancel_booking'] =
        providerServicemanCanCancelBooking;
    data['provider_serviceman_can_edit_booking'] =
        providerServicemanCanEditBooking;
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
  String? assignmentStatus;
  String? executionType;
  String? dispatchRole;
  bool dispatchCanControl = false;
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
  DetailsCustomerModel? customer;
  Provider? provider;
  Serviceman? serviceman;
  String? totalCampaignDiscountAmount;
  String? totalCouponDiscountAmount;
  List<String>? photoEvidence;
  List<String>? photoEvidenceFullPath;
  double? extraFee;
  int? isGuest;
  double? additionalCharge;
  double? totalReferralDiscountAmount;
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
    this.photoEvidence,
    this.photoEvidenceFullPath,
    this.extraFee,
    this.isGuest,
    this.additionalCharge,
    this.totalReferralDiscountAmount,
    this.subBooking,
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
    assignmentStatus = json['assignment_status'];
    executionType = json['execution_type'];
    dispatchRole = json['dispatch_role'];
    dispatchCanControl =
        json['dispatch_can_control'] == true ||
        json['dispatch_can_control'] == 1;
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
    customer = json['customer'] != null
        ? DetailsCustomerModel.fromJson(json['customer'])
        : null;
    provider = json['provider'] != null
        ? Provider.fromJson(json['provider'])
        : null;
    serviceman = json['serviceman'] != null
        ? Serviceman.fromJson(json['serviceman'])
        : null;
    totalCampaignDiscountAmount = json['total_campaign_discount_amount']
        .toString();
    totalCouponDiscountAmount = json['total_coupon_discount_amount'].toString();
    photoEvidence = json["evidence_photos"] != null
        ? json["evidence_photos"].cast<String>()
        : [];
    photoEvidenceFullPath = json["evidence_photos_full_path"] != null
        ? json["evidence_photos_full_path"].cast<String>()
        : [];
    extraFee = double.tryParse(json["extra_fee"].toString());
    additionalCharge = double.tryParse(json["additional_charge"].toString());
    isGuest = int.tryParse(json["is_guest"].toString());
    totalReferralDiscountAmount = double.tryParse(
      json['total_referral_discount_amount'].toString(),
    );
    subBooking = json['booking'] != null
        ? BookingDetailsContent.fromJson(json['booking'])
        : null;
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
    data['assignment_status'] = assignmentStatus;
    data['execution_type'] = executionType;
    data['dispatch_role'] = dispatchRole;
    data['dispatch_can_control'] = dispatchCanControl;
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
    if (customer != null) {
      data['customer'] = customer!.toJson();
    }
    if (provider != null) {
      data['provider'] = provider!.toJson();
    }
    if (serviceman != null) {
      data['serviceman'] = serviceman!.toJson();
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
    quantity = int.tryParse(json['quantity']?.toString() ?? '');
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
  double? avgRating;
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
    this.avgRating,
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
    avgRating = double.tryParse(json['avg_rating'].toString());
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

    data['tax'] = tax;
    data['order_count'] = orderCount;
    data['is_active'] = isActive;
    data['rating_count'] = ratingCount;
    data['avg_rating'] = avgRating;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    return data;
  }
}

class StatusHistories {
  int? id;
  String? bookingId;
  String? changedBy;
  String? bookingStatus;
  String? bookingSchedule;
  String? createdAt;
  String? updatedAt;
  User? user;

  StatusHistories({
    this.id,
    this.bookingId,
    this.changedBy,
    this.bookingStatus,
    this.bookingSchedule,
    this.createdAt,
    this.updatedAt,
    this.user,
  });

  StatusHistories.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    bookingId = json['booking_id'];
    changedBy = json['changed_by'];
    bookingStatus = json['booking_status'].toString();
    bookingSchedule = json['schedule'].toString();
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
    data['schedule'] = bookingSchedule;
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
  String? lat;
  String? lon;
  String? city;
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
  });

  ServiceAddress.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    userId = json['user_id'];
    lat = json['lat'].toString();
    lon = json['lon'].toString();
    city = json['city'];
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

class DetailsCustomerModel {
  String? id;
  String? roleId;
  String? firstName;
  String? lastName;
  String? email;
  String? phone;
  String? identificationNumber;
  String? identificationType;
  List<void>? identificationImage;
  String? dateOfBirth;
  String? gender;
  String? profileImage;
  String? profileImageFullPath;
  String? fcmToken;
  int? isPhoneVerified;
  int? isEmailVerified;
  String? phoneVerifiedAt;
  String? emailVerifiedAt;
  int? isActive;
  String? userType;
  String? rememberToken;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;

  DetailsCustomerModel({
    this.id,
    this.roleId,
    this.firstName,
    this.lastName,
    this.email,
    this.phone,
    this.identificationNumber,
    this.identificationType,
    this.identificationImage,
    this.dateOfBirth,
    this.gender,
    this.profileImage,
    this.profileImageFullPath,
    this.fcmToken,
    this.isPhoneVerified,
    this.isEmailVerified,
    this.phoneVerifiedAt,
    this.emailVerifiedAt,
    this.isActive,
    this.userType,
    this.rememberToken,
    this.deletedAt,
    this.createdAt,
    this.updatedAt,
  });

  DetailsCustomerModel.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    roleId = json['role_id'];
    firstName = json['first_name'];
    lastName = json['last_name'];
    email = json['email'];
    phone = json['phone'];
    identificationNumber = json['identification_number'];
    identificationType = json['identification_type'];
    dateOfBirth = json['date_of_birth'];
    gender = json['gender'];
    profileImage = json['profile_image'];
    profileImageFullPath = json['profile_image_full_path'];
    fcmToken = json['fcm_token'];
    isPhoneVerified = json['is_phone_verified'];
    isEmailVerified = json['is_email_verified'];
    phoneVerifiedAt = json['phone_verified_at'];
    emailVerifiedAt = json['email_verified_at'];
    isActive = json['is_active'];
    userType = json['user_type'];
    rememberToken = json['remember_token'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['role_id'] = roleId;
    data['first_name'] = firstName;
    data['last_name'] = lastName;
    data['email'] = email;
    data['phone'] = phone;
    data['identification_number'] = identificationNumber;
    data['identification_type'] = identificationType;
    data['date_of_birth'] = dateOfBirth;
    data['gender'] = gender;
    data['profile_image'] = profileImage;
    data['profile_image_full_path'] = profileImageFullPath;
    data['fcm_token'] = fcmToken;
    data['is_phone_verified'] = isPhoneVerified;
    data['is_email_verified'] = isEmailVerified;
    data['phone_verified_at'] = phoneVerifiedAt;
    data['email_verified_at'] = emailVerifiedAt;
    data['is_active'] = isActive;
    data['user_type'] = userType;
    data['remember_token'] = rememberToken;
    data['deleted_at'] = deletedAt;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    return data;
  }
}

class Provider {
  String? id;
  String? userId;
  String? companyName;
  String? companyPhone;
  String? companyAddress;
  String? companyEmail;
  String? logo;
  String? logoFullPath;
  String? contactPersonName;
  String? contactPersonPhone;
  String? contactPersonEmail;
  int? orderCount;
  int? serviceManCount;
  int? serviceCapacityPerDay;
  int? commissionStatus;
  int? commissionPercentage;
  int? isActive;
  String? createdAt;
  String? updatedAt;
  int? isApproved;
  String? zoneId;
  Coordinates? coordinates;

  Provider({
    this.id,
    this.userId,
    this.companyName,
    this.companyPhone,
    this.companyAddress,
    this.companyEmail,
    this.logo,
    this.logoFullPath,
    this.contactPersonName,
    this.contactPersonPhone,
    this.contactPersonEmail,
    this.orderCount,
    this.serviceManCount,
    this.serviceCapacityPerDay,
    this.commissionStatus,
    this.commissionPercentage,
    this.isActive,
    this.createdAt,
    this.updatedAt,
    this.isApproved,
    this.coordinates,
    this.zoneId,
  });

  Provider.fromJson(Map<String, dynamic> json) {
    id = json['id']?.toString();
    userId = json['user_id']?.toString();
    companyName = json['company_name'];
    companyPhone = json['company_phone'];
    companyAddress = json['company_address'];
    companyEmail = json['company_email'];
    logo = json['logo'];
    logoFullPath = json['logo_full_path'];
    contactPersonName = json['contact_person_name'];
    contactPersonPhone = json['contact_person_phone'];
    contactPersonEmail = json['contact_person_email'];
    orderCount = json['order_count'];
    serviceManCount = json['service_man_count'];
    serviceCapacityPerDay = json['service_capacity_per_day'];
    commissionStatus = json['commission_status'];
    commissionPercentage = json['commission_percentage'];
    isActive = json['is_active'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    isApproved = json['is_approved'];
    zoneId = json['zone_id']?.toString();
    coordinates = json['coordinates'] != null
        ? Coordinates.fromJson(json['coordinates'])
        : null;
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['user_id'] = userId;
    data['company_name'] = companyName;
    data['company_phone'] = companyPhone;
    data['company_address'] = companyAddress;
    data['company_email'] = companyEmail;
    data['logo'] = logo;
    data['logo_full_path'] = logoFullPath;
    data['contact_person_name'] = contactPersonName;
    data['contact_person_phone'] = contactPersonPhone;
    data['contact_person_email'] = contactPersonEmail;
    data['order_count'] = orderCount;
    data['service_man_count'] = serviceManCount;
    data['service_capacity_per_day'] = serviceCapacityPerDay;

    data['commission_status'] = commissionStatus;
    data['commission_percentage'] = commissionPercentage;
    data['is_active'] = isActive;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    data['is_approved'] = isApproved;
    data['zone_id'] = zoneId;
    return data;
  }
}

class Coordinates {
  double? lat;
  double? lng;

  Coordinates({this.lat, this.lng});

  Coordinates.fromJson(dynamic json) {
    if (json == null) return;
    if (json is Map) {
      lat = double.tryParse((json['latitude'] ?? json['lat'])?.toString() ?? '');
      lng = double.tryParse((json['longitude'] ?? json['lng'] ?? json['lon'])?.toString() ?? '');
    } else if (json is List && json.length >= 2) {
      lat = double.tryParse(json[0]?.toString() ?? '');
      lng = double.tryParse(json[1]?.toString() ?? '');
    } else if (json is String) {
      try {
        final decoded = jsonDecode(json);
        if (decoded is Map) {
          lat = double.tryParse((decoded['latitude'] ?? decoded['lat'])?.toString() ?? '');
          lng = double.tryParse((decoded['longitude'] ?? decoded['lng'] ?? decoded['lon'])?.toString() ?? '');
        } else if (decoded is List && decoded.length >= 2) {
          lat = double.tryParse(decoded[0]?.toString() ?? '');
          lng = double.tryParse(decoded[1]?.toString() ?? '');
        }
      } catch (_) {
        if (json.contains(',')) {
          final parts = json.split(',');
          if (parts.length >= 2) {
            lat = double.tryParse(parts[0].trim());
            lng = double.tryParse(parts[1].trim());
          }
        }
      }
    }
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['lat'] = lat;
    data['lng'] = lng;
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
