<?php

declare(strict_types=1);

namespace App\Modules\X111\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class TenantTicket extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'tenant_tickets';

    protected $guarded = [];

    protected $casts = [
        'sla_due_at' => 'datetime',
    ];
}
