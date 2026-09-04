<?php

declare(strict_types=1);

namespace App\Modules\X155\Models;

use Illuminate\Database\Eloquent\Model;

class FormDefinition extends Model
{
    protected $table = 'form_definitions';

    protected $guarded = [];

    protected $casts = [
        'steps' => 'array',
        'schema' => 'array',
    ];
}
