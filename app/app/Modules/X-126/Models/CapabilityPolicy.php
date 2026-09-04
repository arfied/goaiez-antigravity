<?php

declare(strict_types=1);

namespace App\Modules\X126\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class CapabilityPolicy extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'capability_policies';

    protected $guarded = [];

    protected $casts = [
        'rules' => 'array',
        'is_active' => 'boolean',
    ];
}
