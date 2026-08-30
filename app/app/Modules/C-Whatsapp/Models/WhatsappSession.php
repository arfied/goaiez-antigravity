<?php

declare(strict_types=1);

namespace App\Modules\CWhatsapp\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappSession extends Model
{
    protected $table = 'whatsapp_sessions';

    protected $guarded = [];

    protected $casts = [
        'last_inbound_at' => 'datetime',
        'session_window_expires_at' => 'datetime',
        'is_window_open' => 'boolean',
    ];
}
