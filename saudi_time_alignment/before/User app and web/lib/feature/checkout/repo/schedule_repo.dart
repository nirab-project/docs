import 'package:nirab_clean/api/remote/client_api.dart';
import 'package:get/get.dart';
import 'package:nirab_clean/util/app_constants.dart';

class ScheduleRepo extends GetxService {
  final ApiClient apiClient;
  ScheduleRepo({required this.apiClient});

  Future<Response> changePostScheduleTime(
    String postId,
    String scheduleTime,
  ) async {
    return await apiClient.postData(AppConstants.updatePostInfo, {
      "_method": "put",
      "post_id": postId,
      "booking_schedule": scheduleTime,
    });
  }

  Future<Response> getRealAvailability({
    required String zoneId,
    required List<Map<String, dynamic>> items,
    required DateTime startsAt,
    String? originalStartsAt,
    String? providerId,
    double? latitude,
    double? longitude,
    bool validateOnly = true,
    String? quotationId,
    String branchSelection = 'manual',
  }) {
    // Selected slots retain the API offset verbatim. A DateTime fallback is an
    // instant and must include Z, even on Web/Android devices in another zone.
    final wireStart = originalStartsAt ?? startsAt.toUtc().toIso8601String();
    final values = <String, String?>{
      'zone_id': zoneId,
      'contract_version': '2',
      'date': validateOnly
          ? wireStart.split(RegExp('[T ]')).first
          : startsAt.toIso8601String().split('T').first,
      'starts_at': validateOnly ? wireStart : null,
      'provider_id': providerId,
      'latitude': latitude?.toString(),
      'longitude': longitude?.toString(),
      'quotation_id': quotationId,
      'branch_selection': branchSelection,
    };
    final parts = values.entries
        .where((entry) => entry.value != null)
        .map(
          (entry) =>
              '${Uri.encodeQueryComponent(entry.key)}=${Uri.encodeQueryComponent(entry.value!)}',
        )
        .toList();
    void encode(String key, dynamic value) {
      if (value is List) {
        for (var i = 0; i < value.length; i++) {
          encode('$key[$i]', value[i]);
        }
      } else if (value is Map) {
        value.forEach((k, v) => encode('$key[$k]', v));
      } else if (value != null) {
        parts.add(
          '${Uri.encodeQueryComponent(key)}=${Uri.encodeQueryComponent(value is bool ? (value ? '1' : '0') : value.toString())}',
        );
      }
    }

    encode('items', items);
    return apiClient.getData(
      '${AppConstants.bookingAvailability}?${parts.join('&')}',
    );
  }
}
