<?php

namespace App\Modules\CWhatsapp\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class WhatsappDelivery extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'whatsapp_deliveries';

    protected $guarded = ['id', 'business_id'];
}
