<?php

declare(strict_types=1);

namespace App\Modules\X198\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payments';

    protected $guarded = [];

    protected $casts = [
        'amount_cents' => 'integer',
    ];
}
