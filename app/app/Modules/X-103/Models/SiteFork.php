<?php

declare(strict_types=1);

namespace App\Modules\X103\Models;

use Illuminate\Database\Eloquent\Model;

class SiteFork extends Model
{
    public $timestamps = false;

    protected $table = 'site_forks';

    protected $guarded = [];

    protected $casts = [
        'forked_at' => 'datetime',
    ];
}
