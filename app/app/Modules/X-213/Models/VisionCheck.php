<?php

declare(strict_types=1);

namespace App\Modules\X213\Models;

use Illuminate\Database\Eloquent\Model;

class VisionCheck extends Model
{
    protected $table = 'vision_checks';

    protected $guarded = [];

    protected $casts = [
        'passed' => 'boolean',
        'defect_flags' => 'array',
    ];
}
