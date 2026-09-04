<?php

declare(strict_types=1);

namespace App\Modules\X112\Models;

use Illuminate\Database\Eloquent\Model;

class ImpersonationLog extends Model
{
    public $timestamps = false;

    protected $table = 'impersonation_log';

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];
}
