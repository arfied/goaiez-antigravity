<?php

declare(strict_types=1);

namespace App\Modules\X111\Models;

use Database\Factories\TenantTicketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantTicket extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return TenantTicketFactory::new();
    }

    protected $table = 'tenant_tickets';

    protected $guarded = [];

    protected $casts = [
        'sla_due_at' => 'datetime',
    ];
}
