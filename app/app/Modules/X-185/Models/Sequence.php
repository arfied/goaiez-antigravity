<?php

declare(strict_types=1);

namespace App\Modules\X185\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Sequence extends Model implements TenantScoped
{
    use BelongsToTenant;

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
