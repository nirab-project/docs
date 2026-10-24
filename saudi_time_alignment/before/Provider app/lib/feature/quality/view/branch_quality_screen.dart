import 'package:demandium_provider/util/core_export.dart';
import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';

class BranchQualityScreen extends StatefulWidget {
  const BranchQualityScreen({super.key});
  @override
  State<BranchQualityScreen> createState() => _BranchQualityScreenState();
}

class _BranchQualityScreenState extends State<BranchQualityScreen> {
  Map<String, dynamic> _report = {};
  List<dynamic> _complaints = [];
  List<String> _permissions = [];
  int _page = 1, _last = 1, _technicianPage = 1, _technicianLast = 1;
  DateTimeRange? _range;
  bool _busy = false;
  String _status = '';
  ApiClient get _api => Get.find<ApiClient>();

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<dynamic> _content(Future<Response> call) async {
    final response = await call;
    if (response.statusCode != 200)
      throw StateError(
        (response.body is Map ? response.body['message'] : null)?.toString() ??
            'quality_failed'.tr,
      );
    return response.body['content'];
  }

  Future<void> _perform(Future<void> Function() action) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await action();
    } catch (error) {
      if (mounted) showCustomSnackBar(error.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _load() => _perform(() async {
    final capabilities = await _content(
      _api.getData('/api/v1/provider/quality/capabilities'),
    );
    if (!mounted) return;
    _permissions = List<String>.from(
      capabilities['permissions'] as List? ?? [],
    );
    if (_permissions.contains('complaints.read')) {
      final complaints = await _content(
        _api.getData(
          '/api/v1/provider/quality/complaints?page=$_page${_status.isEmpty ? '' : '&status=$_status'}',
        ),
      );
      if (!mounted) return;
      setState(() {
        _complaints = complaints['data'] as List? ?? [];
        _last = (complaints['last_page'] as num? ?? 1).toInt();
      });
    }
    if (_permissions.contains('analytics.read')) {
      final date = _range == null
          ? ''
          : '&from=${_range!.start.toIso8601String().substring(0, 10)}&to=${_range!.end.toIso8601String().substring(0, 10)}';
      final report = await _content(
        _api.getData(
          '/api/v1/provider/analytics/operations?technician_page=$_technicianPage$date',
        ),
      );
      if (!mounted) return;
      setState(() {
        _report = Map<String, dynamic>.from(report);
        _technicianLast = (report['technicians']['last_page'] as num? ?? 1)
            .toInt();
      });
    }
  });

  Future<void> _details(Map<String, dynamic> ticket) async {
    final data = await _content(
      _api.getData('/api/v1/provider/quality/complaints/${ticket['id']}'),
    );
    if (!mounted) return;
    final note = TextEditingController(),
        assigned = TextEditingController(
          text: data['assigned_to']?.toString() ?? '',
        );
    String status = data['status'].toString();
    bool fault = data['fault_confirmed'] == true;
    try {
      await showDialog<void>(
        context: context,
        builder: (context) => StatefulBuilder(
          builder: (context, update) => AlertDialog(
            title: Text('quality_complaints'.tr),
            content: SizedBox(
              width: 500,
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SelectableText(
                      '${'quality_booking'.tr}: ${data['booking_id']}',
                    ),
                    Text(data['description'].toString()),
                    for (final file in data['attachments'] as List? ?? [])
                      TextButton.icon(
                        icon: const NirabIcon(Icons.attach_file),
                        label: Text(file['name'].toString()),
                        onPressed: () async {
                          final uri = Uri.tryParse(
                            file['url']?.toString() ?? '',
                          );
                          if (uri != null &&
                              ['http', 'https'].contains(uri.scheme))
                            await launchUrl(
                              uri,
                              mode: LaunchMode.externalApplication,
                            );
                        },
                      ),
                    for (final item in data['notes']?['data'] as List? ?? [])
                      ListTile(
                        title: Text(item['note'].toString()),
                        subtitle: Text(item['created_at'].toString()),
                      ),

                    if ((data['notes']?['last_page'] as num? ?? 1) > 1)
                      Row(
                        children: [
                          for (final direction in [-1, 1])
                            TextButton(
                              onPressed:
                                  (data['notes']['current_page'] as num)
                                                  .toInt() +
                                              direction <
                                          1 ||
                                      (data['notes']['current_page'] as num)
                                                  .toInt() +
                                              direction >
                                          (data['notes']['last_page'] as num)
                                              .toInt()
                                  ? null
                                  : () async {
                                      try {
                                        final page =
                                            (data['notes']['current_page']
                                                    as num)
                                                .toInt() +
                                            direction;
                                        final next = await _content(
                                          _api.getData(
                                            '/api/v1/provider/quality/complaints/${ticket['id']}?page=$page',
                                          ),
                                        );
                                        if (context.mounted)
                                          update(
                                            () => data['notes'] = next['notes'],
                                          );
                                      } catch (error) {
                                        showCustomSnackBar(error.toString());
                                      }
                                    },
                              child: Text(
                                direction < 0
                                    ? 'quality_previous'.tr
                                    : 'quality_next'.tr,
                              ),
                            ),
                        ],
                      ),
                    if (_permissions.contains('complaints.manage')) ...[
                      DropdownButtonFormField<String>(
                        initialValue: status,
                        items:
                            [
                                  'open',
                                  'under_review',
                                  'resolved',
                                  'rejected',
                                  'closed',
                                ]
                                .map(
                                  (value) => DropdownMenuItem(
                                    value: value,
                                    child: Text('quality_$value'.tr),
                                  ),
                                )
                                .toList(),
                        onChanged: (value) {
                          if (value != null) update(() => status = value);
                        },
                      ),
                      TextField(
                        controller: note,
                        maxLength: 10000,
                        decoration: InputDecoration(
                          labelText: 'quality_internal_note'.tr,
                        ),
                      ),
                      TextField(
                        controller: assigned,
                        decoration: InputDecoration(
                          labelText: 'quality_employee'.tr,
                        ),
                      ),
                      if (_permissions.contains('warranty.approve'))
                        CheckboxListTile(
                          title: Text('quality_fault_confirmed'.tr),
                          value: fault,
                          onChanged: (value) =>
                              update(() => fault = value == true),
                        ),
                    ],
                    if (data['warranty_booking_id'] != null)
                      SelectableText(
                        '${'quality_warranty'.tr}: ${data['warranty_booking_id']}',
                      ),
                  ],
                ),
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context),
                child: Text('quality_close'.tr),
              ),
              if (_permissions.contains('complaints.manage'))
                FilledButton(
                  onPressed: () async {
                    try {
                      await _content(
                        _api.putData(
                          '/api/v1/provider/quality/complaints/${ticket['id']}',
                          {
                            'status': status,
                            'note': note.text,
                            'assigned_to': assigned.text.isEmpty
                                ? null
                                : assigned.text,
                            if (_permissions.contains('warranty.approve'))
                              'fault_confirmed': fault,
                          },
                        ),
                      );
                      if (context.mounted) Navigator.pop(context);
                    } catch (error) {
                      showCustomSnackBar(error.toString());
                    }
                  },
                  child: Text('quality_save'.tr),
                ),
              if (_permissions.contains('warranty.approve') &&
                  data['fault_confirmed'] == true &&
                  data['warranty_booking_id'] == null)
                TextButton(
                  onPressed: () => _warranty(ticket['id'].toString()),
                  child: Text('quality_warranty'.tr),
                ),
            ],
          ),
        ),
      );
    } finally {
      note.dispose();
      assigned.dispose();
    }
  }

  Future<void> _warranty(String id) async {
    final day = await showDatePicker(
      context: context,
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 90)),
      initialDate: DateTime.now().add(const Duration(days: 1)),
    );
    if (day == null || !mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: const TimeOfDay(hour: 9, minute: 0),
    );
    if (time == null || !mounted) return;
    final reason = TextEditingController();
    try {
      final text = await showDialog<String>(
        context: context,
        builder: (context) => AlertDialog(
          title: Text('quality_warranty'.tr),
          content: TextField(
            controller: reason,
            maxLength: 4000,
            decoration: InputDecoration(labelText: 'quality_reason'.tr),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: Text('quality_close'.tr),
            ),
            TextButton(
              onPressed: () => Navigator.pop(context, reason.text),
              child: Text('quality_submit'.tr),
            ),
          ],
        ),
      );
      if (text == null || text.trim().isEmpty) return;
      final booking = await _content(
        _api.postData('/api/v1/provider/quality/complaints/$id/warranty', {
          'reason': text,
          'service_schedule': DateTime(
            day.year,
            day.month,
            day.day,
            time.hour,
            time.minute,
          ).toIso8601String(),
        }),
      );
      showCustomSnackBar('${'quality_warranty'.tr}: ${booking['readable_id']}');
    } finally {
      reason.dispose();
    }
  }

  Widget _pager(int page, int last, void Function(int) change) => Row(
    mainAxisAlignment: MainAxisAlignment.center,
    children: [
      IconButton(
        onPressed: _busy || page <= 1
            ? null
            : () {
                change(page - 1);
                _load();
              },
        icon: const NirabIcon(Icons.chevron_left),
      ),
      Text('$page / $last'),
      IconButton(
        onPressed: _busy || page >= last
            ? null
            : () {
                change(page + 1);
                _load();
              },
        icon: const NirabIcon(Icons.chevron_right),
      ),
    ],
  );

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text('quality_center'.tr),
      actions: [
        IconButton(
          onPressed: _busy ? null : _load,
          icon: const NirabIcon(Icons.refresh),
        ),
      ],
    ),
    body: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        if (_busy) const LinearProgressIndicator(),
        if (_permissions.contains('analytics.read')) ...[
          Text(
            'quality_operations'.tr,
            style: Theme.of(context).textTheme.titleLarge,
          ),
          TextButton.icon(
            icon: const NirabIcon(Icons.date_range),
            label: Text(
              _range == null
                  ? 'quality_from'.tr
                  : '${_range!.start.toIso8601String().substring(0, 10)} — ${_range!.end.toIso8601String().substring(0, 10)}',
            ),
            onPressed: _busy
                ? null
                : () async {
                    final range = await showDateRangePicker(
                      context: context,
                      firstDate: DateTime(2020),
                      lastDate: DateTime.now(),
                      initialDateRange: _range,
                    );
                    if (range != null) {
                      setState(() => _range = range);
                      await _load();
                    }
                  },
          ),
          for (final entry in (_report['summary'] as Map? ?? {}).entries)
            Card(
              child: ListTile(
                title: Text('quality_metric_${entry.key}'.tr),
                trailing: Text('${entry.value ?? '—'}'),
              ),
            ),
          Text(
            'quality_technicians'.tr,
            style: Theme.of(context).textTheme.titleLarge,
          ),
          for (final row in _report['technicians']?['data'] as List? ?? [])
            Card(
              child: ListTile(
                title: Text(
                  '${'quality_technician'.tr}: ${row['technician_name'] ?? row['serviceman_id']}',
                ),
                subtitle: Text(
                  '${'quality_metric_jobs'.tr}: ${row['jobs']} · ${'quality_metric_completed'.tr}: ${row['completed']}\n${'quality_metric_average_service_seconds'.tr}: ${row['average_service_seconds'] ?? '—'}',
                ),
                trailing: row['team_id'] == null
                    ? null
                    : Text('${row['team_name'] ?? row['team_id']}'),
              ),
            ),
          _pager(
            _technicianPage,
            _technicianLast,
            (page) => _technicianPage = page,
          ),
          Text(
            'quality_collections'.tr,
            style: Theme.of(context).textTheme.titleLarge,
          ),
          for (final row in _report['collections'] as List? ?? [])
            ListTile(
              title: Text('quality_payment_${row['status']}'.tr),
              subtitle: Text('${row['count']}'),
              trailing: Text('${row['amount']}'),
            ),
        ],
        if (_permissions.contains('complaints.read')) ...[
          Text(
            'quality_complaints'.tr,
            style: Theme.of(context).textTheme.titleLarge,
          ),
          DropdownButtonFormField<String>(
            initialValue: _status,
            items:
                ['', 'open', 'under_review', 'resolved', 'rejected', 'closed']
                    .map(
                      (value) => DropdownMenuItem(
                        value: value,
                        child: Text(
                          value.isEmpty
                              ? 'quality_all'.tr
                              : 'quality_$value'.tr,
                        ),
                      ),
                    )
                    .toList(),
            onChanged: _busy
                ? null
                : (value) {
                    setState(() {
                      _status = value ?? '';
                      _page = 1;
                    });
                    _load();
                  },
          ),
          if (_complaints.isEmpty) Text('quality_no_data'.tr),
          for (final row in _complaints)
            Card(
              child: ListTile(
                title: Text('quality_${row['status']}'.tr),
                subtitle: Text(row['description'].toString()),
                trailing: const NirabIcon(Icons.chevron_right),
                onTap: () => _perform(
                  () => _details(Map<String, dynamic>.from(row)),
                ).then((_) => _load()),
              ),
            ),
          _pager(_page, _last, (page) => _page = page),
        ],
      ],
    ),
  );
}
