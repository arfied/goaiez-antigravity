<?php

declare(strict_types=1);

namespace App\Modules\X01\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class CustomField extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'custom_fields';

    protected $guarded = [];
}
