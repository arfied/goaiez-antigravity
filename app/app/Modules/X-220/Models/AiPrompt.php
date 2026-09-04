<?php

declare(strict_types=1);

namespace App\Modules\X220\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class AiPrompt extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'ai_prompts';

    protected $guarded = [];

    protected $casts = [
        'version' => 'integer',
        'frozen_at' => 'datetime',
    ];
}
