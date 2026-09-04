<?php

declare(strict_types=1);

namespace App\Modules\X131\Models;

use Illuminate\Database\Eloquent\Model;

class PersonInterest extends Model
{
    protected $table = 'person_interests';

    protected $guarded = [];

    protected $casts = [
        'confidence_rate' => 'float',
        'is_tenant_set' => 'boolean',
    ];
}
