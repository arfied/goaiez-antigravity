<?php

declare(strict_types=1);

namespace App\Modules\X210\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionRedemption extends Model
{
    protected $table = 'promotion_redemptions';

    protected $guarded = [];

    protected $casts = [
        'discount_applied_cents' => 'integer',
        'redeemed_at' => 'datetime',
    ];
}
