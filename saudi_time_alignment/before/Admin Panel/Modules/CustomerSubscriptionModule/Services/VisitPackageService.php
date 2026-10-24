<?php

namespace Modules\CustomerSubscriptionModule\Services;

use Illuminate\Support\Facades\DB;
use Modules\BookingModule\Entities\Booking;
use Modules\BookingModule\Services\BookingSchedule;
use Modules\CustomerSubscriptionModule\Entities\VisitPackage;
use Modules\CustomerSubscriptionModule\Entities\VisitPlan;
use Modules\CustomerSubscriptionModule\Entities\VisitUsage;
use Modules\TransactionModule\Entities\Transaction;
use Modules\UserManagement\Entities\User;
class VisitPackageService
{
    public function purchase(User $actor, string $planId, string $key, bool $autoRenew = false): VisitPackage
    {
        return DB::transaction(function () use ($actor, $planId, $key, $autoRenew): VisitPackage {
            $customer = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($customer->user_type === 'customer', 403);
            $reference = 'visit-wallet:' . $customer->id . ':' . $key;
            if ($existing = VisitPackage::where('payment_reference', $reference)->first()) {
                abort_unless($existing->plan_id === $planId, 409);
                return $existing;
            }
            $plan = VisitPlan::whereKey($planId)->where('active', true)->firstOrFail();
            abort_if($autoRenew && $plan->cadence === 'prepaid', 422, 'visit_auto_renew_unavailable');
            abort_unless(\Modules\ServiceManagement\Entities\Service::withoutGlobalScopes()->whereNull('deleted_at')->whereKey($plan->service_id)->where('is_active', 1)->where('pricing_type', 'fixed')->exists(), 409, __('quality.visit_not_eligible'));
            abort_unless((float) $customer->wallet_balance >= (float) $plan->price, 422, __('quality.insufficient_wallet'));
            $customer->wallet_balance = round((float) $customer->wallet_balance - (float) $plan->price, 3);
            $customer->save();
            Transaction::create(['trx_type' => TRX_TYPE['customer_subscription_purchase'], 'debit' => $plan->price, 'credit' => 0, 'balance' => $customer->wallet_balance, 'from_user_id' => $customer->id, 'to_user_id' => $customer->id, 'from_user_account' => null, 'to_user_account' => 'user_wallet', 'wallet_reference' => $reference, 'reference_note' => __('quality.visit_package')]);
            $company = User::where('user_type', 'super-admin')->firstOrFail();
            $account = \Modules\TransactionModule\Entities\Account::where('user_id', $company->id)->lockForUpdate()->firstOrFail();
            $account->received_balance = (float) $account->received_balance + (float) $plan->price;
            $account->save();
            Transaction::create(['trx_type' => TRX_TYPE['customer_subscription_purchase'], 'debit' => 0, 'credit' => $plan->price, 'balance' => $account->received_balance, 'from_user_id' => $customer->id, 'to_user_id' => $company->id, 'from_user_account' => null, 'to_user_account' => ACCOUNT_STATES[1]['value'], 'reference_note' => $reference]);
            $package = VisitPackage::create(['customer_id' => $customer->id, 'plan_id' => $plan->id, 'payment_reference' => $reference, 'price' => $plan->price, 'currency' => $plan->currency, 'total_visits' => $plan->visits, 'remaining_visits' => $plan->visits, 'auto_renew' => $autoRenew && $plan->cadence !== 'prepaid', 'expires_at' => now()->addDays($plan->validity_days), 'renewal_at' => $plan->cadence === 'prepaid' ? null : now()->addDays($plan->validity_days), 'pricing_snapshot' => $plan->only(['name', 'cadence', 'visits', 'price', 'currency', 'service_id', 'pricing_policy'])]);
            $this->ledger($package, null, 'purchase:' . $package->id, 'purchase', $plan->visits);
            return $package;
        }, 3);
    }
    public function bookingContext(User $actor, string $packageId): array
    {
        abort_unless($actor->user_type === 'customer', 403);
        $package = VisitPackage::where('customer_id', $actor->id)->findOrFail($packageId);
        $service = \Modules\ServiceManagement\Entities\Service::withoutGlobalScopes()
            ->whereNull('deleted_at')->find($package->pricing_snapshot['service_id'] ?? '');
        $eligible = $package->status === 'active' && $package->remaining_visits > 0
            && $package->expires_at->isFuture() && $service?->is_active && $service?->pricing_type === 'fixed';
        return ['package' => $package, 'eligible' => (bool) $eligible,
            'service' => $service?->load('activeFields'),
            'variations' => $service?->variations()->where('price', '>', 0)->get(),
            'max_units_per_visit' => (int) ($package->pricing_snapshot['pricing_policy']['max_units_per_visit'] ?? 1),
            'minimum_interval_days' => (int) ($package->pricing_snapshot['pricing_policy']['minimum_interval_days'] ?? 0),
            'payment_method' => 'prepaid_visit', 'amount_due' => 0];
    }

