<?php

declare(strict_types=1);

namespace App\Modules\X121\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Fact extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'facts';

    protected $guarded = [];

    protected $casts = [
        'version' => 'integer',
        'is_valid' => 'boolean',
    ];
}
