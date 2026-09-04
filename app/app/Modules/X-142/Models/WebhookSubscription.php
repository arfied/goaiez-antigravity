<?php

declare(strict_types=1);

namespace App\Modules\X142\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Models\Business;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookSubscription extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'events' => 'array',
        'secret' => 'encrypted',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
