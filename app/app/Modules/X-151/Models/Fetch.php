<?php

declare(strict_types=1);

namespace App\Modules\X151\Models;

use Illuminate\Database\Eloquent\Model;

class Fetch extends Model
{
    protected $table = 'fetches';

    protected $guarded = [];

    protected $casts = [
        'is_stale' => 'boolean',
        'captcha_attempts' => 'integer',
    ];
}
