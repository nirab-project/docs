<?php

namespace Modules\ServiceManagement\Http\Controllers\Web\Admin;

use App\Http\Controllers\QualityAdministrationController;
use App\Models\OperationalScope;
use App\Services\OperationalAccess;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\ProviderManagement\Entities\BranchServiceArea;
use Modules\ProviderManagement\Entities\Provider;
use Modules\ServiceManagement\Entities\Service;
use Modules\ServiceManagement\Http\Controllers\Api\V1\Admin\ServiceFieldController;
use Modules\UserManagement\Entities\User;

class OperationsController extends Controller
{
    public function index(Request $request)
    {
        $access = app(OperationalAccess::class);
        $actor = $request->user();
        $permissions = collect(OperationalAccess::MANAGEMENT_PERMISSIONS)->filter(fn ($p) => $access->allows($actor, $p, $request->attributes->get('branch_workspace') ? $request->branch_id : null))->values();
        abort_unless($permissions->isNotEmpty() || $actor->user_type === 'super-admin', 403);
        if ($request->attributes->get('branch_workspace')) $permissions = $permissions->reject(fn ($p) => in_array($p, ['fields.manage', 'pricing.manage'], true))->values();
        $scope = OperationalScope::where('user_id', $actor->id)->first();
        $branches = Provider::withoutGlobalScopes()->whereNull('deleted_at')
            ->when($actor->user_type === 'admin-employee' && !$scope?->all_branches, fn ($q) => $q->whereIn('id', $scope?->branch_ids ?? []))
            ->when($request->attributes->get('branch_workspace'), fn ($q) => $q->where('id', $request->branch_id))
            ->when(str_starts_with($actor->user_type, 'provider-'), fn ($q) => $q->where('id', $access->branch($actor)))
            ->orderBy('company_name')->get(['id', 'company_name', 'zone_id']);
        $contextBranches = $branches;
        $selectedBranchId = $request->input('branch_id', $branches->first()?->id);
        abort_if($selectedBranchId && !$branches->contains('id', $selectedBranchId), 403);
        $branches = $branches->where('id', $selectedBranchId)->values();
        $permissions = $permissions->filter(fn ($p) => $access->allows($actor, $p, $selectedBranchId))->values();
        $global = !$request->attributes->get('branch_workspace') && ($actor->user_type === 'super-admin' || ($actor->user_type === 'admin-employee' && $scope?->all_branches));
        return view('servicemanagement::admin.operations', [
            'contextBranches' => $contextBranches, 'selectedBranchId' => $selectedBranchId,
            'permissions' => $permissions, 'globalAccess' => $global,
            'branches' => $branches,
            'areas' => BranchServiceArea::whereIn('provider_id', $branches->pluck('id'))->orderBy('name')->get(['id', 'name', 'provider_id']),
            'services' => Service::query()->orderBy('name')->get(['id', 'name']),
            'fieldTypes' => ServiceFieldController::TYPES,
            'timezone' => config('app.timezone'),
            'users' => $actor->user_type === 'super-admin' ? User::with('roles')->whereIn('user_type', ['admin-employee', 'provider-admin', 'provider-employee'])->orderBy('first_name')->get() : collect(),
            'scopes' => $actor->user_type === 'super-admin' ? OperationalScope::all() : collect(),
            'scopePermissions' => QualityAdministrationController::PERMISSIONS,
        ]);
    }
}
