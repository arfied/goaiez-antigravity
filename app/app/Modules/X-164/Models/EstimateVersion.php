<?php

declare(strict_types=1);

namespace App\Modules\X164\Models;

use Illuminate\Database\Eloquent\Model;

class EstimateVersion extends Model
{
    protected $table = 'estimate_versions';

    protected $guarded = [];

    protected $casts = [
        'version_number' => 'integer',
        'frozen_snapshot' => 'array',
    ];
}
