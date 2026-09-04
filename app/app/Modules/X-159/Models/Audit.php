<?php

declare(strict_types=1);

namespace App\Modules\X159\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Audit extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'audits';

    protected $guarded = [];

    protected $casts = [
        'overall_rating' => 'float',
        'is_scored' => 'boolean',
    ];

    /**
     * @return HasMany<AuditFinding, $this>
     */
    public function findings(): HasMany
    {
        return $this->hasMany(AuditFinding::class, 'audit_id');
    }
}
