<?php

declare(strict_types=1);

namespace App\Modules\X190\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralListing extends Model
{
    protected $table = 'referral_listings';

    protected $guarded = [];

    protected $casts = [
        'is_listed' => 'boolean',
    ];
}
