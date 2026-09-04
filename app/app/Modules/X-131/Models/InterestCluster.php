<?php

declare(strict_types=1);

namespace App\Modules\X131\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class InterestCluster extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'interest_clusters';

    protected $guarded = [];
}
