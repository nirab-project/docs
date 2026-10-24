<?php

namespace Modules\BookingModule\Services;

use App\Services\OperationalAccess;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\BookingModule\Entities\{Booking, BookingRepeat, BookingDetailsAmount};
use Modules\ProviderManagement\Entities\Provider;
use Modules\ServiceManagement\Services\{BookingServiceCatalog, DirectServicePolicy, PricingEngine, ServiceItemRequirements};
use Modules\ServiceManagement\Entities\Variation;

/** All line edits share the same eligibility, option, pricing and reservation contract. */
class BookingAmendmentService
{
    public function authorize(Model $booking): void
    {
        $actor = request()->user();
        abort_unless($actor, 401);
        if ($actor->user_type === 'provider-serviceman') {
            app(BookingExecutionService::class)->authorize($booking, $actor);
            abort_unless((bool) provider_config('provider_serviceman_can_edit_booking', 'serviceman_config', $booking->provider_id)?->live_values, 403);
        } else {
            app(OperationalAccess::class)->authorizeBranch($actor, 'dispatch.manage', $booking->provider_id);
            if (str_starts_with($actor->user_type, 'provider-')) {
                abort_unless((bool) business_config('provider_can_edit_booking', 'provider_config')?->live_values, 403);
            }
        }
    }

    public function replaceAll(Model $subject, array $items, bool $future = false): Model
    {
        Validator::make(['items' => $items], ['items' => 'required|array|max:100',
            'items.*.service_id' => 'required|uuid', 'items.*.variant_key' => 'required|string',
            'items.*.quantity' => 'required|integer|min:0|max:1000', 'items.*.field_answers' => 'nullable|array',
            // Ordinary details have BIGINT IDs; repeat details use UUIDs.
            'items.*.detail_id' => ['nullable', \Illuminate\Validation\Rule::anyOf([['integer', 'min:1'], ['uuid']])]])->validate();
        return DB::transaction(function () use ($subject, $items, $future) {
            $parentId = $subject instanceof BookingRepeat ? $subject->booking_id : $subject->id;
            $parent = Booking::withoutGlobalScopes()->whereKey($parentId)->lockForUpdate()->firstOrFail();
            $booking = $subject instanceof BookingRepeat
                ? BookingRepeat::withoutGlobalScopes()->where('booking_id', $parent->id)->whereKey($subject->id)->lockForUpdate()->firstOrFail() : $parent;
            $this->authorize($booking);
            abort_if($parent->payment_method === 'prepaid_visit', 409, __('quality.visit_change_requires_rebooking'));
            abort_if($parent->quotation_id, 409, 'quotation_change_requires_new_offer');
            $targets = collect([$booking]);
            if ($future && $booking instanceof BookingRepeat) {
                $targets = $targets->merge(BookingRepeat::withoutGlobalScopes()->where('booking_id', $parent->id)
                    ->where('service_schedule', '>', $booking->service_schedule)->whereNotIn('booking_status', ['completed', 'canceled'])
                    ->orderBy('service_schedule')->lockForUpdate()->get());
            }
            // One transaction covers every future visit; a conflict leaves all visits unchanged.
            $sourceLines = $booking->detail()->get();
            foreach ($targets as $target) {
                $targetItems = $items;
                if ($target->id !== $booking->id) {
                    foreach ($targetItems as &$item) {
                        $source = $sourceLines->firstWhere('id', $item['detail_id'] ?? null);
                        if ($source) $item['field_answers'] ??= $source->field_answers ?? [];
                        unset($item['detail_id']);
                        $matches = $target->detail()->where('service_id', $item['service_id'])->where('variant_key', $item['variant_key'])->get()
                            ->filter(fn ($line) => ($line->field_answers ?? []) == ($source?->field_answers ?? $item['field_answers'] ?? []));
                        abort_if($matches->count() > 1, 409, 'ambiguous_future_visit_line');
                        if ($matches->count() === 1) $item['detail_id'] = $matches->first()->id;
                    }
                    unset($item);
                }
                $this->apply($target, $parent, $targetItems);
            }
            if ($booking instanceof BookingRepeat) {
                foreach (['total_booking_amount','total_tax_amount','total_discount_amount','total_campaign_discount_amount',
                    'total_coupon_discount_amount','total_pro_discount_amount','additional_charge','additional_tax_amount',
                    'additional_discount_amount','additional_campaign_discount_amount'] as $field) {
                    $parent->$field = $parent->repeat()->sum($field);
                }
                $activeVisits = $parent->repeat()->whereNotIn('booking_status', ['completed', 'canceled'])->get();
                if ($activeVisits->isNotEmpty()) {
                    $parent->estimated_duration_minutes = $activeVisits->max('estimated_duration_minutes') ?? $parent->estimated_duration_minutes;
                    $parent->required_technicians = $activeVisits->max('required_technicians') ?? $parent->required_technicians;
                    $parent->execution_type = $parent->required_technicians > 1 || $activeVisits->contains('execution_type', 'team') ? 'team' : 'single';
                }
                $parent->save();
            }
            return $booking->fresh();
        }, 3);
    }

