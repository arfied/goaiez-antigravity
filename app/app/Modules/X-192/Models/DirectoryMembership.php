<?php

declare(strict_types=1);

namespace App\Modules\X192\Models;

use Illuminate\Database\Eloquent\Model;

class DirectoryMembership extends Model
{
    protected $table = 'directory_memberships';

    protected $guarded = [];

    protected $casts = [
        'is_noindex' => 'boolean',
        'rank_score' => 'integer',
        'is_purchased' => 'boolean',
    ];

    public function citations()
    {
        return $this->hasMany(Citation::class, 'membership_id');
    }
}
