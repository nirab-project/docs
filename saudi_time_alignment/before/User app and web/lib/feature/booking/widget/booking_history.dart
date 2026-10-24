import 'package:nirab_clean/common/widgets/nirab/nirab_icon.dart';
import 'package:nirab_clean/feature/booking/widget/booking_screen_shimmer.dart';
import 'package:nirab_clean/feature/booking/widget/timeline/connectors.dart';
import 'package:nirab_clean/feature/booking/widget/timeline/indicator_theme.dart';
import 'package:nirab_clean/feature/booking/widget/timeline/indicators.dart';
import 'package:nirab_clean/feature/booking/widget/timeline/timeline_theme.dart';
import 'package:nirab_clean/feature/booking/widget/timeline/timeline_tile_builder.dart';
import 'package:nirab_clean/feature/booking/widget/timeline/timelines.dart';
import 'package:get/get.dart';
import 'package:nirab_clean/util/core_export.dart';

class BookingHistory extends StatelessWidget {
  final String? id;
  final bool isSubBooking;
  const BookingHistory({super.key, this.id, required this.isSubBooking}) ;
  @override
  Widget build(BuildContext context) {

    Get.lazyPut(() => BookingDetailsController(bookingDetailsRepo: BookingDetailsRepo(sharedPreferences: Get.find(), apiClient: Get.find())));
    return RefreshIndicator(
      onRefresh: () async {
        if(id != null){
          if(isSubBooking) {
            await Get.find<BookingDetailsController>().getSubBookingDetails(bookingId: id!);
          } else {
            await Get.find<BookingDetailsController>().getBookingDetails(bookingId: id!);
          }
        }
      },
      child: GetBuilder<BookingDetailsController>(
        builder: (bookingDetailsController){
          BookingDetailsContent? bookingDetails = isSubBooking ? bookingDetailsController.subBookingDetailsContent : bookingDetailsController.bookingDetailsContent;
           if(bookingDetails != null){
             return SingleChildScrollView(
               physics: AlwaysScrollableScrollPhysics(),
               child: Column(
                 children: [

                   ResponsiveHelper.isDesktop(context) ? const SizedBox() :
                   Column(
                     children: [
                       Gaps.verticalGapOf(Dimensions.paddingSizeExtraLarge),
                       Row(
                         mainAxisAlignment: MainAxisAlignment.center,
                         children: [
                           Text('${'booking_place'.tr} : ',
                             style: robotoMedium.copyWith(fontSize: Dimensions.fontSizeDefault, color: Theme.of(context).textTheme.bodyLarge!.color),
                           ),
                           Text(DateConverter.dateMonthYearTimeTwentyFourFormat(DateConverter.isoUtcStringToLocalDate(bookingDetails.createdAt!.toString())),textDirection: TextDirection.ltr,
                             style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeDefault),),
                         ],
                       ),

                       const SizedBox(height: Dimensions.paddingSizeExtraSmall),
                       Gaps.verticalGapOf(Dimensions.paddingSizeExtraSmall),

                       Row(
                         mainAxisAlignment: MainAxisAlignment.center,
                         children: [
                           Text("${'service_scheduled_date'.tr} : ",
                             style: robotoMedium.copyWith(fontSize: Dimensions.fontSizeDefault, color: Theme.of(context).textTheme.bodyLarge!.color),
                           ),
                           Text(DateConverter.dateMonthYearTimeTwentyFourFormat(DateTime.tryParse(bookingDetails.serviceSchedule!)!),
                             style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeDefault),
                             textDirection:  TextDirection.ltr,),
                         ],
                       ),
                       const SizedBox(height: Dimensions.paddingSizeSmall,),
                       Text.rich(
                         TextSpan(text: '${'payment_status'.tr} : ',
                           style: robotoMedium.copyWith(fontSize: Dimensions.fontSizeDefault, color: Theme.of(context).textTheme.bodyLarge!.color),
                           children: [
                             TextSpan(text: '${bookingDetails.isPaid == 0 ? 'unpaid'.tr: 'paid'.tr} ',
                                 style: robotoMedium.copyWith(fontSize: Dimensions.fontSizeDefault,
                                     color: bookingDetails.isPaid == 0?Theme.of(context).colorScheme.error : Colors.green,decoration: TextDecoration.none)),
                           ],
                         ),
                       ),
                       const SizedBox(height: Dimensions.paddingSizeSmall,),
                       Text.rich(
                         TextSpan(
                           text: '${'booking_status'.tr} : ',
                           style: robotoMedium.copyWith(fontSize: Dimensions.fontSizeDefault, color: Theme.of(context).textTheme.bodyLarge!.color),
                           children: [
                             TextSpan(
                                 text: bookingDetails.bookingStatus!.tr,
                                 style: robotoMedium.copyWith(fontSize: Dimensions.fontSizeDefault, color:  Theme.of(context).colorScheme.primary)),
                           ],
                         ),
                       ),
                       Gaps.verticalGapOf(Dimensions.paddingSizeDefault),
                     ],
                   ),


                   Padding(
                     padding:  const EdgeInsets.all(Dimensions.paddingSizeDefault),
                     child: HistoryStatus(
                       bookingDetailsContent: bookingDetails,
                       statusHistories: bookingDetails.statusHistories,
                       scheduleHistories: bookingDetails.scheduleHistories,
                       increment: bookingDetails.scheduleHistories!.length > 1  && bookingDetails.statusHistories!.isNotEmpty ? 2 : 1,
                     ),
                   ),
                   const SizedBox(height: Dimensions.paddingSizeExtraLarge,),
                 ],
               ),
             );
           }else{
             return const SingleChildScrollView(child: BookingScreenShimmer());
           }
        },
      ),
    );
  }
}

