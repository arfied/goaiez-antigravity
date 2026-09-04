<?php

declare(strict_types=1);

namespace App\Modules\X126\Models;

use Illuminate\Database\Eloquent\Model;

class CapabilityDecision extends Model
{
    protected $table = 'capability_decisions';

    protected $guarded = [];

    protected $casts = [
        'context' => 'array',
    ];
}
