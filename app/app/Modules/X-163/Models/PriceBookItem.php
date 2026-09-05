<?php

declare(strict_types=1);

namespace App\Modules\X163\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class PriceBookItem extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'price_book_items';

    protected $guarded = [];

    protected $casts = [
        'price_cents' => 'integer',
        'price_min_cents' => 'integer',
        'price_max_cents' => 'integer',
        'is_sample' => 'boolean',
        'is_confirmed' => 'boolean',
        'tax_rate_pct' => 'float',
        'refusal_flagged_at' => 'datetime',
        'refusal_count' => 'integer',
        'confirmed_at' => 'immutable_datetime',
    ];
}
