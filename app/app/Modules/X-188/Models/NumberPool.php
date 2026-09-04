<?php

declare(strict_types=1);

namespace App\Modules\X188\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class NumberPool extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'number_pool';

    protected $guarded = [];

    protected $casts = [
        'complaint_count' => 'integer',
    ];
}
