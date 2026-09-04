<?php

declare(strict_types=1);

namespace App\Modules\X117\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'orders';

    protected $guarded = [];

    protected $casts = [
        'total_cents' => 'integer',
    ];
}
