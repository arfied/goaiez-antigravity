<?php

declare(strict_types=1);

namespace App\Modules\X199\Models;

use Illuminate\Database\Eloquent\Model;

class CreditTerm extends Model
{
    /**
     * Days a term allows, keyed by terms_type. The column's own model owns its
     * vocabulary so the engine and the draft path cannot disagree (R245).
     */
    public const TERMS_DAYS = [
        'due_on_receipt' => 0,
        'net_15' => 15,
        'net_30' => 30,
        'net_60' => 60,
    ];

    protected $table = 'credit_terms';

    protected $guarded = [];

    protected $casts = [
        'credit_limit_cents' => 'integer',
        'current_outstanding_cents' => 'integer',
    ];
}
