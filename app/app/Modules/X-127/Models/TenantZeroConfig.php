<?php

declare(strict_types=1);

namespace App\Modules\X127\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class TenantZeroConfig extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'tenant_zero_config';

    protected $guarded = [];

    protected $casts = [
        'is_tenant_zero' => 'boolean',
        'public_proof_enabled' => 'boolean',
    ];
}
