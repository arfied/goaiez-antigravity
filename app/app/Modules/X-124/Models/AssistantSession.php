<?php

declare(strict_types=1);

namespace App\Modules\X124\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class AssistantSession extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'assistant_sessions';

    protected $guarded = [];

    protected $casts = [
        'context' => 'array',
    ];
}
