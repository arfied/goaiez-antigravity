<?php

declare(strict_types=1);

namespace App\Modules\X125\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Flow extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'flows';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'consecutive_errors' => 'integer',
        'max_error_threshold' => 'integer',
    ];
}
