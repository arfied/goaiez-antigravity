<?php

declare(strict_types=1);

namespace App\Modules\X117\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $table = 'carts';

    protected $guarded = [];

    protected $casts = [
        'items' => 'array',
        'total_cents' => 'integer',
        'expires_at' => 'datetime',
    ];
}
