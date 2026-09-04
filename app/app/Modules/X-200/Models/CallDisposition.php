<?php

declare(strict_types=1);

namespace App\Modules\X200\Models;

use Illuminate\Database\Eloquent\Model;

class CallDisposition extends Model
{
    protected $table = 'call_dispositions';

    protected $guarded = [];

    protected $casts = [
        'is_uncertain_human' => 'boolean',
    ];
}
