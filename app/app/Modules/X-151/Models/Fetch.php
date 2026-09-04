<?php

declare(strict_types=1);

namespace App\Modules\X151\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Fetch extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'fetches';

    protected $guarded = [];

    protected $casts = [
        'is_stale' => 'boolean',
        'captcha_attempts' => 'integer',
    ];
}
