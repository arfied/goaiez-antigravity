<?php

declare(strict_types=1);

namespace App\Modules\X204\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Suppression extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'suppressions';

    protected $guarded = [];

    protected $casts = [
        'suppressed_at' => 'datetime',
    ];
}
