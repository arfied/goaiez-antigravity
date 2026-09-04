<?php

declare(strict_types=1);

namespace App\Modules\X195\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class MarketItem extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'market_items';

    protected $guarded = [];

    protected $casts = [
        'manifest_json' => 'array',
        'install_count' => 'integer',
        'is_verified' => 'boolean',
    ];
}
