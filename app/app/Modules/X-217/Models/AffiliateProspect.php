<?php

declare(strict_types=1);

namespace App\Modules\X217\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateProspect extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'affiliate_prospects';

    protected $guarded = [];

    /**
     * @return HasMany<RecruitmentOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(RecruitmentOffer::class, 'prospect_id');
    }
}
