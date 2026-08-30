<?php

declare(strict_types=1);

namespace App\Modules\X194\Models;

use Illuminate\Database\Eloquent\Model;

class SavedView extends Model
{
    protected $table = 'saved_views';

    protected $guarded = [];

    protected $casts = [
        'filter_config' => 'array',
        'columns_config' => 'array',
        'is_default' => 'boolean',
    ];
}
