<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\BidModule\Entities\PostBid;
use Modules\BookingModule\Entities\Booking;
use Modules\CustomerSubscriptionModule\Entities\VisitPackage;
use Modules\CustomerSubscriptionModule\Services\VisitPackageService;
use Modules\SMSModule\Services\CommunicationService;
use Modules\UserManagement\Entities\User;
class QualityReminders extends Command
{
    protected $signature = 'nirab:quality-reminders';
    protected $description = 'Record appointment, quotation and package renewal reminders';
    public function handle(CommunicationService $communications): int
    {
        Booking::withoutGlobalScopes()->with(['customer', 'provider.owner'])->where('is_repeated', 0)->whereNotIn('booking_status', ['completed', 'canceled'])->whereBetween('service_schedule', [now(), now()->addDay()])->chunkById(100, function ($bookings) use ($communications): void {
            foreach ($bookings as $booking) {
                $key = 'appointment:' . $booking->id . ':' . $booking->service_schedule->format('U');
                if ($booking->customer) {
                    $communications->record($booking->customer, 'appointment_reminder', $key, $booking->id);
                }
                $communications->whatsapp($booking, 'appointment_reminder', $key, [$booking->readable_id, $booking->service_schedule->toIso8601String()]);
            }
        });
        \Modules\BookingModule\Entities\BookingRepeat::withoutGlobalScopes()->with(['booking.customer'])->whereNotIn('booking_status', ['completed', 'canceled'])->whereBetween('service_schedule', [now(), now()->addDay()])->chunkById(100, function ($visits) use ($communications): void {
            foreach ($visits as $visit) {
                $booking = $visit->booking;
                if (!$booking?->customer) {
                    continue;
                }
                $key = 'appointment:' . $visit->id . ':' . $visit->service_schedule->format('U');
                $communications->record($booking->customer, 'appointment_reminder', $key, $booking->id);
                $communications->whatsapp($booking, 'appointment_reminder', $key, [$booking->readable_id, $visit->service_schedule->toIso8601String()]);
            }
        });
        PostBid::with('post.customer')->where('status', 'ready')->whereBetween('expires_at', [now(), now()->addDay()])->chunkById(100, function ($quotes) use ($communications): void {
            foreach ($quotes as $quote) {
                if ($quote->post?->customer) {
                    $communications->record($quote->post->customer, 'quote_expiring', 'quote-expiring:' . $quote->id . ':' . $quote->revision, $quote->booking_id);
                }
            }
        });
        VisitPackage::where('status', 'active')->whereNotNull('renewal_at')->where('renewal_at', '<=', now()->addDays(3))->chunkById(100, function ($packages) use ($communications): void {
            foreach ($packages as $package) {
                $customer = User::find($package->customer_id);
                if (!$customer) {
                    continue;
                }
                DB::transaction(function () use ($package, $customer, $communications): void {
                    $package = VisitPackage::whereKey($package->id)->lockForUpdate()->firstOrFail();
                    if (!$package->reminded_at) {
                        $communications->record($customer, 'package_renewal_reminder', 'package-renewal:' . $package->id);
                        $package->update(['reminded_at' => now()]);
                    }
                });
                // A full reminder day must elapse before a wallet auto-renewal charge.
                if ($package->auto_renew && $package->renewal_at->isPast() && $package->fresh()->reminded_at?->lte(now()->subDay())) {
                    $this->renew($package, $customer);
                }
            }
        });
        return self::SUCCESS;
    }
    private function renew(VisitPackage $package, User $customer): void
    {
        try {
            DB::transaction(function () use ($package, $customer): void {
                User::whereKey($customer->id)->lockForUpdate()->firstOrFail();
                $package = VisitPackage::whereKey($package->id)->lockForUpdate()->firstOrFail();
                if (!$package->auto_renew || $package->status !== 'active' || $package->renewal_at->isFuture()) {
                    return;
                }
                // Declining a changed renewal price requires a new explicit purchase.
                $plan = $package->plan()->where('active', true)->firstOrFail();
                abort_unless((float) $plan->price === (float) $package->pricing_snapshot['price'], 409);
                app(VisitPackageService::class)->purchase($customer, $plan->id, 'renewal-' . $package->id, true);
                $package->update(['auto_renew' => false, 'status' => 'expired']);
            }, 3);
        } catch (\Throwable $error) {
            app(CommunicationService::class)->record($customer, 'package_renewal_failed', 'renewal-failed:' . $package->id);
        }
    }
}
