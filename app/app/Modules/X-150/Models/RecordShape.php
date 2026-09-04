<?php

declare(strict_types=1);

namespace App\Modules\X150\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class RecordShape extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'record_shapes';

    protected $guarded = [];

    protected $casts = [
        'schema_definition' => 'array',
    ];
}
