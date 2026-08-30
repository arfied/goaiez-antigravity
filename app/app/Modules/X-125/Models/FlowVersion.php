<?php

declare(strict_types=1);

namespace App\Modules\X125\Models;

use Illuminate\Database\Eloquent\Model;

class FlowVersion extends Model
{
    protected $table = 'flow_versions';

    protected $guarded = [];

    protected $casts = [
        'version_number' => 'integer',
        'nodes' => 'array',
    ];
}
