<?php

declare(strict_types=1);

namespace App\Modules\X131\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class PersonInterest extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'person_interests';

    protected $guarded = [];

    protected $casts = [
        'confidence_rate' => 'float',
        'is_tenant_set' => 'boolean',
    ];
}
