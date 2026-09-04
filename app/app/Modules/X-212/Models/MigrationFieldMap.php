<?php

declare(strict_types=1);

namespace App\Modules\X212\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class MigrationFieldMap extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'migration_field_maps';

    protected $guarded = [];
}
