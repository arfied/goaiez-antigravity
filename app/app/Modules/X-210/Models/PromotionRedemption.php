<?php

declare(strict_types=1);

namespace App\Modules\X210\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class PromotionRedemption extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'promotion_redemptions';

    protected $guarded = [];

    protected $casts = [
        'discount_applied_cents' => 'integer',
        'redeemed_at' => 'datetime',
    ];
}
