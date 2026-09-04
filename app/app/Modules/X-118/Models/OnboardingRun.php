<?php

declare(strict_types=1);

namespace App\Modules\X118\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class OnboardingRun extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'onboarding_runs';

    protected $guarded = [];

    protected $casts = [
        'ttfm_ms' => 'integer',
        'asked_fields_count' => 'integer',
    ];
}
