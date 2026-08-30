<?php

declare(strict_types=1);

namespace App\Modules\X121\Models;

use Illuminate\Database\Eloquent\Model;

class Person extends Model
{
    protected $table = 'people';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
    ];
}
