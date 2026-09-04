<?php

declare(strict_types=1);

namespace App\Modules\X194\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class SavedView extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'saved_views';

    protected $guarded = [];

    protected $casts = [
        'filter_config' => 'array',
        'columns_config' => 'array',
        'is_default' => 'boolean',
    ];
}
