<?php

declare(strict_types=1);

namespace App\Modules\X137\Models;

use Illuminate\Database\Eloquent\Model;

class CallToken extends Model
{
    protected $table = 'call_tokens';

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
