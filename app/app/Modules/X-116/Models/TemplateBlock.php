<?php

declare(strict_types=1);

namespace App\Modules\X116\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class TemplateBlock extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'template_blocks';

    protected $guarded = [];

    protected $casts = [
        'order_index' => 'integer',
        'config' => 'array',
    ];
}
