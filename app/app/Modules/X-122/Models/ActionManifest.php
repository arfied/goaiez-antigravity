<?php

declare(strict_types=1);

namespace App\Modules\X122\Models;

use Illuminate\Database\Eloquent\Model;

class ActionManifest extends Model
{
    protected $table = 'action_manifests';

    protected $guarded = [];

    protected $casts = [
        'schema' => 'array',
        'is_reversible' => 'boolean',
    ];
}
