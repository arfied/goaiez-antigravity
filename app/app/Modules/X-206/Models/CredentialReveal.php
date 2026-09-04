<?php

declare(strict_types=1);

namespace App\Modules\X206\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class CredentialReveal extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'credential_reveals';

    protected $guarded = [];

    protected $casts = [
        'revealed_at' => 'datetime',
    ];
}
