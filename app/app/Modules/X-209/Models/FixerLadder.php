<?php

declare(strict_types=1);

namespace App\Modules\X209\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class FixerLadder extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'fixer_ladder';

    protected $guarded = [];

    protected $casts = [
        'current_level' => 'integer',
        'success_count' => 'integer',
    ];
}
