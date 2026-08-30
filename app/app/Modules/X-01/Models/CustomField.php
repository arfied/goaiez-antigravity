<?php

declare(strict_types=1);

namespace App\Modules\X01\Models;

use Illuminate\Database\Eloquent\Model;

class CustomField extends Model
{
    protected $table = 'custom_fields';

    protected $guarded = [];
}
