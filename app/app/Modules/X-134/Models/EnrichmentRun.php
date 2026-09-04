<?php

declare(strict_types=1);

namespace App\Modules\X134\Models;

use Illuminate\Database\Eloquent\Model;

class EnrichmentRun extends Model
{
    protected $table = 'enrichment_runs';

    protected $guarded = [];

    protected $casts = [
        'fetched_at' => 'datetime',
    ];

    public function fields()
    {
        return $this->hasMany(EnrichmentField::class, 'run_id');
    }
}
