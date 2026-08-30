<?php

declare(strict_types=1);

namespace App\Modules\X145\Models;

use Illuminate\Database\Eloquent\Model;

class Decision extends Model
{
    protected $table = 'decisions';

    protected $guarded = [];

    protected $casts = [
        'requires_approval' => 'boolean',
    ];
}
