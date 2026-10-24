import 'package:nirab_clean/feature/booking/widget/repeat/custom_repeat_booking_bottom_sheet.dart';
import 'package:nirab_clean/util/core_export.dart';
import 'package:get/get.dart';

class ScheduleWidget extends StatelessWidget {
  final BookingDetailsContent bookingDetailsContent;
  const ScheduleWidget({super.key, required this.bookingDetailsContent});

  @override
  Widget build(BuildContext context) {

    List<String> weekNames = bookingDetailsContent.weekNames ?? [];

    return Container(
      decoration: BoxDecoration(color: Theme.of(context).cardColor , borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
        boxShadow: Get.find<ThemeController>().darkTheme ? null : searchBoxShadow,
      ),//boxShadow: shadow),
      padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
      child: Column(crossAxisAlignment: CrossAxisAlignment.center, mainAxisAlignment: MainAxisAlignment.center , children: [

        Text(
          'this_booking_will_be_continued'.tr,
          style:robotoMedium.copyWith(
            color: Theme.of(context).textTheme.bodyLarge!.color!,
            fontSize: Dimensions.fontSizeSmall + 1,
          ),
        ),
        const SizedBox(height: Dimensions.paddingSizeExtraSmall),

        (bookingDetailsContent.bookingType?.toLowerCase() == "weekly") ?
         Wrap(
           spacing: 8.0, // Horizontal space between the children
           runSpacing: 4.0,
           children: weekNames.map((entry) {
             return Text(entry.toLowerCase().tr, style:robotoLight.copyWith(color: Theme.of(context).textTheme.bodyLarge!.color!),);
           }).toList(),

         ) : Text(
          bookingDetailsContent.bookingType?.toLowerCase() == "daily"  ?
          "${"daily_at".tr } ${DateConverter.convert24HourTimeTo12HourTime(DateTime.tryParse(bookingDetailsContent.time!)!)}": "within_the_selected_days".tr ,
          style:robotoLight.copyWith(color: Theme.of(context).textTheme.bodyLarge!.color!),
        ),
        const SizedBox(height: Dimensions.paddingSizeExtraSmall),

        Text.rich(
          TextSpan(
            style: DefaultTextStyle.of(context).style,
            children: [
              TextSpan(text: bookingDetailsContent.bookingType?.toLowerCase() != "weekly" ? 'from'.tr : 'every_week_from'.tr, style: robotoLight),
              TextSpan(text: "  ${DateConverter.dateStringMonthYear(DateTime.tryParse(bookingDetailsContent.startDate??"") ?? DateTime.now())}", style: robotoBold.copyWith(
                color: Theme.of(context).colorScheme.primary, fontSize: Dimensions.fontSizeSmall
              )),
              TextSpan(text: "  ${'to'.tr}  ", style: robotoLight),
              TextSpan(text: " ${DateConverter.dateStringMonthYear(DateTime.tryParse(bookingDetailsContent.endDate??"") ?? DateTime.now() )}", style: robotoBold.copyWith(
                color: Theme.of(context).colorScheme.primary, fontSize: Dimensions.fontSizeSmall
              )),
            ],
          ),
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: Dimensions.paddingSizeExtraSmall),

        Center(
          child: Text.rich(
            TextSpan(
              style: DefaultTextStyle.of(context).style,
              children: [
                TextSpan(text: 'you_will_receive_this_services_total'.tr, style: robotoLight),
                TextSpan(text: " ${bookingDetailsContent.totalCount ?? ""} ${'times'.tr}", style: robotoBold.copyWith(
                  color: Theme.of(context).textTheme.bodyLarge?.color, fontSize: Dimensions.fontSizeSmall
                )),
              ],
            ),
            textAlign: TextAlign.center,
          ),
        ),

        if(bookingDetailsContent.bookingType?.toLowerCase() == "custom") InkWell(
          onTap: () {
            if(ResponsiveHelper.isDesktop(context)){
              Get.dialog( CustomRepeatBookingScheduleBottomSheet(bookingDetailsContent: bookingDetailsContent,));
            }else{
              showModalBottomSheet(
                context: context, useRootNavigator: true,
                isScrollControlled: true, backgroundColor: Colors.transparent,
                builder: (context) =>  CustomRepeatBookingScheduleBottomSheet(bookingDetailsContent: bookingDetailsContent,),
              );
            }
          },
          child: Container(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(Dimensions.radiusSeven),
              color: Theme.of(context).colorScheme.primary.withValues(alpha: 0.1),
            ),
            margin: const EdgeInsets.only(top: Dimensions.paddingSizeDefault),
            padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeDefault, vertical: Dimensions.paddingSizeExtraSmall),
            child: Text('view_schedules'.tr, style: robotoBold.copyWith(color: Theme.of(context).colorScheme.primary, fontSize: Dimensions.fontSizeSmall),),
          ),
        ),

        const SizedBox(height: Dimensions.paddingSizeTine),

      ]),
    );
  }
}
