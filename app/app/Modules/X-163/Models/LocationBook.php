<?php

declare(strict_types=1);

namespace App\Modules\X163\Models;

use Illuminate\Database\Eloquent\Model;

class LocationBook extends Model
{
    protected $table = 'location_books';

    protected $guarded = [];

    protected $casts = [
        'version' => 'integer',
    ];
}
