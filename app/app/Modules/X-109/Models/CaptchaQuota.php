<?php

declare(strict_types=1);

namespace App\Modules\X109\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class CaptchaQuota extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'captcha_quota';

    protected $guarded = [];

    protected $casts = [
        'available_quota' => 'integer',
        'used_quota' => 'integer',
    ];
}