    public function previewBooking(User $actor, string $packageId, array $input): array
    {
        $context = $this->bookingContext($actor, $packageId);
        abort_unless($context['eligible'], 409, __('quality.visit_not_eligible'));
        [$service, $address, $branch, $schedule, $variation, $execution] = $this->resolveVisit($actor, $context['package'], $input);
        $this->assertPackageSchedule($context['package'], $schedule, (int) $input['quantity']);
        return ['package_id' => $packageId, 'service_id' => $service->id, 'service_name' => $service->name,
            'variant_key' => $variation->variant_key, 'quantity' => (int) $input['quantity'],
            'service_address_id' => $address->id, 'address' => $address->address,
            'provider_id' => $branch->id, 'branch_name' => $branch->company_name,
            'service_schedule' => $schedule->format('Y-m-d H:i:s'), 'timezone' => config('app.timezone'),
            'remaining_before' => $context['package']->remaining_visits,
            'remaining_after' => $context['package']->remaining_visits - 1,
            'visits_required' => 1, 'amount_due' => 0, 'currency' => $context['package']->currency,
            'payment_method' => 'prepaid_visit'];
    }

    public function book(User $actor, string $packageId, array $input): Booking
    {
        abort_unless($actor->user_type === 'customer' && nirab_branch_mode(), 403);
        // Whitelist and order the request before hashing. No client price or paid flag is accepted.
        $payload = ['package_id' => $packageId, 'service_address_id' => (string) $input['service_address_id'],
            'variant_key' => $input['variant_key'], 'quantity' => (int) $input['quantity'], 'field_answers' => $input['field_answers'] ?? [],
            'service_schedule' => BookingSchedule::forStorage($input['service_schedule']),
            'provider_id' => $input['provider_id']];
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        return DB::transaction(function () use ($actor, $packageId, $input, $hash): Booking {
            // Serialize attempts from this customer. Existing reserve/settle keep booking -> package -> usage.
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $prior = DB::table('customer_visit_booking_requests')->where('customer_id', $actor->id)
                ->where('idempotency_key', $input['idempotency_key'])->lockForUpdate()->first();
            if ($prior) {
                abort_unless(hash_equals($prior->request_hash, $hash), 409, __('quality.visit_request_changed'));
                return Booking::withoutGlobalScopes()->where('customer_id', $actor->id)->findOrFail($prior->booking_id);
            }
            $package = VisitPackage::where('customer_id', $actor->id)->findOrFail($packageId);
            [$service, $address, $branch, $schedule, $variation, $execution] = $this->resolveVisit($actor, $package, $input, true);
            $booking = new Booking();
            $booking->forceFill([
                'customer_id' => $actor->id, 'provider_id' => $branch->id, 'zone_id' => $address->zone_id,
                'booking_status' => 'pending', 'assignment_status' => 'pending', 'is_repeated' => 0, 'is_guest' => 0,
                'payment_method' => 'prepaid_visit', 'is_paid' => 0, 'transaction_id' => null,
                'total_booking_amount' => 0, 'total_tax_amount' => 0, 'total_discount_amount' => 0,
                'total_campaign_discount_amount' => 0, 'total_coupon_discount_amount' => 0,
                'service_schedule' => $schedule, 'service_address_id' => $address->id,
                'service_address_location' => json_encode($address->getAttributes()), 'service_location' => 'customer',
                'booking_otp' => random_int(100000, 999999), 'execution_type' => $execution['execution_type'],
                'required_technicians' => $execution['required_technicians'],
                'estimated_duration_minutes' => $execution['duration_minutes'],
                'assignment_due_at' => now()->addMinutes(config('nirab_dispatch.assignment_delay_minutes')),
                'pricing_snapshot' => ['source' => 'visit_package', 'package_id' => $package->id, 'currency' => $package->currency],
            ])->save();
            $detail = new \Modules\BookingModule\Entities\BookingDetail();
            $detail->forceFill(['field_answers' => $input['field_answers'] ?? [], 'execution_snapshot' => $execution,'booking_id' => $booking->id, 'service_id' => $service->id, 'service_name' => $service->name,
                'variant_key' => $variation->variant_key, 'quantity' => (int) $input['quantity'],
                'service_cost' => 0, 'discount_amount' => 0, 'campaign_discount_amount' => 0,
                'overall_coupon_discount_amount' => 0, 'tax_amount' => 0, 'total_cost' => 0])->save();
            $amount = new \Modules\BookingModule\Entities\BookingDetailsAmount();
            $amount->forceFill(['booking_id' => $booking->id, 'booking_details_id' => $detail->id,
                'service_unit_cost' => 0, 'service_quantity' => (int) $input['quantity'], 'service_tax' => 0,
                'discount_by_admin' => 0, 'discount_by_provider' => 0, 'campaign_discount_by_admin' => 0,
                'campaign_discount_by_provider' => 0, 'coupon_discount_by_admin' => 0, 'coupon_discount_by_provider' => 0,
                'admin_commission' => 0])->save();
            // Same engine and ledger as legacy reservation; any failure rolls back the new booking and all events.
            $this->reserve($actor, $packageId, $booking->id, nativeBooking: true);
            \Modules\BookingModule\Entities\BookingStatusHistory::create(['booking_id' => $booking->id,
                'changed_by' => $actor->id, 'booking_status' => 'pending']);
            $history = new \Modules\BookingModule\Entities\BookingScheduleHistory();
            $history->forceFill(['booking_id' => $booking->id, 'changed_by' => $actor->id, 'schedule' => $schedule])->save();
            DB::table('customer_visit_booking_requests')->insert(['customer_id' => $actor->id,
                'idempotency_key' => $input['idempotency_key'], 'request_hash' => $hash,
                'package_id' => $packageId, 'booking_id' => $booking->id, 'created_at' => now()]);
            return $booking->fresh();
        }, 3);
    }

