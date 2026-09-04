<?php

declare(strict_types=1);

namespace App\Modules\X112\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Agency extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'agencies';

    protected $guarded = [];
}
