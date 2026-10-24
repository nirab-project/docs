<?php

namespace Modules\BookingModule\Http\Controllers\Api\V1\Provider;

use App\Services\AuditService;
use App\Services\OperationalAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use MatanYadaev\EloquentSpatial\Objects\LineString;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use Modules\BookingModule\Entities\Booking;
use Modules\BookingModule\Entities\DispatchAlert;
use Modules\BookingModule\Services\BookingAssignmentService;
use Modules\BookingModule\Services\DispatchEngine;
use Modules\ProviderManagement\Entities\BranchServiceArea;
use Modules\ProviderManagement\Entities\Provider;
use Modules\ServiceManagement\Entities\Service;
use Modules\ServicemanModule\Entities\ServicemanLeave;
use Modules\ServicemanModule\Entities\Team;
use Modules\UserManagement\Entities\Serviceman;

class DispatchController extends Controller
{
    public function __construct(
        private readonly DispatchEngine $engine,
        private readonly BookingAssignmentService $assignments,
        private readonly OperationalAccess $access,
        private readonly AuditService $audit,
    ) {
    }

    public function context(Request $request): JsonResponse
    {
        $actor = $request->user();
        $branch = str_starts_with($actor->user_type, 'provider-') ? $this->access->branch($actor) : $request->input('provider_id');
        abort_unless(is_string($branch) && collect(OperationalAccess::MANAGEMENT_PERMISSIONS)->contains(fn ($permission) => $this->access->allows($actor, $permission, $branch)), 403);
        $provider = Provider::withoutGlobalScopes()->findOrFail($branch);
        return $this->response([
            'provider_id' => $branch, 'zone_id' => $provider->zone_id,
            'coordinates' => $provider->zone?->coordinates,
            'timezone' => config('app.timezone'),
            'permissions' => collect(OperationalAccess::MANAGEMENT_PERMISSIONS)
                ->mapWithKeys(fn ($permission) => [$permission => $this->access->allows($request->user(), $permission, $branch)]),
        ]);
    }

