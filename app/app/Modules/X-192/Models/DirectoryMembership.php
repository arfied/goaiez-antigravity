<?php

declare(strict_types=1);

namespace App\Modules\X192\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class DirectoryMembership extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'directory_memberships';

    protected $guarded = [];

    protected $casts = [
        'is_noindex' => 'boolean',
        'directory_index' => 'integer',
        'is_purchased' => 'boolean',
    ];

    public function citations()
    {
        return $this->hasMany(Citation::class, 'membership_id');
    }
}
