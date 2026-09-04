<?php

declare(strict_types=1);

namespace App\Modules\CSms\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class SmsModerationResult extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'sms_moderation_results';

    protected $guarded = [];

    protected $casts = [
        'passed' => 'boolean',
        'warnings' => 'array',
    ];
}
