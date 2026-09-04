<?php

declare(strict_types=1);

namespace App\Modules\X207\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class PushPrompt extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'push_prompts';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
