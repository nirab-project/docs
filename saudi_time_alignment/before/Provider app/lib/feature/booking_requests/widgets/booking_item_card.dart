import 'package:demandium_provider/helper/extension_helper.dart';
import 'package:get/get.dart';
import 'package:demandium_provider/util/core_export.dart';
import 'package:demandium_provider/feature/booking_requests/controller/calendar_controller.dart';

/// Booking item card widget that displays booking information
///
/// This widget displays comprehensive booking details including:
/// - Booking ID and header
/// - Service date
/// - Service location (provider/customer location)
/// - Booking status badge
/// - Total booking amount
///
/// **Usage Example:**
/// ```dart
/// BookingItemCard(
///   booking: calenderBooking,
///   controller: bookingCalendarController,
/// )
/// ```
class BookingItemCard extends StatelessWidget {
  /// The booking data to display
  final CalenderBooking booking;

  /// Controller for managing booking-related logic
  final BookingCalendarController controller;

  const BookingItemCard({
    super.key,
    required this.booking,
    required this.controller,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
      onTap: () {
        if (booking.isRepeatBooking ?? false) {
          Get.toNamed(
            RouteHelper.getRepeatBookingDetailsRoute(bookingId: booking.id),
          );
        } else {
          Get.toNamed(
            RouteHelper.getBookingDetailsRoute(bookingId: booking.id),
          );
        }
      },
      child: Container(
        padding: const EdgeInsets.only(top: Dimensions.paddingSizeDefault),
        decoration: BoxDecoration(
          color: Theme.of(context).cardColor,
          borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
          border: Border.all(
            color: Theme.of(context).hintColor.withValues(alpha: 0.08),
          ),
          boxShadow: [
            BoxShadow(
              color: Theme.of(
                context,
              ).textTheme.bodyLarge!.color!.withValues(alpha: 0.05),
              blurRadius: 9,
              offset: Offset(0, 5),
            ),
            BoxShadow(
              color: Theme.of(
                context,
              ).textTheme.bodyLarge!.color!.withValues(alpha: 0.05),
              blurRadius: 4,
              offset: Offset(0, 0),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _BookingCardHeader(booking: booking),
            Divider(
              height: Dimensions.paddingSizeLarge,
              color: Theme.of(context).hintColor.withValues(alpha: 0.2),
            ),

            _BookingCardServiceDate(booking: booking),
            const SizedBox(height: Dimensions.paddingSizeSmall),

            _BookingCardServiceLocation(booking: booking),
            const SizedBox(height: Dimensions.paddingSizeDefault),

            _BookingCardFooter(booking: booking),
          ],
        ),
      ),
    );
  }
}

/// Service date row widget
///
/// Displays the scheduled service date in a formatted manner.
class _BookingCardServiceDate extends StatelessWidget {
  final CalenderBooking booking;

  const _BookingCardServiceDate({required this.booking});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(
        horizontal: Dimensions.paddingSizeDefault,
      ),
      child: Row(
        children: [
          Text(
            '${'service_date'.tr} :',
            style: robotoRegular.copyWith(
              fontSize: Dimensions.fontSizeDefault,
              color: Theme.of(context).hintColor,
            ),
          ),
          const SizedBox(width: Dimensions.paddingSizeSmall),

          Expanded(
            child: Text(
              DateConverter.dateMonthYearLocalTime(
                booking.serviceSchedule.toLocal(),
              ),
              style: robotoMedium.copyWith(
                fontSize: Dimensions.fontSizeDefault,
                color: Theme.of(context).textTheme.bodyLarge?.color,
              ),
              textAlign: TextAlign.end,
            ),
          ),
        ],
      ),
    );
  }
}

/// Service location widget with badge
///
/// Displays the service location (provider or customer location)
/// with an icon and colored badge.
class _BookingCardServiceLocation extends StatelessWidget {
  final CalenderBooking booking;

