<?php

declare(strict_types=1);

namespace App\Modules\X121\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $table = 'reviews';

    protected $guarded = [];

    protected $casts = [
        'rating' => 'integer',
    ];
}
