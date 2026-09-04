<?php

declare(strict_types=1);

namespace App\Modules\X110\Models;

use Illuminate\Database\Eloquent\Model;

class PixelEvent extends Model
{
    protected $table = 'pixel_events';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
    ];
}
