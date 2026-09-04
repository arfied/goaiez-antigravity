<?php

declare(strict_types=1);

namespace App\Modules\X151\Models;

use Illuminate\Database\Eloquent\Model;

class FetchTarget extends Model
{
    protected $table = 'fetch_targets';

    protected $guarded = [];

    protected $casts = [
        'concurrency_ceiling' => 'integer',
        'rps_ceiling' => 'integer',
    ];
}
