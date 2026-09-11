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

    public static function serviceKey(string $name): string
    {
        return substr(mb_strtolower(preg_replace('/\s+/', ' ', trim($name))), 0, 255);
    }

    protected static function booted(): void
    {
        static::saving(function (PriceBookItem $item) {
            if ($item->isDirty('service_name') || empty($item->service_key)) {
                $item->service_key = self::serviceKey($item->service_name);
            }
        });
    }
}
