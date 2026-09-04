<?php

declare(strict_types=1);

namespace App\Modules\X105\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class LadderStep extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'ladder_steps';

    protected $guarded = [];

    protected $casts = [
        'rung_number' => 'integer',
    ];
}
