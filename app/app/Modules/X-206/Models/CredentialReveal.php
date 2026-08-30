<?php

declare(strict_types=1);

namespace App\Modules\X206\Models;

use Illuminate\Database\Eloquent\Model;

class CredentialReveal extends Model
{
    protected $table = 'credential_reveals';

    protected $guarded = [];

    protected $casts = [
        'revealed_at' => 'datetime',
    ];
}
