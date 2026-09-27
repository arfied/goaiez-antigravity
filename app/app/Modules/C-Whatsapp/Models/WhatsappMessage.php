<?php

namespace App\Modules\CWhatsapp\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class WhatsappMessage extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $guarded = ['id', 'business_id'];
}
