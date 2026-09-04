<?php

declare(strict_types=1);

namespace App\Modules\X191\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class LinkTarget extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'link_targets';

    protected $guarded = [];

    protected $casts = [
        'is_pbn' => 'boolean',
        'domain_authority' => 'integer',
    ];
}
