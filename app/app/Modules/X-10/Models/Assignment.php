<?php

declare(strict_types=1);

namespace App\Modules\X10\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Assignment extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'assignments';

    protected $guarded = [];
}
