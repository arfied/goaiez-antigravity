<?php

declare(strict_types=1);

namespace App\Modules\X161\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class DemoSession extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'demo_sessions';

    protected $guarded = [];

    protected $casts = [
        'is_mock' => 'boolean',
    ];
}
