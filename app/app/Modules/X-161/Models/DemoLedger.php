<?php

declare(strict_types=1);

namespace App\Modules\X161\Models;

use Illuminate\Database\Eloquent\Model;

class DemoLedger extends Model
{
    protected $table = 'demo_ledger';

    protected $guarded = [];

    protected $casts = [
        'amount_cents' => 'integer',
        'is_mock' => 'boolean',
    ];
}
