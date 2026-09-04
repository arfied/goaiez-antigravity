<?php

declare(strict_types=1);

namespace App\Modules\X128\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class IntegrationMatrix extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'integration_matrix';

    protected $guarded = [];

    protected $casts = [
        'matrix_data' => 'array',
        'orphans_count' => 'integer',
        'violations_count' => 'integer',
        'generated_at' => 'datetime',
    ];
}
