<?php

declare(strict_types=1);

namespace App\Modules\X215\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class SignatureRequest extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'signature_requests';

    protected $guarded = [];

    protected $casts = [
        'signed_at' => 'datetime',
    ];
}
