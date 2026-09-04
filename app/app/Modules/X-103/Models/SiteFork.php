<?php

declare(strict_types=1);

namespace App\Modules\X103\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class SiteFork extends Model implements TenantScoped
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'site_forks';

    protected $guarded = [];

    protected $casts = [
        'forked_at' => 'datetime',
    ];
}
