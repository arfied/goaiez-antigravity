<?php

declare(strict_types=1);

namespace App\Modules\X118\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class OnboardingStep extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'onboarding_steps';

    protected $guarded = [];

    protected $casts = [
        'is_hard_stop' => 'boolean',
    ];
}
