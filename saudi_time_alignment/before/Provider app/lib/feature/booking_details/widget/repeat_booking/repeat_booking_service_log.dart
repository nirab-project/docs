import 'package:demandium_provider/feature/booking_details/widget/booking_workflow_panel.dart';
import 'package:demandium_provider/feature/booking_details/widget/booking_status_button_widget.dart';
import 'package:demandium_provider/feature/booking_details/widget/repeat_booking/repeat_booking_info_view.dart';
import 'package:get/get.dart';
import 'package:demandium_provider/util/core_export.dart';

class RepeatBookingServiceLogWidget extends StatefulWidget {
  final String bookingId;
  const RepeatBookingServiceLogWidget({super.key, required this.bookingId});

  @override
  State<RepeatBookingServiceLogWidget> createState() =>
      _RepeatBookingServiceLogWidgetState();
}

class _RepeatBookingServiceLogWidgetState
    extends State<RepeatBookingServiceLogWidget> {
  @override
  void initState() {
    super.initState();
    Get.find<BookingDetailsController>().updateServicePageCurrentState(
      BookingDetailsTabControllerState.status,
      shouldUpdate: false,
    );
  }

  @override
  Widget build(BuildContext context) {
    return GetBuilder<BookingDetailsController>(
      builder: (bookingDetailsController) {
        BookingDetailsContent? bookingDetails =
            bookingDetailsController.bookingDetails?.content;

        if (bookingDetails == null &&
            bookingDetailsController.bookingDetails == null) {
          return const Center(child: BookingDetailsShimmer());
        } else if (bookingDetailsController.bookingDetails != null &&
            bookingDetailsController.bookingDetails!.content == null) {
          return SizedBox(
            height: Get.height * 0.7,
            child: BookingEmptyScreen(bookingId: widget.bookingId),
          );
        } else {
          List<RepeatBooking> alreadyProvided = [];
          List<RepeatBooking> upcomingBooking = [];
          List<RepeatBooking> currentlyOngoing = [];

          bookingDetailsController.bookingDetails?.content?.repeatBookingList
              ?.forEach((element) {
                if (element.bookingStatus == "completed" ||
                    element.bookingStatus == "canceled") {
                  alreadyProvided.add(element);
                } else if (element.bookingStatus == "ongoing") {
                  currentlyOngoing.add(element);
                } else {
                  upcomingBooking.add(element);
                }
              });

          if (currentlyOngoing.isEmpty &&
              upcomingBooking.isNotEmpty &&
              upcomingBooking.first.bookingStatus != "pending") {
            RepeatBooking booking = upcomingBooking.first;
            currentlyOngoing.add(booking);
            upcomingBooking.remove(booking);
          }

          return RefreshIndicator(
            onRefresh: () async {
              await Get.find<BookingDetailsController>().getBookingDetails(
                widget.bookingId,
                reload: false,
              );
            },
            child: SingleChildScrollView(
              physics: const ClampingScrollPhysics(
                parent: AlwaysScrollableScrollPhysics(),
              ),
              child: Column(
                children: [
                  const SizedBox(height: Dimensions.paddingSizeDefault),
                  const RepeatBookingInformationView(),

                  SizedBox(
                    height: currentlyOngoing.isNotEmpty
                        ? Dimensions.paddingSizeDefault
                        : 0,
                  ),

                  currentlyOngoing.isNotEmpty
                      ? _ServiceLogItem(
                          title: "currently_ongoing",
                          serviceList: currentlyOngoing,
                          bookingId: widget.bookingId,
                          bookingDetails: bookingDetails!,
                        )
                      : const SingleChildScrollView(),

                  alreadyProvided.isNotEmpty
                      ? const SizedBox(height: Dimensions.paddingSizeLarge)
                      : const SizedBox(),

                  alreadyProvided.isNotEmpty
                      ? _ServiceLogItem(
                          title: "already_provided",
                          serviceList: alreadyProvided,
                          bookingId: widget.bookingId,
                          bookingDetails: bookingDetails!,
                        )
                      : const SizedBox(),

                  upcomingBooking.isNotEmpty
                      ? const SizedBox(height: Dimensions.paddingSizeLarge)
                      : const SizedBox(),

                  upcomingBooking.isNotEmpty
                      ? _ServiceLogItem(
                          title: "upcoming",
                          serviceList: upcomingBooking,
                          bookingId: widget.bookingId,
                          bookingDetails: bookingDetails!,
                        )
                      : const SizedBox(),

                  const SizedBox(height: Dimensions.paddingSizeLarge),
                ],
              ),
            ),
          );
        }
      },
    );
  }
}

