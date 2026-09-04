<?php

declare(strict_types=1);

namespace App\Modules\X182\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'comments';

    protected $guarded = [];

    protected $casts = [
        'is_publicly_replied' => 'boolean',
        'is_escalated_to_inbox' => 'boolean',
    ];
}
