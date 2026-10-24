<?php

namespace Modules\ServiceManagement\Entities;

use App\Traits\HasUuid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PricingRule extends Model
{
    use HasUuid;

    protected $guarded = [];
    protected $casts = [
        'adjustment_value' => 'float',
        'days_of_week' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeApplicable(Builder $query, string $serviceId, string $zoneId, ?string $providerId, ?string $areaId, CarbonInterface $at): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('service_id')->orWhere('service_id', $serviceId))
            ->where(fn (Builder $q) => $q->whereNull('zone_id')->orWhere('zone_id', $zoneId))
            ->where(fn (Builder $q) => $q->whereNull('provider_id')->orWhere('provider_id', $providerId))
            ->where(fn (Builder $q) => $q->whereNull('branch_service_area_id')->orWhere('branch_service_area_id', $areaId))
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $at))
            ->orderBy('priority')
            ->orderBy('created_at')->orderBy('id');
    }

    public function appliesAt(CarbonInterface $at): bool
    {
        if ($this->scope !== 'peak') {
            return true;
        }

        $days = array_map('strtolower', $this->days_of_week ?? []);
        if ($days && !in_array(strtolower($at->englishDayOfWeek), $days, true)) {
            return false;
        }

        $time = $at->format('H:i:s');
        if ($this->start_time && $this->end_time) {
            return $this->start_time <= $this->end_time
                ? $time >= $this->start_time && $time <= $this->end_time
                : $time >= $this->start_time || $time <= $this->end_time;
        }

        return true;
    }
}
