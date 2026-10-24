<?php

namespace App\Http\Controllers;

use App\Services\{AuditService, OperationalAccess};
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\Rule;
use Modules\BookingModule\Http\Controllers\Api\V1\Provider\DispatchController;
use Modules\ProviderManagement\Entities\{Provider, ProviderSetting};
use Modules\ProviderManagement\Services\{BranchOnboardingService, BranchReadinessService, BranchScheduleService, BranchServicePresentation, BranchZoneChangeService};
use Modules\ServiceManagement\Entities\{BranchServiceAvailability, Service};
use Modules\ServiceManagement\Http\Controllers\Api\V1\Admin\BranchServiceAvailabilityController;
use Modules\ServicemanModule\Entities\Team;
use Modules\UserManagement\Entities\{Serviceman, User};
use Modules\ZoneManagement\Entities\Zone;

class BranchOnboardingController extends Controller
{
    public function __construct(private readonly OperationalAccess $access, private readonly BranchOnboardingService $setup) {}

    private function branch(Request $request, string $id, ?string $permission = null): Provider
    {
        abort_unless(nirab_branch_mode(), 404);
        $request->validate(['branch_id' => 'nullable|uuid', 'provider_id' => 'nullable|uuid']);
        abort_if(($request->filled('branch_id') && $request->branch_id !== $id) || ($request->filled('provider_id') && $request->provider_id !== $id), 403);
        if ($permission) $this->access->authorizeBranch($request->user(), $permission, $id);
        else abort_unless(collect(OperationalAccess::MANAGEMENT_PERMISSIONS)->contains(fn ($p) => $this->access->allows($request->user(), $p, $id)), 403);
        $request->merge(['branch_id' => $id, 'provider_id' => $id]);
        return Provider::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($id);
    }

    private function central(Request $request, string $id, bool $publish = false): void
    {
        abort_unless(in_array($request->user()->user_type, ['super-admin', 'admin-employee'], true), 403);
        Gate::authorize('provider_update');
        $this->access->authorizeBranch($request->user(), $publish ? 'branches.publish' : 'availability.manage', $id);
    }

    public function create(Request $request)
    {
        abort_unless(nirab_branch_mode(), 404); Gate::authorize('provider_add');
        $this->setup->requireMigration();
        return view('branch.onboarding-create', ['zones' => Zone::ofStatus(1)->get(), 'key' => (string) \Illuminate\Support\Str::uuid()]);
    }

    public function store(Request $request)
    {
        abort_unless(nirab_branch_mode(), 404); Gate::authorize('provider_add');
        abort_unless($request->user()->user_type === 'super-admin' || ($request->user()->user_type === 'admin-employee'
            && \App\Models\OperationalScope::where('user_id', $request->user()->id)->where('all_branches', true)->exists()), 403);
        $this->setup->requireMigration();
        $request->validate(['onboarding_key' => 'required|uuid']);
        if ($branch = Provider::withoutGlobalScopes()->where('onboarding_key', $request->onboarding_key)->first()) {
            $this->branch($request, $branch->id);
            return redirect()->route('admin.branch.setup.show', $branch->id);
        }
        $data = $request->validate($this->profileRules() + ['onboarding_key' => 'required|uuid', 'password' => 'required|string|min:8|confirmed']);
        $this->point($data);
        $branch = $this->setup->create($data);
        return redirect()->route('admin.branch.setup.show', [$branch->id, 'step' => 'coverage-services']);
    }

    private function profileRules(?Provider $branch = null): array
    {
        return ['company_name' => 'required|string|max:191', 'company_email' => 'required|email|max:191',
            'company_phone' => 'required|phone', 'company_address' => 'required|string|max:191',
            'branch_code' => ['nullable', 'string', 'max:64', Rule::unique('providers', 'branch_code')->ignore($branch?->id)],
            'branch_priority' => 'required|integer|min:0|max:2147483647', 'auto_assignment_enabled' => 'required|boolean',
            'zone_id' => 'required|uuid|exists:zones,id', 'latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180',
            'manager_name' => 'required|string|max:191', 'account_email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($branch?->user_id)],
            'account_phone' => ['required', 'phone', Rule::unique('users', 'phone')->ignore($branch?->user_id)]];
    }

    private function point(array $data): void
    {
        abort_unless(app(\App\Services\GeoService::class)->containing(Zone::query(), 'coordinates', (float) $data['latitude'], (float) $data['longitude'])->whereKey($data['zone_id'])->ofStatus(1)->exists(), 422, __('branch_setup.zone_mismatch'));
    }