class _ServiceLogItem extends StatelessWidget {
  final String title;
  final String bookingId;
  final List<RepeatBooking?> serviceList;
  final BookingDetailsContent bookingDetails;
  const _ServiceLogItem({
    required this.title,
    required this.serviceList,
    required this.bookingId,
    required this.bookingDetails,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Theme.of(
          context,
        ).colorScheme.onSurfaceVariant.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
      ),
      padding: const EdgeInsets.symmetric(
        horizontal: Dimensions.paddingSizeSmall,
        vertical: Dimensions.paddingSizeDefault,
      ),
      margin: const EdgeInsets.symmetric(
        horizontal: Dimensions.paddingSizeSmall,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title.tr, style: robotoMedium),
          const SizedBox(height: Dimensions.paddingSizeSmall),
          ListView.separated(
            itemCount: serviceList.length,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            padding: EdgeInsets.zero,
            itemBuilder: (context, index) {
              return Container(
                decoration: BoxDecoration(
                  color: Theme.of(context).cardColor,
                  borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
                ),
                child: Stack(
                  children: [
                    Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: Dimensions.paddingSizeDefault,
                        vertical: Dimensions.paddingSizeDefault,
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Column(
                            children: [
                              Row(
                                mainAxisAlignment:
                                    MainAxisAlignment.spaceBetween,
                                children: [
                                  Text(
                                    "${'booking_id'.tr} # ${serviceList[index]?.readableId}",
                                    style: robotoMedium.copyWith(
                                      color: Theme.of(
                                        context,
                                      ).colorScheme.primary,
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),

                          SizedBox(
                            height: title != "upcoming"
                                ? Dimensions.paddingSizeLarge
                                : 5,
                          ),

                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(
                                DateConverter.dateMonthYearTime(
                                  DateTime.tryParse(
                                    serviceList[index]!.serviceSchedule!,
                                  ),
                                ),
                                style: robotoLight,
                              ),
                              title != "upcoming"
                                  ? BookingStatusButtonWidget(
                                      bookingStatus:
                                          serviceList[index]?.bookingStatus,
                                    )
                                  : const SizedBox(),
                            ],
                          ),
                        ],
                      ),
                    ),
                    if (title != "upcoming")
                      Positioned.fill(
                        child: CustomInkWell(
                          onTap: () => Get.toNamed(
                            RouteHelper.getBookingDetailsRoute(
                              subBookingId: serviceList[index]!.id!,
                            ),
                          ),
                        ),
                      ),

                    if (bookingDetails.bookingStatus != "pending")
                      Positioned(
                        right: 10,
                        top: 15,
                        child: GetBuilder<BookingDetailsController>(
                          builder: (bookingDetailsController) {
                            return PopupMenuButton<PopupMenuModel>(
                              shape: RoundedRectangleBorder(
                                borderRadius: const BorderRadius.all(
                                  Radius.circular(Dimensions.radiusDefault),
                                ),
                                side: BorderSide(
                                  color: Theme.of(context)
                                      .colorScheme
                                      .onSurfaceVariant
                                      .withValues(alpha: 0.1),
                                ),
                              ),
                              surfaceTintColor: Theme.of(context).cardColor,
                              position: PopupMenuPosition.under,
                              elevation: 8,
                              shadowColor: Theme.of(
                                context,
                              ).hintColor.withValues(alpha: 0.3),
                              itemBuilder: (BuildContext context) {
                                return bookingDetailsController
                                    .getPopupMenuList(
                                      status:
                                          serviceList[index]?.bookingStatus ??
                                          "",
                                    )
                                    .map((PopupMenuModel option) {
                                      return PopupMenuItem<PopupMenuModel>(
                                        onTap: () async {
                                          if (option.title ==
                                              "booking_details") {
                                            Get.toNamed(
                                              RouteHelper.getBookingDetailsRoute(
                                                subBookingId:
                                                    serviceList[index]!.id!,
                                              ),
                                            );
                                          } else if (option.title ==
                                              "download_invoice") {
                                            await BookingWorkflowPanel.openInvoice(
                                              serviceList[index]!.id!,
                                              isSubBooking: true,
                                            );
                                          } else if (option.title == "cancel") {
                                            showCustomDialog(
                                              child: ConfirmationDialog(
                                                noButtonColor: Theme.of(
                                                  Get.context!,
                                                ).primaryColor,
                                                title:
                                                    "are_you_sure_to_cancel_this_booking"
                                                        .tr,
                                                description:
                                                    "once_you_cancel_the_request",
                                                yesTextColor: Colors.white,
                                                noTextColor: Colors.white,
                                                icon: Images.warning,
                                                yesButtonText: "not_now",
                                                noButtonText: "yes_cancel",
                                                onYesPressed: () async {
                                                  Get.back();
                                                },
                                                onNoPressed: () {
                                                  bookingDetailsController
                                                      .cancelSubBooking(
                                                        bookingId: bookingId,
                                                        subBookingId:
                                                            serviceList[index]!
                                                                .id!,
                                                      );
                                                },
                                              ),
                                            );
                                          }
                                        },
                                        value: option,
                                        height: 35,
                                        child: Row(
                                          children: [
                                            const SizedBox(
                                              width: Dimensions
                                                  .paddingSizeExtraSmall,
                                            ),
                                            NirabIcon(
                                              option.icon,
                                              size: Dimensions.fontSizeLarge,
                                            ),
                                            const SizedBox(
                                              width:
                                                  Dimensions.paddingSizeSmall,
                                            ),
                                            Text(
                                              option.title.tr,
                                              style: robotoRegular.copyWith(
                                                fontSize:
                                                    Dimensions.fontSizeSmall,
                                              ),
                                            ),
                                          ],
                                        ),
                                      );
                                    })
                                    .toList();
                              },
                              child: NirabIcon(
                                Icons.more_vert,
                                color: Theme.of(
                                  context,
                                ).colorScheme.onSurfaceVariant,
                              ),
                            );
                          },
                        ),
                      ),
                  ],
                ),
              );
            },
            separatorBuilder: (context, index) {
              return const SizedBox(height: Dimensions.paddingSizeDefault);
            },
          ),
        ],
      ),
    );
  }
}
