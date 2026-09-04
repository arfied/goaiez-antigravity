<?php

declare(strict_types=1);

namespace App\Modules\X113\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Role extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'roles';

    protected $guarded = [];
}
