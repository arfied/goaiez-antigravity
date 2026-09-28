<?php

declare(strict_types=1);

namespace App\Modules\X182\Models;

use Illuminate\Database\Eloquent\Model;

class SocialAccount extends Model
{
    protected $table = 'social_accounts';

    protected $guarded = ['id', 'account_ref'];

    protected $casts = [
        'is_connected' => 'boolean',
        'connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
    ];

    public function isUsable(): bool
    {
        return $this->status === 'connected' && $this->account_ref !== null;
    }

    public function posts()
    {
        return $this->hasMany(SocialPost::class, 'account_id');
    }
}
