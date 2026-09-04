<?php

declare(strict_types=1);

namespace App\Modules\X163\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class LocationBook extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'location_books';

    protected $guarded = [];

    protected $casts = [
        'version' => 'integer',
    ];
}
