<?php

declare(strict_types=1);

namespace App\Modules\X190\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Referral extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'referrals';

    protected $guarded = [];

    protected $casts = [
        'package_delivered' => 'boolean',
        'partner_bought' => 'boolean',
    ];
}
