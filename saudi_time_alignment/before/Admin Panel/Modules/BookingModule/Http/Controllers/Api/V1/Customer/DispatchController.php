<?php

namespace Modules\BookingModule\Http\Controllers\Api\V1\Customer;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\BookingModule\Entities\Booking;
use Modules\BookingModule\Services\AvailabilityService;
use Modules\BookingModule\Services\BookingSchedule;
use Modules\BookingModule\Services\TrackingService;
use Modules\ProviderManagement\Services\BranchAssignmentService;
use Modules\ServiceManagement\Entities\Service;
use Modules\ServiceManagement\Services\BranchServiceAvailabilityService;

class DispatchController extends Controller
{
    public function __construct(
        private readonly BranchAssignmentService $branches,
        private readonly AvailabilityService $availability,
        private readonly TrackingService $tracking,
    ) {}

    public function availability(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'zone_id' => 'required|uuid', 'items' => 'required_without:quotation_id|array|min:1|max:100',
            'date' => 'required|date|after_or_equal:today|before_or_equal:'.now()->addDays(365)->toDateString(), 'provider_id' => 'nullable|uuid',
            'latitude' => 'required_without:quotation_id|nullable|numeric|between:-90,90', 'longitude' => 'required_without:quotation_id|nullable|numeric|between:-180,180',
            'starts_at' => 'nullable|date',
            'quotation_id' => 'nullable|uuid', 'branch_selection' => 'nullable|in:manual,auto',
        ]);
        if ($validator->fails()) return response()->json(response_formatter(DEFAULT_400, null, error_processor($validator)), 400);
        $day = Carbon::parse($request->date, config('app.timezone'))->startOfDay();
        $requirements = !$request->quotation_id ? app(\Modules\ServiceManagement\Services\ServiceItemRequirements::class)->evaluate($request->input('items', []), $request->zone_id) : null;
        $serviceIds = $requirements['service_ids'] ?? [];
        $duration = $requirements['duration_minutes'] ?? 0;
        $required = $requirements['required_technicians'] ?? 0;
        $executionType = $requirements['execution_type'] ?? 'single';
        $serviceAvailability = app(BranchServiceAvailabilityService::class);
        $availableBranches = [];
        $hasServices = function ($candidate) use ($request, &$serviceIds, $serviceAvailability, &$availableBranches): bool {
            $areaId = app(\Modules\ProviderManagement\Services\BranchServiceAreaRepository::class)
                ->resolveAreaId($candidate->id, $request->zone_id, $request->latitude, $request->longitude);
            return $availableBranches[$candidate->id] ??= $areaId && collect($serviceIds)->every(fn ($serviceId) => $serviceAvailability->isAvailable($candidate->id, $serviceId, $areaId));
        };
        if ($request->quotation_id) {
            $quote = \Modules\BidModule\Entities\PostBid::with('post')
                ->whereHas('post', fn ($query) => $query->where('customer_user_id', $request->user('api')?->id))
                ->where('status', 'ready')->findOrFail($request->quotation_id);
            abort_if($quote->isExpired(), 422);
            $serviceIds = [$quote->post->service_id];
            $duration = max(1, (int) $quote->estimated_duration_minutes);
            $required = max(1, (int) $quote->required_technicians);
            $executionType = $quote->execution_type;
            $address = $quote->post->location_snapshot ?: [];
            $request->merge([
                'provider_id' => $quote->provider_id, 'branch_selection' => 'manual', 'zone_id' => $quote->post->zone_id,
                'latitude' => $address['lat'] ?? $address['latitude'] ?? null,
                'longitude' => $address['lon'] ?? $address['longitude'] ?? null,
            ]);
        }
        $resolve = fn ($start) => $this->branches->resolve(
            $request->zone_id, $serviceIds, $start, $request->latitude, $request->longitude, $request->provider_id,
            fn ($candidate) => $hasServices($candidate) && $this->branches->isEligible($candidate, $request->zone_id, $serviceIds, $start->copy()->addMinutes($duration), $request->latitude, $request->longitude, (bool) $request->provider_id && $request->branch_selection !== 'auto')
                && $this->availability->canFulfill($candidate->id, $serviceIds, $start, $duration, $required, $executionType),
            $request->branch_selection === 'auto',
        );
        $resolveGroups = function ($start) use ($request, $resolve) {
            if ($request->quotation_id) {
                $candidate = $resolve($start);
                return $candidate ? [['provider_id' => $candidate->id, 'branch_id' => $candidate->id, 'branch_name' => $candidate->company_name]] : [];
            }
            $cart = collect($request->input('items'))->map(function ($item, $index) {
                $row = new \Modules\CartModule\Entities\Cart();
                return $row->forceFill($item + ['id' => 'availability-'.$index, 'provider_id' => null]);
            });
            $payload = ['zone_id' => $request->zone_id, 'address' => ['lat' => (float) $request->latitude, 'lon' => (float) $request->longitude]];
            try {
                $plans = app(\Modules\BookingModule\Services\CheckoutBranchGroupingService::class)->plan($cart, $request->all(), [$start->toDateTimeString()], $payload,
                    function ($group, $date, $items) use ($start, $request): bool {
                        $branch = \Modules\ProviderManagement\Entities\Provider::withoutGlobalScopes()->find($group['provider_id']);
                        foreach ($items as $item) {
                            $service = app(\Modules\ServiceManagement\Services\DirectServicePolicy::class)->catalog($request->zone_id)->findOrFail($item->service_id);
                            $variation = app(\Modules\ServiceManagement\Services\ServicePriceResolver::class)->resolve($service->id, $item->variant_key, $request->zone_id);
                            app(\Modules\ServiceManagement\Services\PricingEngine::class)->calculate($service, $variation, $item->quantity, $request->zone_id, $branch->id, $group['area_id'], $start, fieldAnswers: $item->field_answers ?? []);
                        }
                        return $this->branches->isEligible($branch, $request->zone_id, $group['service_ids'], $start, $request->latitude, $request->longitude)
                            && app(\Modules\ProviderManagement\Services\BranchScheduleService::class)->contains($branch, $start, $group['duration'])
                            && $this->availability->canFulfill($branch->id, $group['service_ids'], $start, $group['duration'], $group['required_technicians'], $group['execution_type']);
                    });
                return array_map(fn ($g) => ['provider_id' => $g['provider_id'], 'branch_id' => $g['provider_id'], 'branch_name' => $g['branch_name'], 'duration_minutes' => $g['duration']], $plans);
            } catch (\Illuminate\Validation\ValidationException $error) { return []; }
            catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) { if ($error->getStatusCode() !== 422) throw $error; return []; }
        };
        $slots = []; $selectedGroups = [];
        $requestedStart = $request->starts_at ? BookingSchedule::parse($request->starts_at) : null;
        $branch = null;
        if ($requestedStart) {
            $selectedGroups = $requestedStart->isSameDay($day) && !$requestedStart->isPast() ? $resolveGroups($requestedStart) : [];
        } else {
            for ($cursor = $day->copy(); $cursor->copy()->addMinutes(1)->lt($day->copy()->addDay()); $cursor->addMinutes(30)) {
                if ($cursor->isPast()) continue;
                $groups = $resolveGroups($cursor);
                if ($groups) {
                    if (!$selectedGroups) $selectedGroups = $groups;
                    $slots[] = ['starts_at' => $cursor->toIso8601String(), 'ends_at' => $cursor->copy()->addMinutes(max(array_column($groups, 'duration_minutes') ?: [$duration]))->toIso8601String(),
                        'provider_id' => $groups[0]['provider_id'], 'branch_id' => $groups[0]['provider_id'], 'groups' => $groups];
                }
            }
        }
        return response()->json(response_formatter(DEFAULT_200, [
            'provider_id' => $selectedGroups[0]['provider_id'] ?? null, 'branch_id' => $selectedGroups[0]['provider_id'] ?? null, 'groups' => $selectedGroups, 'unavailability_message_key' => !$selectedGroups ? 'no_slots_for_date' : null, 'timezone' => config('app.timezone'),
            'execution_type' => $executionType,
            'required_technicians' => $required, 'duration_minutes' => $duration,
            'requested_slot_available' => $requestedStart ? $selectedGroups !== [] : null,
            'slots' => $slots,
        ]), 200);
    }

    public function tracking(Request $request, string $bookingId): JsonResponse
    {
        $booking = Booking::withoutGlobalScopes()->where('customer_id', $request->user()->id)->findOrFail($bookingId);
        $data = $request->validate(['booking_repeat_id' => 'nullable|uuid']);
        $repeat = !empty($data['booking_repeat_id']) ? $booking->repeat()->findOrFail($data['booking_repeat_id']) : null;
        return response()->json(response_formatter(DEFAULT_200, $this->tracking->customerTracking($booking, $repeat)), 200);
    }
}
