<?php

declare(strict_types=1);

namespace App\Modules\CAi\Models;

use Illuminate\Database\Eloquent\Model;

class AiProviderAccount extends Model
{
    protected $table = 'ai_provider_accounts';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
