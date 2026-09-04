<?php

declare(strict_types=1);

namespace App\Modules\X145\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Decision extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'decisions';

    protected $guarded = [];

    protected $casts = [
        'requires_approval' => 'boolean',
    ];
}
