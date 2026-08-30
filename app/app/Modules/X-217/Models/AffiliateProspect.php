<?php

declare(strict_types=1);

namespace App\Modules\X217\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliateProspect extends Model
{
    protected $table = 'affiliate_prospects';

    protected $guarded = [];

    public function offers()
    {
        return $this->hasMany(RecruitmentOffer::class, 'prospect_id');
    }
}
