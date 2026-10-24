import 'package:nirab_clean/common/widgets/custom_date_range_picker.dart';
import 'package:nirab_clean/feature/checkout/widget/order_details_section/available_repeat_time_picker.dart';
import 'package:nirab_clean/common/widgets/custom_tooltip_widget.dart';
import 'package:nirab_clean/util/core_export.dart';
import 'package:get/get.dart';

class DailyRepeatBookingSchedule extends StatelessWidget {
  const DailyRepeatBookingSchedule({super.key});

  @override
  Widget build(BuildContext context) {
    return GetBuilder<CartController>(
      builder: (cartController) {
        return GetBuilder<ScheduleController>(
          builder: (scheduleController) {
            int scheduleDays =
                CheckoutHelper.calculateDaysCountBetweenDateRange(
                  scheduleController.pickedDailyRepeatBookingDateRange,
                );
            int? applicableCouponCount =
                CheckoutHelper.getNumberOfDaysForApplicableCoupon(
                  pickedScheduleDays: scheduleDays,
                );

            return Container(
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
                color: Theme.of(context).hintColor.withValues(alpha: 0.05),
              ),
              padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text("select_time_and_date".tr, style: robotoMedium),
                  const SizedBox(height: Dimensions.paddingSizeExtraSmall),

                  Text(
                    "daily_select_time_and_date_hint".tr,
                    style: robotoLight,
                  ),
                  const SizedBox(height: Dimensions.paddingSizeDefault),

                  Row(
                    children: [
                      Text("date_range".tr, style: robotoMedium),
                      const SizedBox(width: Dimensions.paddingSizeExtraSmall),
                      CustomToolTip(
                        message: "daily_select_time_and_date_hint".tr,
                        iconColor: Theme.of(context).colorScheme.primary,
                        preferredDirection: AxisDirection.down,
                        size: 16,
                      ),
                    ],
                  ),
                  const SizedBox(height: Dimensions.paddingSizeSmall),

                  CustomDateRangePicker(
                    onTimeChanged: (DateTimeRange? dateRange) {
                      if (dateRange != null &&
                          scheduleController.pickedDailyRepeatTime != null) {
                        DateTime startDate = dateRange.start;
                        DateTime now = DateTime.now();
                        if (startDate.year == now.year &&
                            startDate.month == now.month &&
                            startDate.day == now.day) {
                          TimeOfDay pickedTime =
                              scheduleController.pickedDailyRepeatTime!;
                          TimeOfDay nowTime = TimeOfDay.now();
                          if (pickedTime.hour < nowTime.hour ||
                              (pickedTime.hour == nowTime.hour &&
                                  pickedTime.minute < nowTime.minute)) {
                            customSnackBar('selected_time_cannot_earlier'.tr);
                            scheduleController.updatePickedDailyRepeatTime =
                                null;
                          }
                        }
                      }
                      scheduleController.updatePickedDailyRepeatTime = null;
                      scheduleController.resolvedProviderId = null;
                      scheduleController.updateDailyRepeatBookingDateRange =
                          dateRange;
                      scheduleController.calculateScheduleCountDays(
                        repeatBookingType: RepeatBookingType.daily,
                      );
                      scheduleController.update();
                    },
                    dateTimeRange:
                        scheduleController.pickedDailyRepeatBookingDateRange ==
                            null
                        ? null
                        : DateConverter.convertDateTimeRangeToString(
                            scheduleController
                                .pickedDailyRepeatBookingDateRange!,
                          ),
                    icon: Images.calendar2,
                  ),
                  const SizedBox(height: Dimensions.paddingSizeLarge),

                  Text("arrival_time".tr, style: robotoMedium),
                  const SizedBox(height: Dimensions.paddingSizeSmall),
                  AvailableRepeatTimePicker(
                    days: () => scheduleController.repeatDaysForSlots(
                      RepeatBookingType.daily,
                    ),
                    onTimeChanged: (TimeOfDay? time) {
                      if (time != null &&
                          scheduleController
                                  .pickedDailyRepeatBookingDateRange !=
                              null) {
                        DateTime startDate = scheduleController
                            .pickedDailyRepeatBookingDateRange!
                            .start;
                        DateTime now = DateTime.now();
                        if (startDate.year == now.year &&
                            startDate.month == now.month &&
                            startDate.day == now.day) {
                          TimeOfDay nowTime = TimeOfDay.now();
                          if (time.hour < nowTime.hour ||
                              (time.hour == nowTime.hour &&
                                  time.minute < nowTime.minute)) {
                            customSnackBar('selected_time_cannot_earlier'.tr);
                            return;
                          }
                        }
                      }
                      scheduleController.updatePickedDailyRepeatTime = time;
                      scheduleController.update();
                    },
                    time: scheduleController.pickedDailyRepeatTime,
                  ),

                  if (scheduleController.pickedDailyRepeatBookingDateRange !=
                      null)
                    Padding(
                      padding: const EdgeInsets.only(
                        top: Dimensions.paddingSizeDefault,
                        bottom: Dimensions.paddingSizeExtraSmall,
                      ),
                      child: Center(
                        child: Text.rich(
                          TextSpan(
                            style: DefaultTextStyle.of(context).style,
                            children: [
                              TextSpan(
                                text: 'you_will_receive_this_services_total'.tr,
                                style: robotoLight,
                              ),
                              TextSpan(
                                text: " $scheduleDays ${'times'.tr}",
                                style: robotoBold.copyWith(
                                  color: Theme.of(context).colorScheme.primary,
                                ),
                              ),
                            ],
                          ),
                          textAlign: TextAlign.center,
                        ),
                      ),
                    ),

                  if (scheduleController.pickedDailyRepeatBookingDateRange !=
                          null &&
                      applicableCouponCount != null)
                    Padding(
                      padding: const EdgeInsets.only(
                        bottom: Dimensions.paddingSizeExtraSmall,
                      ),
                      child: Center(
                        child: Text.rich(
                          TextSpan(
                            style: DefaultTextStyle.of(context).style,
                            children: [
                              TextSpan(
                                text: 'coupon_will_be_applied_first'.tr,
                                style: robotoLight,
                              ),
                              TextSpan(
                                text: " $applicableCouponCount ",
                                style: robotoBold.copyWith(
                                  color: Theme.of(context).colorScheme.primary,
                                ),
                              ),
                              TextSpan(text: 'services'.tr, style: robotoLight),
                            ],
                          ),
                          textAlign: TextAlign.center,
                        ),
                      ),
                    ),
                ],
              ),
            );
          },
        );
      },
    );
  }
}
