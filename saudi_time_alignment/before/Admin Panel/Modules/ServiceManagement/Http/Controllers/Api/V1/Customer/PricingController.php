<?php

namespace Modules\ServiceManagement\Http\Controllers\Api\V1\Customer;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\ServiceManagement\Entities\Service;
use Modules\ServiceManagement\Entities\Variation;
use Modules\ServiceManagement\Services\BranchServiceAvailabilityService;
use Modules\ServiceManagement\Services\PricingEngine;

class PricingController extends Controller
{
    public function quote(Request $request, PricingEngine $engine, BranchServiceAvailabilityService $availability): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'service_id' => 'required|uuid', 'variant_key' => 'required|string',
            'field_answers' => 'sometimes|array',
            'quantity' => 'sometimes|integer|min:1|max:1000', 'provider_id' => 'nullable|uuid',
            'branch_service_area_id' => 'nullable|uuid', 'schedule_at' => 'required|date',
            'latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180',
        ]);
        if ($validator->fails()) return response()->json(response_formatter(DEFAULT_400, null, error_processor($validator)), 422);

        $zoneId = app(\App\Services\GeoService::class)->containing(\Modules\ZoneManagement\Entities\Zone::query(), 'coordinates', (float) $request->latitude, (float) $request->longitude)
            ->ofStatus(1)->orderBy('id')->value('id');
        abort_unless($zoneId, 422, 'service_booking_unavailable_at_location');
        config(['zone_id' => $zoneId]);
        $service = app(\Modules\ServiceManagement\Services\DirectServicePolicy::class)->catalog($zoneId)->findOrFail($request->service_id);
        $at = Carbon::parse($request->schedule_at)->setTimezone(config('app.timezone'));
        $requirements = app(\Modules\ServiceManagement\Services\ServiceItemRequirements::class)->evaluate([[
            'service_id' => $service->id, 'variant_key' => $request->variant_key, 'quantity' => $request->integer('quantity', 1), 'field_answers' => $request->input('field_answers', []),
        ]], $zoneId);
        $branch = app(\Modules\ProviderManagement\Services\BranchAssignmentService::class)->resolve($zoneId, [$service->id], $at, $request->latitude, $request->longitude, $request->provider_id,
            fn ($candidate) => $availability->isAvailable($candidate->id, $service->id, app(\Modules\ProviderManagement\Services\BranchServiceAreaRepository::class)->resolveAreaId($candidate->id, $zoneId, $request->latitude, $request->longitude))
                && app(\Modules\ProviderManagement\Services\BranchScheduleService::class)->contains($candidate, $at, $requirements['duration_minutes'])
                && app(\Modules\BookingModule\Services\AvailabilityService::class)->canFulfill($candidate->id, $requirements['service_ids'], $at, $requirements['duration_minutes'], $requirements['required_technicians'], $requirements['execution_type']));
        $areaId = $branch ? app(\Modules\ProviderManagement\Services\BranchServiceAreaRepository::class)->resolveAreaId($branch->id, $zoneId, $request->latitude, $request->longitude) : null;
        if (!$branch || !$availability->isAvailable($branch->id, $service->id, $areaId)) {
            abort(422, 'service_booking_unavailable_at_location');
        }
        $variation = app(\Modules\ServiceManagement\Services\ServicePriceResolver::class)->resolve($service->id, $request->variant_key, $zoneId);
        $quantity = (int) $request->input('quantity', 1);
        $pre = $engine->calculate($service, $variation, $quantity, $zoneId,
            $branch->id, $areaId, $at, fieldAnswers: $request->input('field_answers', []));
        $discount = max(basic_discount_calculation($service, $pre->adjustedPrice), campaign_discount_calculation($service, $pre->adjustedPrice));
        $breakdown = $engine->calculate($service, $variation, $quantity, $zoneId, $branch->id, $areaId, $at,
            $discount, $request->input('field_answers', []));

        return response()->json(response_formatter(DEFAULT_200, $breakdown));
    }
}
