<?php

declare(strict_types=1);

namespace App\Modules\X215\Models;

use Illuminate\Database\Eloquent\Model;

class SignatureRequest extends Model
{
    protected $table = 'signature_requests';

    protected $guarded = [];

    protected $casts = [
        'signed_at' => 'datetime',
    ];
}
