<?php

declare(strict_types=1);

namespace App\Modules\X129\Models;

use Illuminate\Database\Eloquent\Model;

class RedirectMap extends Model
{
    protected $table = 'redirect_maps';

    protected $guarded = [];

    protected $casts = [
        'status_code' => 'integer',
        'is_verified' => 'boolean',
    ];
}
