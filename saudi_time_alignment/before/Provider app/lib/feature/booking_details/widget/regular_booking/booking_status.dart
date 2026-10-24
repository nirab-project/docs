import 'package:demandium_provider/feature/booking_details/widget/timeline/connectors.dart';
import 'package:demandium_provider/feature/booking_details/widget/timeline/indicator_theme.dart';
import 'package:demandium_provider/feature/booking_details/widget/timeline/indicators.dart';
import 'package:demandium_provider/feature/booking_details/widget/timeline/timeline_theme.dart';
import 'package:demandium_provider/feature/booking_details/widget/timeline/timeline_tile_builder.dart';
import 'package:demandium_provider/feature/booking_details/widget/timeline/timelines.dart';
import 'package:demandium_provider/helper/extension_helper.dart';
import 'package:get/get.dart';
import 'package:demandium_provider/util/core_export.dart';

class BookingStatus extends StatefulWidget {
  final String? bookingId;
  final bool isSubBooking;
  const BookingStatus({super.key, this.bookingId, required this.isSubBooking});

  @override
  State<BookingStatus> createState() => _BookingStatusState();
}

class _BookingStatusState extends State<BookingStatus> {
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
        final bookingDetailsContent = widget.isSubBooking
            ? bookingDetailsController.subBookingDetails
            : bookingDetailsController.bookingDetails;

        if (bookingDetailsContent == null &&
            bookingDetailsContent?.content == null) {
          return const Center(child: BookingDetailsShimmer());
        } else if (bookingDetailsController.bookingDetails != null &&
            bookingDetailsController.bookingDetails!.content == null) {
          return SizedBox(
            height: Get.height * 0.7,
            child: BookingEmptyScreen(bookingId: widget.bookingId),
          );
        } else {
          final bookingDetails = widget.isSubBooking
              ? bookingDetailsController.subBookingDetails!.content!
              : bookingDetailsController.bookingDetails!.content!;
          return Padding(
            padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
            child: Column(
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      '${'booking_date'.tr} : ',
                      style: robotoRegular.copyWith(
                        fontSize: Dimensions.fontSizeDefault,
                      ),
                    ),
                    Text(
                      DateConverter.dateMonthYearTime(
                        DateConverter.isoUtcStringToLocalDate(
                          bookingDetails.createdAt!,
                        ),
                      ),
                      style: robotoRegular.copyWith(
                        fontSize: Dimensions.fontSizeDefault,
                      ),
                      textDirection: TextDirection.ltr,
                    ),
                  ],
                ),

                const SizedBox(height: Dimensions.paddingSizeDefault),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      '${'scheduled_date'.tr} : ',
                      style: robotoRegular.copyWith(
                        fontSize: Dimensions.fontSizeDefault,
                      ),
                    ),

                    if (bookingDetails.serviceSchedule != null)
                      Text(
                        DateConverter.dateMonthYearTime(
                          DateTime.tryParse(bookingDetails.serviceSchedule!),
                        ),
                        style: robotoRegular.copyWith(
                          fontSize: Dimensions.fontSizeDefault,
                        ),
                        textDirection: TextDirection.ltr,
                      ),
                  ],
                ),

                const SizedBox(height: Dimensions.paddingSizeDefault),
                Text.rich(
                  TextSpan(
                    text: '${'payment_status'.tr}:   ',
                    style: robotoMedium.copyWith(
                      fontSize: Dimensions.fontSizeDefault,
                      color: Theme.of(context).textTheme.bodyLarge!.color,
                    ),
                    children: [
                      TextSpan(
                        text: bookingDetails.isPaid == 1
                            ? 'paid'.tr
                            : 'unpaid'.tr,
                        style: robotoMedium.copyWith(
                          fontSize: Dimensions.fontSizeDefault,
                          color: bookingDetails.isPaid == 0
                              ? Theme.of(context).colorScheme.error
                              : Colors.green,
                          decoration: TextDecoration.none,
                        ),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: Dimensions.paddingSizeDefault),
                Text.rich(
                  TextSpan(
                    text: '${'Booking_Status'.tr}:   ',
                    style: robotoMedium.copyWith(
                      fontSize: Dimensions.fontSizeDefault,
                      color: Theme.of(context).textTheme.bodyLarge!.color,
                    ),
                    children: [
                      TextSpan(
                        text: bookingDetails.bookingStatus!.tr,
                        style: robotoMedium.copyWith(
                          fontSize: Dimensions.fontSizeDefault,
                          color: context
                              .customThemeColors
                              .buttonTextColorMap[bookingDetails.bookingStatus],
                          decoration: TextDecoration.none,
                        ),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: Dimensions.paddingSizeDefault),

                Timeline1(
                  bookingDetails: bookingDetails,
                  statusHistories: bookingDetails.statusHistories,
                  scheduleHistories: bookingDetails.scheduleHistories,
                  increment:
                      bookingDetails.scheduleHistories!.length > 1 &&
                          bookingDetails.statusHistories!.isNotEmpty
                      ? 2
                      : 1,
                ),

                const SizedBox(height: Dimensions.paddingSizeExtraLarge),
              ],
            ),
          );
        }
      },
    );
  }
}

