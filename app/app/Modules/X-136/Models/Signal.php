<?php

declare(strict_types=1);

namespace App\Modules\X136\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Signal extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'signals';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
    ];
}
