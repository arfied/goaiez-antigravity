<?php

declare(strict_types=1);

namespace App\Modules\X173\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class AccountingSyncConflict extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'accounting_sync_conflicts';

    protected $guarded = [];

    protected $casts = [
        'confidence_rate' => 'float',
        'flagged_for_review' => 'boolean',
    ];
}
