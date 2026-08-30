<?php

declare(strict_types=1);

namespace App\Modules\X142\Models;

use Illuminate\Database\Eloquent\Model;

class McpToken extends Model
{
    protected $table = 'mcp_tokens';

    protected $guarded = [];

    protected $casts = [
        'permissions' => 'array',
        'is_revoked' => 'boolean',
    ];
}