class Timeline1 extends StatelessWidget {
  final BookingDetailsContent? bookingDetails;
  final List<StatusHistories>? statusHistories;
  final List<ScheduleHistories>? scheduleHistories;
  final int increment;
  const Timeline1({
    super.key,
    required this.statusHistories,
    this.scheduleHistories,
    required this.increment,
    this.bookingDetails,
  });

  @override
  Widget build(BuildContext context) {
    return Timeline.tileBuilder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      theme: TimelineThemeData(
        nodePosition: 0,
        indicatorTheme: const IndicatorThemeData(position: 0, size: 30.0),
      ),
      padding: EdgeInsets.symmetric(
        vertical: 20.0,
        horizontal: Get.find<LocalizationController>().isLtr ? 0 : 10,
      ),
      builder: TimelineTileBuilder.connected(
        connectionDirection: ConnectionDirection.before,

        itemCount: statusHistories!.length + increment,

        contentsBuilder: (_, index) {
          return Padding(
            padding: const EdgeInsets.only(
              left: 20.0,
              bottom: 20.0,
              top: 7,
              right: 10,
            ),
            child: index == 0
                ? Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        "${'booking_placed_by'.tr} ${scheduleHistories![index].user != null ? scheduleHistories![index].user!.firstName : "customer".tr} "
                        "${scheduleHistories![index].user != null ? scheduleHistories![index].user!.lastName : ""}",
                        style: robotoRegular.copyWith(
                          fontSize: Dimensions.fontSizeDefault,
                        ),
                      ),
                      const SizedBox(height: Dimensions.paddingSizeSmall),

                      Text(
                        DateConverter.dateMonthYearTime(
                          DateConverter.isoUtcStringToLocalDate(
                            scheduleHistories![index].createdAt!,
                          ),
                        ),
                        style: robotoRegular.copyWith(
                          fontSize: Dimensions.fontSizeSmall,
                          color: Theme.of(context).secondaryHeaderColor,
                        ),
                        textDirection: TextDirection.ltr,
                      ),
                      const SizedBox(height: Dimensions.paddingSizeExtraSmall),
                    ],
                  )
                : index == 1 && statusHistories!.isNotEmpty
                ? Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        "${'booking'.tr} ${statusHistories![index - 1].bookingStatus.toString().tr.toLowerCase()} ${'by'.tr} "
                        "${statusHistories![index - 1].user?.userType.toString().tr} ",
                        style: robotoRegular.copyWith(
                          fontSize: Dimensions.fontSizeDefault,
                        ),
                      ),

                      const SizedBox(height: Dimensions.paddingSizeSmall),
                      statusHistories![index - 1].user?.userType !=
                              'provider-admin'
                          ? Text(
                              "${statusHistories![index - 1].user?.firstName ?? ""} ${statusHistories![index - 1].user?.lastName ?? ""}",
                              style: robotoRegular.copyWith(
                                fontSize: Dimensions.fontSizeSmall,
                                color: Theme.of(context).secondaryHeaderColor,
                              ),
                            )
                          : Text(
                              Get.find<UserProfileController>()
                                      .providerModel
                                      ?.content
                                      ?.providerInfo
                                      ?.companyName ??
                                  "",
                              style: robotoRegular.copyWith(
                                fontSize: Dimensions.fontSizeSmall,
                                color: Theme.of(context).secondaryHeaderColor,
                              ),
                            ),

                      const SizedBox(height: Dimensions.paddingSizeSmall),

