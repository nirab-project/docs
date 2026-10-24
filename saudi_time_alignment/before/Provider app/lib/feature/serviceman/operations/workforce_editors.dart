import 'package:demandium_provider/util/core_export.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

class SkillEditor extends StatefulWidget {
  final List<Map<String, dynamic>> services;
  final Map<String, dynamic>? skill;
  const SkillEditor({super.key, required this.services, this.skill});
  @override
  State<SkillEditor> createState() => _SkillEditorState();
}

class _SkillEditorState extends State<SkillEditor> {
  final _form = GlobalKey<FormState>();
  late final TextEditingController _capacity;
  String? _service;
  String _search = '';
  @override
  void initState() {
    super.initState();
    _capacity = TextEditingController(
      text: (widget.skill?['capacity'] ?? 1).toString(),
    );
    _service = widget.skill?['service_id']?.toString();
  }

  @override
  void dispose() {
    _capacity.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text('add_skill'.tr),
    content: SizedBox(
      width: 500,
      child: Form(
        key: _form,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              decoration: InputDecoration(labelText: 'search_services'.tr),
              onChanged: (value) =>
                  setState(() => _search = value.toLowerCase()),
            ),
            DropdownButtonFormField<String>(
              initialValue: widget.services.any((row) => row['id'] == _service)
                  ? _service
                  : null,
              isExpanded: true,
              decoration: InputDecoration(labelText: 'service'.tr),
              items: widget.services
                  .where(
                    (row) =>
                        row['id'] == _service ||
                        row['name'].toString().toLowerCase().contains(_search),
                  )
                  .map(
                    (row) => DropdownMenuItem(
                      value: row['id'].toString(),
                      child: Text(
                        row['name'].toString(),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  )
                  .toList(),
              onChanged: (value) => setState(() => _service = value),
              validator: (value) => value == null ? 'required_field'.tr : null,
            ),
            TextFormField(
              controller: _capacity,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(labelText: 'skill_capacity'.tr),
              validator: (value) {
                final capacity = int.tryParse(value ?? '');
                return capacity != null && capacity >= 1 && capacity <= 255
                    ? null
                    : 'valid_skill_capacity_required'.tr;
              },
            ),
          ],
        ),
      ),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: Text('cancel'.tr),
      ),
      FilledButton(
        onPressed: () {
          if (_form.currentState!.validate())
            Navigator.pop(context, {
              'service_id': _service,
              'capacity': int.parse(_capacity.text),
            });
        },
        child: Text('save'.tr),
      ),
    ],
  );
}

class ShiftEditor extends StatefulWidget {
  final Map<String, dynamic>? shift;
  const ShiftEditor({super.key, this.shift});
  @override
  State<ShiftEditor> createState() => _ShiftEditorState();
}

class _ShiftEditorState extends State<ShiftEditor> {
  int _day = 0;
  String _start = '08:00', _end = '17:00';
  bool _active = true;
  String? _error;
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
    _day = (widget.shift?['day_of_week'] as num? ?? 0).toInt();
    _start = (widget.shift?['starts_at'] ?? '08:00').toString().substring(0, 5);
    _end = (widget.shift?['ends_at'] ?? '17:00').toString().substring(0, 5);
    _active = widget.shift?['is_active'] != false;
  }

  Future<void> _pick(bool start) async {
    final parts = (start ? _start : _end).split(':');
    final result = await showTimePicker(
      context: context,
      initialTime: TimeOfDay(
        hour: int.parse(parts[0]),
        minute: int.parse(parts[1]),
      ),
    );
    if (result != null && mounted)
      setState(() {
        final value =
            '${result.hour.toString().padLeft(2, '0')}:${result.minute.toString().padLeft(2, '0')}';
        if (start) {
          _start = value;
        } else {
          _end = value;
        }
      });
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text('add_shift'.tr),
    content: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        DropdownButtonFormField<int>(
          initialValue: _day,
          decoration: InputDecoration(labelText: 'day_of_week'.tr),
          items: _days
              .asMap()
              .entries
              .map(
                (entry) => DropdownMenuItem(
                  value: entry.key,
                  child: Text(entry.value.tr),
                ),
              )
              .toList(),
          onChanged: (value) => setState(() => _day = value!),
        ),
        ListTile(
          title: Text('starts_at'.tr),
          trailing: Text(_start),
          onTap: () => _pick(true),
        ),
        ListTile(
          title: Text('ends_at'.tr),
          trailing: Text(_end),
          onTap: () => _pick(false),
        ),
        SwitchListTile(
          title: Text('active'.tr),
          value: _active,
          onChanged: (value) => setState(() => _active = value),
        ),
        if (_error != null)
          Text(
            _error!,
            style: TextStyle(color: Theme.of(context).colorScheme.error),
          ),
      ],
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: Text('cancel'.tr),
      ),
      FilledButton(
        onPressed: () {
          if (_end.compareTo(_start) <= 0) {
            setState(() => _error = 'invalid_shift_range'.tr);
          } else {
            Navigator.pop(context, {
              'day_of_week': _day,
              'starts_at': _start,
              'ends_at': _end,
              'is_active': _active,
            });
          }
        },
        child: Text('save'.tr),
      ),
    ],
  );
}

