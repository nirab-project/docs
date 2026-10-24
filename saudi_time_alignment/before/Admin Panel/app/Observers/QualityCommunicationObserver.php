<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Modules\BidModule\Entities\PostBid;
use Modules\BookingModule\Entities\Booking;
use Modules\BookingModule\Entities\BookingAssignmentHistory;
use Modules\BookingModule\Entities\PaymentCollection;
use Modules\CustomerSubscriptionModule\Services\VisitPackageService;
use Modules\SMSModule\Services\CommunicationService;
class QualityCommunicationObserver
{
    public function updating(Model $model): void
    {
        if (!$model instanceof Booking) {
            return;
        }
        if ($model->is_warranty || $model->payment_method === 'prepaid_visit') {
            abort_unless((float) $model->total_booking_amount === 0.0 && (float) $model->total_tax_amount === 0.0, 409, 'visit_observer:nonzero_booking_amount');
        }
        if ($model->getOriginal('payment_method') === 'prepaid_visit') {
            $usage = \Modules\CustomerSubscriptionModule\Entities\VisitUsage::where('booking_id', $model->id)->first();
            if ($usage) {
                // A covered visit cannot become an uncovered edited or reopened order.
                abort_if($model->isDirty(['customer_id', 'service_schedule', 'service_address_id', 'service_location',
                    'service_address_location', 'is_repeated', 'payment_method', 'quotation_id', 'pricing_snapshot']), 409, __('quality.visit_change_requires_rebooking'));
                abort_unless((float) $model->additional_charge === 0.0 && (float) $model->additional_tax_amount === 0.0, 409, 'visit_observer:additional_charge');
                abort_if($model->isDirty('is_paid') && (int) $model->is_paid !== 1, 409, 'visit_observer:paid_flag_change');
                abort_if($model->isDirty('booking_status') && !in_array($model->booking_status, ['completed', 'canceled'], true)
                    && $usage->status !== 'reserved', 409, __('quality.visit_not_eligible'));
            }
        }
        $actor = request()->user();
        if ($actor && in_array($actor->user_type, ['admin-employee', 'provider-employee'], true) && $model->isDirty(['total_booking_amount', 'total_tax_amount', 'pricing_snapshot', 'additional_charge'])) {
            app(\App\Services\OperationalAccess::class)->authorizeBranch($actor, 'pricing.manage', $model->provider_id);
        }
    }
    public function saved(Model $model): void
    {
        if (!config('nirab.branch_mode')) {
            return;
        }
        $comms = app(CommunicationService::class);
        if ($model instanceof Booking) {
            if ($model->wasRecentlyCreated || $model->wasChanged('booking_status')) {
                $event = $model->wasRecentlyCreated ? 'booking_confirmation' : $model->booking_status;
                $key = 'booking:' . $model->id . ':' . $event . ':' . ($model->updated_at?->format('U.u') ?? 'created');
                $comms->bookingEvent($model, $event, $key);
                if ($model->wasRecentlyCreated) {
                    $comms->whatsapp($model, 'booking_confirmation', 'confirmation:' . $model->id, [$model->readable_id, $model->service_schedule?->toIso8601String()]);
                }
                app(VisitPackageService::class)->settle($model);
            }
            if (!$model->wasRecentlyCreated && $model->wasChanged('service_schedule')) {
                $comms->bookingEvent($model, 'schedule_changed', 'schedule:' . $model->id . ':' . $model->service_schedule->format('U'));
            }
            if (!$model->wasRecentlyCreated && $model->wasChanged('serviceman_id') && $model->serviceman_id && !$model->assignments()->exists()) {
                $comms->bookingEvent($model, $model->getOriginal('serviceman_id') ? 'reassigned' : 'assigned', 'legacy-assignment:' . $model->id . ':' . $model->updated_at?->format('U.u'));
            }
        } elseif ($model instanceof \Modules\BookingModule\Entities\BookingRepeat && ($model->wasRecentlyCreated || $model->wasChanged('booking_status'))) {
            $booking = Booking::withoutGlobalScopes()->find($model->booking_id);
            if ($booking) {
                $comms->bookingEvent($booking, $model->booking_status, 'repeat:' . $model->id . ':' . $model->booking_status . ':' . $model->updated_at?->format('U.u'));
            }
        } elseif ($model instanceof BookingAssignmentHistory && $model->wasRecentlyCreated) {
            $booking = Booking::withoutGlobalScopes()->find($model->booking_id);
            if ($booking) {
                $comms->bookingEvent($booking, match ($model->event) {
                    'offered' => $booking->assignment_attempts > 1 ? 'reassigned' : 'assigned',
                    'accepted' => 'technician_accepted',
                    'rejected' => 'technician_rejected',
                    default => 'assigned',
                }, 'assignment:' . $model->id);
            }
            if ($model->event === 'rejected') {
                $technician = \Modules\UserManagement\Entities\Serviceman::withoutGlobalScopes()->find($model->serviceman_id)?->user;
                if ($technician) {
                    $comms->record($technician, 'technician_rejected', 'assignment:' . $model->id, $model->booking_id);
                }
            }
        } elseif ($model instanceof PaymentCollection && $model->wasChanged('status')) {
            $booking = Booking::withoutGlobalScopes()->find($model->booking_id);
            if ($booking && in_array($model->status, ['ready_for_accounting_sync', 'branch_rejected', 'accountant_rejected'], true)) {
                $comms->bookingEvent($booking, str_ends_with($model->status, 'rejected') ? 'payment_rejected' : 'payment_approved', 'collection:' . $model->id . ':' . $model->status);
            }
        } elseif ($model instanceof PostBid && ($model->wasRecentlyCreated || $model->wasChanged('status'))) {
            $customer = $model->post?->customer;
            if ($customer && in_array($model->status, ['ready', 'issued', 'sent'], true)) {
                $comms->record($customer, 'quote_ready', 'quote:' . $model->id . ':' . $model->revision, $model->booking_id, ['post_id' => $model->post_id]);
            }
        }
    }
}
