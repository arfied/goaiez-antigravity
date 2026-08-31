<?php

declare(strict_types=1);

namespace App\Modules\X184\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentPlan extends Model
{
    protected $table = 'content_plans';

    protected $guarded = [];

    protected $casts = [
        'posts_per_week_cadence' => 'integer',
        'is_cadence_approved' => 'boolean',
    ];

    /**
     * @return HasMany<PlanItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PlanItem::class, 'plan_id');
    }
}
