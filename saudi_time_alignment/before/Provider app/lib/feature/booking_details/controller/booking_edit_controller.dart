import 'dart:convert';
import 'package:demandium_provider/util/core_export.dart';
import 'package:get/get.dart';

class BookingEditController extends GetxController implements GetxService {
  final DirectServiceRepo serviceRepo;
  final BookingDetailsRepo bookingDetailsRepo;
  BookingEditController({
    required this.bookingDetailsRepo,
    required this.serviceRepo,
  });

  @override
  void onInit() {
    super.onInit();
    initializedControllerValue(
      Get.find<BookingDetailsController>().bookingDetails?.content ??
          BookingDetailsContent(),
    );
    if (Get.find<SplashController>()
            .configModel
            .content
            ?.providerCanCancelBooking ==
        0) {
      statusTypeList.remove('canceled');
    }
  }

  final List<String> statusTypeList = [
    "accepted",
    "ongoing",
    "completed",
    "canceled",
  ];

  String _bookingStatus = '';
  String get selectedBookingStatus => _bookingStatus;

  bool _isLoading = false;
  bool get isLoading => _isLoading;

  bool _statusUpdateLoading = false;
  bool get statusUpdateLoading => _statusUpdateLoading;

  String initialBookingStatus = 'pending';

  bool _isCartButtonActive = false;
  bool get isCartButtonActive => _isCartButtonActive;

  bool _paymentStatusPaid = true;
  bool get paymentStatusPaid => _paymentStatusPaid;

  ServicemanModel? _selectedServiceman;
  ServicemanModel? get selectedServiceman => _selectedServiceman;

  String? _scheduleTime;
  String? get scheduleTime => _scheduleTime;

  String? _previousScheduleTime;

  BookingDetailsContent? _bookingDetailsContent;
  BookingDetailsContent? get bookingDetailsContent => _bookingDetailsContent;

  List<Map<String, dynamic>> draftFieldAnswers = [];
  List<CartModel> _cartList = [];
  List<CartModel> get cartList => _cartList;

  List<ServiceModel>? _serviceList;
  List<ServiceModel>? get serviceList => _serviceList;

  List<BookingPriceItem>? bookingPriceList;

  DateTime _selectedDate = DateTime.now();
  DateTime get selectedDate => _selectedDate;

  TimeOfDay _selectedTimeOfDay = TimeOfDay.now();
  TimeOfDay get selectedTimeOfDay => _selectedTimeOfDay;

  bool _isCheckedAllUpcomingBooking = false;
  bool get isCheckedAllUpcomingBooking => _isCheckedAllUpcomingBooking;

  ServiceLocationType _selectedServiceLocationType =
      ServiceLocationType.customer;
  ServiceLocationType get selectedServiceLocationType =>
      _selectedServiceLocationType;

  ServiceAddress? _pickedServiceAddress;
  ServiceAddress? get pickedServiceAddress => _pickedServiceAddress;

  void initializedControllerValue(
    BookingDetailsContent? bookingDetailsContent, {
    bool shouldUpdate = true,
  }) {
    initialBookingStatus = bookingDetailsContent?.bookingStatus ?? "";
    _paymentStatusPaid = bookingDetailsContent?.isPaid == 1 ? true : false;
    _selectedServiceman =
        bookingDetailsContent?.serviceman?.user ??
        bookingDetailsContent?.subBooking?.serviceman?.user;
    _bookingStatus = bookingDetailsContent?.bookingStatus ?? "";
    _scheduleTime =
        bookingDetailsContent?.serviceSchedule ??
        bookingDetailsContent?.subBooking?.serviceSchedule;
    _previousScheduleTime =
        bookingDetailsContent?.serviceSchedule ??
        bookingDetailsContent?.subBooking?.serviceSchedule;

    _initializedCartValue(
      bookingDetailsContent?.nextService?.details ??
          bookingDetailsContent?.details,
    );

    if (shouldUpdate) {
      update();
    }
  }

