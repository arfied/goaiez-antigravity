<?php

declare(strict_types=1);

namespace App\Modules\X175\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class FieldSuggestion extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'field_suggestions';

    protected $guarded = [];

    protected $casts = [
        'is_unconfirmed_price' => 'boolean',
        'is_upsell' => 'boolean',
    ];
}
