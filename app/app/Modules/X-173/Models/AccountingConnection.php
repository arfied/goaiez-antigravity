<?php

declare(strict_types=1);

namespace App\Modules\X173\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingConnection extends Model
{
    protected $table = 'accounting_connections';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
