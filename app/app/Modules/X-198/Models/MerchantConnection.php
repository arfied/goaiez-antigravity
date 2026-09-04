<?php

declare(strict_types=1);

namespace App\Modules\X198\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class MerchantConnection extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'merchant_connections';

    protected $guarded = [];

    protected $casts = [
        'is_connected' => 'boolean',
    ];
}
