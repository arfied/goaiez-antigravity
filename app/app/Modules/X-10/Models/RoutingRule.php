<?php

declare(strict_types=1);

namespace App\Modules\X10\Models;

use Illuminate\Database\Eloquent\Model;

class RoutingRule extends Model
{
    protected $table = 'routing_rules';

    protected $guarded = [];

    protected $casts = [
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];
}
