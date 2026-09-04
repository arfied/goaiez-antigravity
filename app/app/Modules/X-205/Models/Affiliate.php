<?php

declare(strict_types=1);

namespace App\Modules\X205\Models;

use Illuminate\Database\Eloquent\Model;

class Affiliate extends Model
{
    protected $table = 'affiliates';

    protected $guarded = [];

    protected $casts = [
        'commission_rate_bps' => 'integer',
        'lifetime_earnings_cents' => 'integer',
        'current_balance_cents' => 'integer',
    ];

    public function attributions()
    {
        return $this->hasMany(AffiliateAttribution::class, 'affiliate_id');
    }

    public function payouts()
    {
        return $this->hasMany(AffiliatePayout::class, 'affiliate_id');
    }
}
