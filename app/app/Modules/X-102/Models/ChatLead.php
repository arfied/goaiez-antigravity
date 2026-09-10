<?php

declare(strict_types=1);

namespace App\Modules\X102\Models;

use Illuminate\Database\Eloquent\Model;

class ChatLead extends Model
{
    protected $table = 'chat_leads';

    protected $guarded = [];

    protected $casts = [
        'consent_logged_at' => 'datetime',
    ];
}
