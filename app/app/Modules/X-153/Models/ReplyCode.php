<?php

declare(strict_types=1);

namespace App\Modules\X153\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class ReplyCode extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'reply_codes';

    protected $guarded = [];

    protected $casts = [
        'is_live' => 'boolean',
    ];
}