class HistoryStatus extends StatelessWidget {
  final BookingDetailsContent? bookingDetailsContent;
  final List<StatusHistories>? statusHistories;
  final List<ScheduleHistories>? scheduleHistories;
  final int increment;
  const HistoryStatus({super.key,
    required this.statusHistories, this.scheduleHistories, required this.increment, this.bookingDetailsContent
  });
  @override
  Widget build(BuildContext context) {
    return Timeline.tileBuilder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      theme: TimelineThemeData(
          nodePosition: 0,
          indicatorTheme: const IndicatorThemeData(position: 0, size: 30.0)),
      padding: EdgeInsets.symmetric(vertical: 20.0,horizontal: Get.find<LocalizationController>().isLtr?0:10),
      builder: TimelineTileBuilder.connected(connectionDirection: ConnectionDirection.before,

        itemCount: statusHistories!.length+increment,

        contentsBuilder: (_, index) {

          return Padding(padding:  const EdgeInsets.only(left: 20.0,bottom: 20.0,top: 7,right: 10), child:
          index==0?
          Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text("${'service_booked_by_customer'.tr} ${bookingDetailsContent?.customer?.firstName?? bookingDetailsContent?.serviceAddress?.contactPersonName??""} "
                  "${bookingDetailsContent?.customer?.lastName??""}",
                  style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeDefault)
              ),
              const SizedBox(height: Dimensions.paddingSizeSmall),

              Text(DateConverter.dateMonthYearTimeTwentyFourFormat(DateConverter.isoUtcStringToLocalDate(scheduleHistories![index].createdAt!)),
                style: robotoRegular.copyWith(
                  fontSize: Dimensions.fontSizeSmall,
                  color: Theme.of(context).secondaryHeaderColor,
                ),
                textDirection: TextDirection.ltr,
              ),
              const SizedBox(height: Dimensions.paddingSizeExtraSmall),
            ],
          ) :index==1 && statusHistories!.isNotEmpty ?
          Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text("${'booking'.tr} ${statusHistories![index-1].bookingStatus.toString().tr.toLowerCase()} ${'by'.tr} "
                  "${statusHistories![index-1].user!.userType.toString().tr} ",
                style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeDefault,),
              ),


