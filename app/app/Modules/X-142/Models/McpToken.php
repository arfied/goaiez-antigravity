<?php

declare(strict_types=1);

namespace App\Modules\X142\Models;

use App\Models\Business;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McpToken extends Model
{
    protected $guarded = [];

    protected $casts = [
        'abilities' => 'array',
        'is_revoked' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
