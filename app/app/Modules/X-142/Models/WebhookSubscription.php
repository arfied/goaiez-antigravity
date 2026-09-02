<?php

declare(strict_types=1);

namespace App\Modules\X142\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Business;

class WebhookSubscription extends Model
{
    protected $guarded = [];

    protected $casts = [
        'events' => 'array',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
