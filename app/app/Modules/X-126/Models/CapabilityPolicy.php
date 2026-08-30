<?php

declare(strict_types=1);

namespace App\Modules\X126\Models;

use Illuminate\Database\Eloquent\Model;

class CapabilityPolicy extends Model
{
    protected $table = 'capability_policies';

    protected $guarded = [];

    protected $casts = [
        'rules' => 'array',
        'is_active' => 'boolean',
    ];
}
