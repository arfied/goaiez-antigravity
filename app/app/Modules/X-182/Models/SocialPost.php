<?php

declare(strict_types=1);

namespace App\Modules\X182\Models;

use Illuminate\Database\Eloquent\Model;

class SocialPost extends Model
{
    protected $table = 'social_posts';

    protected $guarded = [];

    protected $casts = [
        'has_branded_overlay' => 'boolean',
        'is_published' => 'boolean',
    ];

    public function comments()
    {
        return $this->hasMany(Comment::class, 'post_id');
    }
}
