<?php

declare(strict_types=1);

namespace App\Modules\X01\Models;

use Illuminate\Database\Eloquent\Model;

class LeadScore extends Model
{
    protected $table = 'lead_scores';

    protected $guarded = [];

    protected $casts = [
        'lead_rating' => 'integer',
        'confidence' => 'float',
        'signals' => 'array',
    ];
}
