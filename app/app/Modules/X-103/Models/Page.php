<?php

declare(strict_types=1);

namespace App\Modules\X103\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Page extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'pages';

    protected $guarded = [];

    protected $casts = [
        'is_tenant_edited' => 'boolean',
        'is_published' => 'boolean',
    ];
}
