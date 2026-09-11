<?php

declare(strict_types=1);

namespace App\Modules\X173\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingConnection extends Model
{
    /** The three the connect screen offers. The column is `accounting_connections.provider`. */
    public const PROVIDERS = ['quickbooks', 'xero', 'sage'];

    protected $table = 'accounting_connections';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
