<?php

declare(strict_types=1);

namespace App\Modules\X111\Models;

use Illuminate\Database\Eloquent\Model;

class TenantTicket extends Model
{
    protected $table = 'tenant_tickets';

    protected $guarded = [];

    protected $casts = [
        'sla_due_at' => 'datetime',
    ];
}
