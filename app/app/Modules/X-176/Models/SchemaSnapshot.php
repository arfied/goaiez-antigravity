<?php

declare(strict_types=1);

namespace App\Modules\X176\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class SchemaSnapshot extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'schema_snapshots';

    protected $guarded = [];

    protected $casts = [
        'json_ld' => 'array',
        'is_valid_schema' => 'boolean',
    ];
}
