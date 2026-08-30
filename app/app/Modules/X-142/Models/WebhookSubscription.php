<?php

declare(strict_types=1);

namespace App\Modules\X142\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookSubscription extends Model
{
    protected $table = 'webhook_subscriptions';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
