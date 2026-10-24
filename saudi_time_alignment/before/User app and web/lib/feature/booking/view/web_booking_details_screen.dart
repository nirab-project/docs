import 'package:nirab_clean/common/widgets/nirab/nirab_icon.dart';
import 'package:nirab_clean/common/widgets/nirab/nirab_service_card.dart';
import 'package:nirab_clean/common/widgets/nirab/nirab_foundation.dart';
import 'package:nirab_clean/feature/booking/widget/booking_status_widget.dart';
import 'package:nirab_clean/feature/booking/widget/booking_workflow_panel.dart';
import 'package:nirab_clean/feature/booking/widget/booking_service_location.dart';
import 'package:nirab_clean/feature/booking/widget/payment_info_widget.dart';
import 'package:nirab_clean/feature/booking/widget/timeline/customer_info_widget.dart';
import 'package:nirab_clean/util/core_export.dart';
import 'package:nirab_clean/feature/booking/widget/booking_otp_widget.dart';
import 'package:nirab_clean/feature/booking/widget/booking_photo_evidence.dart';
import 'package:nirab_clean/feature/booking/widget/booking_screen_shimmer.dart';
import 'package:nirab_clean/feature/booking/widget/regular/booking_summery_widget.dart';
import 'package:get/get.dart';
import 'package:nirab_clean/feature/quality/view/quality_center_screen.dart';

