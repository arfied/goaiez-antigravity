<?php

declare(strict_types=1);

namespace App\Modules\X103\Models;

use Illuminate\Database\Eloquent\Model;

class PageVariant extends Model
{
    protected $table = 'page_variants';

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'stopped_at' => 'datetime',
    ];
}
