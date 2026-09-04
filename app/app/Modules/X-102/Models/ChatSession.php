<?php

declare(strict_types=1);

namespace App\Modules\X102\Models;

use Illuminate\Database\Eloquent\Model;

class ChatSession extends Model
{
    protected $table = 'chat_sessions';

    protected $guarded = [];

    protected $casts = [
        'rage_clicks_count' => 'integer',
        'is_ai_capped' => 'boolean',
    ];
}
