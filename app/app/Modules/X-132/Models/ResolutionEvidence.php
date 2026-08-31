<?php

declare(strict_types=1);

namespace App\Modules\X132\Models;

use Illuminate\Database\Eloquent\Model;

class ResolutionEvidence extends Model
{
    protected $table = 'resolution_evidence';

    protected $guarded = [];

    protected $casts = [
        'confidence_rate' => 'float',
    ];
}
