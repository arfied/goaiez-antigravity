<?php

declare(strict_types=1);

namespace App\Modules\X110\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class IdentityLink extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'identity_links';

    protected $guarded = [];
}
