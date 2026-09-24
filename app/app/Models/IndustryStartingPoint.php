<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IndustryFamily;
use Illuminate\Database\Eloquent\Model;

class IndustryStartingPoint extends Model
{
    protected $table = 'industry_starting_points';

    protected $guarded = [];

    protected $casts = [
        'family' => IndustryFamily::class,
        'palette' => 'array',
        'type_pairing' => 'array',
        'section_order' => 'array',
        'questions' => 'array',
    ];
}
