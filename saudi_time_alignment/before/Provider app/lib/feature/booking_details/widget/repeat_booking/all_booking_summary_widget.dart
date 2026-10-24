import 'package:demandium_provider/helper/extension_helper.dart';
import 'package:demandium_provider/util/core_export.dart';
import 'package:get/get.dart';

class AllBookingSummaryWidget extends StatelessWidget {
  final TabController? tabController;
  final BookingDetailsContent bookingDetails;
  const AllBookingSummaryWidget({
    super.key,
    this.tabController,
    required this.bookingDetails,
  });

  @override
  Widget build(BuildContext context) {
    String selectedWeekDays = "";

    bookingDetails.weekNames?.forEach((element) {
      selectedWeekDays = "$selectedWeekDays${element.toLowerCase().tr}  ";
    });

    return Container(
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        boxShadow: context.customThemeColors.lightShadow,
      ),
      padding: const EdgeInsets.symmetric(
        vertical: Dimensions.paddingSizeDefault,
      ),

      child: Padding(
        padding: const EdgeInsets.symmetric(
          horizontal: Dimensions.paddingSizeDefault,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Text(
                    'all_booking_summary'.tr,
                    style: robotoBold.copyWith(
                      fontSize: Dimensions.fontSizeLarge,
                      color: Theme.of(
                        context,
                      ).textTheme.bodyLarge!.color?.withValues(alpha: 0.9),
                      decoration: TextDecoration.none,
                    ),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                const SizedBox(width: 5),
                InkWell(
                  onTap: () {
                    tabController?.index = 1;
                  },
                  child: Text(
                    'view_all_booking'.tr,
                    style: robotoBold.copyWith(
                      color: Theme.of(context).primaryColor,
                    ),
                  ),
                ),
              ],
            ),

            const SizedBox(height: Dimensions.paddingSizeSmall),
            Divider(
              height: 1,
              thickness: 1,
              color: Theme.of(
                context,
              ).colorScheme.onSurfaceVariant.withValues(alpha: 0.4),
            ),
            const SizedBox(height: Dimensions.paddingSizeSmall),
            _ItemWidget(
              title: "booking_type",
              subtitle: bookingDetails.bookingType?.toLowerCase().tr ?? "",
            ),
            _ItemWidget(
              title: "total_booking",
              subtitle: "${bookingDetails.totalCount ?? ""}",
            ),
            _ItemWidget(
              title: "completed",
              subtitle: "${bookingDetails.completedCount ?? ""}",
            ),
            _ItemWidget(
              title: "canceled",
              subtitle: "${bookingDetails.canceledCount ?? ""}",
            ),
            if (bookingDetails.bookingType != "custom" &&
                bookingDetails.startDate != null)
              _ItemWidget(
                title: "arrival_time",
                subtitle: DateConverter.convertDateTimeToTime(
                  DateTime.tryParse(bookingDetails.startDate!) ??
                      DateTime.now(),
                ),
              ),
            _ItemWidget(
              title: "total_amount",
              subtitle: PriceConverter.convertPrice(
                bookingDetails.totalBookingAmount ?? 0,
              ),
            ),
            _ItemWidget(
              title: "payment",
              subtitle: "${bookingDetails.paymentMethod}".tr,
            ),
            if (bookingDetails.bookingType == "weekly")
              _ItemWidget(
                title: 'selected_days'.tr,
                subtitle: selectedWeekDays,
              ),
            _ItemWidget(
              title: "date_range",
              subtitle: DateConverter.convertDateTimeRangeToString(
                DateTimeRange(
                  start:
                      DateTime.tryParse(bookingDetails.startDate ?? "") ??
                      DateTime.now(),
                  end:
                      DateTime.tryParse(bookingDetails.endDate ?? "") ??
                      DateTime.now(),
                ),
                format: "d MMM, y",
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ItemWidget extends StatelessWidget {
  final String title;
  final String subtitle;
  const _ItemWidget({required this.title, required this.subtitle});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            flex: 5,
            child: Text(
              title.tr,
              style: robotoRegular.copyWith(
                fontSize: Dimensions.fontSizeSmall + 1,
                color: Theme.of(
                  context,
                ).textTheme.bodyLarge!.color!.withValues(alpha: 0.65),
              ),
            ),
          ),
          Text(
            " :  ",
            style: robotoRegular.copyWith(
              fontSize: Dimensions.fontSizeSmall + 1,
              height: 1.2,
            ),
          ),
          Expanded(
            flex: 8,
            child: Text(
              subtitle,
              style: robotoRegular.copyWith(
                fontSize: Dimensions.fontSizeSmall + 1,
                color: Theme.of(
                  context,
                ).textTheme.bodyLarge!.color!.withValues(alpha: 0.85),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
