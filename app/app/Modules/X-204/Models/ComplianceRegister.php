<?php

declare(strict_types=1);

namespace App\Modules\X204\Models;

use Illuminate\Database\Eloquent\Model;

class ComplianceRegister extends Model
{
    protected $table = 'compliance_registers';

    protected $guarded = [];

    protected $casts = [
        'slot_states' => 'array',
    ];
}
