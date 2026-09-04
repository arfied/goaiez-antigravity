<?php

declare(strict_types=1);

namespace App\Modules\X196\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class ExtensionInjection extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'extension_injections';

    protected $guarded = [];

    protected $casts = [
        'prospect_payload' => 'array',
    ];
}
