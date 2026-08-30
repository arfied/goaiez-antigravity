<?php

declare(strict_types=1);

namespace App\Modules\X127\Models;

use Illuminate\Database\Eloquent\Model;

class TenantZeroConfig extends Model
{
    protected $table = 'tenant_zero_config';

    protected $guarded = [];

    protected $casts = [
        'is_tenant_zero' => 'boolean',
        'public_proof_enabled' => 'boolean',
    ];
}
