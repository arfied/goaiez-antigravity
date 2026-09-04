<?php

declare(strict_types=1);

namespace App\Modules\X182\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialPost extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'social_posts';

    protected $guarded = [];

    protected $casts = [
        'has_branded_overlay' => 'boolean',
        'is_published' => 'boolean',
    ];

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'post_id');
    }
}
