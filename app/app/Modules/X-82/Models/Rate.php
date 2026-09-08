<?php

declare(strict_types=1);

namespace App\Modules\X82\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rate extends Model
{
    protected $table = 'rates';

    protected $guarded = [];

    protected $casts = [
        'amount_cents' => 'integer',
        'current_version' => 'integer',
        'is_active' => 'boolean',
        'is_sample' => 'boolean',
    ];

    /**
     * @return HasMany<RateVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(RateVersion::class, 'rate_id');
    }
}
