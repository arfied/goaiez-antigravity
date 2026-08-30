<?php

declare(strict_types=1);

namespace App\Modules\X172\Models;

use Illuminate\Database\Eloquent\Model;

class PortalView extends Model
{
    public $timestamps = false;

    protected $table = 'portal_views';

    protected $guarded = [];

    protected $casts = [
        'opened_at' => 'datetime',
    ];
}
