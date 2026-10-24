import 'package:demandium_provider/helper/extension_helper.dart';
import 'package:demandium_provider/theme/saudi_riyal.dart';
import 'package:demandium_provider/util/core_export.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart' show DateFormat;

/// Shared labels and formatting for the booking list, overview and status tab.
abstract final class BookingDisplay {
  static String statusLabel(String? status) {
    const keys = {
      'pending': 'quality_event_pending',
      'accepted': 'accepted',
      'assigned': 'quality_event_assigned',
      'ongoing': 'ongoing',
      'on_the_way': 'quality_event_on_the_way',
      'arrived': 'quality_event_arrived',
      'work_finished': 'quality_event_work_finished',
      'completed': 'completed',
      'canceled': 'canceled',
    };
    return (keys[status] ?? 'booking_status_unknown').tr;
  }

  static String price(BuildContext context, double amount) {
    PriceConverter.getCurrency();
    final formatted = PriceConverter.convertPrice(
      amount,
      isShowLongPrice: true,
    ).trim();
    if (PriceConverter.currencySymbol == SaudiRiyal.symbol) {
      return SaudiRiyal.format(
        formatted.replaceAll(SaudiRiyal.symbol, '').trim(),
      );
    }
    return formatted;
  }

  static String date(BuildContext context, String? raw, {required bool utc}) {
    final value = raw?.trim() ?? '';
    final parsed = DateTime.tryParse(value);
    if (parsed == null) return 'booking_value_unavailable'.tr;
    // Creation timestamps are UTC; unzoned service schedules are local wall time.
    final hasZone = RegExp(
      r'(Z|[+-]\d{2}:?\d{2})$',
      caseSensitive: false,
    ).hasMatch(value);
    final date = utc && !hasZone
        ? DateTime.utc(
            parsed.year,
            parsed.month,
            parsed.day,
            parsed.hour,
            parsed.minute,
            parsed.second,
          ).toLocal()
        : parsed.toLocal();
    final locale = Localizations.localeOf(context).languageCode;
    final timeFormat =
        Get.find<SplashController>().configModel.content?.timeFormat == '24'
        ? 'HH:mm'
        : 'h:mm a';
    return '${DateFormat.yMMMd(locale).format(date)} • ${DateFormat(timeFormat, locale).format(date)}';
  }
}

class BookingDateRow extends StatelessWidget {
  const BookingDateRow({
    super.key,
    required this.icon,
    required this.label,
    required this.value,
  });
  final IconData icon;
  final String label, value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        ExcludeSemantics(
          child: NirabIcon(icon, size: 20, color: theme.colorScheme.primary),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                value,
                style: theme.textTheme.bodyMedium?.copyWith(
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class BookingStatusBadge extends StatelessWidget {
  const BookingStatusBadge({super.key, required this.status});
  final String? status;
  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
      decoration: BoxDecoration(
        color:
            context.customThemeColors.buttonBackgroundColorMap[status] ??
            theme.colorScheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(30),
      ),
      child: Text(
        BookingDisplay.statusLabel(status),
        style: theme.textTheme.labelMedium?.copyWith(
          color:
              context.customThemeColors.buttonTextColorMap[status] ??
              theme.colorScheme.onSurfaceVariant,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}
