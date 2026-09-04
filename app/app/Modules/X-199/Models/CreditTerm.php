<?php

declare(strict_types=1);

namespace App\Modules\X199\Models;

use Illuminate\Database\Eloquent\Model;

class CreditTerm extends Model
{
    protected $table = 'credit_terms';

    protected $guarded = [];

    protected $casts = [
        'credit_limit_cents' => 'integer',
        'current_outstanding_cents' => 'integer',
    ];
}
