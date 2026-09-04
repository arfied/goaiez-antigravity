<?php

declare(strict_types=1);

namespace App\Modules\X112\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Markup extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'markups';

    protected $guarded = [];

    protected $casts = [
        'wholesale_rate_cents' => 'integer',
        'retail_markup_cents' => 'integer',
        'retail_rate_cents' => 'integer',
    ];
}
