<?php

namespace Modules\BidModule\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\BookingModule\Entities\Booking;
use Modules\ServiceManagement\Entities\Service;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Entities\UserAddress;
use Modules\ProviderManagement\Entities\Provider;

class Post extends Model
{
    use HasFactory, HasUuid;

    protected $guarded = [];

    protected $hidden = ['category_id', 'sub_category_id'];

    protected $casts = [
        'is_booked' => 'boolean',
        'booking_schedule' => 'datetime',
        'requested_window_start' => 'datetime',
        'requested_window_end' => 'datetime',
        'location_snapshot' => 'array',
    ];

    protected static function newFactory()
    {
        return \Modules\BidModule\Database\factories\PostFactory::new();
    }

    /** Relations */




    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function bids(): HasMany
    {
        return $this->hasMany(PostBid::class, 'post_id', 'id');
    }

    public function quotations(): HasMany
    {
        return $this->bids()->orderByDesc('revision');
    }

    public function activeQuotation(): HasOne
    {
        return $this->hasOne(PostBid::class, 'post_id')->ofMany('revision', 'max', fn ($query) => $query->whereIn('status', ['ready', 'accepted']));
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'branch_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(QuoteRequestAttachment::class);
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(ServiceFieldValue::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(QuoteInspection::class);
    }

    public function addition_instructions(): HasMany
    {
        return $this->hasMany(PostAdditionalInstruction::class, 'post_id', 'id');
    }

    public function postDeleteNote(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PostAdditionalInformation::class, 'post_id')->where('key', 'post_delete_note');
    }

    public function ignored_posts(): HasMany
    {
        return $this->hasMany(IgnoredPost::class, 'post_id', 'id');
    }

    public function service_address(): BelongsTo
    {
        return $this->belongsTo(UserAddress::class, 'service_address_id');
    }
}
