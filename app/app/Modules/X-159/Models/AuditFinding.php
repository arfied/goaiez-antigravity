<?php

declare(strict_types=1);

namespace App\Modules\X159\Models;

use Illuminate\Database\Eloquent\Model;

class AuditFinding extends Model
{
    protected $table = 'audit_findings';

    protected $guarded = [];

    protected $casts = [
        'measured_at' => 'datetime',
    ];
}
