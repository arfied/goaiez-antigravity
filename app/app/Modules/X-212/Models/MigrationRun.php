<?php

declare(strict_types=1);

namespace App\Modules\X212\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class MigrationRun extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'migration_runs';

    protected $guarded = [];

    protected $casts = [
        'total_records' => 'integer',
        'imported_records' => 'integer',
        'rejected_records' => 'integer',
        'is_silent_mode' => 'boolean',
    ];
}