    public function technicians(Request $request): JsonResponse
    {
        $branch = $this->providerId($request, 'technicians.manage', 'teams.manage');
        $query = Serviceman::withoutGlobalScopes()->whereNull('deleted_at')->with('user')->where('provider_id', $branch)
            ->when($request->input('search'), fn ($q, $search) => $q->whereHas('user', fn ($u) => $u
                ->where(fn ($names) => $names->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))))
            ->orderBy('id');
        return $this->response($query->paginate(max(1, min($request->integer('limit', 100), 200))));
    }

    public function technician(Request $request, string $servicemanId): JsonResponse
    {
        $serviceman = $this->branchServiceman($request, $servicemanId)->load(['user', 'skills.service', 'shifts', 'leaves']);
        $payload = $serviceman->toArray();
        $payload['leaves'] = $serviceman->leaves->map(fn ($leave) => array_merge($leave->toArray(), [
            'starts_at' => $leave->starts_at->setTimezone(config('app.timezone'))->toIso8601String(),
            'ends_at' => $leave->ends_at->setTimezone(config('app.timezone'))->toIso8601String(),
        ]));
        return $this->response($payload);
    }

    public function services(Request $request): JsonResponse
    {
        $this->providerId($request, 'technicians.manage', 'availability.manage');
        return $this->response(Service::query()->when($request->input('search'), fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')->paginate(max(1, min($request->integer('limit', 100), 200))));
    }

    public function updateTechnicianStatus(Request $request, string $servicemanId): JsonResponse
    {
        $data = $request->validate(['is_active' => 'required|boolean']);
        $serviceman = $this->branchServiceman($request, $servicemanId);
        DB::transaction(function () use ($serviceman, $data, $request): void {
            Provider::withoutGlobalScopes()->whereKey($serviceman->provider_id)->lockForUpdate()->firstOrFail();
            $user = $serviceman->user()->lockForUpdate()->firstOrFail();
            $old = ['is_active' => $user->is_active];
            $user->update($data);
            $this->audit->record('technician.status.updated', $serviceman, $old, $data, $request->user());
        });
        return $this->response($serviceman->fresh('user'));
    }

    public function candidates(Request $request, string $bookingId): JsonResponse
    {
        return $this->response($this->engine->rank($this->branchBooking($request, $bookingId)));
    }

    public function bookings(Request $request): JsonResponse
    {
        $bookings = $this->access->scope(Booking::withoutGlobalScopes(), $request->user(), 'dispatch.manage')
            ->whereNotIn('booking_status', ['completed', 'canceled'])
            ->when($request->input('provider_id'), fn ($q, $id) => $q->where('provider_id', $id))
            ->when($request->input('search'), fn ($q, $search) => $q->where('readable_id', 'like', "%{$search}%"))
            ->orderBy('service_schedule')->select(['id', 'provider_id', 'readable_id', 'booking_status', 'service_schedule', 'execution_type', 'required_technicians'])
            ->paginate(max(1, min($request->integer('limit', 50), 200)));
        return $this->response($bookings);
    }

    public function assign(Request $request, string $bookingId): JsonResponse
    {
        $data = $request->validate([
            'serviceman_id' => 'nullable|uuid|required_without:team_id',
            'team_id' => 'nullable|uuid|required_without:serviceman_id',
        ]);
        $booking = $this->branchBooking($request, $bookingId);
        return $this->response($this->assignments->assign($booking->id, $data['serviceman_id'] ?? null, $data['team_id'] ?? null, 'branch_manager', $request->user()->id));
    }

    public function teams(Request $request): JsonResponse
    {
        return $this->response(Team::query()->with(['leader.user', 'members.user'])->withCount('members')
            ->where('provider_id', $this->providerId($request, 'teams.manage'))->orderBy('name')->get());
    }

    public function storeTeam(Request $request): JsonResponse
    {
        return $this->saveTeam($request, new Team());
    }

    public function updateTeam(Request $request, string $teamId): JsonResponse
    {
        $team = Team::query()->where('provider_id', $this->providerId($request, 'teams.manage'))->findOrFail($teamId);
        return $this->saveTeam($request, $team);
    }

    private function saveTeam(Request $request, Team $team): JsonResponse
    {
        $branch = $this->providerId($request, 'teams.manage');
        $data = $request->validate([
            'name' => ($team->exists ? 'sometimes|' : '').'required|string|max:191',
            'leader_id' => ($team->exists ? 'sometimes|' : '').'required|uuid',
            'member_ids' => ($team->exists ? 'sometimes|' : '').'required|array|min:1|max:200',
            'member_ids.*' => 'required|uuid|distinct', 'default_capacity' => 'sometimes|integer|min:1|max:20',
            'is_active' => 'sometimes|boolean',
        ]);
        DB::transaction(function () use ($team, $data, $branch, $request): void {
            Provider::withoutGlobalScopes()->whereKey($branch)->lockForUpdate()->firstOrFail();
            if ($team->exists) $team->refresh();
            $old = $team->exists ? $team->load('members')->toArray() : [];
            $leader = $data['leader_id'] ?? $team->leader_id;
            $members = collect($data['member_ids'] ?? $team->members()->pluck('servicemen.id')->all())->push($leader)->unique()->values();
            $capacity = $data['default_capacity'] ?? ($team->default_capacity ?: 2);
            if ($members->count() < $capacity) $this->invalid('member_ids', 'team_members_below_default_capacity');
            if (Serviceman::withoutGlobalScopes()->whereNull('deleted_at')->where('provider_id', $branch)->whereIn('id', $members)->count() !== $members->count()) {
                $this->invalid('member_ids', 'team_members_must_belong_to_branch');
            }
            $attributes = collect($data)->except('member_ids')->all();
            $attributes['provider_id'] = $branch;
            $attributes['default_capacity'] = $capacity;
            if (!$team->exists && !array_key_exists('is_active', $attributes)) $attributes['is_active'] = true;
            $team->fill($attributes)->save();
            $team->members()->sync($members->mapWithKeys(fn ($id) => [$id => ['is_leader' => $id === $leader]])->all());
            $this->audit->record('team.saved', $team, $old, $team->fresh('members')->toArray(), $request->user(), $branch);
        });
        return $this->response($team->fresh(['leader.user', 'members.user'])->loadCount('members'));
    }

    public function replaceSkills(Request $request, string $servicemanId): JsonResponse
    {
        $data = $request->validate([
            'service_ids' => 'sometimes|array|max:200', 'service_ids.*' => 'uuid|distinct|exists:services,id',
            'skills' => 'sometimes|array|max:200', 'skills.*.service_id' => 'required|uuid|distinct|exists:services,id',
            'skills.*.capacity' => 'required|integer|min:1|max:255',
        ]);
        if (!$request->has('skills') && !$request->has('service_ids')) {
            throw ValidationException::withMessages(['skills' => __('validation.required', ['attribute' => __('validation.attributes.skills')])]);
        }
        $serviceman = $this->branchServiceman($request, $servicemanId);
        $skills = $data['skills'] ?? array_map(fn ($id) => ['service_id' => $id, 'capacity' => 1], $data['service_ids'] ?? []);
        $authorized = \Modules\ServiceManagement\Entities\BranchServiceAvailability::where('provider_id', $serviceman->provider_id)->where('admin_enabled', true)->pluck('service_id');
        abort_unless(collect($skills)->every(fn ($skill) => $authorized->contains($skill['service_id'])), 403, 'service_not_authorized_for_branch');
        DB::transaction(function () use ($serviceman, $skills, $request): void {
            Provider::withoutGlobalScopes()->whereKey($serviceman->provider_id)->lockForUpdate()->firstOrFail();
            Serviceman::withoutGlobalScopes()->whereKey($serviceman->id)->lockForUpdate()->firstOrFail();
            $old = $serviceman->skills()->get()->toArray();
            $serviceman->skills()->delete();
            $serviceman->skills()->createMany($skills);
            $this->audit->record('technician.skills.updated', $serviceman, ['skills' => $old], ['skills' => $skills], $request->user());
        });
        return $this->response($serviceman->fresh('skills.service'));
    }

    public function replaceShifts(Request $request, string $servicemanId): JsonResponse
    {
        $data = $request->validate([
            'shifts' => 'present|array|max:100', 'shifts.*.day_of_week' => 'required|integer|between:0,6',
            'shifts.*.starts_at' => 'required|date_format:H:i', 'shifts.*.ends_at' => 'required|date_format:H:i',
            'shifts.*.is_active' => 'sometimes|boolean',
        ]);
        $serviceman = $this->branchServiceman($request, $servicemanId);
        $shifts = $data['shifts'];
        foreach ($shifts as $index => $shift) {
            if ($shift['ends_at'] <= $shift['starts_at']) $this->invalid("shifts.{$index}.ends_at", 'invalid_shift_range');
            foreach (array_slice($shifts, 0, $index) as $other) {
                if (($shift['is_active'] ?? true) && ($other['is_active'] ?? true) && (int) $shift['day_of_week'] === (int) $other['day_of_week']
                    && $shift['starts_at'] < $other['ends_at'] && $shift['ends_at'] > $other['starts_at']) {
                    $this->invalid("shifts.{$index}.starts_at", 'overlapping_shifts');
                }
            }
        }
        DB::transaction(function () use ($serviceman, $shifts, $request): void {
            Provider::withoutGlobalScopes()->whereKey($serviceman->provider_id)->lockForUpdate()->firstOrFail();
            Serviceman::withoutGlobalScopes()->whereKey($serviceman->id)->lockForUpdate()->firstOrFail();
            $old = $serviceman->shifts()->get()->toArray();
            $serviceman->shifts()->delete();
            $serviceman->shifts()->createMany(array_map(fn ($shift) => $shift + ['is_active' => true], $shifts));
            $this->audit->record('technician.shifts.updated', $serviceman, ['shifts' => $old], ['shifts' => $shifts], $request->user());
        });
        return $this->response($serviceman->fresh('shifts'));
    }

    public function storeLeave(Request $request, string $servicemanId): JsonResponse
    {
        return $this->saveLeave($request, $servicemanId);
    }

    public function updateLeave(Request $request, string $servicemanId, string $leaveId): JsonResponse
    {
        return $this->saveLeave($request, $servicemanId, $leaveId);
    }

    private function saveLeave(Request $request, string $servicemanId, ?string $leaveId = null): JsonResponse
    {
        $data = $request->validate(['starts_at' => 'required|date', 'ends_at' => 'required|date|after:starts_at', 'reason' => 'nullable|string|max:255']);
        $serviceman = $this->branchServiceman($request, $servicemanId);
        $data['starts_at'] = \Carbon\Carbon::parse($data['starts_at'], config('app.timezone'))->setTimezone(config('app.timezone'));
        $data['ends_at'] = \Carbon\Carbon::parse($data['ends_at'], config('app.timezone'))->setTimezone(config('app.timezone'));
        $leave = DB::transaction(function () use ($serviceman, $leaveId, $data, $request) {
            Provider::withoutGlobalScopes()->whereKey($serviceman->provider_id)->lockForUpdate()->firstOrFail();
            Serviceman::withoutGlobalScopes()->whereKey($serviceman->id)->lockForUpdate()->firstOrFail();
            $leave = $leaveId ? $serviceman->leaves()->findOrFail($leaveId) : new ServicemanLeave(['serviceman_id' => $serviceman->id]);
            if ($serviceman->leaves()->where('status', 'approved')->when($leaveId, fn ($q) => $q->where('id', '!=', $leaveId))
                ->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at'])->exists()) {
                $this->invalid('starts_at', 'overlapping_leave');
            }
            $old = $leave->exists ? $leave->toArray() : [];
            $leave->fill($data + ['status' => 'approved'])->save();
            $this->audit->record('technician.leave.saved', $leave, $old, $leave->toArray(), $request->user(), $serviceman->provider_id);
            return $leave;
        });
        return $this->response($leave);
    }

    public function cancelLeave(Request $request, string $servicemanId, string $leaveId): JsonResponse
    {
        $serviceman = $this->branchServiceman($request, $servicemanId);
        DB::transaction(function () use ($serviceman, $leaveId, $request): void {
            Provider::withoutGlobalScopes()->whereKey($serviceman->provider_id)->lockForUpdate()->firstOrFail();
            Serviceman::withoutGlobalScopes()->whereKey($serviceman->id)->lockForUpdate()->firstOrFail();
            $leave = $serviceman->leaves()->findOrFail($leaveId);
            $old = $leave->toArray();
            $leave->update(['status' => 'rejected']);
            $this->audit->record('technician.leave.canceled', $leave, $old, $leave->toArray(), $request->user(), $serviceman->provider_id);
        });
        return $this->response(null);
    }

    public function serviceAreas(Request $request): JsonResponse
    {
        return $this->response(BranchServiceArea::query()->where('provider_id', $this->providerId($request, 'areas.manage', 'availability.manage'))->orderBy('priority')->get());
    }

    public function storeServiceArea(Request $request): JsonResponse
    {
        return $this->saveArea($request);
    }

    public function updateServiceArea(Request $request, string $areaId): JsonResponse
    {
        return $this->saveArea($request, $areaId);
    }

    private function saveArea(Request $request, ?string $areaId = null): JsonResponse
    {
        $branch = $this->providerId($request, 'areas.manage');
        $prefix = $areaId ? 'sometimes|' : '';
        $data = $request->validate([
            'name' => $prefix.'required|string|max:191', 'area_type' => $prefix.'required|in:primary,neighborhood,overflow',
            'zone_id' => 'sometimes|uuid|exists:zones,id', 'coordinates' => $prefix.'required|array|min:3|max:1000',
            'coordinates.*.latitude' => 'required|numeric|between:-90,90', 'coordinates.*.longitude' => 'required|numeric|between:-180,180',
            'priority' => 'sometimes|integer|min:0|max:2147483647', 'is_active' => 'sometimes|boolean',
        ]);
        $provider = Provider::withoutGlobalScopes()->findOrFail($branch);
        if (isset($data['zone_id']) && $data['zone_id'] !== $provider->zone_id) $this->invalid('zone_id', 'service_area_must_use_branch_zone');
        if (isset($data['coordinates'])) {
            $points = collect($data['coordinates'])->map(fn ($p) => new Point((float) $p['latitude'], (float) $p['longitude']))->all();
            if ($points[0]->latitude !== end($points)->latitude || $points[0]->longitude !== end($points)->longitude) $points[] = $points[0];
            $polygon = new Polygon([new LineString($points)]);
            $valid = DB::selectOne('SELECT ST_IsValid(ST_GeomFromText(?, 0)) AS valid', [$polygon->toWkt()]);
            if (!$valid?->valid) $this->invalid('coordinates', 'invalid_service_area_polygon');
            if (!DB::table('zones')->where('id', $provider->zone_id)->whereRaw('ST_Contains(coordinates, ST_GeomFromText(?, 0))', [$polygon->toWkt()])->exists()) $this->invalid('coordinates', 'service_area_outside_zone');
            $data['coordinates'] = $polygon;
        }
        $area = DB::transaction(function () use ($areaId, $branch, $provider, $data, $request) {
            $current = Provider::withoutGlobalScopes()->whereKey($branch)->lockForUpdate()->firstOrFail();
            abort_unless($current->zone_id === $provider->zone_id, 409, 'zone_change_impact_changed');
            $area = $areaId ? BranchServiceArea::where('provider_id', $branch)->lockForUpdate()->findOrFail($areaId) : new BranchServiceArea();
            $shape = $data['coordinates'] ?? $area->coordinates;
            if (!$shape || !DB::table('zones')->where('id', $current->zone_id)->whereRaw('ST_Contains(coordinates, ST_GeomFromText(?, 0))', [$shape->toWkt()])->exists()) $this->invalid('coordinates', 'service_area_outside_zone');
            $old = $area->exists ? $area->toArray() : [];
            $area->fill($data + ['provider_id' => $branch, 'zone_id' => $provider->zone_id])->save();
            $this->audit->record('branch.area.saved', $area, $old, $area->toArray(), $request->user(), $branch);
            return $area;
        });
        return $this->response($area);
    }

    public function alerts(Request $request): JsonResponse
    {
        return $this->response(DispatchAlert::where('provider_id', $this->providerId($request, 'dispatch.manage'))
            ->whereNull('resolved_at')->latest()->paginate(max(1, min($request->integer('limit', 20), 200))));
    }

    public function resolveAlert(Request $request, string $alertId): JsonResponse
    {
        $alert = DispatchAlert::where('provider_id', $this->providerId($request, 'dispatch.manage'))->findOrFail($alertId);
        $data = $request->validate(['reason' => 'nullable|string|max:2000']);
        DB::transaction(function () use ($alert, $request, $data): void {
            $alert = DispatchAlert::whereKey($alert->id)->lockForUpdate()->firstOrFail();
            if ($alert->resolved_at) return;
            $old = $alert->only('resolved_at');
            $alert->forceFill(['resolved_at' => now()])->save();
            $this->audit->record('dispatch.alert_resolved', $alert, $old, ['resolved_at' => $alert->resolved_at, 'reason' => $data['reason'] ?? null], $request->user());
        });
        return $this->response($alert);
    }

    private function branchBooking(Request $request, string $bookingId): Booking
    {
        return $this->access->scope(Booking::withoutGlobalScopes(), $request->user(), 'dispatch.manage')
            ->when($request->attributes->get('branch_workspace'), fn ($q) => $q->where('provider_id', $request->branch_id))->findOrFail($bookingId);
    }

    private function branchServiceman(Request $request, string $id): Serviceman
    {
        return Serviceman::withoutGlobalScopes()->whereNull('deleted_at')->where('provider_id', $this->providerId($request, 'technicians.manage'))->findOrFail($id);
    }

    private function providerId(Request $request, string $permission, ?string $alternative = null): string
    {
        $actor = $request->user();
        $branch = str_starts_with($actor->user_type, 'provider-') ? $this->access->branch($actor) : $request->input('provider_id');
        abort_unless(is_string($branch) && $branch !== '', 403);
        if (!$alternative || !$this->access->allows($actor, $alternative, $branch)) $this->access->authorizeBranch($actor, $permission, $branch);
        return $branch;
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => __('operations.'.$message)]);
    }

    private function response(mixed $data): JsonResponse
    {
        return response()->json(response_formatter(DEFAULT_200, $data));
    }
}
