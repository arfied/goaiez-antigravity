<?php

declare(strict_types=1);

namespace App\Modules\X121\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $table = 'assets';

    protected $guarded = [];

    protected $casts = [
        'version' => 'integer',
        'metadata' => 'array',
    ];
}
