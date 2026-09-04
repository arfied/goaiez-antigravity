<?php

declare(strict_types=1);

namespace App\Modules\X163\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class PriceBookVersion extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'price_book_versions';

    protected $guarded = [];

    protected $casts = [
        'version' => 'integer',
    ];
}
