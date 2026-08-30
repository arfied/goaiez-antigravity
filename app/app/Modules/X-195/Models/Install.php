<?php

declare(strict_types=1);

namespace App\Modules\X195\Models;

use Illuminate\Database\Eloquent\Model;

class Install extends Model
{
    protected $table = 'installs';

    protected $guarded = [];

    protected $casts = [
        'config_values' => 'array',
    ];
}