                      Text(
                        DateConverter.dateMonthYearTime(
                          DateConverter.isoUtcStringToLocalDate(
                            statusHistories![index - 1].updatedAt!,
                          ),
                        ),
                        style: robotoRegular.copyWith(
                          fontSize: Dimensions.fontSizeSmall,
                          color: Theme.of(context).secondaryHeaderColor,
                        ),
                        textDirection: TextDirection.ltr,
                      ),
                      const SizedBox(height: Dimensions.paddingSizeDefault),
                    ],
                  )
                : index == 2 && scheduleHistories!.length > 1
                ? Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      SizedBox(
                        height: (scheduleHistories!.length - 1) * 80,

                        child: ListView.builder(
                          itemBuilder: (_, index) {
                            return Column(
                              mainAxisSize: MainAxisSize.min,
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  "${'booking_schedule_changed_by'.tr} ${scheduleHistories![index + 1].user!.userType.toString().tr}",
                                  style: robotoRegular.copyWith(
                                    fontSize: Dimensions.fontSizeDefault,
                                  ),
                                ),
                                const SizedBox(
                                  height: Dimensions.paddingSizeSmall,
                                ),

                                if (scheduleHistories![index + 1]
                                        .user!
                                        .userType !=
                                    'provider-admin')
                                  Text(
                                    "${scheduleHistories![index + 1].user?.firstName.toString()} ${scheduleHistories![index + 1].user?.lastName.toString()}",
                                    style: robotoRegular.copyWith(
                                      fontSize: Dimensions.fontSizeSmall,
                                      color: Theme.of(
                                        context,
                                      ).secondaryHeaderColor,
                                    ),
                                    textDirection: TextDirection.ltr,
                                  ),
                                if (scheduleHistories![index + 1]
                                        .user!
                                        .userType ==
                                    'provider-admin')
                                  Text(
                                    "${bookingDetails?.provider?.companyName ?? ""} ",
                                    style: robotoRegular.copyWith(
                                      fontSize: Dimensions.fontSizeSmall,
                                      color: Theme.of(
                                        context,
                                      ).secondaryHeaderColor,
                                    ),
                                    textDirection: TextDirection.ltr,
                                  ),
                                const SizedBox(
                                  height: Dimensions.paddingSizeSmall,
                                ),
                                Text(
                                  DateConverter.dateMonthYearTime(
                                    DateTime.tryParse(
                                      scheduleHistories![index + 1].schedule!,
                                    ),
                                  ),
                                  style: robotoRegular.copyWith(
                                    fontSize: Dimensions.fontSizeSmall,
                                    color: Theme.of(
                                      context,
                                    ).secondaryHeaderColor,
                                  ),
                                  textDirection: TextDirection.ltr,
                                ),
                                const SizedBox(
                                  height: Dimensions.paddingSizeExtraSmall,
                                ),
                              ],
                            );
                          },
                          shrinkWrap: true,
                          physics: const NeverScrollableScrollPhysics(),
                          itemCount: scheduleHistories!.length - 1,
                        ),
                      ),
                    ],
                  )
                : Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        "${'booking'.tr} ${statusHistories![index - increment].bookingStatus.toString().tr.toLowerCase()} ${'by'.tr} "
                        "${statusHistories![index - increment].user?.userType.toString().tr} ",
                        style: robotoRegular.copyWith(
                          fontSize: Dimensions.fontSizeDefault,
                        ),
                      ),

                      const SizedBox(height: Dimensions.paddingSizeSmall),
                      statusHistories![index - increment].user?.userType !=
                              'provider-admin'
                          ? Text(
                              "${statusHistories![index - increment].user?.firstName} ${statusHistories![index - increment].user?.lastName ?? ""}",
                              style: robotoRegular.copyWith(
                                fontSize: Dimensions.fontSizeSmall,
                                color: Theme.of(context).secondaryHeaderColor,
                              ),
                            )
                          : Text(
                              Get.find<UserProfileController>()
                                      .providerModel
                                      ?.content
                                      ?.providerInfo
                                      ?.companyName ??
                                  "",
                              style: robotoRegular.copyWith(
                                fontSize: Dimensions.fontSizeSmall,
                                color: Theme.of(context).secondaryHeaderColor,
                              ),
                            ),

                      const SizedBox(height: Dimensions.paddingSizeSmall),

                      Text(
                        DateConverter.dateMonthYearTime(
                          DateConverter.isoUtcStringToLocalDate(
                            statusHistories![index - increment].updatedAt!,
                          ),
                        ),
                        style: robotoRegular.copyWith(
                          fontSize: Dimensions.fontSizeSmall,
                          color: Theme.of(context).secondaryHeaderColor,
                        ),
                        textDirection: TextDirection.ltr,
                      ),
                      const SizedBox(height: Dimensions.paddingSizeDefault),
                    ],
                  ),
          );
        },

        connectorBuilder: (_, index, _) =>
            SolidLineConnector(color: Theme.of(context).primaryColor),

        indicatorBuilder: (_, index) {
          return DotIndicator(
            color: Theme.of(context).primaryColor,
            child: Center(
              child: NirabIcon(Icons.check, color: light.cardColor),
            ),
          );
        },
      ),
    );
  }
}
