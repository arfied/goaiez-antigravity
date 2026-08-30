<?php

declare(strict_types=1);

namespace App\Modules\X157\Models;

use Illuminate\Database\Eloquent\Model;

class EdgeZone extends Model
{
    protected $table = 'edge_zones';

    protected $guarded = [];

    protected $casts = [
        'has_valid_ssl' => 'boolean',
    ];
}
