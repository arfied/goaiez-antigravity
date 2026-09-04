<?php

declare(strict_types=1);

namespace App\Modules\X178\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class DesignChange extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'design_changes';

    protected $guarded = [];

    protected $casts = [
        'previous_state' => 'array',
        'new_state' => 'array',
        'contrast_ratio' => 'float',
    ];
}
