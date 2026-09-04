<?php

declare(strict_types=1);

namespace App\Modules\X217\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentOffer extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'recruitment_offers';

    protected $guarded = [];

    protected $casts = [
        'offered_rate_bps' => 'integer',
        'ceiling_rate_bps' => 'integer',
        'is_accepted' => 'boolean',
        'accepted_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<AffiliateProspect, $this>
     */
    public function prospect(): BelongsTo
    {
        return $this->belongsTo(AffiliateProspect::class, 'prospect_id');
    }
}
