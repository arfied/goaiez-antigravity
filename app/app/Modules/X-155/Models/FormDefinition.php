<?php

declare(strict_types=1);

namespace App\Modules\X155\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class FormDefinition extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'form_definitions';

    protected $guarded = [];

    protected $casts = [
        'steps' => 'array',
        'schema' => 'array',
    ];
}
