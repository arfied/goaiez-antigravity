<?php

declare(strict_types=1);

namespace App\Modules\X121\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Number extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'numbers';

    protected $guarded = [];

    protected $casts = [
        'provisioned_at' => 'datetime',
    ];
}
