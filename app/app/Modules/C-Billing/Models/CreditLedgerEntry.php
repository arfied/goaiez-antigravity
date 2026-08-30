<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Models;

use Illuminate\Database\Eloquent\Model;

class CreditLedgerEntry extends Model
{
    public $timestamps = false;

    protected $table = 'credit_ledger_entries';

    protected $guarded = [];

    protected $casts = [
        'amount_hundredths_cents' => 'integer',
        'balance_after_hundredths_cents' => 'integer',
        'created_at' => 'datetime',
    ];
}
