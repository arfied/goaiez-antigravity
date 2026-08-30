<?php

declare(strict_types=1);

namespace App\Modules\X175\Models;

use Illuminate\Database\Eloquent\Model;

class FieldSuggestion extends Model
{
    protected $table = 'field_suggestions';

    protected $guarded = [];

    protected $casts = [
        'is_unconfirmed_price' => 'boolean',
        'is_upsell' => 'boolean',
    ];
}
