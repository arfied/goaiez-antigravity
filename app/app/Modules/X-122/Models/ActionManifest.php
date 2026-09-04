<?php

declare(strict_types=1);

namespace App\Modules\X122\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class ActionManifest extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'action_manifests';

    protected $guarded = [];

    protected $casts = [
        'schema' => 'array',
        'is_reversible' => 'boolean',
    ];
}
