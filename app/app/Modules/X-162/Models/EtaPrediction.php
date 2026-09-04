<?php

declare(strict_types=1);

namespace App\Modules\X162\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class EtaPrediction extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'eta_predictions';

    protected $guarded = [];

    protected $casts = [
        'estimated_arrival_at' => 'datetime',
        'notification_sent_at' => 'datetime',
        'eta_minutes' => 'integer',
    ];
}
