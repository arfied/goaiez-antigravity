<?php

declare(strict_types=1);

namespace App\Modules\X144\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisibilityQuery extends Model
{
    protected $table = 'visibility_queries';

    protected $guarded = [];

    /**
     * @return HasMany<VisibilityAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(VisibilityAnswer::class, 'query_id');
    }
}
