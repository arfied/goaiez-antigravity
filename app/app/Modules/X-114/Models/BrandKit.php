<?php

declare(strict_types=1);

namespace App\Modules\X114\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class BrandKit extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'brand_kits';

    protected $guarded = [];
}
