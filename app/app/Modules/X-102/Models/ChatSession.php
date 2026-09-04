<?php

declare(strict_types=1);

namespace App\Modules\X102\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class ChatSession extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'chat_sessions';

    protected $guarded = [];

    protected $casts = [
        'rage_clicks_count' => 'integer',
        'is_ai_capped' => 'boolean',
    ];
}