  const _BookingCardServiceLocation({required this.booking});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(
        horizontal: Dimensions.paddingSizeDefault,
      ),
      child: Row(
        children: [
          Text(
            '${'service_location'.tr} :',
            style: robotoRegular.copyWith(
              fontSize: Dimensions.fontSizeDefault,
              color: Theme.of(context).hintColor,
            ),
          ),
          const SizedBox(width: Dimensions.paddingSizeSmall),
          Expanded(
            child: Align(
              alignment: Alignment.centerRight,
              child: Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: Dimensions.paddingSizeDefault,
                  vertical: Dimensions.paddingSizeExtraSmall,
                ),
                decoration: BoxDecoration(
                  color: Theme.of(context).primaryColor.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    NirabIcon(
                      Icons.location_on,
                      size: Dimensions.paddingSizeDefault,
                      color: Theme.of(context).primaryColor,
                    ),
                    const SizedBox(width: Dimensions.paddingSizeTini),
                    Text(
                      booking.serviceLocation == ServiceLocation.provider
                          ? 'at_provider_location'.tr
                          : 'at_customer_location'.tr,
                      style: robotoMedium.copyWith(
                        fontSize: Dimensions.fontSizeSmall,
                        color: Theme.of(context).primaryColor,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Footer widget showing status badge and price
///
/// Displays:
/// - Booking status with color-coded badge
/// - Total booking amount
class _BookingCardFooter extends StatelessWidget {
  final CalenderBooking booking;

  const _BookingCardFooter({required this.booking});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
      decoration: BoxDecoration(
        color: Theme.of(context).hintColor.withValues(alpha: 0.03),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Container(
            padding: const EdgeInsets.symmetric(
              horizontal: Dimensions.paddingSizeDefault,
              vertical: Dimensions.paddingSizeExtraSmall,
            ),
            decoration: BoxDecoration(
              color: context
                  .customThemeColors
                  .buttonBackgroundColorMap[booking.bookingStatus]
                  ?.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
            ),
            child: Text(
              booking.bookingStatus.tr,
              style: robotoMedium.copyWith(
                fontSize: Dimensions.fontSizeSmall,
                color: context
                    .customThemeColors
                    .buttonTextColorMap[booking.bookingStatus],
              ),
            ),
          ),
          Text(
            PriceConverter.convertPrice(booking.totalBookingAmount),
            style: robotoBold.copyWith(
              fontSize: Dimensions.fontSizeExtraLarge,
              color: Theme.of(context).primaryColor,
            ),
          ),
        ],
      ),
    );
  }
}

// ========== Private Widget Classes ==========

/// Private widget for booking card header
class _BookingCardHeader extends StatelessWidget {
  final CalenderBooking booking;

  const _BookingCardHeader({required this.booking});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(
        horizontal: Dimensions.paddingSizeDefault,
      ),
      child: Row(
        children: [
          Text(
            '${"booking".tr} # ',
            style: robotoMedium.copyWith(
              fontSize: Dimensions.fontSizeLarge,
              color: Theme.of(context).colorScheme.onSurfaceVariant,
            ),
          ),
          Text(
            booking.readableId.toString(),
            style: robotoBold.copyWith(
              color: Theme.of(context).textTheme.bodyLarge?.color,
              fontSize: Dimensions.fontSizeLarge,
            ),
            overflow: TextOverflow.ellipsis,
          ),

          if (booking.isRepeatBooking ?? false)
            Container(
              decoration: const BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.green,
              ),
              padding: const EdgeInsets.all(2),
              margin: const EdgeInsets.symmetric(
                horizontal: Dimensions.paddingSizeExtraSmall,
              ),
              child: const NirabIcon(
                Icons.repeat,
                color: Colors.white,
                size: 12,
              ),
            ),

          Spacer(),

          Text(
            DateConverter.convertDateTimeToTime(
              DateConverter.isoUtcStringToLocalDate(
                booking.createdAt.toIso8601String(),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
