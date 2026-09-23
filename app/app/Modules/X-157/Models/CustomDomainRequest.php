<?php

declare(strict_types=1);

namespace App\Modules\X157\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class CustomDomainRequest extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $guarded = [];
}
