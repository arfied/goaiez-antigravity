<?php

declare(strict_types=1);

namespace App\Modules\X154\Models;

use Illuminate\Database\Eloquent\Model;

class TenantLexicon extends Model
{
    protected $table = 'tenant_lexicons';

    protected $guarded = [];

    protected $casts = [
        'is_confirmed' => 'boolean',
    ];
}
