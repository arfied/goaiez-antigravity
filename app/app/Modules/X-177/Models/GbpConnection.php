<?php

declare(strict_types=1);

namespace App\Modules\X177\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class GbpConnection extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'gbp_connections';

    protected $guarded = [];
}
