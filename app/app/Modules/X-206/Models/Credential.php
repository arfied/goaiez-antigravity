<?php

declare(strict_types=1);

namespace App\Modules\X206\Models;

use Illuminate\Database\Eloquent\Model;

class Credential extends Model
{
    protected $table = 'credentials';

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