class LeaveEditor extends StatefulWidget {
  final Map<String, dynamic>? leave;
  final String timezone;
  const LeaveEditor({super.key, this.leave, required this.timezone});
  @override
  State<LeaveEditor> createState() => _LeaveEditorState();
}

class _LeaveEditorState extends State<LeaveEditor> {
  late final TextEditingController _reason;
  DateTime? _start, _end;
  String? _error;
  @override
  void initState() {
    super.initState();
    _reason = TextEditingController(text: widget.leave?['reason']?.toString());
    _start = _projectDate(widget.leave?['starts_at']);
    _end = _projectDate(widget.leave?['ends_at']);
  }

  DateTime? _projectDate(dynamic value) {
    final date = value?.toString() ?? '';
    return date.length < 19 ? null : DateTime.tryParse(date.substring(0, 19));
  }

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  Future<void> _pick(bool start) async {
    final initial = (start ? _start : _end) ?? DateTime.now();
    final day = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(2000),
      lastDate: DateTime(2100),
    );
    if (day == null || !mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay(hour: initial.hour, minute: initial.minute),
    );
    if (time != null && mounted)
      setState(() {
        final value = DateTime(
          day.year,
          day.month,
          day.day,
          time.hour,
          time.minute,
        );
        if (start) {
          _start = value;
        } else {
          _end = value;
        }
      });
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text('add_leave'.tr),
    content: SingleChildScrollView(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text('${'timezone'.tr}: ${widget.timezone}'),
          ListTile(
            title: Text('starts_at'.tr),
            subtitle: Text(
              _start == null
                  ? 'select_date_time'.tr
                  : DateFormat('yyyy-MM-dd HH:mm').format(_start!),
            ),
            trailing: const NirabIcon(Icons.calendar_month),
            onTap: () => _pick(true),
          ),
          ListTile(
            title: Text('ends_at'.tr),
            subtitle: Text(
              _end == null
                  ? 'select_date_time'.tr
                  : DateFormat('yyyy-MM-dd HH:mm').format(_end!),
            ),
            trailing: const NirabIcon(Icons.calendar_month),
            onTap: () => _pick(false),
          ),
          TextField(
            controller: _reason,
            maxLength: 255,
            decoration: InputDecoration(labelText: 'reason'.tr),
          ),
          if (_error != null)
            Text(
              _error!,
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
        ],
      ),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: Text('cancel'.tr),
      ),
      FilledButton(
        onPressed: () {
          if (_start == null || _end == null || !_end!.isAfter(_start!)) {
            setState(() => _error = 'invalid_leave_range'.tr);
            return;
          }
          Navigator.pop(context, {
            'starts_at': DateFormat('yyyy-MM-dd HH:mm:ss').format(_start!),
            'ends_at': DateFormat('yyyy-MM-dd HH:mm:ss').format(_end!),
            'reason': _reason.text.trim(),
          });
        },
        child: Text('save'.tr),
      ),
    ],
  );
}
