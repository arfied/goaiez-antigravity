<?php

declare(strict_types=1);

namespace App\Modules\X110\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Visit extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'visits';

    protected $guarded = [];
}
