<?php

declare(strict_types=1);

namespace App\Modules\X182\Models;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    protected $table = 'comments';

    protected $guarded = [];

    protected $casts = [
        'is_publicly_replied' => 'boolean',
        'is_escalated_to_inbox' => 'boolean',
        'hidden_at' => 'datetime',
        'private_replied_at' => 'datetime',
    ];
}
