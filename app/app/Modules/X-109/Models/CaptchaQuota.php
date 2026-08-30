<?php

declare(strict_types=1);

namespace App\Modules\X109\Models;

use Illuminate\Database\Eloquent\Model;

class CaptchaQuota extends Model
{
    protected $table = 'captcha_quota';

    protected $guarded = [];

    protected $casts = [
        'available_quota' => 'integer',
        'used_quota' => 'integer',
    ];
}
