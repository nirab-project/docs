import 'package:demandium_provider/feature/booking_details/widget/booking_status_button_widget.dart';
import 'package:demandium_provider/helper/booking_helper.dart';
import 'package:demandium_provider/helper/extension_helper.dart';
import 'package:demandium_provider/util/core_export.dart';
import 'package:get/get.dart';

class BookingEditScreen extends StatefulWidget {
  final BookingEditType bookingEditType;
  const BookingEditScreen({super.key, required this.bookingEditType});
  @override
  State<BookingEditScreen> createState() => _BookingEditScreenState();
}

class _BookingEditScreenState extends State<BookingEditScreen> {
  @override
  void initState() {
    super.initState();

    var bookingDetailsController = Get.find<BookingDetailsController>();

    Get.find<BookingEditController>().initializedControllerValue(
      widget.bookingEditType == BookingEditType.subBooking
          ? bookingDetailsController.subBookingDetails?.content
          : bookingDetailsController.bookingDetails?.content,
      shouldUpdate: false,
    );

    Get.find<BookingEditController>().updateIsCheckedAllUpcomingBooking(
      shouldUpdate: false,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: CustomAppBar(title: "edit_booking".tr),
      body: GetBuilder<BookingEditController>(
        builder: (bookingEditController) {
          String? providerId = Get.find<UserProfileController>()
              .providerModel
              ?.content
              ?.providerInfo
              ?.id;
          RepeatBooking? nextBooking =
              BookingHelper.getNextUpcomingRepeatBooking(
                Get.find<BookingDetailsController>().bookingDetails?.content,
                providerId,
              );

          return Column(
            children: [
              Expanded(
                child: SingleChildScrollView(
                  child: Padding(
                    padding: const EdgeInsets.all(
                      Dimensions.paddingSizeDefault,
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        widget.bookingEditType != BookingEditType.repeat
                            ? const PaymentStatusButton()
                            : const SizedBox(),

                        widget.bookingEditType != BookingEditType.repeat
                            ? TextFieldTitle(
                                title: "serviceman".tr,
                                fontSize: Dimensions.fontSizeLarge,
                              )
                            : const SizedBox(),

                        widget.bookingEditType != BookingEditType.repeat
                            ? Container(
                                width: Get.width,
                                padding: const EdgeInsets.symmetric(
                                  horizontal: Dimensions.paddingSizeLarge,
                                ),
                                margin: const EdgeInsets.only(
                                  bottom: Dimensions.paddingSizeDefault,
                                ),
                                decoration: BoxDecoration(
                                  color: Theme.of(
                                    context,
                                  ).cardColor.withValues(alpha: 0.1),
                                  borderRadius: BorderRadius.circular(
                                    Dimensions.paddingSizeSmall,
                                  ),
                                  border: Border.all(
                                    color: Theme.of(
                                      context,
                                    ).primaryColor.withValues(alpha: 0.2),
                                    width: 1,
                                  ),
                                ),
                                child: DropdownButtonHideUnderline(
                                  child: DropdownButton(
                                    dropdownColor: Theme.of(context).cardColor,
                                    borderRadius: BorderRadius.circular(5),
                                    elevation: 2,
                                    hint: Text(
                                      bookingEditController
                                                  .selectedServiceman ==
                                              null
                                          ? "select_serviceman".tr
                                          : "${bookingEditController.selectedServiceman?.firstName ?? ""} ${bookingEditController.selectedServiceman?.lastName ?? ""}",
                                      style: robotoRegular.copyWith(
                                        color:
                                            bookingEditController
                                                    .selectedServiceman ==
                                                null
                                            ? Theme.of(
                                                context,
                                              ).colorScheme.onSurfaceVariant
                                            : Theme.of(context)
                                                  .textTheme
                                                  .bodyLarge!
                                                  .color!
                                                  .withValues(alpha: 0.8),
                                      ),
                                    ),
                                    icon: const NirabIcon(
                                      Icons.keyboard_arrow_down,
                                    ),
                                    items: Get.find<ServicemanSetupController>()
                                        .servicemanList
                                        ?.map((ServicemanModel serviceman) {
                                          return DropdownMenuItem(
                                            value: serviceman,
                                            child: Row(
                                              children: [
                                                Text(
                                                  "${serviceman.firstName ?? ""} ${serviceman.lastName ?? ""}",
                                                  style: robotoRegular.copyWith(
                                                    color: Theme.of(context)
                                                        .textTheme
                                                        .bodyLarge!
                                                        .color!
                                                        .withValues(alpha: 0.8),
                                                  ),
                                                ),
                                              ],
                                            ),
                                          );
                                        })
                                        .toList(),
                                    onChanged: (ServicemanModel? newValue) =>
                                        bookingEditController
                                            .updateSelectedServiceman(newValue),
                                  ),
                                ),
                              )
                            : const SizedBox(),

                        widget.bookingEditType != BookingEditType.repeat
                            ? Container(
                                width: Get.width,
                                padding: const EdgeInsets.symmetric(
                                  horizontal: Dimensions.paddingSizeLarge,
                                ),
                                decoration: BoxDecoration(
                                  color: Theme.of(
                                    context,
                                  ).cardColor.withValues(alpha: 0.1),
                                  borderRadius: BorderRadius.circular(
                                    Dimensions.paddingSizeSmall,
                                  ),
                                  border: Border.all(
                                    color: Theme.of(
                                      context,
                                    ).primaryColor.withValues(alpha: 0.2),
                                    width: 1,
                                  ),
                                ),
                                child: DropdownButtonHideUnderline(
                                  child: DropdownButton(
                                    dropdownColor: Theme.of(context).cardColor,
                                    borderRadius: BorderRadius.circular(5),
                                    elevation: 2,
                                    hint: Text(
                                      bookingEditController
                                                  .selectedBookingStatus ==
                                              ''
                                          ? "select_booking_status".tr
                                          : "${'booking_status'.tr} :   ${bookingEditController.selectedBookingStatus.tr}",
                                      style: robotoRegular.copyWith(
                                        color:
                                            bookingEditController
                                                    .selectedBookingStatus ==
                                                ''
                                            ? Theme.of(
                                                context,
                                              ).colorScheme.onSurfaceVariant
                                            : Theme.of(context)
                                                  .textTheme
                                                  .bodyLarge!
                                                  .color!
                                                  .withValues(alpha: 0.8),
                                      ),
                                    ),
                                    icon: const NirabIcon(
                                      Icons.keyboard_arrow_down,
                                    ),
                                    items: bookingEditController.statusTypeList
                                        .map((String items) {
                                          return DropdownMenuItem(
                                            value: items,
                                            child: Row(
                                              children: [
                                                Text(
                                                  items.tr,
                                                  style: robotoRegular.copyWith(
                                                    color: Theme.of(context)
                                                        .textTheme
                                                        .bodyLarge!
                                                        .color!
                                                        .withValues(alpha: 0.8),
                                                  ),
                                                ),
                                              ],
                                            ),
                                          );
                                        })
                                        .toList(),
                                    onChanged: (String? newValue) {
                                      if (Get.find<BookingDetailsController>()
                                                  .bookingDetails
                                                  ?.content
                                                  ?.bookingStatus ==
                                              "ongoing" &&
                                          newValue?.toLowerCase() ==
                                              "accepted") {
                                        showCustomSnackBar(
                                          'service_is_already_ongoing'.tr,
                                          type: ToasterMessageType.info,
                                        );
                                        bookingEditController
                                            .changeBookingStatusDropDownValue(
                                              "ongoing",
                                            );
                                      } else {
                                        bookingEditController
                                            .changeBookingStatusDropDownValue(
                                              newValue!,
                                            );
                                      }
                                    },
                                  ),
                                ),
                              )
                            : const SizedBox(),

                        widget.bookingEditType != BookingEditType.repeat
                            ? TextFieldTitle(
                                title: "service_schedule".tr,
                                fontSize: Dimensions.fontSizeLarge,
                              )
                            : const SizedBox(),

                        widget.bookingEditType != BookingEditType.repeat
                            ? Container(
                                decoration: BoxDecoration(
                                  color: Theme.of(
                                    context,
                                  ).cardColor.withValues(alpha: 0.1),
                                  borderRadius: BorderRadius.circular(
                                    Dimensions.paddingSizeSmall,
                                  ),
                                  border: Border.all(
                                    color: Theme.of(
                                      context,
                                    ).primaryColor.withValues(alpha: 0.2),
                                    width: 1,
                                  ),
                                ),
                                margin: const EdgeInsets.only(
                                  bottom: Dimensions.paddingSizeDefault,
                                ),
                                padding: const EdgeInsets.only(
                                  left: Dimensions.paddingSizeDefault,
                                ),
                                child: Row(
                                  mainAxisAlignment:
                                      MainAxisAlignment.spaceBetween,
                                  children: [
                                    (bookingEditController.scheduleTime != null)
                                        ? Text(
                                            DateConverter.dateMonthYearTime(
                                              DateTime.tryParse(
                                                bookingEditController
                                                    .scheduleTime!,
                                              ),
                                            ),
                                            style: robotoRegular.copyWith(
                                              fontSize:
                                                  Dimensions.fontSizeDefault,
                                            ),
                                            textDirection: TextDirection.ltr,
                                          )
                                        : const SizedBox(),
                                    const SizedBox(
                                      width: Dimensions.paddingSizeDefault,
                                    ),

                                    IconButton(
                                      onPressed: () async {
                                        Get.find<UserProfileController>()
                                            .trialWidgetShow(
                                              route: "show-dialog",
                                            );
                                        await bookingEditController
                                            .selectDate();
                                        Get.find<UserProfileController>()
                                            .trialWidgetShow(route: "");
                                      },
                                      icon: NirabIcon(
                                        Icons.calendar_month_outlined,
                                        color: Theme.of(
                                          context,
                                        ).primaryColor.withValues(alpha: 0.5),
                                      ),
                                    ),
                                  ],
                                ),
                              )
                            : const SizedBox(),

                        nextBooking != null &&
                                widget.bookingEditType == BookingEditType.repeat
                            ? _NextUpcomingBookingWidget(booking: nextBooking)
                            : const SizedBox(),

                        SizedBox(
                          height:
                              nextBooking != null &&
                                  widget.bookingEditType ==
                                      BookingEditType.repeat
                              ? 15
                              : 0,
                        ),

                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              'service_list'.tr,
                              style: robotoMedium.copyWith(
                                color: Theme.of(context).primaryColor,
                                fontSize: Dimensions.fontSizeLarge,
                              ),
                            ),

                            widget.bookingEditType == BookingEditType.regular
                                ? TextButton(
                                    onPressed:
                                        bookingEditController.cartList.length ==
                                                1 &&
                                            bookingEditController
                                                    .cartList[0]
                                                    .variantKey ==
                                                null
                                        ? null
                                        : () {
                                            showModalBottomSheet(
                                              useRootNavigator: true,
                                              isScrollControlled: true,
                                              backgroundColor:
                                                  Colors.transparent,
                                              context: context,
                                              builder: (context) =>
                                                  BookingServiceView(
                                                    serviceList:
                                                        bookingEditController
                                                            .serviceList ??
                                                        [],
                                                  ),
                                            );
                                          },
                                    style: ElevatedButton.styleFrom(
                                      shape: RoundedRectangleBorder(
                                        borderRadius: BorderRadius.circular(
                                          Dimensions.radiusDefault,
                                        ),
                                        side: BorderSide(
                                          color:
                                              bookingEditController
                                                          .cartList
                                                          .length ==
                                                      1 &&
                                                  bookingEditController
                                                          .cartList[0]
                                                          .variantKey ==
                                                      null
                                              ? Theme.of(context).hintColor
                                              : Theme.of(context).primaryColor,
                                        ), // Customize the border color here
                                      ),
                                    ),
                                    child: Row(
                                      mainAxisSize: MainAxisSize.min,
                                      children: [
                                        NirabIcon(
                                          Icons.add,
                                          color:
                                              bookingEditController
                                                          .cartList
                                                          .length ==
                                                      1 &&
                                                  bookingEditController
                                                          .cartList[0]
                                                          .variantKey ==
                                                      null
                                              ? Theme.of(context).hintColor
                                              : Theme.of(context).primaryColor,
                                          size: Dimensions.fontSizeDefault,
                                        ),
                                        const SizedBox(
                                          width: 8.0,
                                        ), // Adjust the spacing between the icon and text here
                                        Text(
                                          "add_service".tr,
                                          style: robotoMedium.copyWith(
                                            fontSize:
                                                Dimensions.fontSizeDefault,
                                            color:
                                                bookingEditController
                                                            .cartList
                                                            .length ==
                                                        1 &&
                                                    bookingEditController
                                                            .cartList[0]
                                                            .variantKey ==
                                                        null
                                                ? Theme.of(context).hintColor
                                                : Theme.of(
                                                    context,
                                                  ).primaryColor,
                                          ),
                                        ),
                                      ],
                                    ),
                                  )
                                : const SizedBox(),
                          ],
                        ),

                        const SizedBox(height: Dimensions.paddingSizeDefault),

                        GridView.builder(
                          key: UniqueKey(),
                          gridDelegate:
                              SliverGridDelegateWithFixedCrossAxisCount(
                                crossAxisSpacing: Dimensions.paddingSizeLarge,
                                mainAxisSpacing:
                                    ResponsiveHelper.isDesktop(context)
                                    ? Dimensions.paddingSizeLarge
                                    : Dimensions.paddingSizeSmall,
                                childAspectRatio:
                                    ResponsiveHelper.isMobile(context) ? 5 : 6,
                                crossAxisCount: 1,
                                mainAxisExtent: 110,
                              ),
                          physics: const NeverScrollableScrollPhysics(),
                          shrinkWrap: true,
                          itemCount: bookingEditController.cartList.length,
                          itemBuilder: (context, index) {
                            bool disableQuantityButton =
                                bookingEditController.cartList.length == 1 &&
                                bookingEditController.cartList[0].variantKey ==
                                    null;
                            return CartServiceWidget(
                              bookingEditType: widget.bookingEditType,
                              cart: bookingEditController.cartList[index],
                              cartIndex: index,
                              disableQuantityButton: disableQuantityButton,
                            );
                          },
                        ),

                        SizedBox(
                          height:
                              widget.bookingEditType == BookingEditType.repeat
                              ? 20
                              : 0,
                        ),

                        widget.bookingEditType == BookingEditType.repeat
                            ? const CheckAllUpcomingBookingWidget()
                            : const SizedBox(),

                        const SizedBox(height: 90),
                      ],
                    ),
                  ),
                ),
              ),

              Container(
                color: Theme.of(context).cardColor,
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: Dimensions.paddingSizeDefault,
                    vertical: 10,
                  ),
                  child: SafeArea(
                    child: CustomButton(
                      btnTxt: "update_status".tr,
                      isLoading: bookingEditController.statusUpdateLoading,
                      onPressed: () {
                        bookingEditController.updateBooking(
                          bookingId: Get.find<BookingDetailsController>()
                              .bookingDetails
                              ?.content
                              ?.id,
                          subBookingId: Get.find<BookingDetailsController>()
                              .subBookingDetails
                              ?.content
                              ?.id,
                          zoneId: Get.find<BookingDetailsController>()
                              .bookingDetails
                              ?.content
                              ?.zoneId,
                          bookingEditType: widget.bookingEditType,
                        );
                      },
                    ),
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _NextUpcomingBookingWidget extends StatelessWidget {
  final RepeatBooking booking;
  const _NextUpcomingBookingWidget({required this.booking});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        boxShadow: context.customThemeColors.lightShadow,
        borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
      ),
      padding: const EdgeInsets.symmetric(
        vertical: Dimensions.paddingSizeDefault,
        horizontal: Dimensions.paddingSizeDefault,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                "${booking.bookingStatus == "accepted" ? 'next_upcoming'.tr : "currently_ongoing".tr} # ${booking.readableId ?? ""}",
                style: robotoMedium.copyWith(
                  color: Theme.of(context).primaryColor,
                  fontSize: Dimensions.fontSizeDefault,
                ),
              ),
              const SizedBox(width: 10),

              BookingStatusButtonWidget(bookingStatus: booking.bookingStatus),
            ],
          ),

          const SizedBox(height: Dimensions.paddingSizeSmall),

          Text(
            "${'scheduled_date'.tr} : ${DateConverter.dateMonthYearTime(DateTime.tryParse(booking.serviceSchedule!))}",
            style: robotoLight,
          ),
        ],
      ),
    );
  }
}

class CheckAllUpcomingBookingWidget extends StatelessWidget {
  const CheckAllUpcomingBookingWidget({super.key});

  @override
  Widget build(BuildContext context) {
    return GetBuilder<BookingEditController>(
      builder: (bookingEditController) {
        return Container(
          decoration: BoxDecoration(
            color: Theme.of(context).hintColor.withValues(alpha: 0.05),
          ),
          padding: const EdgeInsets.symmetric(vertical: 10),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Transform.scale(
                scale: 1.2,
                child: Checkbox(
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(3),
                  ),
                  activeColor: Theme.of(context).colorScheme.primary,
                  value: bookingEditController.isCheckedAllUpcomingBooking,
                  onChanged: (value) {
                    bookingEditController.updateIsCheckedAllUpcomingBooking();
                  },
                ),
              ),

              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text("check_this_box".tr, style: robotoRegular),
                  const SizedBox(height: 3),
                  Text(
                    "if_want_to_update_for_all_upcoming_booking".tr,
                    style: robotoLight,
                  ),
                ],
              ),
            ],
          ),
        );
      },
    );
  }
}