    public function show(Request $request, string $id)
    {
        $branch = $this->branch($request, $id); $this->setup->requireMigration();
        $step = $request->input('step', $branch->setup_step ?: 'review');
        abort_unless(in_array($step, BranchOnboardingService::STEPS, true), 404);
        $permissions = collect(OperationalAccess::MANAGEMENT_PERMISSIONS)->filter(fn ($p) => $this->access->allows($request->user(), $p, $id));
        $canProfile = in_array($request->user()->user_type, ['super-admin', 'admin-employee'], true) && Gate::allows('provider_update') && $permissions->contains('availability.manage');
        $zones = Zone::ofStatus(1)->get();
        $areas = $branch->serviceAreas()->get();
        $previewMap = null;
        if ($step === 'review' && $permissions->contains('availability.manage') && $permissions->contains('technicians.manage')) {
            $previewMap = ['branch_location' => $branch->coordinates, 'zone' => $zones->firstWhere('id', $branch->zone_id)?->coordinates,
                'areas' => $areas->filter(fn ($area) => $area->is_active && $area->zone_id === $branch->zone_id)
                    ->map(fn ($area) => ['name' => $area->name, 'geometry' => $area->coordinates])->values()->all()];
        }
        return view('branch.onboarding', ['branch' => $branch, 'step' => $step, 'permissions' => $permissions, 'canProfile' => $canProfile,
            'canPublish' => $canProfile && $this->access->allows($request->user(), 'branches.publish', $id),
            'owner' => $canProfile ? $branch->owner : null, 'zones' => $zones, 'previewMap' => $previewMap,
            'services' => BranchServicePresentation::localized(Service::withoutGlobalScope('zone_wise_data'))->whereNull('deleted_at')->with(['activeFields.options', 'variations' => fn ($q) => $q->withoutGlobalScopes()->forZone($branch->zone_id)])->orderBy('name')->get(),
            'servicePermissions' => BranchServiceAvailability::where('provider_id', $id)->get()->keyBy('service_id'),
            'areas' => $areas, 'schedule' => app(BranchScheduleService::class)->read($id),
            'staff' => $permissions->contains('technicians.manage') || $permissions->contains('teams.manage') ? $branch->servicemen()->with(['user', 'skills', 'shifts'])->get() : collect(),
            'teams' => $permissions->contains('teams.manage') ? $branch->teams()->with('members')->get() : collect(),
            'report' => $step === 'review' ? app(BranchReadinessService::class)->report($branch) : ['legacy' => $branch->getAttribute('intake_enabled') === null],
            'templates' => $permissions->contains('availability.manage') ? $this->access->scope(Provider::withoutGlobalScopes(), $request->user(), 'availability.manage', 'id')->whereNull('deleted_at')->where('id', '!=', $id)->get(['id', 'company_name']) : collect()]);
    }