    private function assertPackageSchedule(VisitPackage $package, \Carbon\CarbonInterface $schedule, int $quantity): void
    {
        abort_unless($package->status === 'active' && $package->remaining_visits > 0
            && $package->expires_at->isFuture() && $package->expires_at->gte($schedule), 409, __('quality.visit_not_eligible'));
        $policy = $package->pricing_snapshot['pricing_policy'] ?? [];
        abort_unless($quantity >= 1 && $quantity <= (int) ($policy['max_units_per_visit'] ?? 1), 422, __('quality.visit_units_exceeded'));
        $interval = (int) ($policy['minimum_interval_days'] ?? 0);
        if ($interval > 0) {
            $conflict = DB::table('customer_visit_usage AS usage')->join('bookings', 'bookings.id', '=', 'usage.booking_id')
                ->where('usage.package_id', $package->id)->whereIn('usage.status', ['reserved', 'consumed'])
                ->whereBetween('bookings.service_schedule', [$schedule->copy()->subDays($interval)->addSecond(), $schedule->copy()->addDays($interval)->subSecond()])->exists();
            abort_if($conflict, 409, __('quality.visit_interval_conflict'));
        }
    }

    private function resolveVisit(User $actor, VisitPackage $package, array $input, bool $lock = false): array
    {
        $service = \Modules\ServiceManagement\Entities\Service::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('is_active', 1)->where('pricing_type', 'fixed')->findOrFail($package->pricing_snapshot['service_id'] ?? '');
        $address = \Modules\UserManagement\Entities\UserAddress::where('user_id', $actor->id)->findOrFail($input['service_address_id']);
        \Illuminate\Support\Facades\Validator::make($address->getAttributes(), [
            'lat' => 'required|numeric|between:-90,90', 'lon' => 'required|numeric|between:-180,180',
            'address' => 'required|string', 'contact_person_name' => 'required|string',
            'contact_person_number' => 'required|string',
        ])->validate();
        $zone = app(\App\Services\GeoService::class)->containing(\Modules\ZoneManagement\Entities\Zone::query(), 'coordinates', $address->lat, $address->lon)
            ->ofStatus(1)->orderBy('id')->value('id');
        abort_unless($zone && $zone === $address->zone_id, 422, 'service_address_zone_mismatch');
        $variation = app(\Modules\ServiceManagement\Services\ServicePriceResolver::class)
            ->resolve($service->id, $input['variant_key'], $zone);
        // Parse exactly like regular checkout. Offset-free values are app-timezone wall time;
        // explicit offsets retain the instant returned by the availability API.
        $schedule = BookingSchedule::parse($input['service_schedule']);
        $branches = app(\Modules\ProviderManagement\Services\BranchAssignmentService::class);
        $execution = app(\Modules\ServiceManagement\Services\ServiceItemRequirements::class)->evaluate([['service_id'=>$service->id,'variant_key'=>$variation->variant_key,'quantity'=>(int)$input['quantity'],'field_answers'=>$input['field_answers'] ?? []]],$zone)['items'][0];
        $included = $package->pricing_snapshot['pricing_policy']['included_option_values'] ?? [];
        foreach ($execution['field_answers'] as $answer) foreach ($answer['selected_options'] as $option) {
            abort_if((float)$option['price_adjustment'] > 0 && !in_array($option['value'],$included[$answer['field_id']] ?? [],true),422,'visit_option_not_in_package');
        }

        $duration = $execution['duration_minutes'];
        $available = function ($candidate) use ($zone, $service, $address, $schedule, $duration, $branches, $execution): bool {
            $area = app(\Modules\ProviderManagement\Services\BranchServiceAreaRepository::class)->resolveAreaId($candidate->id, $zone, $address->lat, $address->lon);
            return app(\Modules\ServiceManagement\Services\BranchServiceAvailabilityService::class)->isAvailable($candidate->id, $service->id, $area)
                && app(\Modules\ProviderManagement\Services\BranchScheduleService::class)->contains($candidate, $schedule, $duration)
                && $branches->isEligible($candidate, $zone, [$service->id], $schedule->copy()->addMinutes($duration), $address->lat, $address->lon)
                && app(\Modules\BookingModule\Services\AvailabilityService::class)->canFulfill($candidate->id, [$service->id], $schedule, $duration,
                    $execution['required_technicians'], $execution['execution_type']);
        };
        if ($lock) {
            \Modules\ProviderManagement\Entities\Provider::withoutGlobalScopes()->whereKey($input['provider_id'])->lockForUpdate()->firstOrFail();
        }
        $branch = $branches->resolve($zone, [$service->id], $schedule, $address->lat, $address->lon,
            $input['provider_id'], $available, true);
        abort_unless($branch, 409, __('quality.visit_slot_unavailable'));
        return [$service, $address, $branch, $schedule, $variation, $execution];
    }

