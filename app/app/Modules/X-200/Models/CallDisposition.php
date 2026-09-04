<?php

declare(strict_types=1);

namespace App\Modules\X200\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class CallDisposition extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'call_dispositions';

    protected $guarded = [];

    protected $casts = [
        'is_uncertain_human' => 'boolean',
    ];
}
