import 'package:nirab_clean/common/widgets/nirab/nirab_icon.dart';
import 'package:nirab_clean/common/widgets/address_selection_drawer.dart';
import 'package:nirab_clean/common/widgets/confirmation_dialog.dart';
import 'package:nirab_clean/common/widgets/custom_app_bar.dart';
import 'package:nirab_clean/common/widgets/menu_drawer.dart';
import 'package:nirab_clean/common/widgets/text_hover.dart';
import 'package:nirab_clean/common/widgets/custom_button.dart';
import 'package:nirab_clean/common/widgets/footer_base_view.dart';
import 'package:nirab_clean/feature/splash/controller/splash_controller.dart';
import 'package:nirab_clean/util/images.dart';
import 'package:nirab_clean/feature/profile/controller/user_controller.dart';
import 'package:nirab_clean/feature/provider/widgets/pro_plan_terms_dialog_widget.dart';
import 'package:nirab_clean/feature/subscription/controller/subscription_controller.dart';
import 'package:nirab_clean/helper/date_converter.dart';
import 'package:nirab_clean/helper/extension_helper.dart';
import 'package:nirab_clean/helper/price_converter.dart';
import 'package:nirab_clean/helper/responsive_helper.dart';
import 'package:nirab_clean/helper/route_helper.dart';
import 'package:nirab_clean/util/dimensions.dart';
import 'package:nirab_clean/util/styles.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class MySubscriptionDetailsScreen extends StatefulWidget {
  const MySubscriptionDetailsScreen({super.key});

  @override
  State<MySubscriptionDetailsScreen> createState() =>
      _MySubscriptionDetailsScreenState();
}

