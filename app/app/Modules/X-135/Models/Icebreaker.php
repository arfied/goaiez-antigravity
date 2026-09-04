<?php

declare(strict_types=1);

namespace App\Modules\X135\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Icebreaker extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'icebreakers';

    protected $guarded = [];

    protected $casts = [
        'observed_date' => 'date',
    ];
}
