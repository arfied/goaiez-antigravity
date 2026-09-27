<?php

declare(strict_types=1);

namespace App\Modules\CWhatsapp\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

final class WhatsappConnection extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $guarded = ['id', 'business_id', 'account_ref'];

    protected function casts(): array
    {
        return [
            'connected_at' => 'datetime',
            'disconnected_at' => 'datetime',
        ];
    }
}
