<?php

declare(strict_types=1);

namespace App\Modules\X179\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class TemplateMatch extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'template_matches';

    protected $guarded = [];

    protected $casts = [
        'match_rate' => 'float',
    ];
}
