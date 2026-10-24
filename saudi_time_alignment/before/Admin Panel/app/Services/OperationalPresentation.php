<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/** Display vocabulary only. Stored codes, API contracts and authorization stay unchanged. */
class OperationalPresentation
{
    private array $translations = [];
    public const BOOLEAN_FIELDS = ['active', 'is_active', 'required', 'is_required', 'is_paid', 'is_warranty', 'is_available', 'enabled', 'admin_enabled', 'operational_enabled', 'all_branches', 'complete', 'fault_confirmed'];
    public const ENUM_FIELDS = [
        'status' => 'states', 'booking_status' => 'states', 'quote_status' => 'states', 'sync_status' => 'states',
        'method' => 'methods', 'payment_method' => 'methods', 'paid_with' => 'methods',
        'actor_type' => 'roles', 'user_type' => 'roles', 'type' => 'types', 'response_type' => 'types',
        'scope' => 'types', 'adjustment_type' => 'types', 'cadence' => 'types', 'fee_type' => 'types',
        'destination' => 'types', 'subject_type' => 'entities', 'entity_type' => 'entities', 'source_type' => 'entities',
        'day_of_week' => 'days', 'days_of_week' => 'days', 'permissions' => 'permissions',
    ];

    public function labels(): array
    {
        $locale = app('translator')->getLocale();
        if (isset($this->translations[$locale])) return $this->translations[$locale];
        $labels = __('operational');
        $operations = __('operations');
        $labels['fields'] += __('quality.metrics');
        $labels['fields']['completion_rate'] = __('branch.completion_rate');
        $labels['fields']['actions'] = $operations['actions'];
        foreach ($operations as $key => $value) {
            if (str_starts_with($key, 'type_')) $labels['types'][substr($key, 5)] = $value;
            if (str_starts_with($key, 'permission_')) $labels['permissions'][substr($key, 11)] = $value;
        }
        $labels['permissions'] += $labels['finance_permissions'];
        foreach (['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as $index => $day) {
            $labels['days'][$day] = $operations[$day];
            $labels['days'][(string) $index] = $operations[$day];
        }
        $labels['enum_fields'] = self::ENUM_FIELDS;
        $labels['boolean_fields'] = self::BOOLEAN_FIELDS;
        return $this->translations[$locale] = $labels;
    }

    public function label(string $group, string $key, string $fallback = 'unknown_value'): string
    {
        $labels = $this->labels();
        if ($group === 'entities') $key = class_basename($key);
        return $labels[$group][$key] ?? $labels[$fallback];
    }

    public function value(string $field, mixed $value, array $references = []): string
    {
        $labels = $this->labels();
        if ($value === null || $value === '') return $labels['empty'];
        if (is_array($value)) {
            if (!$value) return $labels['empty'];
            $list = array_is_list($value);
            return implode(' · ', array_map(function ($key, $item) use ($field, $list, $references): string {
                return ($list ? '' : $this->label('fields', (string) $key, 'unknown_field') . ': ')
                    . $this->value($list ? $field : (string) $key, $item, $references);
            }, array_keys($value), array_values($value)));
        }
        if (is_bool($value) || in_array($field, self::BOOLEAN_FIELDS, true) && in_array($value, [0, 1, '0', '1'], true)) {
            return $labels[$value ? 'yes' : 'no'];
        }
        if (isset($references[$field][(string) $value])) return $references[$field][(string) $value];
        if (isset(self::ENUM_FIELDS[$field])) return $this->label(self::ENUM_FIELDS[$field], (string) $value);
        if (str_ends_with($field, '_at') || in_array($field, ['service_schedule', 'scheduled_at'], true)) return $this->date((string) $value);
        if (str_ends_with($field, '_id') || str_ends_with($field, '_by') || in_array($field, ['id', 'branch_ids', 'assigned_to'], true)) {
            return $this->label('fields', $field, 'unknown_entity');
        }
        return (string) $value;
    }

    public function date(?string $value): string
    {
        if (!$value) return __('operational.empty');
        try {
            $timezone = config('nirab_execution.settlement_timezone', config('app.timezone', 'UTC'));
            return CarbonImmutable::parse($value, 'UTC')->timezone($timezone)
                ->locale(app('translator')->getLocale())->isoFormat('D MMMM YYYY — h:mm A') . ' (' . $timezone . ')';
        } catch (\Exception) {
            return __('operational.unknown_value');
        }
    }
}
