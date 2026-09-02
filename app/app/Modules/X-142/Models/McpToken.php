<?php

declare(strict_types=1);

namespace App\Modules\X142\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Business;

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
