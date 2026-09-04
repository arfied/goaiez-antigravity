<?php

declare(strict_types=1);

namespace App\Modules\X189\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class BrandedMedia extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'branded_media';

    protected $guarded = [];

    protected $casts = [
        'overlay_layer' => 'array',
    ];
}
