<?php

declare(strict_types=1);

namespace App\Modules\X173\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class AccountingConnection extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'accounting_connections';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
