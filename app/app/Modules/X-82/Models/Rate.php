<?php

declare(strict_types=1);

namespace App\Modules\X82\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Rate extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'rates';

    protected $guarded = [];

    protected $casts = [
        'amount_cents' => 'integer',
        'current_version' => 'integer',
        'is_active' => 'boolean',
    ];
}
