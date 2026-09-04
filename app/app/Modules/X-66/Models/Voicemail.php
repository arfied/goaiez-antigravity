<?php

declare(strict_types=1);

namespace App\Modules\X66\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Voicemail extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'voicemails';

    protected $guarded = [];

    protected $casts = [
        'duration_seconds' => 'integer',
    ];
}
