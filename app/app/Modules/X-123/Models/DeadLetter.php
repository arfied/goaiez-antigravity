<?php

declare(strict_types=1);

namespace App\Modules\X123\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class DeadLetter extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'dead_letters';

    protected $guarded = [];

    protected $casts = [
        'attempts' => 'integer',
        'notified_at' => 'datetime',
        'replayed_at' => 'datetime',
    ];
}
