<?php

declare(strict_types=1);

namespace App\Modules\CTelephony\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $business_id
 * @property string $carrier_name
 * @property ?string $phone_number
 */
class CarrierBinding extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'carrier_bindings';

    protected $guarded = [];
}
