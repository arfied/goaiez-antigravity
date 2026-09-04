<?php

declare(strict_types=1);

namespace App\Modules\X205\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class AffiliateAttribution extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'affiliate_attributions';

    protected $guarded = [];

    protected $casts = [
        'sale_amount_cents' => 'integer',
        'commission_cents' => 'integer',
        'attributed_at' => 'datetime',
        'is_clawed_back' => 'boolean',
    ];
}
