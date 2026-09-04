<?php

declare(strict_types=1);

namespace App\Modules\X137\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class CallToken extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'call_tokens';

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
