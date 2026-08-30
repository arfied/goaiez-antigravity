<?php

declare(strict_types=1);

namespace App\Modules\X164\Models;

use Illuminate\Database\Eloquent\Model;

class Estimate extends Model
{
    protected $table = 'estimates';

    protected $guarded = [];

    protected $casts = [
        'price_book_version' => 'integer',
        'total_cents' => 'integer',
        'deposit_amount_cents' => 'integer',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];
}
