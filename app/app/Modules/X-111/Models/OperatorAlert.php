<?php

declare(strict_types=1);

namespace App\Modules\X111\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class OperatorAlert extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'operator_alerts';

    protected $guarded = [];
}
