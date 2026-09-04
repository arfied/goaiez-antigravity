<?php

declare(strict_types=1);

namespace App\Modules\X195\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Install extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'installs';

    protected $guarded = [];

    protected $casts = [
        'config_values' => 'array',
    ];
}
