<?php

declare(strict_types=1);

namespace App\Modules\X201\Models;

use Illuminate\Database\Eloquent\Model;

class Dispute extends Model
{
    protected $table = 'disputes';

    protected $guarded = [];

    protected $casts = [
        'chargeback_amount_cents' => 'integer',
    ];
}
