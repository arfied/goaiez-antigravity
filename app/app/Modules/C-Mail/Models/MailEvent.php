<?php

declare(strict_types=1);

namespace App\Modules\CMail\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class MailEvent extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'mail_events';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
    ];
}
