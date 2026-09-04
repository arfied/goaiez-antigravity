<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class DunningState extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'dunning_states';

    protected $guarded = [];

    protected $casts = [
        'day_in_cycle' => 'integer',
        'ai_enabled' => 'boolean',
        'phone_answering' => 'boolean',
        'voicemail_only' => 'boolean',
    ];
}
