<?php

declare(strict_types=1);

namespace App\Modules\CAi\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $category
 * @property int $max_ttft_ms
 * @property int $cost_limit_cents
 */
class AiTask extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'ai_tasks';

    protected $guarded = [];

    protected $casts = [
        'max_ttft_ms' => 'integer',
        'cost_limit_cents' => 'integer',
    ];
}
