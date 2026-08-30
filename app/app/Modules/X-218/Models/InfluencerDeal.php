<?php

declare(strict_types=1);

namespace App\Modules\X218\Models;

use Illuminate\Database\Eloquent\Model;

class InfluencerDeal extends Model
{
    protected $table = 'influencer_deals';

    protected $guarded = [];

    protected $casts = [
        'deal_amount_cents' => 'integer',
        'is_paid' => 'boolean',
    ];

    public function deliverables()
    {
        return $this->hasMany(Deliverable::class, 'deal_id');
    }
}
