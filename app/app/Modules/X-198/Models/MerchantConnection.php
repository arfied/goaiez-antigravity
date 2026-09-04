<?php

declare(strict_types=1);

namespace App\Modules\X198\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantConnection extends Model
{
    protected $table = 'merchant_connections';

    protected $guarded = [];

    protected $casts = [
        'is_connected' => 'boolean',
    ];
}
