<?php

declare(strict_types=1);

namespace App\Modules\X121\Models;

use Illuminate\Database\Eloquent\Model;

class Fact extends Model
{
    protected $table = 'facts';

    protected $guarded = [];

    protected $casts = [
        'version' => 'integer',
        'is_valid' => 'boolean',
    ];
}
