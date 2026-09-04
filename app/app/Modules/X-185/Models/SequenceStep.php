<?php

declare(strict_types=1);

namespace App\Modules\X185\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class SequenceStep extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'sequence_steps';

    protected $guarded = [];

    protected $casts = [
        'step_number' => 'integer',
        'delay_hours' => 'integer',
    ];
}
