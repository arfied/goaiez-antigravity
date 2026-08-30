<?php

declare(strict_types=1);

namespace App\Modules\X178\Models;

use Illuminate\Database\Eloquent\Model;

class DesignChange extends Model
{
    protected $table = 'design_changes';

    protected $guarded = [];

    protected $casts = [
        'previous_state' => 'array',
        'new_state' => 'array',
        'contrast_ratio' => 'float',
    ];
}
