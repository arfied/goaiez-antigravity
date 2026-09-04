<?php

declare(strict_types=1);

namespace App\Modules\X159\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class AuditFinding extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'audit_findings';

    protected $guarded = [];

    protected $casts = [
        'measured_at' => 'datetime',
    ];
}
