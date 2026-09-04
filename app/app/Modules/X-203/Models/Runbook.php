<?php

declare(strict_types=1);

namespace App\Modules\X203\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Runbook extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'runbooks';

    protected $guarded = [];

    protected $casts = [
        'steps' => 'array',
    ];
}
