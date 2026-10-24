<?php
namespace Modules\ProviderManagement\Http\Controllers\Api\V1\Provider;
use App\Services\OperationalAccess;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Routing\Controller;
use Modules\ProviderManagement\Services\BranchScheduleService;
class TimeScheduleController extends Controller
{
    public function getAvailableTimeSchedule(Request $request): JsonResponse
    {
        $provider = app(OperationalAccess::class)->provider($request->user());
        app(OperationalAccess::class)->authorizeBranch($request->user(), 'availability.manage', $provider->id);
        $data = app(BranchScheduleService::class)->read($provider->id);
        if ($data['time_schedule'] === []) $data['time_schedule'] = null;
        return response()->json(response_formatter(DEFAULT_200, $data + [
            'service_availability' => (int) $provider->service_availability,
            'intake_enabled' => $provider->getAttribute('intake_enabled'), 'timezone' => config('app.timezone'),
        ]));
    }
    public function setAvailableTimeSchedule(Request $request): JsonResponse
    {
        $provider = app(OperationalAccess::class)->provider($request->user());
        app(OperationalAccess::class)->authorizeBranch($request->user(), 'availability.manage', $provider->id);
        $service = app(BranchScheduleService::class);
        $current = $service->read($provider->id);
        if (!$request->boolean('service_availability') && !$service->configured($provider) && !$request->filled('start_time') && !$request->filled('end_time')) {
            $request->validate(['service_availability' => 'required|in:0,1']);
            \Illuminate\Support\Facades\DB::transaction(function () use ($provider, $request): void {
                $provider = $provider->newQueryWithoutScopes()->whereKey($provider->id)->lockForUpdate()->firstOrFail();
                $old = ['service_availability' => $provider->service_availability];
                $provider->service_availability = 0; $provider->save();
                app(\App\Services\AuditService::class)->record('branch.schedule.paused', $provider, $old, ['service_availability' => 0], $request->user(), $provider->id);
            });
        } else {
            $data = $request->all();
            foreach (['start_time', 'end_time'] as $key) if (empty($data[$key]) && !empty($current['time_schedule'][$key])) $data[$key] = $current['time_schedule'][$key];
            $service->save($provider, $data + ['weekends' => $current['weekends']]);
        }
        return response()->json(response_formatter(DEFAULT_UPDATE_200));
    }
}
