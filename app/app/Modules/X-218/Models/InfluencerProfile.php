<?php

declare(strict_types=1);

namespace App\Modules\X218\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class InfluencerProfile extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'influencer_profiles';

    protected $guarded = [];

    protected $casts = [
        'audience_size' => 'integer',
        'engagement_rate' => 'float',
    ];

    public function deals()
    {
        return $this->hasMany(InfluencerDeal::class, 'influencer_id');
    }
}
