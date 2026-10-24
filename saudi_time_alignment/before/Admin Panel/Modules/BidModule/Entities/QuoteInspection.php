<?php

namespace Modules\BidModule\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteInspection extends Model
{
    use HasUuid;

    protected $guarded = [];
    protected $casts = [
        'amount' => 'float',
        'scheduled_at' => 'datetime',
        'paid_at' => 'datetime',
        'completed_at' => 'datetime',
        'deducted_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(PostBid::class, 'post_bid_id');
    }
}
