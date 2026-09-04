<?php

declare(strict_types=1);

namespace App\Modules\X198\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'payments';

    protected $guarded = [];

    protected $casts = [
        'amount_cents' => 'integer',
    ];
}
