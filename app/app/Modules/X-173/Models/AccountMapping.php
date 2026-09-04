<?php

declare(strict_types=1);

namespace App\Modules\X173\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class AccountMapping extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'account_mappings';

    protected $guarded = [];
}
