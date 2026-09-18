<?php

declare(strict_types=1);

namespace App\Modules\X218\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfluencerDeal extends Model
{
    protected $table = 'influencer_deals';

    protected $guarded = [];

    protected $casts = [
        'deal_amount_cents' => 'integer',
        'is_paid' => 'boolean',
    ];

    /**
     * @return HasMany<Deliverable, $this>
     */
    public function deliverables(): HasMany
    {
        return $this->hasMany(Deliverable::class, 'deal_id');
    }

    /**
     * @return BelongsTo<InfluencerProfile, $this>
     */
    public function influencer(): BelongsTo
    {
        return $this->belongsTo(InfluencerProfile::class, 'influencer_id');
    }
}
