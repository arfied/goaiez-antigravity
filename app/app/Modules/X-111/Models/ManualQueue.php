<?php

declare(strict_types=1);

namespace App\Modules\X111\Models;

use Illuminate\Database\Eloquent\Model;

class ManualQueue extends Model
{
    protected $table = 'manual_queue';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
    ];
}