class _MySubscriptionDetailsScreenState
    extends State<MySubscriptionDetailsScreen> {
  bool _membershipActive(UserController user) {
    final plan = user.userInfoModel?.subscribedPlan;
    final expires = DateTime.tryParse(plan?.expireDate ?? '');
    return plan?.status == 'active' &&
        expires != null &&
        expires.isAfter(DateTime.now());
  }

  String _membershipStatus(UserController user) {
    if (_membershipActive(user)) return 'active'.tr;
    if (user.userInfoModel?.subscribedPlan?.status == 'cancelled') {
      return 'ux_pro_cancelled'.tr;
    }
    return 'expired'.tr;
  }

  @override
  void initState() {
    Get.find<SubscriptionController>().getSubscriptionTerms();
    Get.find<SubscriptionController>().getSubscriptionList();
    super.initState();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).colorScheme.surface,
      drawer: ResponsiveHelper.isDesktop(context)
          ? const AddressSelectionDrawer()
          : null,
      endDrawer: ResponsiveHelper.isDesktop(context)
          ? const MenuDrawer()
          : null,
      appBar: CustomAppBar(
        title: 'subscription_plan'.tr,
        centerTitle: true,
        bgColor: Theme.of(context).primaryColor,
        isBackButtonExist: true,
        onBackPressed: () {
          Get.back();
        },
      ),
      body: FooterBaseView(
        isScrollView: true,
        child: GetBuilder<UserController>(
          builder: (userController) {
            return GetBuilder<SubscriptionController>(
              builder: (subscriptionController) {
                return Padding(
                  padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
                  child: Center(
                    child: SizedBox(
                      width: ResponsiveHelper.isDesktop(context)
                          ? Dimensions.webMaxWidth
                          : null,
                      child: Column(
                        children: [
                          const SizedBox(height: Dimensions.paddingSizeSmall),
                          Text(
                            'my_subscription'.tr,
                            style: robotoMedium.copyWith(
                              fontSize: Dimensions.fontSizeLarge,
                              color: Theme.of(
                                context,
                              ).textTheme.bodyLarge?.color,
                            ),
                          ),
                          const SizedBox(height: Dimensions.paddingSizeDefault),

                          Container(
                            decoration: BoxDecoration(
                              color: Theme.of(context).cardColor,
                              borderRadius: BorderRadius.circular(
                                Dimensions.radiusLarge,
                              ),
                              border: Border.all(
                                color: Theme.of(
                                  context,
                                ).hintColor.withValues(alpha: 0.2),
                              ),
                            ),
                            padding: EdgeInsets.all(
                              Dimensions.paddingSizeDefault,
                            ),
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              crossAxisAlignment: CrossAxisAlignment.center,
                              children: [
                                Container(
                                  decoration: BoxDecoration(
                                    color: Theme.of(
                                      context,
                                    ).hintColor.withValues(alpha: 0.1),
                                    borderRadius: BorderRadius.circular(
                                      Dimensions.radiusLarge,
                                    ),
                                    border: Border.all(
                                      color: Theme.of(
                                        context,
                                      ).hintColor.withValues(alpha: 0.2),
                                    ),
                                  ),
                                  padding: const EdgeInsets.all(
                                    Dimensions.paddingSizeDefault,
                                  ),
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Row(
                                        mainAxisAlignment:
                                            MainAxisAlignment.spaceBetween,
                                        children: [
                                          Text(
                                            userController
                                                    .userInfoModel
                                                    ?.subscribedPlan
                                                    ?.planName ??
                                                '',
                                            style: robotoMedium.copyWith(
                                              fontSize:
                                                  Dimensions.fontSizeLarge,
                                              color: Theme.of(
                                                context,
                                              ).textTheme.bodyLarge?.color,
                                            ),
                                          ),
                                          Container(
                                            padding: const EdgeInsets.symmetric(
                                              horizontal:
                                                  Dimensions.paddingSizeSmall,
                                              vertical: Dimensions
                                                  .paddingSizeExtraSmall,
                                            ),
                                            decoration: BoxDecoration(
                                              color: Theme.of(
                                                context,
                                              ).cardColor,
                                              borderRadius:
                                                  BorderRadius.circular(
                                                    Dimensions.radiusLarge,
                                                  ),
                                              border: Border.all(
                                                color: Theme.of(context)
                                                    .hintColor
                                                    .withValues(alpha: 0.3),
                                              ),
                                            ),
                                            child: Text(
                                              _membershipStatus(userController),
                                              style: robotoMedium.copyWith(
                                                color:
                                                    _membershipActive(
                                                      userController,
                                                    )
                                                    ? context
                                                          .customThemeColors
                                                          .success
                                                    : Theme.of(
                                                        context,
                                                      ).hintColor,
                                                fontSize:
                                                    Dimensions.fontSizeSmall,
                                              ),
                                            ),
                                          ),
                                        ],
                                      ),
                                      const SizedBox(
                                        height: Dimensions.paddingSizeLarge,
                                      ),
                                      Row(
                                        children: [
                                          Expanded(
                                            child: Container(
                                              padding: const EdgeInsets.all(
                                                Dimensions.paddingSizeDefault,
                                              ),
                                              decoration: BoxDecoration(
                                                color: Theme.of(
                                                  context,
                                                ).cardColor,
                                                borderRadius:
                                                    BorderRadius.circular(
                                                      Dimensions.radiusDefault,
                                                    ),
                                                border: Border.all(
                                                  color: Theme.of(context)
                                                      .hintColor
                                                      .withValues(alpha: 0.2),
                                                ),
                                              ),
                                              child: Column(
                                                crossAxisAlignment:
                                                    CrossAxisAlignment.start,
                                                children: [
                                                  Text(
                                                    'total_saved'.tr,
                                                    style: robotoRegular
                                                        .copyWith(
                                                          fontSize: Dimensions
                                                              .fontSizeSmall,
                                                          color:
                                                              Theme.of(context)
                                                                  .textTheme
                                                                  .bodyMedium
                                                                  ?.color
                                                                  ?.withValues(
                                                                    alpha: 0.7,
                                                                  ),
                                                        ),
                                                  ),
                                                  const SizedBox(
                                                    height: Dimensions
                                                        .paddingSizeExtraSmall,
                                                  ),
                                                  Text(
                                                    PriceConverter.convertPrice(
                                                      userController
                                                              .userInfoModel
                                                              ?.currentPlanTotalSaved ??
                                                          0,
                                                    ),
                                                    style: robotoBold.copyWith(
                                                      fontSize: Dimensions
                                                          .fontSizeLarge,
                                                      color: Theme.of(context)
                                                          .textTheme
                                                          .bodyLarge
                                                          ?.color,
                                                    ),
                                                  ),
                                                ],
                                              ),
                                            ),
                                          ),
                                          const SizedBox(
                                            width:
                                                Dimensions.paddingSizeDefault,
                                          ),
                                          Expanded(
                                            child: Container(
                                              padding: const EdgeInsets.all(
                                                Dimensions.paddingSizeDefault,
                                              ),
                                              decoration: BoxDecoration(
                                                color: Theme.of(
                                                  context,
                                                ).cardColor,
                                                borderRadius:
                                                    BorderRadius.circular(
                                                      Dimensions.radiusDefault,
                                                    ),
                                                border: Border.all(
                                                  color: Theme.of(context)
                                                      .hintColor
                                                      .withValues(alpha: 0.2),
                                                ),
                                              ),
                                              child: Column(
                                                crossAxisAlignment:
                                                    CrossAxisAlignment.start,
                                                children: [
                                                  Text(
                                                    'orders_placed'.tr,
                                                    style: robotoRegular
                                                        .copyWith(
                                                          fontSize: Dimensions
                                                              .fontSizeSmall,
                                                          color:
                                                              Theme.of(context)
                                                                  .textTheme
                                                                  .bodyMedium
                                                                  ?.color
                                                                  ?.withValues(
                                                                    alpha: 0.7,
                                                                  ),
                                                        ),
                                                  ),
                                                  const SizedBox(
                                                    height: Dimensions
                                                        .paddingSizeExtraSmall,
                                                  ),
                                                  Text(
                                                    '${userController.userInfoModel?.currentPlanTotalOrder ?? 0}',
                                                    style: robotoBold.copyWith(
                                                      fontSize: Dimensions
                                                          .fontSizeLarge,
                                                      color: Theme.of(context)
                                                          .textTheme
                                                          .bodyLarge
                                                          ?.color,
                                                    ),
                                                  ),
                                                ],
                                              ),
                                            ),
                                          ),
                                        ],
                                      ),
                                    ],
                                  ),
                                ),
                                const SizedBox(
                                  height: Dimensions.paddingSizeDefault,
                                ),

                                Container(
                                  decoration: BoxDecoration(
                                    color: Theme.of(context).cardColor,
                                    borderRadius: BorderRadius.circular(
                                      Dimensions.radiusLarge,
                                    ),
                                    border: Border.all(
                                      color: Theme.of(
                                        context,
                                      ).hintColor.withValues(alpha: 0.2),
                                    ),
                                  ),
                                  padding: const EdgeInsets.all(
                                    Dimensions.paddingSizeDefault,
                                  ),
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        'plan_details'.tr,
                                        style: robotoMedium.copyWith(
                                          fontSize: Dimensions.fontSizeDefault,
                                          color: Theme.of(
                                            context,
                                          ).textTheme.bodyLarge?.color,
                                        ),
                                      ),
                                      const SizedBox(
                                        height: Dimensions.paddingSizeDefault,
                                      ),
                                      _buildDetailRow(
                                        context,
                                        Icons.calendar_today_outlined,
                                        'active_since',
                                        userController
                                                    .userInfoModel
                                                    ?.subscribedPlan
                                                    ?.startDate !=
                                                null
                                            ? DateConverter.isoStringToLocalDateOnly(
                                                userController
                                                        .userInfoModel
                                                        ?.subscribedPlan
                                                        ?.startDate ??
                                                    '',
                                              )
                                            : '',
                                      ),
                                      Divider(
                                        color: Theme.of(
                                          context,
                                        ).hintColor.withValues(alpha: 0.2),
                                      ),
                                      _buildDetailRow(
                                        context,
                                        Icons.calendar_today_outlined,
                                        'expires_on',
                                        userController
                                                    .userInfoModel
                                                    ?.subscribedPlan
                                                    ?.expireDate !=
                                                null
                                            ? DateConverter.isoStringToLocalDateOnly(
                                                userController
                                                        .userInfoModel
                                                        ?.subscribedPlan
                                                        ?.expireDate ??
                                                    '',
                                              )
                                            : '',
                                      ),
                                      Divider(
                                        color: Theme.of(
                                          context,
                                        ).hintColor.withValues(alpha: 0.2),
                                      ),
                                      _buildDetailRow(
                                        context,
                                        Icons.credit_card,
                                        'days_remaining',
                                        '${userController.userInfoModel?.currentPlanRemainingDays ?? 0}',
                                      ),
                                      Divider(
                                        color: Theme.of(
                                          context,
                                        ).hintColor.withValues(alpha: 0.2),
                                      ),
                                      _buildDetailRow(
                                        context,
                                        Icons.account_balance_wallet_outlined,
                                        'paid_by',
                                        userController
                                                .userInfoModel
                                                ?.subscribedPlan
                                                ?.paidBy ??
                                            '',
                                      ),
                                    ],
                                  ),
                                ),
                                const SizedBox(
                                  height: Dimensions.paddingSizeDefault,
                                ),

                                Container(
                                  decoration: BoxDecoration(
                                    color: Theme.of(context).cardColor,
                                    borderRadius: BorderRadius.circular(
                                      Dimensions.radiusLarge,
                                    ),
                                    border: Border.all(
                                      color: Theme.of(
                                        context,
                                      ).hintColor.withValues(alpha: 0.2),
                                    ),
                                  ),
                                  padding: const EdgeInsets.all(
                                    Dimensions.paddingSizeDefault,
                                  ),
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        'package_benefits'.tr,
                                        style: robotoMedium.copyWith(
                                          fontSize: Dimensions.fontSizeDefault,
                                          color: Theme.of(
                                            context,
                                          ).textTheme.bodyLarge?.color,
                                        ),
                                      ),
                                      const SizedBox(
                                        height: Dimensions.paddingSizeDefault,
                                      ),
                                      Row(
                                        children: [
                                          Container(
                                            height: 6,
                                            width: 6,
                                            decoration: BoxDecoration(
                                              color: context
                                                  .customThemeColors
                                                  .success,
                                              shape: BoxShape.circle,
                                            ),
                                          ),
                                          const SizedBox(
                                            width: Dimensions.paddingSizeSmall,
                                          ),
                                          Expanded(
                                            child: Text(
                                              _checkDiscountType(),
                                              style: robotoRegular.copyWith(
                                                fontSize:
                                                    Dimensions.fontSizeSmall,
                                                color: Theme.of(
                                                  context,
                                                ).textTheme.bodyLarge?.color,
                                              ),
                                            ),
                                          ),
                                        ],
                                      ),
                                    ],
                                  ),
                                ),
                                const SizedBox(
                                  height: Dimensions.paddingSizeLarge,
                                ),

                                Row(
                                  children: [
                                    Expanded(
                                      child: CustomButton(
                                        isLoading:
                                            subscriptionController.isLoading,
                                        buttonText: 'cancel_subscription'.tr,
                                        onPressed: () => Get.dialog(
                                          ConfirmationDialog(
                                            icon: Images.warning,
                                            title: "${"are_you_sure".tr} ?",
                                            description:
                                                "are_you_want_to_cancel_subscription"
                                                    .tr,
                                            onYesPressed: () {
                                              Get.back();
                                              subscriptionController
                                                  .cancelSubscription();
                                            },
                                          ),
                                        ),
                                        backgroundColor: Theme.of(
                                          context,
                                        ).hintColor.withValues(alpha: 0.2),
                                        textColor: Theme.of(
                                          context,
                                        ).textTheme.bodyLarge?.color,
                                      ),
                                    ),

                                    if (Get.find<SplashController>()
                                            .configModel
                                            .content
                                            ?.isProFeatureActive ==
                                        1) ...[
                                      const SizedBox(
                                        width: Dimensions.paddingSizeDefault,
                                      ),
                                      Expanded(
                                        child: CustomButton(
                                          buttonText: 'renew_subscription'.tr,
                                          textColor: Theme.of(
                                            context,
                                          ).cardColor,
                                          onPressed: () => Get.toNamed(
                                            RouteHelper.getSubscriptionRoute(),
                                          ),
                                        ),
                                      ),
                                    ],
                                  ],
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(height: Dimensions.paddingSizeDefault),

                          Container(
                            decoration: BoxDecoration(
                              color: Theme.of(context).cardColor,
                              borderRadius: BorderRadius.circular(
                                Dimensions.radiusLarge,
                              ),
                              border: Border.all(
                                color: Theme.of(
                                  context,
                                ).hintColor.withValues(alpha: 0.2),
                              ),
                            ),
                            padding: EdgeInsets.all(
                              Dimensions.paddingSizeDefault,
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'frequently_asked_questions'.tr,
                                  style: robotoMedium,
                                ),

                                ListView.separated(
                                  itemCount:
                                      subscriptionController
                                          .subscriptionModel
                                          ?.content
                                          ?.faqs
                                          ?.length ??
                                      0,
                                  shrinkWrap: true,
                                  physics: NeverScrollableScrollPhysics(),
                                  itemBuilder: (context, index) {
                                    return ExpansionTile(
                                      tilePadding: EdgeInsets.zero,
                                      shape: const Border(),
                                      collapsedShape: const Border(),
                                      textColor: Theme.of(
                                        context,
                                      ).textTheme.bodyMedium?.color,
                                      iconColor: Theme.of(
                                        context,
                                      ).textTheme.bodyMedium?.color,
                                      title: Text(
                                        subscriptionController
                                                .subscriptionModel
                                                ?.content
                                                ?.faqs?[index]
                                                .question ??
                                            '',
                                        style: robotoRegular.copyWith(
                                          fontSize: Dimensions.fontSizeSmall,
                                        ),
                                      ),
                                      children: [
                                        Align(
                                          alignment: Alignment.centerLeft,
                                          child: Text(
                                            subscriptionController
                                                    .subscriptionModel
                                                    ?.content
                                                    ?.faqs?[index]
                                                    .answer ??
                                                '',
                                            style: robotoRegular.copyWith(
                                              fontSize:
                                                  Dimensions.fontSizeSmall,
                                            ),
                                          ),
                                        ),
                                      ],
                                    );
                                  },
                                  separatorBuilder: (ctx, idx) {
                                    return Divider(
                                      color: Theme.of(
                                        context,
                                      ).hintColor.withValues(alpha: 0.5),
                                    );
                                  },
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(height: Dimensions.paddingSizeSmall),

                          Center(
                            child: TextHover(
                              cursor: SystemMouseCursors.click,
                              builder: (isHovered) => GestureDetector(
                                onTap: () {
                                  if (ResponsiveHelper.isDesktop(context)) {
                                    showDialog(
                                      context: context,
                                      builder: (context) {
                                        return Dialog(
                                          shape: RoundedRectangleBorder(
                                            borderRadius: BorderRadius.circular(
                                              Dimensions.radiusLarge,
                                            ),
                                          ),
                                          insetPadding:
                                              const EdgeInsets.symmetric(
                                                horizontal: 20,
                                                vertical: 20,
                                              ),
                                          child: Container(
                                            width: 500,
                                            padding: const EdgeInsets.all(
                                              Dimensions.paddingSizeDefault,
                                            ),
                                            child:
                                                const ProPlanTermsDialogWidget(
                                                  isBottomSheet: false,
                                                ),
                                          ),
                                        );
                                      },
                                    );
                                  } else {
                                    showModalBottomSheet(
                                      context: context,
                                      isScrollControlled: true,
                                      backgroundColor: Colors.transparent,
                                      builder: (context) {
                                        return DraggableScrollableSheet(
                                          initialChildSize: 0.6,
                                          minChildSize: 0.4,
                                          maxChildSize: 0.95,
                                          expand: false,
                                          builder: (context, scrollController) {
                                            return ProPlanTermsDialogWidget(
                                              scrollController:
                                                  scrollController,
                                              isBottomSheet: true,
                                            );
                                          },
                                        );
                                      },
                                    );
                                  }
                                },
                                child: AnimatedDefaultTextStyle(
                                  duration: const Duration(milliseconds: 150),
                                  style: robotoSemiBold.copyWith(
                                    fontSize: Dimensions.fontSizeSmall,
                                    color: isHovered
                                        ? Theme.of(context).primaryColor
                                        : Theme.of(
                                            context,
                                          ).textTheme.bodyMedium?.color,
                                    decorationColor: isHovered
                                        ? Theme.of(context).primaryColor
                                        : Theme.of(
                                            context,
                                          ).textTheme.bodyMedium?.color,
                                    decoration: TextDecoration.underline,
                                  ),
                                  child: Text('terms_and_conditions'.tr),
                                ),
                              ),
                            ),
                          ),
                          const SizedBox(height: Dimensions.paddingSizeSmall),
                        ],
                      ),
                    ),
                  ),
                );
              },
            );
          },
        ),
      ),
    );
  }

  String _checkDiscountType() {
    final benefits =
        Get.find<SubscriptionController>().subscriptionModel?.content?.benefits;

    if (benefits?.discount?.status == 1) {
      return '${'you_can_able_discount'.tr} ${benefits?.discount?.discount}% ${'for_all_bookings'.tr}';
    } else if (benefits?.coupon?.status == 1) {
      return 'you_can_able_coupon_from_coupon_list'.tr;
    } else {
      return '${'you_can_able_service'.tr} ${(benefits?.serviceFee?.type ?? '').tr}';
    }
  }

  Widget _buildDetailRow(
    BuildContext context,
    IconData icon,
    String title,
    String value,
  ) {
    return Padding(
      padding: const EdgeInsets.symmetric(
        vertical: Dimensions.paddingSizeSmall,
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          NirabIcon(icon, size: 20, color: Theme.of(context).hintColor),
          const SizedBox(width: Dimensions.paddingSizeDefault),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title.tr,
                  style: robotoRegular.copyWith(
                    fontSize: Dimensions.fontSizeSmall,
                    color: Theme.of(
                      context,
                    ).textTheme.bodyMedium?.color?.withValues(alpha: 0.7),
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  value.tr,
                  style: robotoMedium.copyWith(
                    fontSize: Dimensions.fontSizeSmall,
                    color: Theme.of(context).textTheme.bodyLarge?.color,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