  void _initializedCartValue(List<ItemService>? bookedCartList) {
    _cartList = [];
    bookedCartList?.forEach((element) {
      CartModel cartModel = CartModel(
        id: element.id,
        answers: element.fieldAnswers,
        isNewItem: false,
        serviceId: element.serviceId,
        variantKey: element.variantKey,
        serviceName: element.serviceName,
        quantity: element.quantity,
        serviceThumbnail: element.service?.thumbnailFullPath ?? "",
        serviceCost: element.serviceCost,
        totalCost: element.totalCost ?? 0,
        taxAmount: element.taxAmount,
        campaignDiscountAmount: element.campaignDiscountAmount,
        discountAmount: element.discountAmount,
      );
      _cartList.add(cartModel);
    });
  }

  Future<void> getBookingEligibleServices({required String bookingId}) async {
    Response response = await serviceRepo.getBookingEligibleServices(bookingId);
    if (response.statusCode == 200) {
      List<dynamic> list = response.body['content']['data'];
      _serviceList = [];
      for (var service in list) {
        _serviceList?.add(ServiceModel.fromJson(service));
      }
    } else {
      ApiChecker.checkApi(response);
    }

    update();
  }

  Future<void> updateBooking({
    String? bookingId,
    String? subBookingId,
    String? zoneId,
    required BookingEditType bookingEditType,
  }) async {
    _statusUpdateLoading = true;
    update();

    List<Map<String, dynamic>> updatedBookedServiceList = [];

    for (var element in _cartList) {
      updatedBookedServiceList.add({
        "service_id": element.serviceId,
        "variant_key": element.variantKey,
        "quantity": element.quantity.toString(),
        "field_answers": element.fieldAnswers,
        if (element.id?.isNotEmpty == true) "detail_id": element.id,
      });
    }

    Response response = await bookingDetailsRepo.updateBooking(
      bookingId: bookingId,
      zoneId: zoneId,
      paymentStatus: paymentStatusPaid ? "1" : "0",
      servicemanId: _selectedServiceman?.serviceman?.id,
      bookingStatus: _bookingStatus,
      serviceSchedule: _scheduleTime,
      serviceInfo: jsonEncode(updatedBookedServiceList),
      bookingEditType: bookingEditType,
      subBookingId: subBookingId,
      changeNextAllBooking: _isCheckedAllUpcomingBooking,
    );
    if (response.statusCode == 200) {
      if (bookingEditType == BookingEditType.subBooking &&
          subBookingId != null) {
        await Get.find<BookingDetailsController>().getBookingDetails(
          bookingId!,
          reload: false,
          initEditBooking: false,
        );
        await Get.find<BookingDetailsController>().getBookingSubDetails(
          subBookingId,
          reload: false,
        );
      } else {
        await Get.find<BookingDetailsController>().getBookingDetails(
          bookingId!,
          reload: false,
        );
      }
      showCustomSnackBar(
        response.body['message'].toString().capitalizeFirst,
        type: ToasterMessageType.success,
      );
    } else {
      Get.back();
      ApiChecker.checkApi(response);
    }
    _statusUpdateLoading = false;
    update();
  }

  void addCartItem(CartModel cartModel) {
    bool availableToCart = false;

    for (int i = 0; i < _cartList.length; i++) {
      if (_cartList[i].serviceId == cartModel.serviceId &&
          _cartList[i].variantKey == cartModel.variantKey &&
          jsonEncode(_cartList[i].fieldAnswers) ==
              jsonEncode(cartModel.fieldAnswers)) {
        availableToCart = true;
        _cartList[i].quantity =
            (_cartList[i].quantity ?? 0) + (cartModel.quantity ?? 0);
        _cartList[i].totalCost =
            (_cartList[i].totalCost ?? 0) + (cartModel.totalCost ?? 0);
        break;
      }
    }
    if (!availableToCart) {
      _cartList.add(cartModel);
    }
  }

