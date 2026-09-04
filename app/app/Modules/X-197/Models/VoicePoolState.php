<?php

declare(strict_types=1);

namespace App\Modules\X197\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class VoicePoolState extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'voice_pool_state';

    protected $guarded = [];

    protected $casts = [
        'warm_instances' => 'integer',
        'active_calls' => 'integer',
        'max_capacity' => 'integer',
    ];
}
