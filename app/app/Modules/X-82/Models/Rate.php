<?php

declare(strict_types=1);

namespace App\Modules\X82\Models;

use Illuminate\Database\Eloquent\Model;

class Rate extends Model
{
    protected $table = 'rates';

    protected $guarded = [];

    protected $casts = [
        'amount_cents' => 'integer',
        'current_version' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<RateVersion, $this>
     */
    public function versions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RateVersion::class, 'rate_id');
    }
}
