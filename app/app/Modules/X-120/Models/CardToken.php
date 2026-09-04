<?php

declare(strict_types=1);

namespace App\Modules\X120\Models;

use Illuminate\Database\Eloquent\Model;

class CardToken extends Model
{
    protected $table = 'card_tokens';

    protected $guarded = [];

    protected $casts = [
        'exp_month' => 'integer',
        'exp_year' => 'integer',
        'is_default' => 'boolean',
        'alert_sent' => 'boolean',
    ];
}
