<?php

declare(strict_types=1);

namespace App\Modules\X134\Models;

use Illuminate\Database\Eloquent\Model;

class EnrichmentField extends Model
{
    protected $table = 'enrichment_fields';

    protected $guarded = [];

    protected $casts = [
        'confidence_score' => 'float',
        'is_usable' => 'boolean',
    ];
}
