<?php

declare(strict_types=1);

namespace App\Modules\X202\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalItem extends Model
{
    protected $table = 'approval_items';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'is_l1_forever' => 'boolean',
        'current_step' => 'integer',
        'decided_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function chain(): BelongsTo
    {
        return $this->belongsTo(ApprovalChain::class, 'approval_chain_id');
    }
}
