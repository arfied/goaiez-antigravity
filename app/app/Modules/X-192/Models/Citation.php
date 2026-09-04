<?php

declare(strict_types=1);

namespace App\Modules\X192\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Citation extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'citations';

    protected $guarded = [];

    protected $casts = [
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];
}
