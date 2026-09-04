<?php

declare(strict_types=1);

namespace App\Modules\X153\Models;

use Illuminate\Database\Eloquent\Model;

class ReplyCode extends Model
{
    protected $table = 'reply_codes';

    protected $guarded = [];

    protected $casts = [
        'is_live' => 'boolean',
    ];
}