  void updateCartItemQuantity(
    CartModel? cart,
    int cartIndex, {
    bool increment = true,
  }) async {
    _isLoading = true;
    update();

    List<Map<String, dynamic>> variationList = [];

    int quantity;
    if (increment) {
      quantity = (cart?.quantity ?? 0) + 1;
    } else {
      quantity = (cart?.quantity ?? 0) - 1;
    }

    if (quantity <= 0 && cart?.isNewItem == true) {
      _cartList.remove(cart);
    } else {
      variationList.add({
        "service_id": cart?.serviceId ?? "",
        "variant_key": cart?.variantKey ?? "",
        "quantity": quantity.toString(),
        "field_answers": cart?.fieldAnswers ?? [],
      });

      Response response = await bookingDetailsRepo.getBookingPriceList(
        Get.find<BookingDetailsController>().bookingDetails?.content?.zoneId ??
            "",
        jsonEncode(variationList),
      );
      if (response.statusCode == 200 &&
          response.body["response_code"] == "default_200") {
        bookingPriceList =
            BookingPrice.fromJson(response.body).bookingPriceContent ?? [];

        bookingPriceList?.forEach((element) {
          _cartList[cartIndex].totalCost = element.totalCost;
          _cartList[cartIndex].quantity = element.quantity;
        });
      } else {}
    }

    _isLoading = false;
    update();
  }

  void updatedVariationQuantity(
    int serviceIndex,
    int variationIndex, {
    bool increment = true,
  }) {
    if (increment) {
      int quantity =
          _serviceList![serviceIndex].variations![variationIndex].quantity + 1;
      _serviceList![serviceIndex].variations![variationIndex].quantity =
          quantity;
    } else {
      int quantity =
          _serviceList![serviceIndex].variations![variationIndex].quantity - 1;
      _serviceList![serviceIndex].variations![variationIndex].quantity =
          quantity;
    }

    for (int i = 0; i < _serviceList![serviceIndex].variations!.length; i++) {
      if (_serviceList![serviceIndex].variations![i].quantity > 0) {
        _isCartButtonActive = true;
        break;
      } else {
        _isCartButtonActive = false;
      }
    }

    update();
  }

  Future<void> addMultipleCartItem(int serviceIndex) async {
    List<Map<String, dynamic>> variationList = [];

    for (int i = 0; i < _serviceList![serviceIndex].variations!.length; i++) {
      if (_serviceList![serviceIndex].variations![i].quantity > 0) {
        Variations variations = _serviceList![serviceIndex].variations![i];

        variationList.add({
          "service_id": variations.serviceId ?? "",
          "variant_key": variations.variantKey ?? "",
          "quantity": variations.quantity.toString(),
          "field_answers": draftFieldAnswers,
        });
      }
    }
    await getBookingPriceList(
      jsonEncode(variationList),
      thumbnail: _serviceList![serviceIndex].thumbnailFullPath,
    );
  }

  Future<void> getBookingPriceList(
    String serviceInfo, {
    String? thumbnail,
  }) async {
    _isLoading = true;
    update();

    Response response = await bookingDetailsRepo.getBookingPriceList(
      Get.find<BookingDetailsController>().bookingDetails?.content?.zoneId ??
          "",
      serviceInfo,
    );
    if (response.statusCode == 200 &&
        response.body["response_code"] == "default_200") {
      bookingPriceList =
          BookingPrice.fromJson(response.body).bookingPriceContent ?? [];

      bookingPriceList?.forEach((element) {
        addCartItem(
          CartModel(
            id: "",
            answers: element.fieldAnswers,
            isNewItem: true,
            serviceId: element.serviceId,
            serviceName: element.serviceName,
            variantKey: element.variantKey,
            quantity: element.quantity,
            serviceThumbnail: thumbnail ?? "",
            serviceCost: element.serviceCost,
            totalCost: element.totalCost,
          ),
        );
      });
    }
    _isLoading = false;
    update();
  }

  void resetSelectedServiceVariationQuantity(int serviceIndex) {
    if (_serviceList?[serviceIndex] != null &&
        _serviceList?[serviceIndex].variations != null) {
      for (
        int variationIndex = 0;
        variationIndex < _serviceList![serviceIndex].variations!.length;
        variationIndex++
      ) {
        _serviceList![serviceIndex].variations![variationIndex].quantity = 0;
      }
      _isCartButtonActive = false;
    }
  }