    public function save(Request $request, string $id, string $section)
    {
        $branch = $this->branch($request, $id);
        $this->setup->requireMigration();
        $next = $section;
        DB::transaction(function () use ($request, $id, $section, &$branch, &$next): void {
            $branch = Provider::withoutGlobalScopes()->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($section === 'profile') {
                $this->central($request, $id);
                $data = $request->validate($this->profileRules($branch));
                abort_unless($data['zone_id'] === $branch->zone_id, 422, __('branch_setup.zone_confirmation_required'));
                $this->point($data); $old = $branch->only(array_keys($data));
                $branch->forceFill(collect($data)->except(['manager_name', 'account_email', 'account_phone', 'latitude', 'longitude'])->all());
                $branch->coordinates = ['latitude' => (float) $data['latitude'], 'longitude' => (float) $data['longitude']]; $branch->save();
                $branch->owner()->firstOrFail()->forceFill(['first_name' => $data['manager_name'], 'email' => $data['account_email'], 'phone' => $data['account_phone']])->save();
                app(AuditService::class)->record('branch.profile.saved', $branch, $old, $branch->only(array_keys($data)), $request->user(), $id);
                $next = 'coverage-services';
            } elseif ($section === 'coverage-services') {
                $this->access->authorizeBranch($request->user(), 'availability.manage', $id);
                if (!$request->input('service_ids')) $request->merge(['service_ids' => []]);
                $request->validate(['service_ids' => 'present|array|max:200', 'service_ids.*' => 'uuid|distinct|exists:services,id']);
                $central = in_array($request->user()->user_type, ['super-admin', 'admin-employee'], true);
                $ids = collect($request->service_ids);
                $existing = BranchServiceAvailability::where('provider_id', $id)->get();
                $controller = app(BranchServiceAvailabilityController::class);
                foreach ($existing->pluck('service_id')->merge($ids)->unique() as $serviceId) {
                    if (!$central && !$existing->firstWhere('service_id', $serviceId)?->admin_enabled && !$ids->contains($serviceId)) continue;
                    $child = $this->child($request, ['provider_id' => $id, 'service_id' => $serviceId, 'is_active' => $ids->contains($serviceId)]);
                    if ($central) $child->merge(['admin_enabled' => $ids->contains($serviceId)]);
                    $controller->update($child);
                }
                $next = 'schedule-capacity';
            } elseif ($section === 'schedule-capacity') {
                $this->access->authorizeBranch($request->user(), 'availability.manage', $id);
                if (!$request->input('weekends')) $request->merge(['weekends' => []]);
                $data = $request->validate(['service_capacity_per_day' => 'required|integer|min:1|max:100000']);
                $old = ['service_capacity_per_day' => $branch->service_capacity_per_day];
                $branch->service_capacity_per_day = $data['service_capacity_per_day']; $branch->save();
                app(BranchScheduleService::class)->save($branch, $request->all());
                app(AuditService::class)->record('branch.capacity.updated', $branch, $old, $data, $request->user(), $id);
                $next = 'workforce';
            } else abort(404);
            $this->setup->advance($branch, $next);
        });
        return redirect()->route($this->routePrefix($request).'.branch.setup.show', [$id, 'step' => $next])->with('success', __('branch_setup.saved'));
    }

    private function routePrefix(Request $request): string { return str_starts_with($request->user()->user_type, 'provider-') ? 'provider' : 'admin'; }

    private function child(Request $request, array $data): Request
    {
        $child = clone $request;
        $child->setJson(new \Symfony\Component\HttpFoundation\InputBag($data));
        $child->replace($data);
        return $child;
    }

    public function action(Request $request, string $id, string $action)
    {
        $branch = $this->branch($request, $id);
        $data = match ($action) {
            'publish', 'pause' => $this->publish($request, $branch, $action === 'publish'),
            'readiness' => app(BranchReadinessService::class)->report($branch),
            'preview' => $this->preview($request, $branch),
            'coverage-point' => $this->coveragePoint($request, $branch),
            'zone-change-preview' => $this->zone($request, $branch, false),
            'zone-change-confirm' => $this->zone($request, $branch, true),
            'area' => $this->area($request, $branch),
            'workforce' => $this->workforce($request, $branch),
            'template' => $this->template($request, $branch),
            default => abort(404),
        };
        return response()->json(response_formatter(DEFAULT_200, $data));
    }

    private function publish(Request $request, Provider $branch, bool $enabled): array
    {
        $this->central($request, $branch->id, true);
        return $this->setup->intake($branch, $enabled);
    }

    private function authorizePreview(Request $request, Provider $branch): void
    {
        $this->access->authorizeBranch($request->user(), 'availability.manage', $branch->id);
        $this->access->authorizeBranch($request->user(), 'technicians.manage', $branch->id);
    }

    private function preview(Request $request, Provider $branch): array
    {
        $this->authorizePreview($request, $branch);
        $data = $request->validate(['latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180',
            'starts_at' => 'required|date|before_or_equal:'.now()->addDays(365)->toDateString(), 'items' => 'required|array|min:1|max:20',
            'items.*.service_id' => 'required|uuid|exists:services,id', 'items.*.variant_key' => 'required|string|max:191',
            'items.*.quantity' => 'required|integer|min:1|max:1000', 'items.*.field_answers' => 'nullable|array|max:100']);
        return app(BranchReadinessService::class)->preview($branch, $data);
    }

    private function zone(Request $request, Provider $branch, bool $confirm): array
    {
        $this->central($request, $branch->id, true);
        $data = $request->validate(['zone_id' => 'required|uuid|exists:zones,id', 'confirmation_hash' => $confirm ? 'required|string|size:64' : 'nullable',
            'confirmed' => $confirm ? 'required|accepted' : 'nullable', 'reason' => 'nullable|string|max:2000']);
        $service = app(BranchZoneChangeService::class);
        if ($confirm) $service->confirm($branch, $data['zone_id'], $data['confirmation_hash'], $data['reason'] ?? null);
        return $confirm ? ['saved' => true] : $service->preview($branch, $data['zone_id']);
    }

