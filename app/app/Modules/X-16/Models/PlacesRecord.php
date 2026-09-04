<?php

declare(strict_types=1);

namespace App\Modules\X16\Models;

use Illuminate\Database\Eloquent\Model;

class PlacesRecord extends Model
{
    protected $table = 'places_records';

    protected $guarded = [];

    protected $casts = [
        'is_chain' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];
}
