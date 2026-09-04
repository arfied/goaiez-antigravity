<?php

declare(strict_types=1);

namespace App\Modules\X181\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class QaTicket extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'qa_tickets';

    protected $guarded = [];

    protected $casts = [
        'arrived_at' => 'datetime',
        'sla_due_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];
}