  Future<void> selectDate() async {
    final DateTime? picked = await showDatePicker(
      context: Get.context!,
      initialDate: DateTime.now(),
      initialEntryMode: DatePickerEntryMode.calendarOnly,
      builder: (context, child) {
        return child!;
      },
      firstDate: DateTime.now(),
      lastDate: DateTime(2101),
    );
    if (picked != null) {
      _selectedDate = picked;
      await selectTimeOfDay();
    }
  }

  Future<void> selectTimeOfDay() async {
    TimeOfDay? pickedTime = await showCustomTimePicker();

    if (pickedTime != null) {
      _selectedTimeOfDay = pickedTime;

      if (DateTime.tryParse(
        "${DateConverter.dateTimeStringToDate(_selectedDate.toString())} "
        "${DateConverter.timeToTimeString(_selectedTimeOfDay)}",
      )!.isBefore(DateTime.now())) {
        showCustomSnackBar(
          "schedule_time_must_be_after_current_time".tr,
          type: ToasterMessageType.info,
        );
        _scheduleTime = _previousScheduleTime;
      } else {
        _scheduleTime =
            "${DateConverter.dateTimeStringToDate(_selectedDate.toString())} ${DateConverter.timeToTimeString(_selectedTimeOfDay)}";
      }

      update();
    }
  }

  Future<ResponseModel> changeServiceLocation({
    required BookingEditType bookingEditType,
    ServiceAddress? address,
    required serviceLocation,
    String? bookingId,
    bool? changeNextAllBooking,
    String? subBookingId,
  }) async {
    _isLoading = true;
    update();

    Response response = await bookingDetailsRepo.changeServiceLocation(
      address: address,
      serviceLocation: serviceLocation,
      bookingId: bookingId,
      bookingEditType: bookingEditType,
      changeNextAllBooking: changeNextAllBooking,
      subBookingId: subBookingId,
    );
    if (response.statusCode == 200) {
      if (bookingEditType == BookingEditType.subBooking &&
          subBookingId != null) {
        await Get.find<BookingDetailsController>().getBookingDetails(
          bookingId!,
          reload: false,
          initEditBooking: false,
        );
        await Get.find<BookingDetailsController>().getBookingSubDetails(
          subBookingId,
          reload: false,
        );
      } else {
        await Get.find<BookingDetailsController>().getBookingDetails(
          bookingId!,
          reload: false,
        );
      }
      _isLoading = false;
      update();
      return ResponseModel(true, response.body['message']);
    } else if (response.statusCode == 400) {
      _isLoading = false;
      update();
      return ResponseModel(false, response.body['errors'][0]['message'] ?? "");
    } else {
      _isLoading = false;
      update();
      return ResponseModel(false, response.body['message']);
    }
  }

  void changeBookingStatusDropDownValue(String status) {
    _bookingStatus = status;
    update();
  }

  void togglePaymentStatus() {
    _paymentStatusPaid = !_paymentStatusPaid;
    update();
  }

  void removeCartItem(int index) {
    _cartList.removeAt(index);
    update();
  }

  void updateSelectedServiceman(ServicemanModel? servicemanModel) {
    _selectedServiceman = servicemanModel;
    update();
  }

  void updateIsCheckedAllUpcomingBooking({bool shouldUpdate = true}) {
    _isCheckedAllUpcomingBooking = !_isCheckedAllUpcomingBooking;

    if (shouldUpdate) {
      update();
    } else {
      _isCheckedAllUpcomingBooking = false;
    }
  }

  void updateServiceLocationType({
    required ServiceLocationType type,
    bool shouldUpdate = true,
  }) {
    _selectedServiceLocationType = type;
    if (shouldUpdate) {
      update();
    }
  }

  void setPickedServiceAddress({
    ServiceAddress? address,
    bool shouldUpdate = true,
  }) {
    _pickedServiceAddress = address;

    if (shouldUpdate) {
      update();
    }
  }
}
