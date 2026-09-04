<?php

declare(strict_types=1);

namespace App\Modules\X121\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class EntityHistoryRecord extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'entity_history';

    protected $guarded = [];

    protected $casts = [
        'version' => 'integer',
        'field_deltas' => 'array',
        'snapshot' => 'array',
    ];
}
