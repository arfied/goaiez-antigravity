<?php

declare(strict_types=1);

namespace App\Modules\X207\Models;

use Illuminate\Database\Eloquent\Model;

class PushPrompt extends Model
{
    protected $table = 'push_prompts';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
