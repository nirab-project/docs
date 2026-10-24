<?php

namespace Modules\ServiceManagement\Http\Controllers\Api\V1\Admin;

use App\Services\AuditService;
use App\Services\OperationalAccess;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ProviderManagement\Entities\BranchServiceArea;
use Modules\ProviderManagement\Entities\Provider;
use Modules\ServiceManagement\Entities\PriceAudit;
use Modules\ServiceManagement\Entities\PricingRule;

class PricingRuleController extends Controller
{
    public function __construct(private readonly OperationalAccess $access, private readonly AuditService $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $rules = $this->rules($request)
            ->when($request->scope, fn ($q, $scope) => $q->where('scope', $scope))
            ->when($request->provider_id, fn ($q, $id) => $q->where(fn ($branch) => $branch->where('provider_id', $id)
                ->orWhere(fn ($legacy) => $legacy->whereNull('provider_id')->whereIn('branch_service_area_id', BranchServiceArea::where('provider_id', $id)->select('id')))))
            ->when($request->zone_id, fn ($q, $id) => $q->where('zone_id', $id))
            ->when($request->input('search'), fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('priority')->orderBy('created_at')->paginate(max(1, min($request->integer('limit', 50), 200)));
        $rules->setCollection($rules->getCollection()->map(function ($rule) {
            $payload = $rule->toArray();
            foreach (['starts_at', 'ends_at'] as $key) {
                $payload[$key] = $rule->$key?->setTimezone(config('app.timezone'))->toIso8601String();
            }
            return $payload;
        }));
        return response()->json(response_formatter(DEFAULT_200, $rules));
    }

    public function effective(Request $request): JsonResponse
    {
        $data = $request->validate(['service_id' => 'required|uuid|exists:services,id', 'provider_id' => 'required|uuid|exists:providers,id', 'branch_service_area_id' => 'nullable|uuid', 'at' => 'required|date']);
        $this->access->authorizeBranch($request->user(), 'pricing.manage', $data['provider_id']);
        $provider = Provider::withoutGlobalScopes()->findOrFail($data['provider_id']);
        $area = $data['branch_service_area_id'] ?? null;
        if ($area && !BranchServiceArea::where('provider_id', $provider->id)->whereKey($area)->exists()) {
            throw ValidationException::withMessages(['branch_service_area_id' => __('operations.service_area_must_belong_to_branch')]);
        }
        $at = Carbon::parse($data['at'], config('app.timezone'))->setTimezone(config('app.timezone'));
        $rules = PricingRule::applicable($data['service_id'], $provider->zone_id, $provider->id, $area, $at)->get()->filter(fn ($rule) => $rule->appliesAt($at));
        return response()->json(response_formatter(DEFAULT_200, [
            'timezone' => config('app.timezone'), 'at' => $at->toIso8601String(),
            'rules' => collect(['branch', 'neighborhood', 'peak'])->flatMap(fn ($scope) => $rules->where('scope', $scope)->values())->values(),
        ]));
    }

    public function store(Request $request): JsonResponse { return $this->persist($request); }
    public function update(Request $request, string $id): JsonResponse { return $this->persist($request, $id); }

    public function bulk(Request $request): JsonResponse
    {
        // Preserve the existing catalog-wide rule contract.
        $request->merge(['service_id' => null]);
        return $this->persist($request);
    }

    public function bulkStatus(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => 'required|array|min:1|max:200', 'ids.*' => 'uuid|distinct', 'is_active' => 'required|boolean', 'reason' => 'nullable|string|max:2000']);
        $rules = DB::transaction(function () use ($request, $data) {
            $rules = $this->rules($request)->whereIn('id', $data['ids'])->lockForUpdate()->get();
            abort_unless($rules->count() === count($data['ids']), 404);
            foreach ($rules as $rule) {
                $old = $rule->toArray();
                $rule->update(['is_active' => $data['is_active']]);
                PriceAudit::record($rule, 'updated', $old, $rule->toArray(), $data['reason'] ?? null);
                $this->audit->record('pricing.rule.updated', $rule, $old, $rule->toArray(), $request->user());
            }
            return $rules;
        });
        return response()->json(response_formatter(DEFAULT_UPDATE_200, $rules));
    }

    private function persist(Request $request, ?string $id = null): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:191', 'scope' => 'required|in:branch,neighborhood,peak',
            'service_id' => 'nullable|uuid|exists:services,id',
            'provider_id' => 'nullable|uuid|exists:providers,id|required_if:scope,branch',
            'branch_service_area_id' => 'nullable|uuid|exists:branch_service_areas,id|required_if:scope,neighborhood',
            'zone_id' => 'nullable|uuid|exists:zones,id', 'adjustment_type' => 'required|in:percentage,fixed',
            'adjustment_value' => 'required|numeric|between:-999999999,999999999',
            'days_of_week' => 'nullable|array|max:7', 'days_of_week.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday|distinct',
            'start_time' => 'nullable|required_with:end_time|date_format:H:i', 'end_time' => 'nullable|required_with:start_time|date_format:H:i|different:start_time',
            'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after:starts_at',
            'priority' => 'sometimes|integer|between:-2147483648,2147483647', 'is_active' => 'sometimes|boolean',
            'reason' => 'nullable|string|max:2000',
        ]);
        if ($data['adjustment_type'] === 'percentage' && (float) $data['adjustment_value'] < -100) {
            throw ValidationException::withMessages(['adjustment_value' => __('operations.invalid_pricing_adjustment')]);
        }
        if (!empty($data['branch_service_area_id'])) {
            $area = BranchServiceArea::findOrFail($data['branch_service_area_id']);
            if (!empty($data['provider_id']) && $area->provider_id !== $data['provider_id']
                || !empty($data['zone_id']) && $area->zone_id !== $data['zone_id']) {
                throw ValidationException::withMessages(['branch_service_area_id' => __('operations.service_area_must_belong_to_branch')]);
            }
            $data['provider_id'] = $area->provider_id;
            $data['zone_id'] = $area->zone_id;
        }
        if (!empty($data['provider_id'])) {
            $provider = Provider::withoutGlobalScopes()->findOrFail($data['provider_id']);
            if (!empty($data['zone_id']) && $data['zone_id'] !== $provider->zone_id) {
                throw ValidationException::withMessages(['zone_id' => __('operations.service_area_must_use_branch_zone')]);
            }
            $data['zone_id'] = $provider->zone_id;
        }
        $this->access->authorizeBranch($request->user(), 'pricing.manage', $data['provider_id'] ?? null);
        foreach (['starts_at', 'ends_at'] as $key) {
            if (!empty($data[$key])) $data[$key] = Carbon::parse($data[$key], config('app.timezone'))->setTimezone(config('app.timezone'));
        }
        $rule = DB::transaction(function () use ($request, $data, $id) {
            $rule = $id ? $this->rules($request)->lockForUpdate()->findOrFail($id) : new PricingRule();
            $old = $rule->exists ? $rule->toArray() : [];
            $rule->fill($data)->save();
            PriceAudit::record($rule, $id ? 'updated' : 'created', $old, $rule->fresh()->toArray(), $data['reason'] ?? null);
            $this->audit->record('pricing.rule.saved', $rule, $old, $rule->toArray(), $request->user());
            return $rule;
        });
        return response()->json(response_formatter($id ? DEFAULT_UPDATE_200 : DEFAULT_STORE_200, $rule));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        DB::transaction(function () use ($request, $id): void {
            $rule = $this->rules($request)->lockForUpdate()->findOrFail($id);
            PriceAudit::record($rule, 'deleted', $rule->toArray(), null, $request->input('reason'));
            $this->audit->record('pricing.rule.deleted', $rule, $rule->toArray(), [], $request->user());
            $rule->delete();
        });
        return response()->json(response_formatter(DEFAULT_DELETE_200));
    }

    private function rules(Request $request): Builder
    {
        $scoped = $this->access->scope(PricingRule::query(), $request->user(), 'pricing.manage');
        if (!$scoped->getQuery()->wheres) return $scoped;
        // Earlier neighborhood rules can reference an area without storing its provider_id.
        $areas = $this->access->scope(BranchServiceArea::query(), $request->user(), 'pricing.manage')->select('id');
        return PricingRule::query()->where(fn ($query) => $query->whereIn('id', $scoped->select('id'))
            ->orWhere(fn ($legacy) => $legacy->whereNull('provider_id')->whereIn('branch_service_area_id', $areas)));
    }
}
