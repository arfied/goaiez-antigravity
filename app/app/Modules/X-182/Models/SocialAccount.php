<?php

declare(strict_types=1);

namespace App\Modules\X182\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class SocialAccount extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'social_accounts';

    protected $guarded = [];

    protected $casts = [
        'is_connected' => 'boolean',
    ];

    public function posts()
    {
        return $this->hasMany(SocialPost::class, 'account_id');
    }
}