    private function area(Request $request, Provider $branch): array
    {
        $controller = app(DispatchController::class);
        $this->access->authorizeBranch($request->user(), 'areas.manage', $branch->id);
        $request->validate(['area_id' => 'nullable|uuid', 'creation_key' => 'required_without:area_id|uuid']);
        return DB::transaction(function () use ($request, $branch, $controller) {
            Provider::withoutGlobalScopes()->whereKey($branch->id)->lockForUpdate()->firstOrFail();
            $marker = ['provider_id' => $branch->id, 'settings_type' => 'branch_setup', 'key_name' => 'area_'.$request->creation_key];
            if (!$request->filled('area_id') && ($saved = ProviderSetting::where($marker)->first())) {
                $state = json_decode($saved->getRawOriginal('live_values'), true);
                return $branch->serviceAreas()->findOrFail($state['area_id'])->toArray();
            }
            $response = $request->filled('area_id') ? $controller->updateServiceArea($request, $request->area_id) : $controller->storeServiceArea($request);
            $result = $response->getData(true)['content'];
            if (!$request->filled('area_id')) ProviderSetting::create($marker + ['live_values' => ['area_id' => $result['id']], 'test_values' => ['area_id' => $result['id']], 'mode' => 'live', 'is_active' => 1]);
            return $result;
        });
    }

    private function coveragePoint(Request $request, Provider $branch): array
    {
        // Read-only coverage checks also serve users already authorized to preview bookings.
        if (!$this->access->allows($request->user(), 'areas.manage', $branch->id)) $this->authorizePreview($request, $branch);
        $data = $request->validate(['latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180']);
        try { $area = app(\Modules\ProviderManagement\Services\BranchServiceAreaRepository::class)->resolveAreaId($branch->id, $branch->zone_id, (float) $data['latitude'], (float) $data['longitude']); }
        catch (\Illuminate\Validation\ValidationException $error) { $area = null; }
        return ['available' => $area !== null, 'message' => __('branch_setup.'.($area ? 'point_inside' : 'outside_coverage'))];
    }

