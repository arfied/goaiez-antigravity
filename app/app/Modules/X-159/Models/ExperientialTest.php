<?php

declare(strict_types=1);

namespace App\Modules\X159\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class ExperientialTest extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'experiential_tests';

    protected $guarded = [];

    protected $casts = [
        'passed' => 'boolean',
    ];
}
