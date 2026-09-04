<?php

declare(strict_types=1);

namespace App\Modules\CSms\Models;

use Illuminate\Database\Eloquent\Model;

class SmsModerationResult extends Model
{
    protected $table = 'sms_moderation_results';

    protected $guarded = [];

    protected $casts = [
        'passed' => 'boolean',
        'warnings' => 'array',
    ];
}
