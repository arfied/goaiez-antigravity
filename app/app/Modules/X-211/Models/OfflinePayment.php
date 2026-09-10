<?php

declare(strict_types=1);

namespace App\Modules\X211\Models;

use Illuminate\Database\Eloquent\Model;

class OfflinePayment extends Model
{
    /**
     * The vocabulary `offline_payments.payment_method` accepts, transcribed from the column's own
     * migration comment (2026_08_30_000027_create_x211_ar_tables.php:46). It lives on the model that
     * owns the column so the screen and any later writer cannot disagree about it.
     */
    public const METHODS = ['cash', 'check', 'zelle', 'wire'];

    protected $table = 'offline_payments';

    protected $guarded = [];

    protected $casts = [
        'amount_cents' => 'integer',
    ];
}
