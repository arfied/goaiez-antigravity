<?php

declare(strict_types=1);

namespace App\Modules\X124\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantSession extends Model
{
    protected $table = 'assistant_sessions';

    protected $guarded = [];

    protected $casts = [
        'context' => 'array',
    ];
}
