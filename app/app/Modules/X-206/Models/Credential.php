<?php

declare(strict_types=1);

namespace App\Modules\X206\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Credential extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'credentials';

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
