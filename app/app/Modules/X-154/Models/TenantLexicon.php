<?php

declare(strict_types=1);

namespace App\Modules\X154\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class TenantLexicon extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'tenant_lexicons';

    protected $guarded = [];

    protected $casts = [
        'is_confirmed' => 'boolean',
    ];
}
