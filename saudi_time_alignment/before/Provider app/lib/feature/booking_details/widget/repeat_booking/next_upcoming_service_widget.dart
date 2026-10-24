import 'package:demandium_provider/feature/booking_details/widget/booking_status_button_widget.dart';
import 'package:demandium_provider/helper/extension_helper.dart';
import 'package:demandium_provider/util/core_export.dart';
import 'package:get/get.dart';

class NextUpcomingServiceWidget extends StatelessWidget {
  final RepeatBooking booking;
  const NextUpcomingServiceWidget({super.key, required this.booking});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        boxShadow: context.customThemeColors.lightShadow,
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
              Expanded(
                child: Text(
                  "${booking.bookingStatus == "accepted" ? 'next_upcoming'.tr : "currently_ongoing".tr} # ${booking.readableId ?? ""}",
                  style: robotoBold.copyWith(
                    fontSize: Dimensions.fontSizeLarge,
                    color: Theme.of(context).colorScheme.onSurface,
                    decoration: TextDecoration.none,
                  ),
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              const SizedBox(width: 5),
              InkWell(
                onTap: () {
                  Get.toNamed(
                    RouteHelper.getBookingDetailsRoute(
                      subBookingId: booking.id!,
                    ),
                  );
                },
                child: Text(
                  'view_details'.tr,
                  style: robotoBold.copyWith(
                    color: Theme.of(context).primaryColor,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: Dimensions.paddingSizeExtraSmall),

          BookingStatusButtonWidget(bookingStatus: booking.bookingStatus),

          const SizedBox(height: Dimensions.paddingSizeSmall),
          Divider(
            height: 0.5,
            thickness: 0.5,
            color: Theme.of(context).colorScheme.onSurfaceVariant,
          ),
          const SizedBox(height: Dimensions.paddingSizeDefault),

          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              DottedBorder(
                options: RoundedRectDottedBorderOptions(
                  dashPattern: const [4, 1],
                  strokeWidth: 1,
                  color: Theme.of(
                    context,
                  ).colorScheme.primary.withValues(alpha: 0.15),
                  radius: const Radius.circular(Dimensions.radiusSmall),
                ),
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    vertical: Dimensions.paddingSizeSmall,
                    horizontal: Dimensions.paddingSizeSmall,
                  ),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(5),
                    color: Theme.of(
                      context,
                    ).colorScheme.primary.withValues(alpha: 0.05),
                  ),
                  child: Column(
                    children: [
                      const Row(),
                      Text(
                        DateConverter.dateStringMonthYear(
                          DateTime.tryParse(booking.serviceSchedule!),
                          format: "EEEE, dd-MMM-yyyy",
                        ),
                        style: robotoLight.copyWith(
                          fontSize: Dimensions.fontSizeSmall,
                        ),
                      ),
                      const SizedBox(height: Dimensions.paddingSizeExtraSmall),
                      Text(
                        DateConverter.convert24HourTimeTo12HourTime(
                          DateTime.tryParse(booking.serviceSchedule!),
                        ),
                        style: robotoLight.copyWith(
                          fontSize: Dimensions.fontSizeSmall,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text.rich(
                    TextSpan(
                      style: DefaultTextStyle.of(context).style,
                      children: [
                        TextSpan(
                          text: 'booking_amount'.tr,
                          style: robotoLight.copyWith(
                            fontSize: Dimensions.fontSizeSmall,
                          ),
                        ),
                        TextSpan(
                          text:
                              " ${PriceConverter.convertPrice(booking.totalBookingAmount ?? 0)}",
                          style: robotoMedium.copyWith(
                            fontSize: Dimensions.fontSizeSmall,
                          ),
                        ),
                      ],
                    ),

                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: Dimensions.paddingSizeExtraSmall),
                  Text.rich(
                    TextSpan(
                      style: DefaultTextStyle.of(context).style,
                      children: [
                        TextSpan(
                          text: 'payment_status'.tr,
                          style: robotoLight.copyWith(
                            fontSize: Dimensions.fontSizeSmall,
                          ),
                        ),
                        TextSpan(
                          text:
                              " : ${booking.isPaid == 1 ? "paid".tr : "unpaid".tr}",
                          style: robotoMedium.copyWith(
                            color: booking.isPaid == 1
                                ? (Theme.of(context).brightness ==
                                          Brightness.dark
                                      ? BrandTokens.successDark
                                      : BrandTokens.successLight)
                                : Theme.of(context).colorScheme.error,
                            fontSize: Dimensions.fontSizeSmall,
                          ),
                        ),
                      ],
                    ),

                    textAlign: TextAlign.center,
                  ),
                ],
              ),
              const SizedBox(),
              const SizedBox(),
            ],
          ),
        ],
      ),
    );
  }
}
