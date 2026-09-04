<?php

declare(strict_types=1);

namespace App\Modules\X108\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Waitlist extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'waitlists';

    protected $guarded = [];

    protected $casts = [
        'preferred_date' => 'date',
        'is_member' => 'boolean',
    ];
}
