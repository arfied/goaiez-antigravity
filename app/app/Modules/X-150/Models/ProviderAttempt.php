<?php

declare(strict_types=1);

namespace App\Modules\X150\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class ProviderAttempt extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'provider_attempts';

    protected $guarded = [];

    protected $casts = [
        'tier_level' => 'integer',
        'returned_data' => 'array',
    ];
}
