import 'package:demandium_provider/feature/booking_requests/controller/calendar_controller.dart';
import 'package:demandium_provider/helper/extension_helper.dart';
import 'package:demandium_provider/util/core_export.dart';
import 'package:get/get.dart';
import 'package:syncfusion_flutter_calendar/calendar.dart';

class MonthCellOrderCountWidget extends StatelessWidget {
  final MonthCellDetails details;
  final int count;

  const MonthCellOrderCountWidget({
    super.key,
    required this.details,
    required this.count,
  });

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<BookingCalendarController>();
    final bool isCurrentMonth =
        details.date.month ==
        details.visibleDates[details.visibleDates.length ~/ 2].month;
    final bool isToday =
        details.date.day == DateTime.now().day &&
        details.date.month == DateTime.now().month &&
        details.date.year == DateTime.now().year;

    // Check if the date is within the filter date range
    final bool isWithinFilterRange = DateConverter.isDateWithinRange(
      details.date,
      startDate: controller.filterStartDate,
      endDate: controller.filterEndDate,
    );

    return Container(
      decoration: BoxDecoration(
        border: Border.all(
          color: Theme.of(context).hintColor.withValues(alpha: 0.2),
          width: 0.5,
        ),
        color: !isWithinFilterRange
            ? Theme.of(context).hintColor.withValues(alpha: 0.2)
            : isToday
            ? Theme.of(context).primaryColor.withValues(alpha: 0.1)
            : null,
      ),
      child: Stack(
        children: [
          // Date number
          Positioned(
            top: 4,
            left: 4,
            child: Container(
              width: 28,
              height: 28,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: isToday
                    ? Theme.of(context).primaryColor
                    : Colors.transparent,
              ),
              alignment: Alignment.center,
              child: Text(
                details.date.day.toString(),
                style: robotoMedium.copyWith(
                  color: isToday
                      ? Colors.white
                      : isCurrentMonth
                      ? Theme.of(context).textTheme.bodyLarge?.color
                      : Theme.of(context).colorScheme.onSurfaceVariant,
                  fontSize: Dimensions.fontSizeDefault,
                ),
              ),
            ),
          ),
          // Order count badge
          if (count > 0)
            Positioned.fill(
              child: Align(
                alignment: Alignment.center,
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: Dimensions.paddingSizeDefault,
                    vertical: 2,
                  ),
                  decoration: BoxDecoration(
                    color: context.customThemeColors.warning.withValues(
                      alpha: 0.5,
                    ),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Text(
                    count.toString().padLeft(2, '0'),
                    style: robotoMedium.copyWith(
                      color: Colors.black,
                      fontSize: Dimensions.fontSizeDefault,
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
