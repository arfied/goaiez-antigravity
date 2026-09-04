<?php

declare(strict_types=1);

namespace App\Modules\CSms\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class SmsComposition extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'sms_compositions';

    protected $guarded = [];

    protected $casts = [
        'segments_count' => 'integer',
        'scheduled_at' => 'datetime',
    ];
}
