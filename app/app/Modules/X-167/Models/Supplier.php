<?php

declare(strict_types=1);

namespace App\Modules\X167\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'suppliers';

    protected $guarded = [];
}
