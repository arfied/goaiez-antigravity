<?php

declare(strict_types=1);

namespace App\Modules\X200\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class DialerSeat extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'dialer_seats';

    protected $guarded = [];

    protected $casts = [
        'is_ai_agent' => 'boolean',
        'is_logged_in' => 'boolean',
    ];
}
