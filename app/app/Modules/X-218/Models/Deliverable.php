<?php

declare(strict_types=1);

namespace App\Modules\X218\Models;

use Illuminate\Database\Eloquent\Model;

class Deliverable extends Model
{
    protected $table = 'deliverables';

    protected $guarded = [];

    protected $casts = [
        'http_status' => 'integer',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];
}