    private function apply(Model $booking, Booking $parent, array $items): void
    {
        $this->authorize($booking);
        abort_if(in_array($booking->booking_status, ['completed', 'canceled'], true), 409, 'booking_not_editable');
        $context = app(BookingServiceCatalog::class)->context($booking);
        abort_unless($context['provider_id'] && $context['area_id'], 409, 'booking_branch_coverage_unavailable');
        Provider::withoutGlobalScopes()->whereKey($context['provider_id'])->lockForUpdate()->firstOrFail();
        $old = $booking->detail()->lockForUpdate()->get();
        $before = ['amount'=>$booking->total_booking_amount, 'lines'=>$old->toArray()];
        $used = [];
        $normalized = [];
        foreach ($items as $item) {
            if (!(int) $item['quantity']) continue;
            $matches = $old->where('service_id', $item['service_id'])->where('variant_key', $item['variant_key']);
            if (!empty($item['detail_id'])) $matches = $matches->where('id', $item['detail_id']);
            elseif (array_key_exists('field_answers', $item)) $matches = $matches->filter(fn ($line) => ($line->field_answers ?? []) == ($item['field_answers'] ?? []));
            abort_if(empty($item['detail_id']) && $matches->count() > 1, 409, 'booking_line_id_required');
            $line = $matches->first();
            abort_if(!empty($item['detail_id']) && !$line, 422, 'booking_line_not_owned');
            if ($line) {
                abort_if(in_array($line->id, $used, true), 422, 'duplicate_booking_line');
                $used[] = $line->id;
            }
            $item['field_answers'] = $item['field_answers'] ?? $line?->field_answers ?? [];
            $item['_line'] = $line;
            $normalized[] = $item;
        }
        abort_if(empty($normalized), 422, 'booking_requires_service');
        $requirements = app(ServiceItemRequirements::class)->evaluate($normalized, $context['zone_id']);
        abort_unless(app(DirectServicePolicy::class)->allAvailable($context['provider_id'], $requirements['service_ids'], $context['area_id'], false), 409, 'service_not_available_for_branch');
        $start = Carbon::parse($booking->service_schedule);
        // In-progress work reserves its remaining window from now; completed work cannot be edited.
        $window = $start->isPast() ? now()->addSeconds(5) : $start;
        $availability = app(AvailabilityService::class);
        abort_unless($availability->canFulfill($context['provider_id'], $requirements['service_ids'], $window,
            $requirements['duration_minutes'], $requirements['required_technicians'], $requirements['execution_type'], $parent->id), 409, 'booking_amendment_capacity_conflict');
        $assignments = $parent->assignments()->whereIn('status', ['offered','accepted'])->with('serviceman')->get();
        abort_if($assignments->isNotEmpty() && $assignments->pluck('serviceman_id')->unique()->count() < $requirements['required_technicians'], 409, 'assigned_team_insufficient_members');
        foreach ($assignments as $assignment) {
            abort_unless($assignment->serviceman && $availability->explain($assignment->serviceman, $requirements['service_ids'],
                $window, $requirements['duration_minutes'], $parent->id)['available'], 409, 'assigned_technician_no_longer_eligible');
        }
        $engine = app(PricingEngine::class);
        $priced = [];
        foreach ($normalized as $i => $item) {
            $service = app(DirectServicePolicy::class)->catalog($context['zone_id'])->findOrFail($item['service_id']);
            $variation = app(\Modules\ServiceManagement\Services\ServicePriceResolver::class)->resolve($service->id, $item['variant_key'], $context['zone_id']);
            $pre = $engine->calculate($service, $variation, (int) $item['quantity'], $context['zone_id'], $context['provider_id'], $context['area_id'], $start, fieldAnswers: $item['field_answers']);
            $basic = basic_discount_calculation($service, $pre->adjustedPrice);
            $campaign = campaign_discount_calculation($service, $pre->adjustedPrice);
            $promotion = min($pre->adjustedPrice, max($basic, $campaign));
            $priced[] = compact('item','service','variation','pre','promotion') + ['basic' => $basic > $campaign ? $promotion : 0, 'campaign' => $campaign >= $basic ? $promotion : 0, 'coupon' => 0];
        }
        // Keep the already-used coupon on eligible lines only; do not consume another use.
        if ($booking->coupon_code) {
            $coupon = \Modules\PromotionManagement\Entities\Coupon::withoutGlobalScopes()->with('discount.discount_types')->where('coupon_code', $booking->coupon_code)->where('is_active', 1)->first();
            $discount = $coupon?->discount;
            if ($discount && $discount->is_active && $discount->start_date <= today()->toDateString() && $discount->end_date >= today()->toDateString()
                && $discount->discount_types->where('discount_type','zone')->contains('type_wise_id', $context['zone_id'])) {
                $eligible = array_keys(array_filter($priced, fn ($p) => $discount->discount_types->where('discount_type','service')->contains('type_wise_id', $p['service']->id)));
                $basis = array_sum(array_map(fn ($i) => $priced[$i]['pre']->adjustedPrice - $priced[$i]['promotion'], $eligible));
                $remaining = $total = min($basis, booking_discount_calculator($discount, $basis));
                foreach ($eligible as $i) {
                    $lineBasis = $priced[$i]['pre']->adjustedPrice - $priced[$i]['promotion'];
                    $share = $i === end($eligible) ? $remaining : ($basis > 0 ? round($total * $lineBasis / $basis, 3) : 0);
                    $priced[$i]['coupon'] = min($lineBasis, max(0, $share));
                    $remaining = round($remaining - $priced[$i]['coupon'], 3);
                }
            }
        }
        $sum = ['total_booking_amount' => 0.0, 'total_tax_amount' => 0.0, 'total_discount_amount' => 0.0, 'total_campaign_discount_amount' => 0.0, 'total_coupon_discount_amount' => 0.0];
        $savedIds = [];
        foreach ($priced as $i => $entry) {
            extract($entry);
            $price = $engine->calculate($service, $variation, (int) $item['quantity'], $context['zone_id'], $context['provider_id'], $context['area_id'], $start, $promotion + $coupon, $item['field_answers']);
            $line = $item['_line'] ?? $booking->detail()->make();
            $line->forceFill(['booking_id'=>$parent->id, 'service_id'=>$service->id, 'service_name'=>$service->name, 'variant_key'=>$variation->variant_key,
                'quantity'=>(int)$item['quantity'], 'service_cost'=>round($price->adjustedPrice / $item['quantity'],3),
                'discount_amount'=>$basic, 'campaign_discount_amount'=>$campaign, 'overall_coupon_discount_amount'=>$coupon,
                'tax_amount'=>$price->tax, 'total_cost'=>$price->total, 'field_answers'=>$item['field_answers'],
                'execution_snapshot'=>$requirements['items'][$i]])->save();
            $savedIds[] = $line->id;
            $links = BookingAmountLinks::forLine($parent, $booking, $line);
            // Report filters must never hide an existing amount during a write.
            $amount = BookingDetailsAmount::withoutGlobalScopes()->where($links)->lockForUpdate()->first() ?? new BookingDetailsAmount();
            $amount->forceFill($links + ['service_unit_cost'=>$line->service_cost, 'service_quantity'=>$line->quantity, 'service_tax'=>$line->tax_amount]);
            foreach (['discount'=>$basic, 'campaign_discount'=>$campaign, 'coupon_discount'=>$coupon] as $type=>$value) {
                $setting = business_config(match($type) {'discount'=>'discount_cost_bearer', 'campaign_discount'=>'campaign_cost_bearer', default=>'coupon_cost_bearer'}, 'promotional_setup')?->live_values ?? [];
                $admin = round($value * (float)($setting['admin_percentage'] ?? 100) / 100,3);
                $amount->{$type.'_by_admin'} = $admin;
                $amount->{$type.'_by_provider'} = round($value-$admin,3);
            }
            $amount->save();
            $sum['total_booking_amount'] += $price->total;
            $sum['total_tax_amount'] += $price->tax;
            $sum['total_discount_amount'] += $basic;
            $sum['total_campaign_discount_amount'] += $campaign;
            $sum['total_coupon_discount_amount'] += $coupon;
        }
        foreach ($old->whereNotIn('id',$savedIds) as $removed) {
            BookingDetailsAmount::withoutGlobalScopes()->where('booking_id', $parent->id)
                ->where($booking instanceof BookingRepeat ? 'booking_repeat_details_id' : 'booking_details_id', $removed->id)->delete();
            $removed->delete();
        }
        $basis = $sum['total_booking_amount'] - $sum['total_tax_amount'];
        $referral = (float)$booking->total_referral_discount_amount;
        $pro = (float)$booking->total_pro_discount_amount > 0
            ? min(max(0,$basis-$referral), \Modules\CustomerSubscriptionModule\Lib\ProMemberBenefit::discountFromConfig(max(0,$basis-$referral))) : 0;
        $sum['total_pro_discount_amount'] = $pro;
        $sum['total_tax_amount'] = 0;
        foreach ($booking->detail()->get() as $line) {
            $tax = discounted_tax_amount($line->tax_amount,$basis,$referral+$pro);
            $line->total_cost = round($line->total_cost-$line->tax_amount+$tax,3);
            $line->tax_amount = $tax;
            $line->save();
            BookingDetailsAmount::withoutGlobalScopes()->where('booking_id', $parent->id)
                ->where($booking instanceof BookingRepeat ? 'booking_repeat_details_id' : 'booking_details_id',$line->id)->update(['service_tax'=>$tax]);
            $sum['total_tax_amount'] += $tax;
        }
        // extra_fee already stores the net fee after the original Pro fee benefit.
        $sum['total_booking_amount'] = round($basis+$sum['total_tax_amount']+(float)$booking->extra_fee-$pro-$referral,3);
        abort_if($sum['total_booking_amount'] < 0, 409, 'booking_discounts_require_review');
        $delta = round($sum['total_booking_amount'] - (float)$booking->total_booking_amount,3);
        $paid = (bool)$booking->is_paid || !in_array($booking->payment_method,['cash','cash_after_service','offline_payment'],true);
        $refund = $paid && $delta < 0 ? max(0, -$delta - max(0,(float)$booking->additional_charge)) : 0;
        foreach (['additional_charge'=>'total_booking_amount','additional_tax_amount'=>'total_tax_amount',
            'additional_discount_amount'=>'total_discount_amount','additional_campaign_discount_amount'=>'total_campaign_discount_amount'] as $extra=>$total) {
            $booking->$extra = round((float)$booking->$extra + $sum[$total] - (float)$booking->$total,3);
        }
        $booking->additional_charge = max(0,$booking->additional_charge);
        $booking->forceFill($sum)->save();
        $booking->forceFill(['estimated_duration_minutes'=>$requirements['duration_minutes'], 'required_technicians'=>$requirements['required_technicians'], 'execution_type'=>$requirements['execution_type']])->save();
        if ($refund > 0) removeBookingServiceTransactionForDigitalPayment($booking,$refund);
        app(\App\Services\AuditService::class)->record('booking.services_amended',$booking,$before,
            ['amount'=>$booking->total_booking_amount,'lines'=>$booking->detail()->get()->toArray()],request()->user(),$booking->provider_id);
        if ($booking instanceof BookingRepeat) {
            (new \Modules\BookingModule\Entities\BookingRepeatHistory())->forceFill([
                'booking_id'=>$parent->id,'booking_repeat_id'=>$booking->id,'readable_id'=>$booking->readable_id,
                'old_quantity'=>$old->sum('quantity'),'new_quantity'=>$booking->detail()->sum('quantity'),
                'is_multiple'=>request()->boolean('next_all_booking_change') ? 1 : 0,
                'total_booking_amount'=>$booking->total_booking_amount,'total_tax_amount'=>$booking->total_tax_amount,
                'total_discount_amount'=>$booking->total_discount_amount,'extra_fee'=>$booking->extra_fee,
                'log_details'=>json_encode($booking->detail()->get()->toArray(),JSON_THROW_ON_ERROR),
            ])->save();
        }
        foreach ($requirements['service_ids'] as $serviceId) app(BookingExecutionService::class)->snapshotService($booking, $serviceId);
        DB::afterCommit(function () use ($parent) {
            $settings = business_config('booking', 'notification_settings')?->live_values ?? [];
            foreach (['user'=>$parent->customer, 'provider'=>$parent->provider?->owner, 'serviceman'=>$parent->serviceman?->user] as $role=>$user) {
                if (($settings['push_notification_booking'] ?? false) && isNotificationActive(null, 'booking', 'notification', $role) && $user?->fcm_token) device_notification($user->fcm_token, translate('booking_updated'), null, null, $parent->id, 'booking');
            }
        });
    }

