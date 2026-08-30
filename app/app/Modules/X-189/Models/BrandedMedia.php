<?php

declare(strict_types=1);

namespace App\Modules\X189\Models;

use Illuminate\Database\Eloquent\Model;

class BrandedMedia extends Model
{
    protected $table = 'branded_media';

    protected $guarded = [];

    protected $casts = [
        'overlay_layer' => 'array',
    ];
}
