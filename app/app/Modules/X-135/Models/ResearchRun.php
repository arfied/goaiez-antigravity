<?php

declare(strict_types=1);

namespace App\Modules\X135\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchRun extends Model
{
    protected $table = 'research_runs';

    protected $guarded = [];

    protected $casts = [
        'is_scored' => 'boolean',
        'dossier' => 'array',
    ];

    /**
     * @return HasMany<Icebreaker, $this>
     */
    public function icebreakers(): HasMany
    {
        return $this->hasMany(Icebreaker::class, 'run_id');
    }

    /**
     * @return HasMany<ProspectSignal, $this>
     */
    public function signals(): HasMany
    {
        return $this->hasMany(ProspectSignal::class, 'run_id');
    }
}
