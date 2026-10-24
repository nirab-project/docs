import 'package:demandium_provider/common/widgets/nirab/nirab_tabs.dart';
import 'package:demandium_provider/util/core_export.dart';
import 'package:demandium_provider/feature/serviceman/operations/operations_api.dart';
import 'package:demandium_provider/feature/serviceman/operations/workforce_editors.dart';
import 'package:get/get.dart';

class TechnicianOperationsScreen extends StatefulWidget {
  final String timezone;
  const TechnicianOperationsScreen({super.key, required this.timezone});
  @override
  State<TechnicianOperationsScreen> createState() =>
      _TechnicianOperationsScreenState();
}

class _TechnicianOperationsScreenState
    extends State<TechnicianOperationsScreen> {
  List<Map<String, dynamic>> _technicians = [],
      _services = [],
      _skills = [],
      _shifts = [];
  Map<String, dynamic>? _technician;
  String? _error;
  String _search = '';
  bool _loading = true, _saving = false;
  static const _days = [
    'sunday',
    'monday',
    'tuesday',
    'wednesday',
    'thursday',
    'friday',
    'saturday',
  ];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final result = await Future.wait([
        OperationsApi.all('${OperationsApi.base}/servicemen'),
        OperationsApi.all('${OperationsApi.base}/services'),
      ]);
      if (mounted)
        setState(() {
          _technicians = result[0];
          _services = result[1];
        });
    } catch (error) {
      if (mounted) setState(() => _error = OperationsApi.message(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _select(String id) async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final result = Map<String, dynamic>.from(
        await OperationsApi.request('${OperationsApi.base}/servicemen/$id'),
      );
      if (mounted)
        setState(() {
          _technician = result;
          final technicianIndex = _technicians.indexWhere(
            (row) => row['id'] == result['id'],
          );
          if (technicianIndex >= 0) _technicians[technicianIndex] = result;
          _skills = (result['skills'] as List? ?? [])
              .map((row) => Map<String, dynamic>.from(row))
              .toList();
          _shifts = (result['shifts'] as List? ?? [])
              .map((row) => Map<String, dynamic>.from(row))
              .toList();
        });
    } catch (error) {
      if (mounted) setState(() => _error = OperationsApi.message(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _save(
    String action,
    Map<String, dynamic> body, {
    String method = 'PUT',
  }) async {
    if (_saving) return;
    setState(() => _saving = true);
    try {
      await OperationsApi.request(
        '${OperationsApi.base}/servicemen/${_technician!['id']}/$action',
        method: method,
        body: body,
      );
      if (mounted) {
        await _select(_technician!['id'].toString());
        showCustomSnackBar(
          'saved_successfully'.tr,
          type: ToasterMessageType.success,
        );
      }
    } catch (error) {
      if (mounted) showCustomSnackBar(OperationsApi.message(error));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _skill([int? index]) async {
    final choices = _services
        .where(
          (service) =>
              !_skills.any((skill) => skill['service_id'] == service['id']) ||
              index != null && _skills[index]['service_id'] == service['id'],
        )
        .toList();
    final result = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => SkillEditor(
        services: choices,
        skill: index == null ? null : _skills[index],
      ),
    );
    if (result != null && mounted)
      setState(() {
        if (index == null) {
          _skills.add(result);
        } else {
          _skills[index] = result;
        }
      });
  }

  Future<void> _shift([int? index]) async {
    final result = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => ShiftEditor(shift: index == null ? null : _shifts[index]),
    );
    if (result != null && mounted)
      setState(() {
        if (index == null) {
          _shifts.add(result);
        } else {
          _shifts[index] = result;
        }
      });
  }

  Future<void> _leave([Map<String, dynamic>? leave]) async {
    final result = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => LeaveEditor(leave: leave, timezone: widget.timezone),
    );
    if (result != null)
      await _save(
        'leaves${leave == null ? '' : '/${leave['id']}'}',
        result,
        method: leave == null ? 'POST' : 'PUT',
      );
  }

  @override
  Widget build(BuildContext context) => DefaultTabController(
    length: 3,
    child: Scaffold(
      appBar: CustomAppBar(title: 'technician_operations'.tr),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
          ? OperationsError(
              message: _error!,
              retry: () => _technician == null
                  ? _load()
                  : _select(_technician!['id'].toString()),
            )
          : _technician == null
          ? Column(
              children: [
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: TextField(
                    decoration: InputDecoration(
                      labelText: 'search_technicians'.tr,
                      prefixIcon: const NirabIcon(Icons.search),
                    ),
                    onChanged: (value) =>
                        setState(() => _search = value.toLowerCase()),
                  ),
                ),
                Expanded(
                  child: ListView(
                    children: [
                      if (_technicians.isEmpty)
                        Padding(
                          padding: const EdgeInsets.all(24),
                          child: Text('no_serviceman_available'.tr),
                        ),
                      ..._technicians
                          .where(
                            (row) => OperationsApi.name(
                              row,
                            ).toLowerCase().contains(_search),
                          )
                          .map(
                            (row) => ListTile(
                              leading: TechnicianAvatar(technician: row),
                              title: Text(OperationsApi.name(row)),
                              subtitle: Text(
                                (OperationsApi.active(row['user']?['is_active'])
                                        ? 'active'
                                        : 'inactive')
                                    .tr,
                              ),
                              trailing: const NirabIcon(Icons.chevron_right),
                              onTap: () => _select(row['id'].toString()),
                            ),
                          ),
                    ],
                  ),
                ),
              ],
            )
          : Column(
              children: [
                ListTile(
                  leading: TechnicianAvatar(technician: _technician!),
                  title: Text(OperationsApi.name(_technician!)),
                  subtitle: Text('${'timezone'.tr}: ${widget.timezone}'),
                  trailing: TextButton(
                    onPressed: _saving
                        ? null
                        : () => setState(() => _technician = null),
                    child: Text('change_technician'.tr),
                  ),
                ),
                SwitchListTile(
                  title: Text('technician_active'.tr),
                  value: OperationsApi.active(
                    _technician!['user']?['is_active'],
                  ),
                  onChanged: _saving
                      ? null
                      : (value) => _save('status', {'is_active': value}),
                ),
                if (_saving) const LinearProgressIndicator(),
                NirabTabs(
                  isScrollable: true,
                  labels: [
                    'technician_skills'.tr,
                    'technician_shifts'.tr,
                    'technician_leaves'.tr,
                  ],
                ),
                Expanded(
                  child: TabBarView(
                    children: [
                      ListView(
                        padding: const EdgeInsets.all(16),
                        children: [
                          ..._skills.asMap().entries.map((entry) {
                            final skill = entry.value;
                            final service = _services.firstWhere(
                              (row) => row['id'] == skill['service_id'],
                              orElse: () => Map<String, dynamic>.from(
                                skill['service'] ?? {},
                              ),
                            );
                            return ListTile(
                              title: Text(service['name']?.toString() ?? ''),
                              subtitle: Text(
                                '${'skill_capacity'.tr}: ${skill['capacity'] ?? 1}\n${'service_technicians_range'.tr}: ${service['min_technicians'] ?? 1} / ${service['default_technicians'] ?? 1} / ${service['max_technicians'] ?? 1}',
                              ),
                              onTap: _saving ? null : () => _skill(entry.key),
                              trailing: IconButton(
                                icon: const NirabIcon(Icons.delete_outline),
                                onPressed: _saving
                                    ? null
                                    : () => setState(
                                        () => _skills.removeAt(entry.key),
                                      ),
                              ),
                            );
                          }),
                          OutlinedButton.icon(
                            onPressed: _saving ? null : _skill,
                            icon: const NirabIcon(Icons.add),
                            label: Text('add_skill'.tr),
                          ),
                          FilledButton(
                            onPressed: _saving
                                ? null
                                : () => _save('skills', {
                                    'skills': _skills
                                        .map(
                                          (row) => {
                                            'service_id': row['service_id'],
                                            'capacity': row['capacity'] ?? 1,
                                          },
                                        )
                                        .toList(),
                                  }),
                            child: Text('save_skills'.tr),
                          ),
                        ],
                      ),
                      ListView(
                        padding: const EdgeInsets.all(16),
                        children: [
                          ..._shifts.asMap().entries.map(
                            (entry) => ListTile(
                              title: Text(
                                _days[(entry.value['day_of_week'] as num)
                                        .toInt()]
                                    .tr,
                              ),
                              subtitle: Text(
                                '${entry.value['starts_at']} – ${entry.value['ends_at']} • ${(OperationsApi.active(entry.value['is_active']) ? 'active' : 'inactive').tr}',
                              ),
                              onTap: _saving ? null : () => _shift(entry.key),
                              trailing: IconButton(
                                icon: const NirabIcon(Icons.delete_outline),
                                onPressed: _saving
                                    ? null
                                    : () => setState(
                                        () => _shifts.removeAt(entry.key),
                                      ),
                              ),
                            ),
                          ),
                          OutlinedButton.icon(
                            onPressed: _saving ? null : _shift,
                            icon: const NirabIcon(Icons.add),
                            label: Text('add_shift'.tr),
                          ),
                          FilledButton(
                            onPressed: _saving
                                ? null
                                : () => _save('shifts', {
                                    'shifts': _shifts
                                        .map(
                                          (row) => {
                                            'day_of_week': row['day_of_week'],
                                            'starts_at': row['starts_at']
                                                .toString()
                                                .substring(0, 5),
                                            'ends_at': row['ends_at']
                                                .toString()
                                                .substring(0, 5),
                                            'is_active': OperationsApi.active(
                                              row['is_active'],
                                            ),
                                          },
                                        )
                                        .toList(),
                                  }),
                            child: Text('save_shifts'.tr),
                          ),
                        ],
                      ),
                      ListView(
                        padding: const EdgeInsets.all(16),
                        children: [
                          ...(_technician!['leaves'] as List? ?? []).map((
                            item,
                          ) {
                            final leave = Map<String, dynamic>.from(item);
                            return ListTile(
                              title: Text(
                                '${leave['starts_at'].toString().replaceFirst('T', ' ').substring(0, 16)} – ${leave['ends_at'].toString().replaceFirst('T', ' ').substring(0, 16)}',
                              ),
                              subtitle: Text(
                                '${(leave['status'] == 'rejected' ? 'leave_canceled' : leave['status'].toString()).tr}\n${leave['reason'] ?? ''}',
                              ),
                              onTap: _saving || leave['status'] == 'rejected'
                                  ? null
                                  : () => _leave(leave),
                              trailing: leave['status'] == 'rejected'
                                  ? null
                                  : IconButton(
                                      tooltip: 'cancel_leave'.tr,
                                      icon: const NirabIcon(
                                        Icons.cancel_outlined,
                                      ),
                                      onPressed: _saving
                                          ? null
                                          : () async {
                                              final confirmed =
                                                  await showDialog<bool>(
                                                    context: context,
                                                    builder: (dialogContext) =>
                                                        AlertDialog(
                                                          title: Text(
                                                            'cancel_leave'.tr,
                                                          ),
                                                          actions: [
                                                            TextButton(
                                                              onPressed: () =>
                                                                  Navigator.pop(
                                                                    dialogContext,
                                                                    false,
                                                                  ),
                                                              child: Text(
                                                                'no'.tr,
                                                              ),
                                                            ),
                                                            FilledButton(
                                                              onPressed: () =>
                                                                  Navigator.pop(
                                                                    dialogContext,
                                                                    true,
                                                                  ),
                                                              child: Text(
                                                                'yes'.tr,
                                                              ),
                                                            ),
                                                          ],
                                                        ),
                                                  );
                                              if (confirmed == true)
                                                await _save(
                                                  'leaves/${leave['id']}',
                                                  {},
                                                  method: 'DELETE',
                                                );
                                            },
                                    ),
                            );
                          }),
                          OutlinedButton.icon(
                            onPressed: _saving ? null : _leave,
                            icon: const NirabIcon(Icons.add),
                            label: Text('add_leave'.tr),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
    ),
  );
}
