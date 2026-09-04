<?php

declare(strict_types=1);

namespace App\Modules\X207\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'device_tokens';

    protected $guarded = [];
}
