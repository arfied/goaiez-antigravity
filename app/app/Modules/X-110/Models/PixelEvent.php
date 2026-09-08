<?php

declare(strict_types=1);

namespace App\Modules\X110\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $event_name
 * @property ?Carbon $created_at
 */
class PixelEvent extends Model
{
    protected $table = 'pixel_events';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
    ];
}
