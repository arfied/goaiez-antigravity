<?php

declare(strict_types=1);

namespace App\Modules\X199\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $table = 'invoices';

    protected $guarded = [];

    protected $casts = [
        'total_cents' => 'integer',
        'paid_cents' => 'integer',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];
}