    public function reserve(User $actor, string $packageId, string $bookingId, bool $nativeBooking = false): VisitUsage
    {
        return DB::transaction(function () use ($actor, $packageId, $bookingId, $nativeBooking): VisitUsage {
            $booking = Booking::withoutGlobalScopes()->whereKey($bookingId)->where('customer_id', $actor->id)->lockForUpdate()->firstOrFail();
            $package = VisitPackage::whereKey($packageId)->where('customer_id', $actor->id)->lockForUpdate()->firstOrFail();
            $existing = VisitUsage::where('booking_id', $booking->id)->first();
            if ($existing && $existing->status !== 'released') {
                abort_unless($existing->package_id === $package->id, 409);
                return $existing;
            }
            abort_unless(!$booking->quotation_id && !$booking->is_repeated
                && \Modules\ServiceManagement\Entities\Service::withoutGlobalScopes()->whereNull('deleted_at')
                    ->whereKey($package->pricing_snapshot['service_id'])->where('is_active', 1)->where('pricing_type', 'fixed')->exists(),
                409, __('quality.visit_not_eligible'));
            // Each refusal gets its own stable reason code. Keep the same safety checks
            // and ordering as the original combined guard; do not weaken eligibility.
            abort_unless(!$existing, 409, 'visit_reserve:existing_usage');
            abort_unless($actor->user_type === 'customer', 409, 'visit_reserve:customer_type');
            abort_unless($package->status === 'active', 409, 'visit_reserve:package_inactive');
            abort_unless($package->remaining_visits > 0, 409, 'visit_reserve:no_visits_remaining');
            abort_unless($package->expires_at->gte($booking->service_schedule), 409, 'visit_reserve:expires_before_schedule');
            abort_unless($package->expires_at->isFuture(), 409, 'visit_reserve:package_expired');
            abort_unless(!$booking->is_warranty, 409, 'visit_reserve:warranty_booking');
            abort_unless(!$booking->is_paid, 409, 'visit_reserve:booking_already_paid');
            abort_unless($booking->booking_status === 'pending', 409, 'visit_reserve:booking_not_pending');
            abort_unless(!$booking->repeat()->exists(), 409, 'visit_reserve:repeat_present');
            abort_unless($nativeBooking ? $booking->payment_method === 'prepaid_visit' : in_array($booking->payment_method, ['cash_after_service', 'cash'], true), 409, 'visit_reserve:payment_method');
            abort_unless(!$booking->transaction_id, 409, 'visit_reserve:transaction_present');
            abort_unless(!DB::table('payment_requests')->where('finance_booking_id', $booking->id)->whereNotIn('gateway_state', ['failed', 'canceled', 'expired'])->exists(), 409, 'visit_reserve:active_payment_request');
            abort_unless($booking->assignment_status === 'pending', 409, 'visit_reserve:assignment_status');
            abort_unless(!$booking->booking_partial_payments()->exists(), 409, 'visit_reserve:partial_payment_present');
            abort_unless(!$booking->booking_offline_payments()->exists(), 409, 'visit_reserve:offline_payment_present');
            abort_unless(!$booking->detail()->where('service_id', '!=', $package->pricing_snapshot['service_id'])->exists(), 409, 'visit_reserve:wrong_service_detail');
            abort_unless($booking->detail()->exists(), 409, 'visit_reserve:missing_service_detail');
            $policy = $package->pricing_snapshot['pricing_policy'];
            abort_unless((int) $booking->detail()->sum('quantity') <= (int) ($policy['max_units_per_visit'] ?? 1), 422);
            $interval = (int) ($policy['minimum_interval_days'] ?? 0);
            if ($interval > 0) {
                $conflict = DB::table('customer_visit_usage AS usage')->join('bookings', 'bookings.id', '=', 'usage.booking_id')->where('usage.package_id', $package->id)->whereIn('usage.status', ['reserved', 'consumed'])->whereBetween('bookings.service_schedule', [$booking->service_schedule->copy()->subDays($interval)->addSecond(), $booking->service_schedule->copy()->addDays($interval)->subSecond()])->exists();
                abort_if($conflict, 409, __('quality.visit_interval_conflict'));
            }
            // Preserve original service pricing as a snapshot; no second payment or revenue.
            $snapshot = ['type' => 'prepaid_visit', 'package_id' => $package->id, 'original' => $booking->pricing_snapshot, 'original_amount' => $booking->total_booking_amount, 'policy' => $policy];
            foreach (array_keys($booking->getAttributes()) as $column) {
                if (preg_match('/amount|charge|fee|deduction/', $column)) {
                    $booking->setAttribute($column, 0);
                }
            }
            $booking->forceFill(['payment_method' => 'prepaid_visit', 'is_paid' => 1, 'transaction_id' => null, 'pricing_snapshot' => $snapshot])->save();
            foreach ($booking->detail()->get() as $line) {
                foreach (array_keys($line->getAttributes()) as $column) {
                    if (preg_match('/cost|amount/', $column)) {
                        $line->setAttribute($column, 0);
                    }
                }
                $line->save();
            }
            foreach ($booking->details_amounts()->get() as $amount) {
                foreach (array_keys($amount->getAttributes()) as $column) {
                    if (preg_match('/cost|tax|discount|commission|earning/', $column)) {
                        $amount->setAttribute($column, 0);
                    }
                }
                $amount->save();
            }
            $usage = VisitUsage::create(['package_id' => $package->id, 'booking_id' => $booking->id]);
            $package->decrement('remaining_visits');
            $package->refresh();
            $this->ledger($package, $booking->id, 'reserve:' . $booking->id, 'reserve', -1);
            return $usage;
        }, 3);
    }
    public function settle(Booking $booking): void
    {
        if (!in_array($booking->booking_status, ['completed', 'canceled'], true)) {
            return;
        }
        DB::transaction(function () use ($booking): void {
            // Booking -> package -> usage is the lock order for both reserve and settle.
            $booking = Booking::withoutGlobalScopes()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if (!in_array($booking->booking_status, ['completed', 'canceled'], true)) return;
            $usage = VisitUsage::where('booking_id', $booking->id)->first();
            if (!$usage) {
                return;
            }
            $package = VisitPackage::whereKey($usage->package_id)->lockForUpdate()->firstOrFail();
            $usage = VisitUsage::whereKey($usage->id)->lockForUpdate()->firstOrFail();
            if ($usage->status !== 'reserved') {
                return;
            }
            if ($booking->booking_status === 'canceled' && !$booking->work_started_at && !$booking->work_finished_at) {
                $package->remaining_visits = min($package->total_visits, $package->remaining_visits + 1);
                $package->save();
                $usage->forceFill(['status' => 'released', 'released_at' => now()])->save();
                $this->ledger($package, $booking->id, 'release:' . $booking->id, 'release', 1);
            } else {
                $usage->forceFill(['status' => 'consumed', 'consumed_at' => now()])->save();
                $this->ledger($package, $booking->id, 'consume:' . $booking->id, 'consume', 0);
            }
        }, 3);
    }
    private function ledger(VisitPackage $package, ?string $bookingId, string $key, string $type, int $delta): void
    {
        DB::table('customer_visit_ledger')->insertOrIgnore(['package_id' => $package->id, 'booking_id' => $bookingId, 'event_key' => $key, 'type' => $type, 'delta' => $delta, 'balance' => $package->remaining_visits, 'created_at' => now()]);
    }
}
