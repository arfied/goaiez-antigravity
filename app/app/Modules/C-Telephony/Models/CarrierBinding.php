<?php

declare(strict_types=1);

namespace App\Modules\CTelephony\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $business_id
 * @property string $carrier_name
 * @property ?string $phone_number
 */
class CarrierBinding extends Model
{
    protected $table = 'carrier_bindings';

    protected $guarded = [];
}
