<?php

declare(strict_types=1);

namespace App\Modules\X198\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class ReconciliationRun extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'reconciliation_runs';

    protected $guarded = [];

    protected $casts = [
        'expected_cents' => 'integer',
        'actual_cents' => 'integer',
        'discrepancy_cents' => 'integer',
    ];
}