class WebBookingDetailsScreen extends StatelessWidget {
  final TabController? tabController;
  final String? id;
  final bool isSubBooking;
  const WebBookingDetailsScreen({
    super.key,
    this.tabController,
    this.id,
    required this.isSubBooking,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: FooterBaseView(
        child: GetBuilder<BookingDetailsController>(
          builder: (bookingDetailsController) {
            BookingDetailsContent? bookingDetails = isSubBooking
                ? bookingDetailsController.subBookingDetailsContent
                : bookingDetailsController.bookingDetailsContent;

            if (bookingDetails != null) {
              bool isOtpStatusActive =
                  ((Get.find<SplashController>()
                          .configModel
                          .content!
                          .confirmationOtpStatus! ||
                      bookingDetails.executionRequiresOtp) &&
                  [
                    'accepted',
                    'assigned',
                    'on_the_way',
                    'arrived',
                    'ongoing',
                    'work_finished',
                  ].contains(bookingDetails.bookingStatus));

              return Center(
                child: SizedBox(
                  width: Dimensions.webMaxWidth,
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.start,
                    children: [
                      BookingDetailsTopCard(
                        bookingDetailsContent: bookingDetails,
                      ),
                      if (!isSubBooking &&
                          Get.find<AuthController>().isLoggedIn())
                        TextButton.icon(
                          onPressed: () => Get.to(
                            () => QualityCenterScreen(
                              bookingId: bookingDetails.id,
                              bookingStatus: bookingDetails.bookingStatus,
                            ),
                          ),
                          icon: const NirabIcon(Icons.support_agent),
                          label: Text('quality_center'.tr),
                        ),
                      if (bookingDetails.isWarranty)
                        Text('quality_warranty'.tr, style: robotoBold),

                      if (Get.find<AuthController>().isLoggedIn())
                        BookingWorkflowPanel(
                          booking: bookingDetails,
                          isSubBooking: isSubBooking,
                        ),
                      BookingTabBar(
                        tabController: tabController,
                        isSubBooking: isSubBooking,
                      ),

                      SizedBox(
                        height: isOtpStatusActive ? 700 : 650,
                        child: TabBarView(
                          controller: tabController,
                          children: [
                            WebBookingDetailsSection(
                              bookingDetails: bookingDetails,
                              isSubBooking: isSubBooking,
                            ),
                            BookingHistory(id: id, isSubBooking: isSubBooking),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              );
            } else {
              return const Center(
                child: SizedBox(
                  width: Dimensions.webMaxWidth,
                  child: BookingScreenShimmer(),
                ),
              );
            }
          },
        ),
      ),
    );
  }
}

class BookingDetailsTopCard extends StatelessWidget {
  const BookingDetailsTopCard({super.key, required this.bookingDetailsContent});
  final BookingDetailsContent bookingDetailsContent;
  @override
  Widget build(BuildContext context) {
    final booking = bookingDetailsContent;
    final schedule = DateTime.tryParse(booking.serviceSchedule ?? '');
    final created = DateTime.tryParse(booking.createdAt ?? '');
    return Padding(
      padding: const EdgeInsets.all(16),
      child: NirabPanel(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            NirabSectionHeader(
              title: '${'booking'.tr} #${booking.readableId ?? ''}',
            ),
            if (booking.isRepeatBooking == 1) Text('repeat'.tr),
            const SizedBox(height: 12),
            if (created != null)
              Text(
                '${'booking_place'.tr}: ${DateConverter.dateMonthYearTimeTwentyFourFormat(created.toLocal())}',
              ),
            if (schedule != null)
              Text(
                '${'service_scheduled_date'.tr}: ${DateConverter.dateMonthYearTimeTwentyFourFormat(schedule)}',
              ),
            const SizedBox(height: 12),
            Align(
              alignment: AlignmentDirectional.centerStart,
              child: BookingStatusButtonWidget(
                bookingStatus: booking.bookingStatus,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class WebBookingDetailsSection extends StatelessWidget {
  final BookingDetailsContent bookingDetails;
  final bool isSubBooking;
  const WebBookingDetailsSection({
    super.key,
    required this.bookingDetails,
    required this.isSubBooking,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SingleChildScrollView(
        child: Column(
          children: [
            const SizedBox(height: Dimensions.paddingSizeDefault),
            NirabAdaptiveGrid(
              minWidth: 480,
              children: [
                BookingSummeryWidget(bookingDetails: bookingDetails),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 5),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.start,
                    children: [
                      ((Get.find<SplashController>()
                                      .configModel
                                      .content!
                                      .confirmationOtpStatus! ||
                                  bookingDetails.executionRequiresOtp) &&
                              [
                                'accepted',
                                'assigned',
                                'on_the_way',
                                'arrived',
                                'ongoing',
                                'work_finished',
                              ].contains(bookingDetails.bookingStatus))
                          ? BookingOtpWidget(bookingDetails: bookingDetails)
                          : const SizedBox(),

                      ((Get.find<SplashController>()
                                      .configModel
                                      .content!
                                      .confirmationOtpStatus! ||
                                  bookingDetails.executionRequiresOtp) &&
                              [
                                'accepted',
                                'assigned',
                                'on_the_way',
                                'arrived',
                                'ongoing',
                                'work_finished',
                              ].contains(bookingDetails.bookingStatus))
                          ? const SizedBox()
                          : const SizedBox(height: Dimensions.paddingSizeEight),

                      PaymentView(
                        bookingDetails: bookingDetails,
                        isSubBooking: isSubBooking,
                      ),
                      const SizedBox(height: Dimensions.paddingSizeDefault),

                      CustomerInfoWidget(bookingDetails: bookingDetails),

                      BookingServiceLocation(bookingDetails: bookingDetails),

                      if (bookingDetails.hasEvidence)
                        BookingPhotoEvidence(
                          bookingDetailsContent: bookingDetails,
                        ),

                      const SizedBox(height: Dimensions.paddingSizeDefault),

                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          bookingDetails.provider != null
                              ? SizedBox(
                                  height: 165,
                                  width: 285,
                                  child: Container(
                                    width: double.infinity,
                                    decoration: BoxDecoration(
                                      color: Theme.of(context).cardColor,
                                      borderRadius: BorderRadius.circular(
                                        Dimensions.radiusDefault,
                                      ),
                                      border: Border.all(
                                        color: Theme.of(
                                          context,
                                        ).hintColor.withValues(alpha: 0.3),
                                      ),
                                      boxShadow:
                                          Get.find<ThemeController>().darkTheme
                                          ? null
                                          : searchBoxShadow,
                                    ), //boxShadow: shadow),
                                    child: Column(
                                      children: [
                                        Gaps.verticalGapOf(
                                          Dimensions.paddingSizeDefault,
                                        ),
                                        Padding(
                                          padding: const EdgeInsets.symmetric(
                                            horizontal:
                                                Dimensions.paddingSizeDefault,
                                          ),
                                          child: Text(
                                            "provider_info".tr,
                                            style: robotoMedium.copyWith(
                                              fontSize:
                                                  Dimensions.fontSizeSmall,
                                              color: Theme.of(
                                                context,
                                              ).textTheme.bodyLarge!.color!,
                                            ),
                                          ),
                                        ),
                                        Gaps.verticalGapOf(
                                          Dimensions.paddingSizeSmall,
                                        ),

                                        ClipRRect(
                                          borderRadius: const BorderRadius.all(
                                            Radius.circular(
                                              Dimensions.paddingSizeExtraLarge,
                                            ),
                                          ),
                                          child: SizedBox(
                                            width: Dimensions.imageSize,
                                            height: Dimensions.imageSize,
                                            child: CustomImage(
                                              image:
                                                  "${bookingDetails.provider?.logoFullPath}",
                                            ),
                                          ),
                                        ),
                                        Gaps.verticalGapOf(
                                          Dimensions.paddingSizeExtraSmall,
                                        ),
                                        Row(
                                          mainAxisAlignment:
                                              MainAxisAlignment.center,
                                          children: [
                                            Flexible(
                                              child: Padding(
                                                padding:
                                                    const EdgeInsets.symmetric(
                                                      horizontal: Dimensions
                                                          .paddingSizeDefault,
                                                    ),
                                                child: Text(
                                                  "${bookingDetails.provider?.companyName}",
                                                  style: robotoBold.copyWith(
                                                    fontSize: Dimensions
                                                        .fontSizeExtraSmall,
                                                  ),
                                                  maxLines: 2,
                                                  overflow:
                                                      TextOverflow.ellipsis,
                                                ),
                                              ),
                                            ),

                                            if (bookingDetails
                                                    .provider
                                                    ?.isProviderVerified ??
                                                false) ...[
                                              const SizedBox(
                                                width:
                                                    Dimensions.paddingSizeSmall,
                                              ),

                                              NirabAssetIcon(
                                                Images.verified,
                                                height: 15,
                                                width: 15,
                                              ),
                                              const SizedBox(
                                                width: Dimensions
                                                    .paddingSizeDefault,
                                              ),
                                            ],
                                          ],
                                        ),
                                        Gaps.verticalGapOf(
                                          Dimensions.paddingSizeExtraSmall,
                                        ),

                                        Text(
                                          "${bookingDetails.provider?.companyPhone}",
                                          style: robotoRegular.copyWith(
                                            fontSize:
                                                Dimensions.fontSizeExtraSmall,
                                          ),
                                        ),
                                        Gaps.verticalGapOf(
                                          Dimensions.paddingSizeDefault,
                                        ),
                                      ],
                                    ),
                                  ),
                                )
                              : const SizedBox(),

                          bookingDetails.serviceman != null
                              ? SizedBox(
                                  height: 165,
                                  width: 285,
                                  child: Container(
                                    width: double.infinity,
                                    decoration: BoxDecoration(
                                      color: Theme.of(context).cardColor,
                                      borderRadius: BorderRadius.circular(
                                        Dimensions.radiusDefault,
                                      ),
                                      border: Border.all(
                                        color: Theme.of(
                                          context,
                                        ).hintColor.withValues(alpha: 0.3),
                                      ),
                                      boxShadow:
                                          Get.find<ThemeController>().darkTheme
                                          ? null
                                          : searchBoxShadow,
                                    ),
                                    child: Column(
                                      children: [
                                        Gaps.verticalGapOf(
                                          Dimensions.paddingSizeDefault,
                                        ),
                                        Padding(
                                          padding: const EdgeInsets.symmetric(
                                            horizontal:
                                                Dimensions.paddingSizeDefault,
                                          ),
                                          child: Text(
                                            "service_man_info".tr,
                                            style: robotoMedium.copyWith(
                                              fontSize:
                                                  Dimensions.fontSizeSmall,
                                              color: Theme.of(
                                                context,
                                              ).textTheme.bodyLarge!.color!,
                                            ),
                                          ),
                                        ),
                                        Gaps.verticalGapOf(
                                          Dimensions.paddingSizeSmall,
                                        ),

                                        ClipRRect(
                                          borderRadius: const BorderRadius.all(
                                            Radius.circular(
                                              Dimensions.paddingSizeExtraLarge,
                                            ),
                                          ),
                                          child: SizedBox(
                                            width: Dimensions.imageSize,
                                            height: Dimensions.imageSize,
                                            child: CustomImage(
                                              image:
                                                  bookingDetails
                                                      .serviceman
                                                      ?.user
                                                      ?.profileImageFullPath ??
                                                  "",
                                            ),
                                          ),
                                        ),
                                        Gaps.verticalGapOf(
                                          Dimensions.paddingSizeExtraSmall,
                                        ),
                                        Text(
                                          "${bookingDetails.serviceman!.user?.firstName} ${bookingDetails.serviceman!.user?.lastName}",
                                          style: robotoBold.copyWith(
                                            fontSize:
                                                Dimensions.fontSizeExtraSmall,
                                          ),
                                        ),
                                        Gaps.verticalGapOf(
                                          Dimensions.paddingSizeExtraSmall,
                                        ),
                                        Text(
                                          "${bookingDetails.serviceman!.user!.phone}",
                                          style: robotoRegular.copyWith(
                                            fontSize:
                                                Dimensions.fontSizeExtraSmall,
                                          ),
                                        ),
                                        Gaps.verticalGapOf(
                                          Dimensions.paddingSizeDefault,
                                        ),
                                      ],
                                    ),
                                  ),
                                )
                              : const SizedBox(),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ],
        ),
      ),

      bottomNavigationBar: Get.find<AuthController>().isLoggedIn()
          ? GetBuilder<BookingDetailsController>(
              builder: (bookingDetailsController) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Padding(
                      padding: const EdgeInsets.only(
                        bottom: Dimensions.paddingSizeDefault,
                        left: Dimensions.paddingSizeDefault,
                        right: Dimensions.paddingSizeDefault,
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.end,
                        children: [
                          FloatingActionButton(
                            hoverColor: Colors.transparent,
                            elevation: 0.0,
                            backgroundColor: Theme.of(
                              context,
                            ).colorScheme.primary,
                            onPressed: () {
                              BookingDetailsContent? bookingDetailsContent =
                                  isSubBooking
                                  ? bookingDetailsController
                                        .subBookingDetailsContent
                                  : bookingDetailsController
                                        .bookingDetailsContent;

                              if (bookingDetailsContent?.provider != null) {
                                showModalBottomSheet(
                                  useRootNavigator: true,
                                  isScrollControlled: true,
                                  backgroundColor: Colors.transparent,
                                  context: context,
                                  builder: (context) => CreateChannelDialog(
                                    isSubBooking: isSubBooking,
                                  ),
                                );
                              } else {
                                customSnackBar(
                                  'provider_or_service_man_assigned'.tr,
                                  type: ToasterMessageType.info,
                                );
                              }
                            },
                            child: NirabIcon(
                              Icons.message_rounded,
                              color: Theme.of(context).primaryColorLight,
                            ),
                          ),
                        ],
                      ),
                    ),

                    !ResponsiveHelper.isDesktop(context) &&
                            bookingDetailsController
                                    .bookingDetailsContent!
                                    .bookingStatus ==
                                'completed'
                        ? Row(
                            children: [
                              Expanded(
                                child: CustomButton(
                                  radius: 0,
                                  buttonText: 'review'.tr,
                                  onPressed: () {
                                    showModalBottomSheet(
                                      context: context,
                                      useRootNavigator: true,
                                      isScrollControlled: true,
                                      backgroundColor: Colors.transparent,
                                      builder: (context) =>
                                          ReviewRecommendationDialog(
                                            id: bookingDetailsController
                                                .bookingDetailsContent!
                                                .id!,
                                          ),
                                    );
                                  },
                                ),
                              ),

                              Container(
                                width: 3,
                                height: 50,
                                color: Theme.of(context).disabledColor,
                              ),

                              GetBuilder<ServiceBookingController>(
                                builder: (serviceBookingController) {
                                  return Expanded(
                                    child: serviceBookingController.isLoading
                                        ? const Center(
                                            child: CircularProgressIndicator(),
                                          )
                                        : CustomButton(
                                            radius: 0,
                                            buttonText: 'rebook'.tr,
                                            onPressed: () {
                                              serviceBookingController
                                                  .checkCartForRebook(
                                                    bookingDetailsController
                                                        .bookingDetailsContent!
                                                        .id!,
                                                  );
                                            },
                                          ),
                                  );
                                },
                              ),
                            ],
                          )
                        : const SizedBox(),
                  ],
                );
              },
            )
          : null,
    );
  }
}
