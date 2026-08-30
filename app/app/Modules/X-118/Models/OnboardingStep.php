<?php

declare(strict_types=1);

namespace App\Modules\X118\Models;

use Illuminate\Database\Eloquent\Model;

class OnboardingStep extends Model
{
    protected $table = 'onboarding_steps';

    protected $guarded = [];

    protected $casts = [
        'is_hard_stop' => 'boolean',
    ];
}
