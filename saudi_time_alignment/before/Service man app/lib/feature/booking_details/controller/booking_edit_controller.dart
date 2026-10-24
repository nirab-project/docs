import 'dart:convert';
import 'package:demandium_serviceman/utils/core_export.dart';
import 'package:get/get.dart';

class BookingEditController extends GetxController implements GetxService {
  final BookingDetailsRepo bookingDetailsRepo;
  BookingEditController({required this.bookingDetailsRepo});

  @override
  void onInit() {
    super.onInit();
    initializedControllerValue(
      Get.find<BookingDetailsController>()
              .bookingDetails
              ?.bookingContent
              ?.bookingDetailsContent ??
          BookingDetailsContent(),
    );
    if (Get.find<SplashController>()
                .configModel
                ?.content
                ?.serviceManCanCancelBooking ==
            0 ||
        Get.find<SplashController>()
                .configModel
                ?.content
                ?.providerServicemanCanCancelBooking ==
            0) {
      statusTypeList.remove('canceled');
    }
  }

  final List<String> statusTypeList = ["ongoing", "completed", "canceled"];

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

  void initializedControllerValue(
    BookingDetailsContent? bookingDetailsContent, {
    bool shouldUpdate = true,
  }) {
    initialBookingStatus = bookingDetailsContent?.bookingStatus ?? "";
    _paymentStatusPaid = bookingDetailsContent?.isPaid == 1 ? true : false;

    _bookingStatus = bookingDetailsContent?.bookingStatus ?? "";
    _scheduleTime = bookingDetailsContent?.serviceSchedule;
    _previousScheduleTime = bookingDetailsContent?.serviceSchedule;

    _initializedCartValue(
      bookingDetailsContent?.details,
      bookingDetailsContent,
    );

    if (shouldUpdate) {
      update();
    }
  }

  void _initializedCartValue(
    List<ItemService>? bookedCartList,
    BookingDetailsContent? bookingDetailsContent,
  ) {
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
    Response response = await bookingDetailsRepo.getBookingEligibleServices(
      bookingId,
    );
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

  Future<void> removeCartServiceFromServer({
    CartModel? cart,
    String? bookingId,
    String? subBookingId,
    String? zoneId,
  }) async {
    Response response = await bookingDetailsRepo.removeCartServiceFromServer(
      cart: cart,
      bookingId: bookingId,
      subBookingId: subBookingId,
      zoneId: zoneId,
    );
    if (response.statusCode == 200) {
      _cartList.remove(cart);
    } else {}

    update();
  }

  Future<void> updateBooking({
    String? bookingId,
    String? subBookingId,
    String? zoneId,
    required bool isSubBooking,
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
      subBookingId: subBookingId,
      zoneId: zoneId,
      paymentStatus: paymentStatusPaid ? "1" : "0",
      isSubBooking: isSubBooking,
      bookingStatus: _bookingStatus,
      serviceSchedule: _scheduleTime,
      serviceInfo: jsonEncode(updatedBookedServiceList),
    );

    if (response.statusCode == 200) {
      await Get.find<BookingDetailsController>().getBookingDetails(
        bookingID: subBookingId ?? "",
        isSubBooking: isSubBooking,
      );
      showCustomSnackBar(
        response.body['message'].toString().capitalizeFirst,
        type: ToasterMessageType.success,
      );
    } else {
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

    String zoneId =
        Get.find<BookingDetailsController>()
            .bookingDetails
            ?.bookingContent
            ?.bookingDetailsContent
            ?.zoneId ??
        Get.find<BookingDetailsController>()
            .bookingDetails
            ?.bookingContent
            ?.bookingDetailsContent
            ?.subBooking
            ?.zoneId ??
        "";

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
        zoneId,
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
      Get.find<BookingDetailsController>()
              .bookingDetails
              ?.bookingContent
              ?.bookingDetailsContent
              ?.zoneId ??
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
        return Theme(
          data: Theme.of(context).copyWith(
            textButtonTheme: TextButtonThemeData(
              style: TextButton.styleFrom(
                foregroundColor: Theme.of(
                  context,
                ).colorScheme.primary, // button text color
              ),
            ),
          ),
          child: child!,
        );
      },
      firstDate: DateTime.now(),
      lastDate: DateTime(2101),
    );
    if (picked != null) {
      _selectedDate = picked;

      selectTimeOfDay();
    }
  }

  Future<void> selectTimeOfDay() async {
    final TimeOfDay? pickedTime = await showTimePicker(
      context: Get.context!,
      initialTime: TimeOfDay.now(),
    );

    if (pickedTime != null) {
      _selectedTimeOfDay = pickedTime;

      if (DateTime.tryParse(
        "${DateConverter.dateTimeStringToDate(_selectedDate.toString())} ${DateConverter.timeToTimeString(_selectedTimeOfDay)}",
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
}
