<?php

declare(strict_types=1);

namespace App\Modules\X139\Models;

use Illuminate\Database\Eloquent\Model;

class ConversionUpload extends Model
{
    protected $table = 'conversion_uploads';

    protected $guarded = [];

    protected $casts = [
        'conversion_value_cents' => 'integer',
    ];
}