              const SizedBox(height: Dimensions.paddingSizeSmall),
              statusHistories![index-1].user?.userType!='provider-admin'?
              Text("${statusHistories![index-1].user?.firstName??""} ${statusHistories![index-1].user?.lastName??""}",
                style: robotoRegular.copyWith(
                  fontSize: Dimensions.fontSizeSmall,
                  color: Theme.of(context).secondaryHeaderColor,
                ),
              ): Text(bookingDetailsContent?.provider?.companyName??"",
                style: robotoRegular.copyWith(
                  fontSize: Dimensions.fontSizeSmall,
                  color: Theme.of(context).secondaryHeaderColor,
                ),
              ),


              const SizedBox(height: Dimensions.paddingSizeSmall,),

              Text(DateConverter.dateMonthYearTimeTwentyFourFormat(DateConverter.isoUtcStringToLocalDate(statusHistories![index-1].createdAt!)),
                style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeSmall,color: Theme.of(context).secondaryHeaderColor),
                textDirection: TextDirection.ltr,
              ),
              const SizedBox(height: Dimensions.paddingSizeDefault,),
            ],
          )

              :index==2 && scheduleHistories!.length>1?
          Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SizedBox(
                height: (scheduleHistories!.length-1)*80,

                child: ListView.builder(itemBuilder: (_,index){
                  return  Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text("${'schedule_changed_by'.tr} ${scheduleHistories![index+1].user!.userType.toString().tr}",
                        style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeDefault,),
                      ),
                      const SizedBox(height: Dimensions.paddingSizeSmall,),
                      if(scheduleHistories?[index+1].user?.userType !='provider-admin')
                      Text("${scheduleHistories![index+1].user?.firstName.toString()} ${scheduleHistories![index+1].user?.lastName.toString()}",
                        style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeSmall,
                            color: Theme.of(context).secondaryHeaderColor),
                        textDirection: TextDirection.ltr,
                      ),
                      if(scheduleHistories?[index+1].user?.userType=='provider-admin')
                        Text("${bookingDetailsContent?.provider?.companyName??""} ",
                          style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeSmall,
                              color: Theme.of(context).secondaryHeaderColor),
                          textDirection: TextDirection.ltr,
                        ),

                      const SizedBox(height: Dimensions.paddingSizeSmall,),

                      Text(DateConverter.dateMonthYearTimeTwentyFourFormat(DateTime.tryParse(scheduleHistories![index+1].schedule!)!),
                        style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeSmall,
                            color: Theme.of(context).secondaryHeaderColor),
                        textDirection: TextDirection.ltr,
                      ),
                      const SizedBox(height: Dimensions.paddingSizeExtraSmall,),

                    ],
                  );},
                  shrinkWrap:true,
                  physics: const NeverScrollableScrollPhysics(),
                  itemCount: scheduleHistories!.length-1,
                ),
              ),
            ],
          )
              :Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text("${'booking'.tr} ${statusHistories?[index-increment].bookingStatus.toString().tr.toLowerCase()} ${'by'.tr} "
                  "${statusHistories?[index-increment].user?.userType.toString().tr} ",
                  style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeDefault)
              ),

              const SizedBox(height: Dimensions.paddingSizeSmall),
              Text(bookingDetailsContent?.provider?.companyName??"",
                  style: robotoRegular.copyWith(
                    fontSize: Dimensions.fontSizeSmall,
                    color: Theme.of(context).secondaryHeaderColor,
                  )
              ),

              const SizedBox(height: Dimensions.paddingSizeSmall),

              Text(DateConverter.dateMonthYearTimeTwentyFourFormat(DateConverter.isoUtcStringToLocalDate(statusHistories![index-increment].updatedAt!)),
                style: robotoRegular.copyWith(fontSize: Dimensions.fontSizeSmall,color: Theme.of(context).secondaryHeaderColor),
                textDirection: TextDirection.ltr,
              ),
              const SizedBox(height: Dimensions.paddingSizeDefault,),
            ],
          ),
          );},

        connectorBuilder: (_, index, c) => SolidLineConnector(color: Theme.of(context).colorScheme.primary),

        indicatorBuilder: (_, index) {
          return DotIndicator(
            color: Theme.of(context).colorScheme.primary,
            child: Center(child : NirabIcon(Icons.check,color:light.cardColor)),
          );
        },
      ),
    );
  }
}