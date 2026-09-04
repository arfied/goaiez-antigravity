<?php

declare(strict_types=1);

namespace App\Modules\X185\Models;

use Illuminate\Database\Eloquent\Model;

class Sequence extends Model
{
    protected $table = 'sequences';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'frozen_elements' => 'array',
    ];

    public function steps()
    {
        return $this->hasMany(SequenceStep::class, 'sequence_id');
    }
}
