<?php

declare(strict_types=1);

namespace App\Modules\X212\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class MigrationReject extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'migration_rejects';

    protected $guarded = [];

    protected $casts = [
        'record_index' => 'integer',
        'raw_data' => 'array',
    ];
}
