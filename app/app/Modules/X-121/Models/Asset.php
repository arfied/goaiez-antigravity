<?php

declare(strict_types=1);

namespace App\Modules\X121\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'assets';

    protected $guarded = [];

    protected $casts = [
        'version' => 'integer',
        'metadata' => 'array',
    ];
}
