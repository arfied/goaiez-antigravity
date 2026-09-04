<?php

declare(strict_types=1);

namespace App\Modules\X137\Models;

use Illuminate\Database\Eloquent\Model;

class LinkClick extends Model
{
    public $timestamps = false;

    protected $table = 'link_clicks';

    protected $guarded = [];

    protected $casts = [
        'clicked_at' => 'datetime',
    ];
}
