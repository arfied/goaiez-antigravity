<?php

declare(strict_types=1);

namespace App\Modules\X210\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Promotion extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'promotions';

    protected $guarded = [];

    protected $casts = [
        'discount_value' => 'integer',
        'max_redemptions' => 'integer',
        'redemptions_count' => 'integer',
        'velocity_threshold_per_hour' => 'integer',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function scopes()
    {
        return $this->hasMany(PromotionScope::class, 'promotion_id');
    }

    public function redemptions()
    {
        return $this->hasMany(PromotionRedemption::class, 'promotion_id');
    }
}
