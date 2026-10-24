<?php

namespace Modules\ProviderManagement\Services;

use App\Services\AuditService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\{DB, Validator};
use Modules\ProviderManagement\Entities\{Provider, ProviderSetting};

final class BranchScheduleService
{
    public const DAYS = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

    public function read(string $id): array
    {
        $rows = ProviderSetting::where('provider_id', $id)->where('settings_type', 'service_schedule')
            ->whereIn('key_name', ['time_schedule', 'weekends'])->pluck('live_values', 'key_name');
        $decode = function ($value): array {
            for ($i = 0; $i < 2 && is_string($value); $i++) $value = json_decode($value, true);
            return is_array($value) ? $value : [];
        };
        return ['time_schedule' => $decode($rows->get('time_schedule', [])), 'weekends' => $decode($rows->get('weekends', []))];
    }

    public function configured(Provider $branch): bool
    {
        $data = $this->read($branch->id); $hours = $data['time_schedule'];
        return count(array_unique($data['weekends'])) < 7 && !empty($hours['start_time']) && !empty($hours['end_time']) && $hours['start_time'] < $hours['end_time'];
    }

    public function contains(Provider $branch, CarbonInterface $start, int $duration = 0): bool
    {
        $data = $this->read($branch->id);
        $start = $start->copy()->setTimezone(config('app.timezone'));
        $end = $start->copy()->addMinutes($duration);
        if (!$start->isSameDay($end) || in_array(strtolower($start->englishDayOfWeek), array_map('strtolower', $data['weekends']), true)) return false;
        $hours = $data['time_schedule'];
        if (empty($hours['start_time']) || empty($hours['end_time'])) return app(BranchIntakePolicy::class)->legacy($branch);
        $day = $hours['weekly'][$start->dayOfWeek] ?? $hours;
        if (isset($day['is_active']) && !$day['is_active']) return false;
        if ($start->format('H:i') < $day['start_time'] || $end->format('H:i') > $day['end_time']) return false;
        foreach ($day['breaks'] ?? [] as $break) {
            if ($start->format('H:i') < $break['end_time'] && $end->format('H:i') > $break['start_time']) return false;
        }
        return true;
    }

    public function save(Provider $branch, array $input): void
    {
        $data = Validator::make($input, ['service_availability' => 'required|boolean',
            'start_time' => 'required|date_format:H:i', 'end_time' => 'required|date_format:H:i|after:start_time',
            'weekends' => 'present|array|max:7', 'weekends.*' => 'in:'.implode(',', self::DAYS),
            'break_start' => 'nullable|date_format:H:i|required_with:break_end', 'break_end' => 'nullable|date_format:H:i|after:break_start|required_with:break_start',
            'weekly' => 'sometimes|array|size:7', 'weekly.*.is_active' => 'required|boolean',
            'weekly.*.start_time' => 'required|date_format:H:i', 'weekly.*.end_time' => 'required|date_format:H:i|after:weekly.*.start_time',
            'weekly.*.breaks' => 'sometimes|array|max:5', 'weekly.*.breaks.*.start_time' => 'required|date_format:H:i',
            'weekly.*.breaks.*.end_time' => 'required|date_format:H:i'])->validate();
        foreach ($data['weekly'] ?? [] as $index => $day) {
            abort_unless(is_int($index) && $index >= 0 && $index <= 6 && $day['start_time'] < $day['end_time'], 422, 'invalid_shift_range');
            foreach ($day['breaks'] ?? [] as $break) abort_unless($break['start_time'] >= $day['start_time'] && $break['end_time'] <= $day['end_time'] && $break['start_time'] < $break['end_time'], 422, 'invalid_break_range');
        }
        if (!empty($data['break_start'])) abort_unless($data['break_start'] >= $data['start_time'] && $data['break_end'] <= $data['end_time'], 422, 'invalid_break_range');
        DB::transaction(function () use ($branch, $data): void {
            Provider::withoutGlobalScopes()->whereKey($branch->id)->lockForUpdate()->firstOrFail();
            $old = $this->read($branch->id);
            $hours = ['start_time' => $data['start_time'], 'end_time' => $data['end_time']];
            $hours['breaks'] = array_key_exists('break_start', $data) ? (!empty($data['break_start']) ? [['start_time' => $data['break_start'], 'end_time' => $data['break_end']]] : []) : ($old['time_schedule']['breaks'] ?? []);
            // Older app clients edit the common hours and cannot erase weekly exceptions.
            if (isset($data['weekly']) || isset($old['time_schedule']['weekly'])) $hours['weekly'] = $data['weekly'] ?? $old['time_schedule']['weekly'];
            foreach (['time_schedule' => $hours, 'weekends' => $data['weekends']] as $key => $value) {
                ProviderSetting::updateOrCreate(['provider_id' => $branch->id, 'settings_type' => 'service_schedule', 'key_name' => $key],
                    ['live_values' => json_encode($value), 'test_values' => json_encode($value), 'mode' => 'live', 'is_active' => 1]);
            }
            $branch->service_availability = $data['service_availability'];
            $branch->save();
            app(AuditService::class)->record('branch.schedule.updated', $branch, $old, $this->read($branch->id), request()->user(), $branch->id);
        });
    }
}
