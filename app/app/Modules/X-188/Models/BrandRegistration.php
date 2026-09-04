<?php

declare(strict_types=1);

namespace App\Modules\X188\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class BrandRegistration extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'brand_registrations';

    protected $guarded = [];
}