    public function applyMetadata(Model $booking, \Illuminate\Http\Request $request): void
    {
        $data = $request->validate(['payment_status'=>'nullable|in:0,1', 'serviceman_id'=>'nullable|uuid',
            'booking_status'=>'nullable|in:'.implode(',',array_column(BOOKING_STATUSES,'key')), 'service_schedule'=>'nullable|date']);
        foreach (['payment_status'=>'is_paid','booking_status'=>'booking_status','service_schedule'=>'service_schedule'] as $input=>$column) {
            if (isset($data[$input])) $booking->$column = $data[$input];
        }
        if (!empty($data['serviceman_id']) && $data['serviceman_id'] !== $booking->serviceman_id) {
            $parentId = $booking instanceof BookingRepeat ? $booking->booking_id : $booking->id;
            app(BookingAssignmentService::class)->assign($parentId,$data['serviceman_id'],null,'branch_manager',$request->user()->id);
            $booking->serviceman_id = $data['serviceman_id'];
        }
        // Existing execution, accounting, cash approval and outbox observers remain authoritative.
        if ($booking->isDirty()) $booking->save();
    }

    public function mutate($request, string $action): Model
    {
        abort_unless(in_array($action, ['add', 'quantity', 'remove'], true), 422);
        $data = Validator::make($request instanceof \Illuminate\Http\Request ? $request->all() : $request, [
            'booking_id' => 'required|uuid', 'booking_repeat_id' => 'nullable|uuid',
            'service_id' => 'required|uuid', 'variant_key' => 'required|string',
            'detail_id' => ['nullable', function ($attribute, $value, $fail) {
                if (self::lineIdentifier($value) === null) $fail('invalid_booking_line_id');
            }],
            'quantity' => ($action === 'add' ? 'required' : 'sometimes').'|integer|min:1|max:1000',
            'new_quantity' => ($action === 'quantity' ? 'required' : 'sometimes').'|integer|min:0|max:1000',
            'old_quantity' => 'sometimes|integer|min:0|max:1000', 'field_answers' => 'nullable|array',
        ])->validate();
        $detailId = isset($data['detail_id']) ? self::lineIdentifier($data['detail_id']) : null;

        return DB::transaction(function () use ($data, $detailId, $action) {
            // Use the same parent-first lock order and visit ownership boundary as replaceAll.
            $parent = Booking::withoutGlobalScopes()->whereKey($data['booking_id'])->lockForUpdate()->firstOrFail();
            $booking = !empty($data['booking_repeat_id'])
                ? BookingRepeat::withoutGlobalScopes()->where('booking_id', $parent->id)->whereKey($data['booking_repeat_id'])->lockForUpdate()->firstOrFail()
                : $parent;
            if ($booking instanceof BookingRepeat) $booking->setRelation('booking', $parent);
            $this->authorize($booking);
            $items = $booking->detail()->lockForUpdate()->get()->map(fn ($line) => [
                'detail_id' => $line->id, 'service_id' => $line->service_id, 'variant_key' => $line->variant_key,
                'quantity' => (int) $line->quantity, 'field_answers' => $line->field_answers ?? [],
            ])->all();
            $matches = array_keys(array_filter($items, fn ($item) =>
                strtolower($item['service_id']) === strtolower($data['service_id']) && $item['variant_key'] === $data['variant_key']
                && ($detailId === null || self::lineIdentifier($item['detail_id']) === $detailId)));
            // Old clients may omit the ID only when the service/variant identifies exactly one line.
            abort_if(count($matches) > 1, 409, 'booking_line_id_required');
            if (!$matches) {
                abort_unless($action === 'add' && $detailId === null, 404, 'booking_line_not_owned');
                $items[] = ['service_id' => $data['service_id'], 'variant_key' => $data['variant_key'],
                    'quantity' => (int) $data['quantity'], 'field_answers' => $data['field_answers'] ?? []];
            } else {
                $index = $matches[0];
                abort_if(isset($data['old_quantity']) && (int) $data['old_quantity'] !== $items[$index]['quantity'], 409, 'booking_changed_refresh_required');
                $items[$index]['quantity'] = match ($action) {
                    'remove' => 0, 'add' => $items[$index]['quantity'] + (int) $data['quantity'],
                    default => (int) $data['new_quantity'],
                };
                if (isset($data['field_answers'])) $items[$index]['field_answers'] = $data['field_answers'];
            }
            abort_unless(array_filter($items, fn ($item) => $item['quantity'] > 0), 422, 'booking_requires_service');
            return $this->replaceAll($booking, $items);
        }, 3);
    }

    /** Compare canonical decimal BIGINT strings or UUIDs without integer/float coercion. */
    private static function lineIdentifier(mixed $value): ?string
    {
        if (is_int($value)) return $value > 0 ? (string) $value : null;
        if (!is_string($value)) return null;
        if (preg_match('/^[1-9][0-9]{0,19}$/D', $value)
            && (strlen($value) < 20 || strcmp($value, '18446744073709551615') <= 0)) return $value;
        return \Illuminate\Support\Str::isUuid($value) ? strtolower($value) : null;
    }
}
