<?php

declare(strict_types=1);

namespace App\Modules\X192\Models;

use Illuminate\Database\Eloquent\Model;

class Citation extends Model
{
    protected $table = 'citations';

    protected $guarded = [];

    protected $casts = [
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];
}
