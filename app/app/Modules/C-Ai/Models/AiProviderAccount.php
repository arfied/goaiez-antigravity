<?php

declare(strict_types=1);

namespace App\Modules\CAi\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class AiProviderAccount extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'ai_provider_accounts';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
