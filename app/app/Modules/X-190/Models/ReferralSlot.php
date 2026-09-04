<?php

declare(strict_types=1);

namespace App\Modules\X190\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralSlot extends Model
{
    protected $table = 'referral_slots';

    protected $guarded = [];

    protected $attributes = [
        'is_network_enabled' => true,
        'status' => 'open',
    ];

    protected $casts = [
        'is_network_enabled' => 'boolean',
    ];
}