    private function workforce(Request $request, Provider $branch): array
    {
        $controller = app(DispatchController::class);
        if ($request->input('operation') === 'team') {
            $this->access->authorizeBranch($request->user(), 'teams.manage', $branch->id);
            $request->validate(['creation_key' => 'required|uuid']);
            return DB::transaction(function () use ($request, $branch, $controller) {
                Provider::withoutGlobalScopes()->whereKey($branch->id)->lockForUpdate()->firstOrFail();
                $marker = ['provider_id' => $branch->id, 'settings_type' => 'branch_setup', 'key_name' => 'team_'.$request->creation_key];
                if ($saved = ProviderSetting::where($marker)->first()) {
                    $teamId = json_decode($saved->getRawOriginal('live_values'), true);
                } else {
                    $result = $controller->storeTeam($request)->getData(true)['content'];
                    $teamId = $result['id'];
                    ProviderSetting::create($marker + ['live_values' => $teamId, 'test_values' => $teamId, 'mode' => 'live', 'is_active' => 1]);
                }
                // First creation and retries share the same branch-scoped representation.
                return Team::where('provider_id', $branch->id)->with(['leader.user', 'members.user'])->withCount('members')->findOrFail($teamId)->toArray();
            });
        }
        $this->access->authorizeBranch($request->user(), 'technicians.manage', $branch->id);
        if ($request->input('operation') === 'skills') {
            $servicemanId = $request->validate(['serviceman_id' => 'required|uuid'])['serviceman_id'];
            $result = $controller->replaceSkills($request, $servicemanId)->getData(true)['content'];
            // Keep the shared dispatch API intact; onboarding needs only a save receipt.
            return ['saved' => true, 'serviceman_id' => $servicemanId, 'skills_count' => count($result['skills'])];
        }
        if ($request->input('operation') === 'shifts') {
            $data = $request->validate(['serviceman_ids' => 'required|array|min:1|max:100', 'serviceman_ids.*' => 'uuid|distinct',
                'days' => 'required|array|min:1|max:7', 'days.*' => 'integer|between:0,6|distinct', 'starts_at' => 'required|date_format:H:i', 'ends_at' => 'required|date_format:H:i|after:starts_at', 'confirmed' => 'sometimes|boolean']);
            $members = Serviceman::withoutGlobalScopes()->whereNull('deleted_at')->with('user')->where('provider_id', $branch->id)->whereIn('id', $data['serviceman_ids'])->get();
            abort_unless($members->count() === count($data['serviceman_ids']), 403);
            $shifts = array_map(fn ($day) => ['day_of_week' => (int) $day, 'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'], 'is_active' => true], $data['days']);
            $preview = ['serviceman_ids' => $data['serviceman_ids'], 'shifts' => $shifts, 'replaces_selected_days_only' => true,
                'approved_leaves' => $members->mapWithKeys(fn ($m) => [$m->id => $m->leaves()->where('status', 'approved')->where('ends_at', '>', now())->count()])->all()];
            $preview['inactive_technicians'] = $members->filter(fn ($m) => !$m->user?->is_active)->pluck('id')->all();
            $preview['leave_conflicts'] = [];
            foreach ($members as $member) {
                $leaves = $member->leaves()->where('status', 'approved')->where('ends_at', '>', now())->limit(101)->get();
                abort_if($leaves->count() > 100, 422, 'diagnostic_candidate_limit');
                foreach ($leaves as $leave) {
                    $first = $leave->starts_at->copy()->setTimezone(config('app.timezone'))->startOfDay()->max(now()->startOfDay());
                    // Eight consecutive dates cover a weekly template even if its first occurrence already ended.
                    for ($offset = 0; $offset < 8; $offset++) {
                        $day = $first->copy()->addDays($offset);
                        if ($day->gt($leave->ends_at)) break;
                        if (!in_array($day->dayOfWeek, array_map('intval', $data['days']), true)) continue;
                        $start = $day->copy()->setTimeFromTimeString($data['starts_at']); $end = $day->copy()->setTimeFromTimeString($data['ends_at']);
                        if ($start->lt($leave->ends_at) && $end->gt($leave->starts_at)) {
                            $preview['leave_conflicts'][] = ['serviceman_id' => $member->id, 'starts_at' => $start->toIso8601String(), 'ends_at' => $end->toIso8601String()];
                            break;
                        }
                    }
                }
            }
            if (!($data['confirmed'] ?? false)) return $preview;
            DB::transaction(function () use ($request, $members, $data, $shifts, $branch, $controller): void {
                Provider::withoutGlobalScopes()->whereKey($branch->id)->lockForUpdate()->firstOrFail();
                foreach ($members as $member) {
                    $keep = $member->shifts()->whereNotIn('day_of_week', $data['days'])->get()->map(fn ($s) => ['day_of_week' => $s->day_of_week, 'starts_at' => substr($s->starts_at, 0, 5), 'ends_at' => substr($s->ends_at, 0, 5), 'is_active' => (bool) $s->is_active])->all();
                    $child = $this->child($request, ['provider_id' => $branch->id, 'shifts' => array_merge($keep, $shifts)]); $controller->replaceShifts($child, $member->id);
                }
                $this->setup->advance($branch, 'review');
            });
            return $preview + ['saved' => true];
        }
        $request->validate(['creation_key' => 'required|uuid']);
        return DB::transaction(function () use ($request, $branch) {
            Provider::withoutGlobalScopes()->whereKey($branch->id)->lockForUpdate()->firstOrFail();
            $marker = ['provider_id' => $branch->id, 'settings_type' => 'branch_setup', 'key_name' => 'staff_'.$request->creation_key];
            if ($saved = ProviderSetting::where($marker)->first()) return ['serviceman_id' => json_decode($saved->getRawOriginal('live_values'), true)];
            $data = $request->validate(['first_name' => 'required|string|max:191', 'last_name' => 'nullable|string|max:191',
                'email' => 'required|email|unique:users,email', 'phone' => 'required|phone|unique:users,phone', 'password' => 'required|string|min:8|confirmed']);
            $user = new User(); $user->forceFill(collect($data)->except(['password', 'password_confirmation'])->all() + ['password' => bcrypt($data['password']), 'user_type' => 'provider-serviceman', 'is_active' => 1])->save();
            $member = new Serviceman(); $member->forceFill(['provider_id' => $branch->id, 'user_id' => $user->id])->save();
            ProviderSetting::create($marker + ['live_values' => $member->id, 'test_values' => $member->id, 'mode' => 'live', 'is_active' => 1]);
            app(AuditService::class)->record('technician.created', $member, [], ['provider_id' => $branch->id], $request->user(), $branch->id);
            return ['serviceman_id' => $member->id];
        });
    }

    private function template(Request $request, Provider $branch): array
    {
        $this->access->authorizeBranch($request->user(), 'availability.manage', $branch->id);
        $data = $request->validate(['source_id' => 'required|uuid', 'confirmed' => 'sometimes|boolean',
            'source_serviceman_id' => 'nullable|uuid', 'target_serviceman_ids' => 'sometimes|array|max:100', 'target_serviceman_ids.*' => 'uuid|distinct']);
        $source = $this->branch($this->child($request, ['branch_id' => $data['source_id'], 'provider_id' => $data['source_id']]), $data['source_id'], 'availability.manage');
        $schedule = app(BranchScheduleService::class)->read($source->id);
        $ids = BranchServiceAvailability::where('provider_id', $source->id)->where('admin_enabled', true)->where('is_active', true)->pluck('service_id');
        $ids = app(\Modules\ServiceManagement\Services\DirectServicePolicy::class)->catalog($branch->zone_id)->whereIn('id', $ids)->pluck('id')->all();
        $workforce = [];
        if ($this->access->allows($request->user(), 'technicians.manage', $source->id) && $this->access->allows($request->user(), 'technicians.manage', $branch->id)) {
            $workforce = $source->servicemen()->with(['user', 'skills', 'shifts'])->get()->map(fn ($m) => ['id' => $m->id,
                'name' => trim($m->user?->first_name.' '.$m->user?->last_name), 'service_ids' => $m->skills->pluck('service_id')->all(),
                'shifts' => $m->shifts->map(fn ($s) => ['day_of_week' => $s->day_of_week, 'starts_at' => substr($s->starts_at, 0, 5), 'ends_at' => substr($s->ends_at, 0, 5), 'is_active' => (bool) $s->is_active])->all()])->all();
        }
        if ($data['confirmed'] ?? false) {
            $this->central($request, $branch->id);
            DB::transaction(function () use ($request, $branch, $schedule, $ids, $workforce, $data): void {
                $hours = $schedule['time_schedule'];
                $weekly = [];
                foreach (BranchScheduleService::DAYS as $day) $weekly[] = ['is_active' => !in_array($day, $schedule['weekends'], true), 'start_time' => $hours['start_time'] ?? '', 'end_time' => $hours['end_time'] ?? '', 'breaks' => $hours['breaks'] ?? []];
                app(BranchScheduleService::class)->save($branch, $hours + ['weekly' => $weekly, 'break_start' => $hours['breaks'][0]['start_time'] ?? null, 'break_end' => $hours['breaks'][0]['end_time'] ?? null, 'weekends' => $schedule['weekends'], 'service_availability' => $branch->service_availability]);
                foreach ($ids as $serviceId) {
                    $child = $this->child($request, ['provider_id' => $branch->id, 'service_id' => $serviceId, 'admin_enabled' => true, 'is_active' => true]);
                    app(BranchServiceAvailabilityController::class)->update($child);
                }
                if (!empty($data['source_serviceman_id'])) {
                    $this->access->authorizeBranch($request->user(), 'technicians.manage', $branch->id);
                    $template = collect($workforce)->firstWhere('id', $data['source_serviceman_id']); abort_unless($template, 403);
                    $members = Serviceman::withoutGlobalScopes()->whereNull('deleted_at')->where('provider_id', $branch->id)->whereIn('id', $data['target_serviceman_ids'] ?? [])->get();
                    abort_unless($members->isNotEmpty() && $members->count() === count($data['target_serviceman_ids'] ?? []), 403);
                    $authorized = BranchServiceAvailability::where('provider_id', $branch->id)->where('admin_enabled', true)->pluck('service_id');
                    foreach ($members as $member) {
                        $child = $this->child($request, ['provider_id' => $branch->id, 'service_ids' => array_values(array_intersect($template['service_ids'], $authorized->all())), 'shifts' => $template['shifts']]);
                        app(DispatchController::class)->replaceSkills($child, $member->id);
                        app(DispatchController::class)->replaceShifts($child, $member->id);
                    }
                }
                app(AuditService::class)->record('branch.template.applied', $branch, [], ['source_id' => $request->source_id, 'service_ids' => $ids], $request->user(), $branch->id);
            });
        }
        return ['schedule' => $schedule, 'service_ids' => $ids, 'workforce_templates' => $workforce, 'saved' => (bool) ($data['confirmed'] ?? false), 'accounts_and_polygons_copied' => false];
    }
}
