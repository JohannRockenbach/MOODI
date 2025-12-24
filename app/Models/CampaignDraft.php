<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignDraft extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'subject',
        'body',
        'product_id',
        'discount_type',
        'discount_value',
        'coupon_code',
        'valid_until',
    ];

    protected $casts = [
        'valid_until' => 'date',
        'discount_value' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
