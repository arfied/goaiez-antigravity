<?php

declare(strict_types=1);

namespace App\Modules\X82\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RateVersion extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'rate_versions';

    protected $guarded = [];

    protected $casts = [
        'version_number' => 'integer',
        'amount_cents' => 'integer',
        'effective_from' => 'datetime',
    ];

    /**
     * @return BelongsTo<Rate, $this>
     */
    public function rate(): BelongsTo
    {
        return $this->belongsTo(Rate::class, 'rate_id');
    }
}
