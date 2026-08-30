<?php

declare(strict_types=1);

namespace App\Modules\X135\Models;

use Illuminate\Database\Eloquent\Model;

class ResearchRun extends Model
{
    protected $table = 'research_runs';

    protected $guarded = [];

    protected $casts = [
        'is_scored' => 'boolean',
        'dossier' => 'array',
    ];

    public function icebreakers()
    {
        return $this->hasMany(Icebreaker::class, 'run_id');
    }

    public function signals()
    {
        return $this->hasMany(ProspectSignal::class, 'run_id');
    }
}
