<?php

declare(strict_types=1);

namespace App\Modules\X128\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationMatrix extends Model
{
    protected $table = 'integration_matrix';

    protected $guarded = [];

    protected $casts = [
        'matrix_data' => 'array',
        'orphans_count' => 'integer',
        'violations_count' => 'integer',
        'generated_at' => 'datetime',
    ];
}
