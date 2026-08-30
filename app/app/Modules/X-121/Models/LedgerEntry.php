<?php

declare(strict_types=1);

namespace App\Modules\X121\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    protected $table = 'ledger_entries';

    protected $guarded = [];

    protected $casts = [
        'amount_cents' => 'integer',
        'balance_after_cents' => 'integer',
    ];
}
