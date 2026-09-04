<?php

declare(strict_types=1);

namespace App\Modules\X112\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class AgencyClient extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'agency_clients';

    protected $guarded = [];
}
