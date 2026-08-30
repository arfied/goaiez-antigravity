<?php

declare(strict_types=1);

namespace App\Modules\X159\Models;

use Illuminate\Database\Eloquent\Model;

class Audit extends Model
{
    protected $table = 'audits';

    protected $guarded = [];

    protected $casts = [
        'overall_score' => 'float',
        'is_scored' => 'boolean',
    ];

    public function findings()
    {
        return $this->hasMany(AuditFinding::class, 'audit_id');
    }
}
